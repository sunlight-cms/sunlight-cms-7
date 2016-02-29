<?php

/**
 * [CLASS] File cache class definition
 * @author ShiraNai7 <shira.cz>
 */

/**
 * File cache
 */
class FileCache
{

    /** @var string */
    protected $path;
    /** @var bool */
    protected $verifyBoundFiles = true;

    /**
     * Constructor
     *
     * @param string $path path to the cache directory
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Check if an entry exists and is valid
     *
     * @param string $category category name
     * @param string $name entry name
     * @return bool
     */
    public function has($category, $name)
    {
        $metaPath = $this->composePath($category, $name) . '.meta';
        return is_file($metaPath) && $this->validateMeta(include $metaPath);
    }

    /**
     * Get an entry
     *
     * @param string $category category name
     * @param string $name entry name
     * @param array|null &$metaData meta data variable
     * @return mixed false on failure
     */
    public function get($category, $name, &$metaData = null)
    {
        $basePath = $this->composePath($category, $name);
        if (is_file($metaPath = $basePath . '.meta')) {
            if ($this->validateMeta($metaData = include $metaPath)) {
                return include $basePath . '.data';
            } else {
                $this->remove($category, $name);
            }
        }

        return false;
    }

    /**
     * Get metadata of an entry
     *
     * @param string $category category name
     * @param string $name entry name
     * @param bool $validate validate the meta data before returning 1/0
     * @return array|bool false on failure
     */
    public function getMeta($category, $name, $validate = true)
    {
        $metaPath = $this->composePath($category, $name) . '.meta';
        if (is_file($metaPath)) {
            $metaData = include $metaPath;
            if (!$validate || $this->validateMeta($metaData)) {
                return $metaData;
            } else {
                $this->remove($category, $name);
            }
        }

        return false;
    }

    /**
     * Create/overwrite an entry
     *
     * Options
     * -------
     * ttl              time to live in seconds
     * bound_files      array of bound files
     * meta             custom data to store in meta data
     *
     * @param string $category category name
     * @param string $name entry name
     * @param mixed $data data to store
     * @param array|null $options array of options
     * @return bool
     */
    public function put($category, $name, $data, array $options = null)
    {
        list($path, $filename) = $this->composePath($category, $name, true);

        // compose meta data
        $metaData = array(
            'category' => $category,
            'name' => $name,
            'bound_files' => isset($options['bound_files']) ? $this->compileBoundFiles($options['bound_files']) : null,
            'expires' => isset($options['ttl']) ? time() + $options['ttl'] : null,
            'time' => time(),
        );
        if (isset($options['meta'])) {
            $metaData += $options['meta'];
        }

        // create path
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }

        // store meta data
        file_put_contents($path . DIRECTORY_SEPARATOR . $filename . '.meta', $this->serialize($metaData), LOCK_EX);

        // store data
        file_put_contents($path . DIRECTORY_SEPARATOR . $filename . '.data', $this->serialize($data), LOCK_EX);

        return true;
    }

    /**
     * Remove an entry
     *
     * @param string $category category name
     * @param string $name entry name
     * @return bool
     */
    public function remove($category, $name)
    {
        $basePath = $this->composePath($category, $name);
        if (is_file($metaPath = $basePath . '.meta')) {
            unlink($metaPath);
        }
        if (is_file($dataPath = $basePath . '.data')) {
            unlink($dataPath);
        }

        return true;
    }

    /**
     * Clear entries
     *
     * @param string|null $category category name or null
     * @return bool
     */
    public function clear($category = null)
    {
        $path = $this->path . ((null === $category) ? '' : DIRECTORY_SEPARATOR . $category);
        if (!is_dir($path)) {
            // nothing to remove
            return true;
        }
        $recursiveDirectoryIteratorFlags = FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS;
        foreach(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, $recursiveDirectoryIteratorFlags),
                RecursiveIteratorIterator::CHILD_FIRST
            ) as $item
        ) {

            // remove
            if ($item->isDir()) {
                // directory
                rmdir($item->getPathname());
            } else {
                // file
                unlink($item->getPathname());
            }

        }

        return true;
    }

    /**
     * Get verify bound files
     *
     * @return bool
     */
    public function getVerifyBoundFiles()
    {
        return $this->verifyBoundFiles;
    }

    /**
     * Set verify bound files
     *
     * @param bool $verifyBoundFiles
     * @return CacheInterface
     */
    public function setVerifyBoundFiles($verifyBoundFiles)
    {
        $this->verifyBoundFiles = $verifyBoundFiles;
        return $this;
    }

    /**
     * Compose cache entry path
     *
     * @param string $category category name
     * @param string|null $name entry name
     * @param bool $asArray return as array(path, filename)
     * @return string|array
     */
    protected function composePath($category, $name, $asArray = false)
    {
        $hash = md5($name);
        if ($asArray) {
            return array(
                $this->path
                    . DIRECTORY_SEPARATOR . $category
                    . DIRECTORY_SEPARATOR . substr($hash, 0, 3)
                    . DIRECTORY_SEPARATOR . substr($hash, 3, 3),
                substr($hash, 6),
            );
        } else {
            return $this->path
                . DIRECTORY_SEPARATOR . $category
                . DIRECTORY_SEPARATOR . substr($hash, 0, 3)
                . DIRECTORY_SEPARATOR . substr($hash, 3, 3)
                . DIRECTORY_SEPARATOR . substr($hash, 6)
            ;
        }
    }

    /**
     * Compile list of bound files
     *
     * @param array $boundFiles
     * @return array
     */
    protected function compileBoundFiles(array $boundFiles)
    {
        $compiledBoundFiles = array();
        foreach ($boundFiles as $boundFile) {
            if (!is_file($boundFile)) {
                throw new RuntimeException(sprintf('Invalid bound file "%s"', $boundFile));
            }
            $compiledBoundFiles[] = array($boundFile, filemtime($boundFile));
        }

        return $compiledBoundFiles;
    }

    /**
     * Validate entry meta data
     *
     * @param array $metaData
     * @return bool
     */
    protected function validateMeta(array $metaData)
    {
        // check expiration time
        if (null !== $metaData['expires'] && $metaData['expires'] < time()) {
            return false;
        }

        // check bound files
        if ($this->verifyBoundFiles && null !== $metaData['bound_files']) {
            foreach ($metaData['bound_files'] as $boundFile) {
                if (!is_file($boundFile[0]) || filemtime($boundFile[0]) !== $boundFile[1]) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Serialize given data into valid PHP code
     *
     * @param  mixed  $data          the data to serialize
     * @param  int    $maxArrayLevel maximum array nesting level (to prevent infinite loop on recursive data)
     * @return string valid PHP code
     */
    protected function serialize($data, $maxArrayLevel = 512)
    {
        // prepare output
        $out = '<?php if (!defined(\'_core\')) die; return ';

        // prepare queue
        // queue item format:
        // [0]: item type - 0/value, 1/arr-item, 2/arr-last-item, 3/arr-last-item-nested
        // [1]: value
        // [2]: array key / null
        // [3]: array level
        $queue = array(array(0, $data, null, 0));
        $queueStack = array();
        $data = null;

        // serialize
        do {

            while ($item = current($queue)) {

                $isEndOfQueue = (false === next($queue));

                if ($item[3] >= $maxArrayLevel) {
                    throw new OverflowException('Array nesting level exceeded - recursive data?');
                }

                // handle type prefix
                if (0 !== $item[0]) {
                    // array item
                    $out .= var_export($item[2], true) . '=>';
                }

                // handle value
                if (is_array($item[1])) {

                    // an array
                    $out .= 'array(';

                    // check for empty array
                    if (!empty($item[1])) {

                        // store current queue
                        if (!$isEndOfQueue) {
                            $queueStack[] = $queue;
                        }

                        // create new queue for the items
                        $queue = array();
                        $arrSize = sizeof($item[1]);
                        $arrCounter = 0;
                        foreach ($item[1] as $arrKey => $arrVal) {
                            ++$arrCounter;
                            if ($arrCounter === $arrSize) {
                                if(1 === $item[0] || 3 === $item[0]) $arrItemMode = 3;
                                else $arrItemMode = 2;
                                $arrItemModeLevel = $item[3] + 1;
                            } else {
                                $arrItemMode = 1;
                                $arrItemModeLevel = 0;
                            }
                            $queue[] = array($arrItemMode, $arrVal, $arrKey, $arrItemModeLevel);
                        }
                        continue;

                    } else {

                        // empty array
                        $out .= ')';

                    }

                } elseif (is_object($item[1])) {

                    // object
                    $out .= 'unserialize(' . var_export(serialize($item[1]), true) . ')';

                } else {

                    // other
                    $out .= var_export($item[1], true);

                }

                // handle type suffix
                switch ($item[0]) {

                    // array item
                    case 1:
                        $out .= ',';
                        break;

                    // last array item
                    case 2:
                    case 3:
                        $out .= str_repeat(')', $item[3]);
                        if(3 === $item[0]) $out .= ',';
                        break;

                }

            }

        } while ($queue = array_pop($queueStack));

        // finish
        $out .= ';';

        // return
        return $out;
    }

}
