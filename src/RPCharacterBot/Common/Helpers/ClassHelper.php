<?php

namespace RPCharacterBot\Common\Helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ClassHelper
{
    const appRoot = __DIR__ . "/../../../../";

    /**
     * Finds all classes in a given namespace.
     *
     * @param string $namespace
     * @return array
     */
    public static function findRecursive(string $namespace): array
    {
        $namespacePath = self::getNamespaceDirectory($namespace);

        if ($namespacePath === '') {
            return [];
        }

        return self::searchClasses($namespace, $namespacePath);
    }

    /**
     * Determines a namespace detector.
     *
     * @return array
     */
    private static function getDefinedNamespaces() : array
    {
        $composerJsonPath = self::appRoot . 'composer.json';
        $composerConfig = json_decode(file_get_contents($composerJsonPath));

        //Apparently PHP doesn't like hyphens, so we use variable variables instead.
        $psr4 = "psr-4";
        return (array) $composerConfig->autoload->$psr4;
    }

    /**
     * Get a directory of a namespace.
     *
     * @param string $namespace
     * @return string
     */
    private static function getNamespaceDirectory($namespace) : string
    {
        $composerNamespaces = self::getDefinedNamespaces();

        $namespaceFragments = explode('\\', $namespace);
        $undefinedNamespaceFragments = [];

        while($namespaceFragments) {
            $possibleNamespace = implode('\\', $namespaceFragments) . '\\';

            if(array_key_exists($possibleNamespace, $composerNamespaces)){
                return realpath(self::appRoot . $composerNamespaces[$possibleNamespace] . implode('/', $undefinedNamespaceFragments));
            }

            array_unshift($undefinedNamespaceFragments, array_pop($namespaceFragments));            
        }

        return false;
    }

    /**
     * Searches for all classes in a namespace path.
     *
     * @param string $namespace
     * @param string $namespacePath
     * @return array
     */
    private static function searchClasses(string $namespace, string $namespacePath): array
    {
        $classes = [];

        /**
         * @var \RecursiveDirectoryIterator $iterator
         * @var \SplFileInfo $item
         */
        foreach ($iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($namespacePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        ) as $item) {
            if ($item->isDir()) {
                $nextPath = $iterator->current()->getPathname();
                $nextNamespace = $namespace . '\\' . $item->getFilename();
                $classes = array_merge($classes, self::searchClasses($nextNamespace, $nextPath));
                continue;
            }
            if ($item->isFile() && $item->getExtension() === 'php') {
                $class = $namespace . '\\' . $item->getBasename('.php');
                if (!class_exists($class)) {
                    continue;
                }
                $classes[] = $class;
            }
        }

        return $classes;
    }
}