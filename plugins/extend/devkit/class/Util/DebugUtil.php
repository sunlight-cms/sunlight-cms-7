<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Debugger class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Util;

/**
 * Debug utility class
 */
abstract class DebugUtil
{

    /** Previous exception limit */
    const PREVIOUS_EXCEPTION_LIMIT = 32;

    /**
     * Dump a value
     *
     * @param  mixed $value        the value to dump
     * @param  int   $maxLevel     maximum nesting level
     * @param  int   $maxStringLen limit of string characters do dump
     * @param  int   $currentLevel current nesting level
     * @return null  prints the output
     */
    public static function dump($value, $maxLevel = 2, $maxStringLen = 64, $currentLevel = 1)
    {
        // indentation
        $indent = str_repeat('    ', $currentLevel);

        // dump
        if (is_array($value)) {

            // array
            if ($currentLevel < $maxLevel) {

                // full
                echo "array(", sizeof($value), ") {\n";
                foreach ($value as $arrKey => $arrValue) {
                    echo $indent, '[', $arrKey, '] => ';
                    self::dump($arrValue, $maxLevel, $maxStringLen, $currentLevel + 1);
                }
                if($currentLevel > 1) echo str_repeat('    ', $currentLevel - 1);
                echo "}\n";

            } else {

                // short
                echo "array(", sizeof($value), ")\n";

            }

        } elseif (is_object($value)) {

            // object
            if ($currentLevel < $maxLevel) {

                // full
                echo "object(", get_class($value), ") {\n";
                try {
                    $objReflection = new \ReflectionObject($value);
                    $objPropertyFilter = \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE;
                    foreach ($objReflection->getProperties($objPropertyFilter) as $objProp) {
                        if (!$objProp->isPublic()) {
                            $objProp->setAccessible(true);
                        }
                        echo $indent, '[', $objProp->getName(), '] => ';
                        self::dump($objProp->getValue($value), $maxLevel, $maxStringLen, $currentLevel + 1);
                    }
                } catch (\Exception $e) {
                    // ignore reflection errors
                    // some internal or extension objects may not be fully accessible
                }
                if ($currentLevel > 1) {
                    echo str_repeat('    ', $currentLevel - 1);
                }
                echo "}\n";

            } else {

                // short
                echo "object(", get_class($value), ")\n";

            }

        } elseif (is_string($value)) {

            // string
            $strLen = strlen($value);
            echo "string({$strLen}) ";
            if ($strLen > $maxStringLen) {
                var_export(substr($value, 0, $maxStringLen));
                echo "...";
            } else {
                var_export($value);
            }
            echo "\n";

        } else {

            // other
            var_dump($value);

        }
    }

    /**
     * Dump value and return the result
     *
     * @param  mixed  $value        the value to dump
     * @param  int    $maxLevel     maximum nesting level
     * @param  int    $maxStringLen limit of string characters do dump
     * @return string
     */
    public static function getDump($value, $maxLevel = 2, $maxStringLen = 64)
    {
        ob_start();
        $e = null;
        try {
            self::dump($value, $maxLevel, $maxStringLen);

            return ob_get_clean();
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        if ($e) {
            ob_clean();
            throw $e;
        }
    }

    /**
     * Format simple associative array of scalar values
     *
     * @param  array  $arr     the array
     * @param  string $padding left pading for all output lines
     * @return string
     */
    public static function formatArray(array $arr, $padding = '')
    {
        if (empty($arr)) {
            return '';
        }

        // figure maximum key length
        $maxKeyLen = max(array_map('strlen', array_keys($arr)));

        // format array
        ob_start();
        foreach ($arr as $key => $value) {

            echo "{$padding}{$key}:";
            $keyPadding = $maxKeyLen - strlen($key);
            if ($keyPadding > 0) {
                echo str_repeat(' ', $keyPadding);
            }
            echo ' ';
            if (is_string($value) || is_numeric($value)) {
                echo $value, "\n";
            } else {
                self::dump($value, 1);
            }

        }

        // return
        return ob_get_clean();
    }

    /**
     * Attempt to clean all output buffers
     *
     * @param  bool        $capture attempt to capture and return buffer contents 1/0
     * @return string|null
     */
    public static function cleanBuffers($capture = false)
    {
        // clear
        $buffer = ($capture ? '' : null);
        try {
            while (0 !== ($bufferLevel = ob_get_level())) {
                if ($capture) {
                    $buffer = $buffer . ob_get_clean();
                } else {
                    ob_end_clean();
                }
            }
        } catch (\ErrorException $e) {
            // some built-in buffers cannot be cleaned
        }

        // return
        return $buffer;
    }

    /**
     * Attempt to replace headers
     *
     * @param  array $headers list of new headers to set
     * @return bool
     */
    public static function replaceHeaders(array $newHeaders)
    {
        if (!headers_sent()) {
            header_remove();
            for ($i = 0; isset($newHeaders[$i]); ++$i) {
                header($newHeaders[$i]);
            }

            return true;
        }

        return false;
    }

    /**
     * Get text information about an exception
     *
     * @param  object  $e            the exception instance
     * @param  string|null $padding      left padding for output lines
     * @param  string|null $tracePadding padding for output lines containing traces or null (= use $padding)
     * @param  bool        $showPrevious show previous exceptions 1/0
     * @param  bool        $showTrace    show exception traces 1/0
     * @return string
     */
    public static function formatException($e, $padding = null, $tracePadding = null, $showPrevious = true, $showTrace = true)
    {
        // prepare padding
        if (null === $padding) {
            $padding = '';
        }
        if (null === $tracePadding) {
            $tracePadding = $padding;
        }

        // compose infos
        $output = '';
        $counter = 0;
        do {

            // check limit
            if (++$counter >= self::PREVIOUS_EXCEPTION_LIMIT) {
                break;
            }

            // separate
            if (1 !== $counter) {
                $output .= "\n\n";
            }

            // header and main info
            $output .= "{$padding}class:   " . get_class($e) . "
{$padding}message: {$e->getMessage()}
{$padding}file:    {$e->getFile()}
{$padding}line:    {$e->getLine()}
";

            // trace
            if ($showTrace) {
                $output .= "{$padding}\n{$padding}trace:\n";
                $trace = $e->getTraceAsString();
                foreach (explode("\n", $trace) as $traceLine) {
                    $output .= "{$tracePadding}{$traceLine}\n";
                }
            }

        } while ($showPrevious && ($e = $e->getPrevious()));

        // return
        return $output;
    }

    /**
     * Get PHP error name by its code
     *
     * @param  int         $code PHP error code
     * @return string|null
     */
    public static function getErrorNameByCode($code)
    {
        switch ($code) {
            case E_ERROR: return 'Error';
            case E_WARNING: return 'Warning';
            case E_NOTICE: return 'Notice';
            case E_USER_ERROR: return 'User error';
            case E_USER_WARNING: return 'User warning';
            case E_USER_NOTICE: return 'User notice';
            case E_STRICT: return 'Strict notice';
            case E_DEPRECATED: return 'Deprecated';
            case E_USER_DEPRECATED: return 'User deprecated';
            case E_RECOVERABLE_ERROR: return 'Recoverable error';
        }
    }

}
