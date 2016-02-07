<?php

/**
 * Class loader
 * @author ShiraNai7 <shira.cz>
 */

/**
 * Class loader class
 *
 * Supports three ways of registering class paths.
 *
 *  - each method has a second one that accepts the parameters as an array
 *  - order of precedence when resolving a class name is: class map > base namespace > base namespace as prefix > prefix
 *
 *      1) registerPrefix() - prefix for class/namespace name
 *
 *       - class file paths are composed according to PSR-0
 *       - see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 *       - ideal for libraries
 *
 *           registerPrefix('Example', './lib/example')
 *
 *              Example\Foo         --> ./lib/example/Example/Foo.php
 *              Example\Foo\Bar     --> ./lib/example/Example/Foo/Bar.php
 *
 *           registerPrefix('Hello_', './lib/hello')
 *
 *              Hello_World         --> ./lib/hello/Hello/World.php
 *              Hello_World_Test    --> ./lib/hello/Hello/World/Test.php
 *
 *       - order of registration is important!
 *
 *              // will not work as expected, prefix for Hello\Example will never match
 *              registerPrefix('Hello', './hello')
 *              registerPrefix('Hello\Example', './hello-example')
 *
 *              // this will work as expected
 *              registerPrefix('Hello\Example', './hello-example')
 *              registerPrefix('Hello', './hello')
 *
 *
 *      2) registerBaseNamespace() - using the base (first) part of namespace
 *
 *       - class file paths are also composed according to PSR-0, but with
 *         one exception - base namespaces are NOT projected into the paths
 *       - ideal for application source
 *
 *           registerBaseNamespace('Modules', './src')
 *
 *              Modules\Example     --> ./src/Example.php
 *              Modules\Test\Foo    --> ./src/Test/Foo.php
 *
 *
 *      3) registerClass() - using direct specification of class location
 *
 *       - ideal for old projects or special cases
 *
 *          registerClass('FooBar', './extra/foo_bar.php')
 *
 *              FooBar  --> ./extra/foo_bar.php
 *
 */
class ClassLoader
{

    # properties

    /** @var bool debug mode 1/0 */
    protected $debug = false;
    /** @var array path cache */
    protected $pathCache = array();
    /** @var array base namespaces, entry: base_namespace => array(paths, paths_normalized 1/0) */
    protected $baseNamespaces = array();
    /** @var array namespace/class prefixes, entry: prefix => array(paths, paths_normalized 1/0, prefix_len) */
    protected $prefixes = array();
    /** @var array class map */
    protected $classMap = array();

    # methods: loader

    /**
     * Register as autoloader
     * @return bool
     */
    public function register()
    {
        return spl_autoload_register(array($this, 'loadClass'));
    }

    /**
     * Get debug mode
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Toggle debug mode
     * @param  bool        $debug
     * @return ClassLoader
     */
    public function setDebug($debug)
    {
        $this->debug = (bool) $debug;

        return $this;
    }

    /**
     * Clear path cache
     * @return ClassLoader
     */
    public function clearPathCache()
    {
        $this->pathCache = array();

        return $this;
    }

    # methods: configuration

    /**
     * Register base namespace
     * @param  string       $baseNamespace   the base namespace (e.g. "Example")
     * @param  string|array $paths           filesystem path or an array of paths
     * @param  bool         $pathsNormalized treat paths as already normalized (correct directory separators, no separator at the end) 1/0
     * @return ClassLoader
     */
    public function registerBaseNamespace($baseNamespace, $paths, $pathsNormalized = false)
    {
        $this->baseNamespaces[$baseNamespace] = array((array) $paths, $pathsNormalized);

        return $this;
    }

    /**
     * Register multiple base namespaces
     * @param  array       $baseNamespacePaths associative array, e.g. array('Example' => './example', 'Foo' => array('./foo', './other/foo'), ...)
     * @param  bool        $pathsNormalized    treat paths as already normalized (correct directory separators, no separator at the end) 1/0
     * @return ClassLoader
     */
    public function registerBaseNamespaces(array $baseNamespaces, $pathsNormalized = false)
    {
        foreach ($baseNamespaces as $baseNamespace => $paths) {
            $this->baseNamespaces[$baseNamespace] = array((array) $paths, $pathsNormalized);
        }

        return $this;
    }

    /**
     * Register namespace or class name prefix
     * @param  string       $prefix          the prefix (e.g. "Example\Hello" or "Example_")
     * @param  string|array $paths           filesystem path or an array of paths
     * @param  bool         $pathsNormalized treat paths as already normalized (correct directory separators, no separator at the end) 1/0
     * @return ClassLoader
     */
    public function registerPrefix($prefix, $paths, $pathsNormalized = false)
    {
        $this->prefixes[$prefix] = array((array) $paths, $pathsNormalized, strlen($prefix));

        return $this;
    }

    /**
     * Register multiple namespace and/or class name prefixes
     * @param  array       $prefixPaths     associative array, e.g. array('Example\Hello' => './example/hello', 'Example_' => array('./example', './other/example'), ...)
     * @param  bool        $pathsNormalized treat paths as already normalized (correct directory separators, no separator at the end) 1/0
     * @return ClassLoader
     */
    public function registerPrefixes(array $prefixPaths, $pathsNormalized = false)
    {
        foreach ($prefixPaths as $prefix => $paths) {
            $this->prefixes[$prefix] = array((array) $paths, $pathsNormalized, strlen($prefix));
        }

        return $this;
    }

    /**
     * Register single class
     * @param  string      $className the class name
     * @param  string      $classFile path to the file with class definition
     * @return ClassLoader
     */
    public function registerClass($className, $classFile)
    {
        $this->classMap[$className] = $classFile;

        return $this;
    }

    /**
     * Register multiple classes
     * @param  array       $classes associative array, e.g. array('Example' => './example.php', 'Hello' => './foo/hello.php')
     * @return ClassLoader
     */
    public function registerClassMap(array $classes)
    {
        $this->classMap = $classes + $this->classMap;

        return $this;
    }

    /**
     * Reset the class loader
     * Unregisters all known paths
     * @return ClassLoader
     */
    public function clear()
    {
        $this->baseNamespaces =
        $this->prefixes =
        $this->pathCache =
            array()
        ;

        return $this;
    }

    # methods: location

    /**
     * Load a class
     * @param  string           $className
     * @throws RuntimeException
     */
    public function loadClass($className)
    {
        $file = $this->find($className, false);
        if (null !== $file) {

            // load file
            try {
                include_once $file;
            } catch (Exception $e) {
                throw new RuntimeException(sprintf('An error occured while loading class "%s" from file "%s"', $className, $file), 0, $e);
            }

            // verify in debug mode
            if ($this->debug && !class_exists($className, false) && !interface_exists($className, false) && (!function_exists('trait_exists') || !trait_exists($className, false))) {
                throw new RuntimeException(sprintf('Class, interface or trait "%s" was not found in file "%s" - possible invalid name or namespace?', $className, $file));
            }

        }
    }

    /**
     * Determine class or namespace path
     * @param  string           $className the class name
     * @param  bool             $exception throw an exception on failure 1/0
     * @throws RuntimeException
     * @return string|null      null on failure
     */
    public function find($className, $exceptionOnFailure = true)
    {
        // check path cache
        if (isset($this->pathCache[$className])) {
            return $this->pathCache[$className];
        }

        // check class map
        if (isset($this->classMap[$className])) {
            return $this->classMap[$className];
        }

        // extract components
        $slash = strpos($className, '\\');
        $lastSlash = strrpos($className, '\\');
        $bareClassName = ((false === $lastSlash) ? $className : substr($className, $lastSlash + 1));
        $baseNamespace = ((false === $lastSlash) ? null : substr($className, 0, $slash));
        $namespace = ((false === $lastSlash) ? '' : substr($className, 0, $lastSlash));

        // match
        $paths = null;
        if (isset($baseNamespace, $this->baseNamespaces[$baseNamespace])) {

            // base namespace
            if (!$this->baseNamespaces[$baseNamespace][1]) {
                $this->normalizePaths($this->baseNamespaces[$baseNamespace]);
            }
            $paths = $this->baseNamespaces[$baseNamespace][0];

            // base namespace portion is not included in the path
            if (false !== $slash && $slash !== $lastSlash) {
                $namespace = substr($namespace, $slash + 1);
            } else {
                $namespace = '';
            }

        } elseif (isset($baseNamespace, $this->prefixes[$baseNamespace])) {

            // base namespace to full prefix match
            if (!$this->prefixes[$baseNamespace][1]) {
                $this->normalizePaths($this->prefixes[$baseNamespace]);
            }
            $paths = $this->prefixes[$baseNamespace][0];

        } else {

            // attempt partial prefix match
            foreach ($this->prefixes as $prefix => $prefixData) {
                if (0 === strncmp($className, $prefix, $prefixData[2])) {
                    if (!$prefixData[1]) {
                        unset($prefixData); // avoid copy-on-write
                        $this->normalizePaths($this->prefixes[$prefix]);
                    }
                    $paths = $this->prefixes[$prefix][0];
                    break;
                }
            }

        }

        // determine final path
        if (null !== $paths) {

            // test each path
            $validPath = false;
            for ($i = 0; isset($paths[$i]); ++$i) {
                $path = $this->composePath($paths[$i], $namespace, $bareClassName);
                if (is_file($path)) {
                    $validPath = true;
                    break;
                }
            }

            // cache and return
            if ($validPath) {
                return $this->pathCache[$className] = $path;
            }

        }

        // could not find path
        if ($exceptionOnFailure) {
            throw new RuntimeException(sprintf('Could not find path for "%s"', $className));
        }
    }

    /**
     * Compose path
     * @param  string      $basePath      base path (no trailing slash)
     * @param  string      $namespacePart namespace part that will be projected into the path
     * @param  string|null $classPart     class part (
     * @return string
     */
    public function composePath($basePath, $namespacePart, $classPart = null)
    {
        return
            $basePath
            . (('' !== $namespacePart) ? DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $namespacePart) : '')
            . ((null !== $classPart) ? DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $classPart) . '.php' : '')
        ;
    }

    # methods: internal

    /**
     * Normalize paths in registry entry
     * @param array &$entry
     */
    protected function normalizePaths(&$entry)
    {
        // normalize each path
        for ($i = 0; isset($entry[0][$i]); ++$i) {
            $entry[0][$i] = rtrim(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $entry[0][$i]), DIRECTORY_SEPARATOR);
        }

        // set normalized flag
        $entry[1] = true;
    }

}
