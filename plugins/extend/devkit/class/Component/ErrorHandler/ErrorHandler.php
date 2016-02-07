<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Error handler class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\ErrorHandler;

use
    Devkit\Util\DebugUtil,
    Devkit\Component\Event\EventDispatcher,
    Devkit\Component\Event\Event
;

/**
 * Error handler class
 *
 * Handles following error conditions:
 *
 *  - fatal errors
 *  - uncaught exceptions
 *  - php errors (conversion to exception)
 *
 */
class ErrorHandler
{

    /** Uncaught exception */
    const ERROR_UNCAUGHT_EXCEPTION = 0;
    /** Fatal error */
    const ERROR_FATAL = 1;

    /** @var bool */
    protected $debug = false;
    /** @var string|null */
    protected $root;
    /** @var EventDispatcher|null */
    protected $eventDispatcher;
    /** @var array error renderers, entry: interface => instance */
    protected $renderers = array();
    /** @var bool */
    protected $handlingFatalError = false;

    /**
     * Register the error handler
     */
    public function register()
    {
        set_error_handler(array($this, 'onError'));
        set_exception_handler(array($this, 'onException'));
        register_shutdown_function(array($this, 'onShutdown'));
    }

    /**
     * Get debug mode
     *
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Toggle debug mode
     *
     * @param  bool         $debug
     * @return ErrorHandler
     */
    public function setDebug($debug)
    {
        $this->debug = (bool) $debug;

        return $this;
    }

    /**
     * Get root directory
     *
     * @return string
     */
    public function getRoot()
    {
        if (null === $this->root) {
            throw new \RuntimeException('Root is not defined');
        }

        return $this->root;
    }

    /**
     * Set root directory
     *
     * @param  string|null  $root
     * @return ErrorHandler
     */
    public function setRoot($root)
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Get event dispatcher
     *
     * @return EventDispatcher|null
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Set event dispatcher
     *
     * @param  EventDispatcher|null $eventDispatcher
     * @return ErrorHandler
     */
    public function setEventDispatcher(EventDispatcher $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Add error renderer
     * Only two renderers can be defined - one for the web interface and the other for CLI.
     *
     * @param  ErrorRendererInterface $renderer
     * @return ErrorHandler
     */
    public function addErrorRenderer(ErrorRendererInterface $renderer)
    {
        $interface = $renderer->getInterface();
        if (ErrorRendererInterface::INTERFACE_WEB !== $interface && ErrorRendererInterface::INTERFACE_CLI !== $interface) {
            throw new \UnexpectedValueException('Unknown error renderer interface');
        }
        if (isset($this->renderers[$interface])) {
            throw new \RuntimeException('Another renderer for given interface already exists');
        }
        $this->renderers[$interface] = $renderer;

        return $this;
    }

    /**
     * Get error renderer for given interface
     *
     * @param  int                    $interface interface, see ErrorRendererInterface::INTERFACE_X constants
     * @return ErrorRendererInterface
     */
    public function getErrorRenderer($interface)
    {
        if (isset($this->renderers[$interface])) {
            return $this->renderers[$interface];
        }
        if (ErrorRendererInterface::INTERFACE_WEB === $interface) {
            return new WebErrorRenderer($this);
        } elseif (ErrorRendererInterface::INTERFACE_CLI === $interface) {
            return new CliErrorRenderer;
        } else {
            throw new \InvalidArgumentException('Unknown error renderer interface');
        }
    }

    /**
     * Unset renderer for given interface
     *
     * @param  int          $interface interface, see ErrorRendererInterface::INTERFACE_X constants
     * @return ErrorHandler
     */
    public function removeErrorRenderer($interface)
    {
        unset($this->renderers[$interface]);

        return $this;
    }

    /**
     * Handle PHP error
     *
     * @throws \ErrorException
     * @param  int             $code    error code
     * @param  string          $message message
     * @param  string|null     $file    file name
     * @param  int|null        $line    line number
     * @return bool
     */
    public function onError($code, $message, $file = null, $line = null)
    {
        $errorReporting = error_reporting();
        $isSurpressed = (0 === ($code & $errorReporting));

        // dispatch event
        if (null !== $this->eventDispatcher) {
            $event = $this->eventDispatcher->dispatch('error_handler.error', new Event(array(
                'code' => $code,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'surpressed' => $isSurpressed,
            )));
            if ($event->isPropagationStopped()) {
                // return if the propagation has been stopped
                return true;
            }
        }

        // ignore suppressed errors
        if ($isSurpressed) {
            return true;
        }

        // throw exception
        throw new \ErrorException($message, $code, 0, $file, $line);
    }

    /**
     * Handle uncaught exception
     *
     * @param $e
     */
    public function onException($e)
    {
        $this->onFatalError(
            self::ERROR_UNCAUGHT_EXCEPTION,
            array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ),
            $e
        );
    }

    /**
     * Check for fatal error on shutdown
     */
    public function onShutdown()
    {
        if (null === ($error = error_get_last())) {
            // no error
            return;
        }
        $this->onFatalError(
            self::ERROR_FATAL,
            array(
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'code' => $error['type'],
            )
        );
    }

    /**
     * Handle fatal error
     *
     * @param int             $type      error type (ErrorHandler::ERROR_X)
     * @param array           $error     error parameters (message, file, line, [code])
     * @param object|null $exception exception instance if available
     */
    protected function onFatalError($type, array $error, $exception = null)
    {
        if ($this->handlingFatalError) {
            return;
        }
        $this->handlingFatalError = true;
        $isCli = ('cli' === PHP_SAPI);
        $headersSent = true;

        $additionalException = null;

        try {
            // fix working directory
            if (null !== $this->root) {
                chdir($this->root);
            }

            // attempt to replace current headers and clean buffers
            if (!$isCli) {
                $headersSent = !DebugUtil::replaceHeaders(array('HTTP/1.1 500 Internal Server Error'));
            }
            $outputBuffer = DebugUtil::cleanBuffers(true);

            // dispatch event
            if (null !== $this->eventDispatcher) {
                $event = $this->eventDispatcher->dispatch('error_handler.fatal', new Event(array(
                    'type' => $type,
                    'error' => $error,
                    'exception' => $exception,
                    'output_buffer' => $outputBuffer,
                    'is_cli' => $isCli,
                )));
                if ($event->isPropagationStopped()) {
                    // return if the propagation has been stopped
                    return;
                }
            }

            // render error
            $this
                ->getErrorRenderer($isCli ? ErrorRendererInterface::INTERFACE_CLI : ErrorRendererInterface::INTERFACE_WEB)
                ->render($this->debug, $type, $error, $exception, $outputBuffer)
            ;
        } catch (\Exception $additionalException) {
        } catch (\Throwable $additionalException) {
        }

        if ($additionalException) {
            // something went terribly wrong
            // additional exception occured when handling the error
            if ($this->debug) {

                // detailed information in debug
                if (!$headersSent) {
                    header('Content-Type: text/plain; charset=UTF-8');
                }
                if ($isCli) {
                    echo "\n";
                }

                echo "Fatal error handler state. Additional exception occured:\n\n";
                $padding = ' > ';

                // additional exception
                echo DebugUtil::formatException($additionalException, $padding);

                // original error
                echo "\n\nOriginal error:\n\n";
                if (null !== $exception) {
                    echo DebugUtil::formatException($exception, $padding);
                } else {
                    echo DebugUtil::formatArray($error, $padding);
                }

            } else {
                // plain message in production
                echo 'Internal server error';
            }
        }

        $this->handlingFatalError = false;
    }

    /**
     * Get name of given error type
     *
     * @param  int         $type
     * @return string|null
     */
    public static function getErrorTypeName($type)
    {
        switch ($type) {
            case self::ERROR_UNCAUGHT_EXCEPTION: return 'Uncaught exception';
            case self::ERROR_FATAL: return 'Fatal error';
        }
    }

}
