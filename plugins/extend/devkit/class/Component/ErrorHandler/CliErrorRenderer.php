<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] CLI error renderer class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\ErrorHandler;

use
    Devkit\Util\DebugUtil
;

/**
 * CLI error renderer class
 */
class CliErrorRenderer implements ErrorRendererInterface
{

    /**
     * {@inheritdoc}
     */
    public function getInterface()
    {
        return self::INTERFACE_CLI;
    }

    /**
     * {@inheritdoc}
     */
    public function render($debug, $type, array $error, $exception = null, $outputBuffer = null)
    {
        echo "\n";
        if ($debug) {
            if ($exception instanceof DebugException) {

                // debug exception
                echo "[Debug";
                if (null !== $exception->getTitle()) {
                    echo ': ', $exception->getTitle();

                }
                echo "]\n\n", $exception->getMessage();

            } else {

                // error or exception
                echo '[', ErrorHandler::getErrorTypeName($type), "]\n\n";

                if (null === $exception) {
                    // error
                    echo DebugUtil::formatArray($error, ' > ');
                } else {
                    // exception
                    echo DebugUtil::formatException($exception, ' > ');
                }

            }
        } else {

            // non-debug
            echo 'Internal application error';

        }
    }

}
