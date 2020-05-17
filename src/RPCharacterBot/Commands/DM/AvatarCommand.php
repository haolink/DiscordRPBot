<?php

namespace RPCharacterBot\Commands\DM;

use React\Filesystem\FilesystemInterface;
use React\Filesystem\Node\File;
use React\Filesystem\Node\FileInterface;
use React\HttpClient\Response;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Stream\WritableStreamInterface;
use RPCharacterBot\Commands\DMCommand;

class AvatarCommand extends DMCommand
{
    /**
     * Checking the file system.
     *
     * @var FilesystemInterface
     */
    private $fileSystem;

    /**
     * Temporary file folder.
     *
     * @var string
     */
    private $tempFolder;

    /**
     * Picture URL.
     *
     * @var string
     */
    private $url;    

    /**
     * Deferred.
     *
     * @var Deferred
     */
    private $deferred;

    /**
     * Temporary file interface.
     *
     * @var FileInterface
     */
    private $outputFile;

    /**
     * Temporary file name locally.
     *
     * @var string
     */
    private $outputFileName;

    /**
     * React output File.
     *
     * @var WritableStreamInterface
     */
    private $outputFileStream;

    /**
     * Imagemagick command line.
     *
     * @var string
     */
    private $imageMagick;

    /**
     * Command to set a character's avatar.
     *
     * @return ExtendedPromiseInterface|null
     */
    protected function handleCommandInternal(): ?ExtendedPromiseInterface
    {
        $words = $this->getMessageWords();

        if(count($words) < 2) {
            return $this->replyDM('Usage: avatar [shortcut] [avatar url]');
        }

        $shortCut = strtolower($words[0]);                
        $existingCharacter = $this->getCharacterByShortcut($shortCut);

        if (is_null($existingCharacter)) {
            return $this->replyDM('A character with the shortcut ' . $shortCut . ' doesn\'t exist.');
        }

        $url = $words[1];
        $this->url = $url;

        $this->tempFolder = sys_get_temp_dir();
        //$existingCharacter->setCharacterAvatar($words[1]);

        //return $this->replyDM('The profile picture for ' . $existingCharacter->getCharacterName() . ' has been set!');

        $this->imageMagick = $this->bot->getConfig('imagemagick');

        $deferred = new Deferred();

        $filesystem = \React\Filesystem\Filesystem::create($this->messageInfo->bot->getLoop());
        $this->fileSystem = $filesystem;

        $that = $this;
        $this->loop->futureTick(function() use ($that) {
            $that->findAvailableFilename();
        });
        
        return $deferred->promise();
    }

    /**
     * Checks whether a file name for the picture download is available.
     *
     * @return void
     */
    private function findAvailableFilename() 
    {
        $filename = $this->tempFolder . '/' . bin2hex(random_bytes(16)) . '.bin';
        $that = $this;

        $this->fileSystem->file($filename)->exists()->then(function() use ($that) {
            //File exists!
            $that->findAvailableFilename();
        }, function() use ($that, $filename) {
            $that->outputFileSelected($filename);
        });
    }

    /**
     * We have a temporary file name.
     *
     * @param string $filename
     * @return void
     */
    private function outputFileSelected(string $filename)
    {
        $this->outputFileName = $filename;
        $this->outputFile = $this->fileSystem->file($filename);
        $this->outputFileStream = \React\Promise\Stream\unwrapWritable($this->outputFile->open('cbw'));

        $client = new \React\HttpClient\Client($this->loop);
        $request = $client->request('GET', $this->url);

        $that = $this;

        $request->on('response', function (Response $response) use ($that, $request) {            
            $response->on('end', function() use ($that, $request, $response) {
                $request->close();
                $response->close();
                $that->outputFileStream->end();
            });

            $response->pipe($that->outputFileStream);
        });

        $that->outputFileStream->on('close', function() use ($that) {
            $that->pictureDownloaded();
        });

        $request->end();
    }

    /**
     * Picture is downloaded.
     *
     * @return void
     */
    private function pictureDownloaded()
    {
        $that = $this;

        $process = new \React\ChildProcess\Process($this->imageMagick . ' -verbose "' . $this->outputFileName . '" info:');
        $stdout = '';
                
        $process->start($this->loop);

        $process->stdout->on('data', function ($chunk) use (&$stdout) {
            $stdout .= $chunk;            
        });
        $process->stderr->on('data', function ($chunk) use (&$stdout) {
            $stdout .= $chunk;            
        });
        
        $process->on('exit', function ($exitCode, $termSignal) use ($that, &$stdout) {
            $strCopy = mb_substr($stdout, 0);            
            $that->fileAnalysed($exitCode, $strCopy);
        });
    }

    /**
     * Image data has been analysed.
     *
     * @param int $exitCode
     * @param string $imageMagickAnalyse
     * @return void
     */
    private function fileAnalysed(int $exitCode, string $imageMagickAnalysis)
    {
        $that = $this;

        if ($exitCode != 0) {
            $this->bot->writeln($this->outputFileName);
            $this->bot->writeln($imageMagickAnalysis);
            $this->reply('Unable to process image - exit code: ' . $exitCode)->then(function() use ($that) {
                /*$that->outputFile->remove()->then(function() { });
                $that->deferred->resolve();*/
            });
            return;            
        }
        
        $mime = null;
        $geometry = null;

        $lines = explode("\n", $imageMagickAnalysis);
        foreach($lines as $line) {
            $lineData = explode(': ', trim(chop($line)), 2);
            switch(strtolower($lineData[0])) {
                case 'mime type':
                    $mime = trim($lineData[1]);
                    break;
                case 'geometry':
                    $geometry = trim($lineData[1]);
                    break;
            }
        }

        if (is_null($mime) || is_null($geometry)) {
            $this->reply('Unable to verify image data')->then(function() use ($that) {
                $that->outputFile->remove()->then(function() { });
                $that->deferred->resolve();
            });
            return;
        }

        $width = 0;
        $height = 0;

        $geometryData = explode('+', $geometry);
        $resolution = trim($geometryData[0]);
        $resData = explode('x', $resolution);

        if (count($resData) != 2 || !is_numeric($resData[0]) || !is_numeric($resData[1])) {
            $this->reply('Unable to extract geometry data')->then(function() use ($that) {
                $that->outputFile->remove()->then(function() { });
                $that->deferred->resolve();
            });
            return;
        }
        $width = (int)round($resData[0]);
        $height = (int)round($resData[1]);

        $this->reply(
                '```Mimetype: ' . $mime . PHP_EOL . 
                'Width: ' . $width . PHP_EOL . 
                'Height: ' . $height . '```')->then(function() use ($that) {
            $that->deferred->resolve();
        });
    }
}
