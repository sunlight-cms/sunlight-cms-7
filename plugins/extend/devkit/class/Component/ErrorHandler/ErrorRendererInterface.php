<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [INTERFACE] Error renderer interface definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\ErrorHandler;

/**
 * Error renderer interface
 */
interface ErrorRendererInterface
{

    /** Web interface */
    const INTERFACE_WEB = 0;
    /** CLI interface */
    const INTERFACE_CLI = 1;

    /**
     * Render the error
     *
     * @param bool            $debug        debug mode 1/0
     * @param int             $type         error type, see ErrorHandler::ERROR_X constants
     * @param array           $error        error parameters (message, file, line, [code])
     * @param object|null $exception    exception instance, if available
     * @param string|null     $outputBuffer captured output buffer, if available
     */
    public function render($debug, $type, array $error, $exception = null, $outputBuffer = null);

    /**
     * Get interface
     *
     * @return int see ErrorRendererInterface::INTERFACE_X constants
     */
    public function getInterface();

}
