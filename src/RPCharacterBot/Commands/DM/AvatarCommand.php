<?php

namespace RPCharacterBot\Commands\DM;

use DOMDocument;
use DOMNode;
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
     * Avatar width and height.
     */
    const AVATAR_DIMENSION = 128;

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
     * Maybe an HTML file was passed along.
     *
     * @var bool
     */
    private $testedForHtml;

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
     * Input width.
     *
     * @var int
     */
    private $inputWidth;

    /**
     * Input Height.
     *
     * @var int
     */
    private $inputHeight;

    /**
     * Input mime type.
     *
     * @var string
     */
    private $inputMime;

    /**
     * File output folder.
     *
     * @var string
     */
    private $outputFolder;

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
        $this->outputFolder = $this->bot->getConfig('avatar_output');

        $deferred = new Deferred();

        $this->testedForHtml = false;

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

        $that->outputFileStream->on('close', function() use ($that, $filename) {
            $that->outputFileStream->close();
            $that->outputFile = $this->fileSystem->file($filename);
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
            if (!$this->testedForHtml && class_exists('DOMDocument')) {
                //Could it be an HTML file?
                $this->testedForHtml = true;
                $this->processAsHtml();
            } else {
                $that->queueCleanup();
                $this->reply('Unable to process image - exit code: ' . $exitCode)->then(function() use ($that) {
                    $that->deferred->resolve();
                });
            }            
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
            $that->queueCleanup();
            $this->reply('Unable to verify image data')->then(function() use ($that) {                
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
            $this->queueCleanup();
            $this->reply('Unable to extract geometry data')->then(function() use ($that) {
                $that->deferred->resolve();
            });
            return;
        }
        $width = (int)round($resData[0]);
        $height = (int)round($resData[1]);

        $this->loop->futureTick(function() use ($width, $height, $mime, $that) {
            $that->imageDataAvailable($width, $height, $mime);
        });
    }

    /**
     * Image data is available. Now parse the image.
     *
     * @param int $width
     * @param int $height
     * @param string $mime
     * @return void
     */
    private function imageDataAvailable($width, $height, $mime)
    {
        $that = $this;

        if ($height < self::AVATAR_DIMENSION || $width < self::AVATAR_DIMENSION) {
            $this->queueCleanup();
            $this->reply(
                'This image is too small. It must be at least ' . self::AVATAR_DIMENSION . ' pixels wide and at least ' .
                     self::AVATAR_DIMENSION . ' pixels high to be considered')->then(function() use ($that) {
                $that->deferred->resolve();
            });
            return;
        }

        $this->inputWidth = $width;
        $this->inputHeight = $height;
        $this->inputMime = $mime;

        $extension = 'png';
        if (strtolower($mime) == 'image/jpeg' || strtolower($mime) == 'image/jpg') {
            $extension = 'jpg';
        }

        $this->findAvailableAvatarFilename($extension);
    }


    /**
     * Checks whether a file name for the picture download is available.
     *
     * @return void
     */
    private function findAvailableAvatarFilename($extension) 
    {
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $that = $this;

        $this->fileSystem->file($this->outputFolder . '/' . $filename)->exists()->then(function() use ($that, $extension) {
            //File exists!
            $that->findAvailableAvatarFilename($extension);
        }, function() use ($that, $filename, $extension) {
            $that->convertDownloadedFile($filename);
        });
    }

    /**
     * Performs the actual image conversion.
     *
     * @param string $avatarFileName
     * @return void
     */
    private function convertDownloadedFile($avatarFileName) {
        $that = $this;

        $inputFileName = $this->outputFileName;
        $outputFileName = $this->outputFolder . '/' . $avatarFileName;

        $width = $this->inputWidth;
        $height = $this->inputHeight;

        $cropX = 0;
        $cropEnabled = false;

        $outputWidth = $width;
        $outputHeight = $height;
        if ($width > $height) {
            $cropX = (int)(floor(($width - $height) / 2));
            $outputWidth = $height;
            $cropEnabled = true;
        } else if ($width != $height) {
            $cropX = 0;
            $cropEnabled = true;
            $outputHeight = $width;
        }

        $commandLine = $this->imageMagick . ' "' . $this->outputFileName . '" ';

        if ($cropEnabled) {
            $commandLine .= '-crop ' . $outputWidth . 'x' . $outputHeight . '+' . $cropX . '+0 ';
        }

        if ($outputWidth != self::AVATAR_DIMENSION || $outputHeight != self::AVATAR_DIMENSION) {
            $commandLine .= ' -set option:filter:lobes 8 -resize ' . self::AVATAR_DIMENSION . 'x' . self::AVATAR_DIMENSION . ' ';
        }

        $commandLine .= ' "' . $outputFileName . '"';

        $process = new \React\ChildProcess\Process($commandLine);

        $process->on('exit', function() use ($outputFileName, $that) {
            $that->conversionComplete($outputFileName);
        });

        $process->start($this->loop);        

        $process->stdout->on('data', function ($chunk) use (&$stdout) {
            echo $chunk;            
        });
        $process->stderr->on('data', function ($chunk) use (&$stdout) {
            echo $chunk;            
        });
    }

    /**
     * File conversion complete.
     *
     * @param string $avatarFileName
     * @return void
     */
    private function conversionComplete($avatarFileName) 
    {
        $that = $this;

        $this->queueCleanup();

        $this->reply('File saved as ' . $avatarFileName)->then(function() use ($that) {
            $that->deferred->resolve();
        });
    }

    /**
     * Queues a file cleanup.
     *
     * @return void
     */
    private function queueCleanup()
    {
        $that = $this;
        $this->loop->futureTick(function() use ($that) {
            $that->performCleanup();
        });
    }

    /**
     * Performs a file cleanup.
     *
     * @return void
     */
    private function performCleanup()
    {
        $that = $this;
        $this->outputFile->exists(function() use($that) {
            $that->remove()->then(function() { });
        });
    }

    /**
     * File was successfully downloaded and it's not an image file.
     * Maybe it is a HTML file?
     *
     * @return void
     */
    private function processAsHtml()
    {
        $that = $this;

        $this->outputFile->getContents()->then(function ($contents) use ($that) {
            $that->outputFile->remove()->then(function() use ($contents, $that) {
                $that->parseContents($contents);
            });            
        });
    }

    /**
     * Attempts parsing the file content now as HTML.
     *
     * @param string $contents
     * @return void
     */
    private function parseContents($contents)
    {
        $that = $this;

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);

        try {
            $dom->loadHTML($contents);
        } catch(\Exception $ex) {
            $this->htmlProcessingFailed();
            return;
        }

        $metaTags = array();

        try {
            $metaDom = $dom->getElementsByTagName("meta");
            for ($i=0; $i < $metaDom->length; ++$i) {
                /** @var DOMElement $item */
                $item = $metaDom->item($i);
                $name = $item->getAttribute('name');
                
                if (empty($name)) {
                    $name = $item->getAttribute('property');                
                }
                
                if (empty($name)) {
                    continue;
                }
                
                $metaTags[$name] = $item->getAttribute('content');            
            }
        } catch(\Exception $ex) {
            $this->htmlProcessingFailed();
            return;
        }
        
        if (!array_key_exists('og:image', $metaTags)) {
            $this->htmlProcessingFailed();
            return;
        }

        $this->url = $metaTags['og:image'];
        $this->findAvailableFilename();
    }

    /**
     * Report an error.
     *
     * @return void
     */
    private function htmlProcessingFailed()
    {
        $that = $this;
        $this->queueCleanup();
        $this->reply('Unable to process image')->then(function() use ($that) {
            $that->deferred->resolve();
        });
    }
}
