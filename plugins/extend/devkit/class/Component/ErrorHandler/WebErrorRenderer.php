<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Web error renderer class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\ErrorHandler;

use
    Devkit\Util\DebugUtil,
    Devkit\Component\Event\Event,
    Devkit\Component\Highlighter\PhpHighlighter
;

/**
 * Web error renderer class
 */
class WebErrorRenderer implements ErrorRendererInterface
{

    /** Code preview - max file size */
    const CODE_PREVIEW_MAX_FILE_SIZE = 2097152; // 2MB
    /** Previous exception limit */
    const PREVIOUS_EXCEPTION_LIMIT = 32;

    /** Flag - narrow wrapper */
    const FLAG_NARROW_WRAPPER = 1;
    /** Flag - no debug assets */
    const FLAG_NO_DEBUG_ASSETS = 2;

    /** @var ErrorHandler */
    protected $errorHandler;
    /** @var PhpHighlighter|null */
    protected $highlighter;
    /** @var int */
    protected $uidSeq = 0;

    /**
     * Constructor
     *
     * @param ErrorHandler $errorHandler
     */
    public function __construct(ErrorHandler $errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterface()
    {
        return self::INTERFACE_WEB;
    }

    /**
     * {@inheritdoc}
     */
    public function render($debug, $type, array $error, $exception = null, $outputBuffer = null)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $html = '';
        $css = null;
        $flags = 0;

        if ($debug) {

            // handle error type
            if (ErrorHandler::ERROR_FATAL === $type || null === $exception) {

                // fatal error
                $title = 'Fatal error';
                $html .= $this->renderError($title, $error);

            } elseif ($exception instanceof \ErrorException) {

                // error exception
                $title = DebugUtil::getErrorNameByCode($exception->getCode());
                if (null === $title) {
                    $title = 'ErrorException';
                }

            } elseif ($exception instanceof DebugException) {

                // debug exception
                $title = $exception->getTitle();
                if (null === $title) {
                    $title = 'Debug';
                }

            } else {

                // exception
                $title = get_class($exception);

            }

            // highlighter css
            $css = $this->getHighlighter()->getCss('div.codePreview > pre ');

            // get extras
            $extras = $this->dispatchEvent('debug.extras', array('html' => ''))->html;

            // render exception
            if (null !== $exception) {

                $exceptionCounter = 0;
                do {
                    $exceptionIsMain = (0 === $exceptionCounter);
                    $html .= $this->renderException($exception, $exceptionIsMain);

                    // render extras after the main exception
                    if ($exceptionIsMain) {
                        $html .= $extras;
                        $html .= $this->renderOutputBuffer($outputBuffer);
                    }

                    // check previous exception limit
                    if (++$exceptionCounter >= self::PREVIOUS_EXCEPTION_LIMIT) {
                        break;
                    }
                } while ($exception = $exception->getPrevious());

            } else {

                // render extras only
                $html .= $extras;
                $html .= $this->renderOutputBuffer($outputBuffer);

            }

        } else {

            // dispatch event
            $event = $this->dispatchEvent('no_debug', array(
                'flags' => self::FLAG_NARROW_WRAPPER | self::FLAG_NO_DEBUG_ASSETS,
                'title' => 'Internal server error',
                'html' => '',
            ));

            // set contents
            $flags = $event->flags;
            $title = $event->title;
            if ('' !== $event->html) {
                $html = $event->html;
            } else {
                $html = "<div class=\"section sectionMajor sectionStandalone\">
<h1>{$title}</h1>
<p>An unexpected error has occured while processing your request.</p>
</div>\n";
            }

        }

        // render layout
        $this->renderLayout(
            $flags,
            $title,
            $html,
            $css
        );
    }

    /**
     * Render the HTML layout
     *
     * @param int         $flags   theme flags, see WebErrorHandler::FLAG_X constants
     * @param string      $title   main title
     * @param string      $content html content
     * @param string|null $css     extra css
     */
    public function renderLayout($flags, $title, $content, $css = null)
    {
        // read flags
        $debugAssets = (0 === ($flags & self::FLAG_NO_DEBUG_ASSETS));

        ?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<style type="text/css">
<?php echo $this->getLayoutCss($flags) ?>
<?php if(null !== $css) echo $css; ?>
</style>
<title><?php echo $this->escape($title) ?></title>
</head>

<body>

    <div id="wrapper">

        <div id="content">
        <?php echo $content ?>

        </div>

    </div>

<?php if($debugAssets): ?>
<script type="text/javascript">
<?php echo $this->getLayoutJs() ?>

</script>
<?php endif ?>
</body>
</html><?php
    }

    /**
     * Get CSS for the HTML layout
     *
     * @param  int    $flags theme flags, see WebErrorHandler::FLAG_X constants
     * @return string
     */
    public function getLayoutCss($flags = 0)
    {
        // read flags
        $narrowWrapper = (0 !== ($flags & self::FLAG_NARROW_WRAPPER));
        $debugAssets = (0 === ($flags & self::FLAG_NO_DEBUG_ASSETS));

        ob_start();
        ?>
* {margin: 0; padding: 0;}
body {background-color: #f0f0f0; font-family: 'Trebuchet MS', 'Geneva CE', lucida, sans-serif; font-size: 13px;}
h1, h2, h3 {font-weight: normal;}
h1 {font-size: 2em;}
h2 {font-size: 1.5em;}
p {line-height: 140%; margin: 0.7em 0;}
em {color: #777;}
table {border-collapse: collapse;}
td, th {padding: 0.5em 1em; border: 1px solid #ddd;}
th {background-color: #ddd;}
td {background-color: #fff;}

#wrapper {margin: 2em auto; overflow: hidden; max-width: <?php echo $narrowWrapper ? '700' : '1200' ?>px; background-color: #ddd; -webkit-box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.5); box-shadow:  0px 0px 10px 0px rgba(0, 0, 0, 0.5);}

div.section {margin: 0.5em 1em; border-radius: 10px;}
div.section > pre {padding: 0.5em; margin-top: 0.5em; border: 3px double #ddd; background-color: #e5e5e5; overflow: auto;}
div.sectionMajor {margin: 0; padding: 1em; border: 3px double #eee; background-color: #fff;}
div.sectionStandalone {margin: 1em;}
div.sectionGroup {margin: 1em; overflow: hidden; border-radius: 10px; background-color: #eee;}
div.sectionGroup:last-child {margin-bottom: 1em;}
<?php if($debugAssets): ?>

div.outputBuffer textarea {width: 95%; margin: 1em 0; padding: 0.5em;}

div.codePreview {position: relative; overflow: auto; border: 1px solid #ddd; line-height: 150%; font-family: monospace; white-space: nowrap; background-color: #fff;}
div.codePreview > pre > span.activeLine {background-color: #ebf4ff;}
div.codePreview span {line-height: 22px;}
div.codePreview > div.codePreviewLines {float: left; margin-right: 0.5em; background-color: #ddd; color: #777;}
div.codePreview > div.codePreviewLines > span {padding-right: 0.7em; padding-left: 0.7em;}

table.trace {width: 100%;}
table.trace > tbody > tr.trace > th {width: 1%;}
table.trace > tbody > tr.trace > td {cursor: pointer;}
table.trace > tbody > tr.trace:hover > td {background-color: #eee;}
table.trace > tbody > tr.trace.traceOpen > td {background-color: #eee;}
table.trace > tbody > tr.traceExtra {display: none;}

table.argumentList {margin-top: 0.5em; width: 100%;}
table.argumentList:first-child {margin: 0;}
table.argumentList > tbody > tr > th,
table.argumentList > tbody > tr > th {width: 1%;}

.hidden {display: none;}
<?php endif ?>
<?php

        return ob_get_clean();
    }

    /**
     * Get javascript for the HTML layout
     *
     * @return string
     */
    public function getLayoutJs()
    {
        ob_start();
        ?>
(function(){
    window.errorHandlerToggleTrace = function(id) {
        var trace = document.getElementById('trace_'+id),
            traceExtra = document.getElementById('trace_extra_'+id)
        ;
        if (trace && traceExtra) {
            if ('' === traceExtra.style.display || 'none' === traceExtra.style.display) {
                // show
                trace.className = 'trace traceOpen';
                traceExtra.style.display = 'table-row';
            } else {
                // hide
                trace.className = 'trace traceClosed';
                traceExtra.style.display = 'none';
            }
        }
    }
})();
<?php

        return ob_get_clean();
    }

    /**
     * Escape string for rendering in HTML
     *
     * @param  string $str the string to escape
     * @return string html
     */
    public function escape($str)
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
    }

    /**
     * Render error
     *
     * @param  bool   $title title
     * @param  array  $error the error array
     * @return string html
     */
    public function renderError($title, array $error)
    {
        $html = "<div class=\"sectionGroup\">
<div class=\"section sectionMajor\">
<h1>{$title}</h1>
<p>" . $this->escape($error['message']) . "</p>
<p>in <em>" . $this->renderFilePath($error['file']) . "</em> on line <em>{$error['line']}</em></p>
</div>\n";

        // code preview
        $codePreview = $this->renderCodePreview($error['file'], $error['line']);
        if (null !== $codePreview) {
            $html .= "<div class=\"section\">{$codePreview}</div>\n";
            $codePreview = null;
        }

        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render code preview
     *
     * @param  string      $file        file path
     * @param  int         $line        line number
     * @param  int         $lineRange   range of lines to render (range-line-range)
     * @param  int         $maxFileSize maximum file size
     * @return string|null
     */
    public function renderCodePreview($file, $line, $lineRange = 5, $maxFileSize = self::CODE_PREVIEW_MAX_FILE_SIZE)
    {
        if (!is_file($file) || filesize($file) > $maxFileSize || $line < 1) {
            return;
        }

        $highlighter = $this->getHighlighter();

        // tokenize file
        $tokens = $highlighter->tokenize(file_get_contents($file), null, $line + 5);

        // get tokens from range
        $tokens = $highlighter->getTokensFromRange($tokens, $line - $lineRange, $line + $lineRange);

        // render lines
        $lines = '<div class="codePreviewLines">';
        $endLine = min($line + $lineRange, $highlighter->getLastLine($tokens));
        for ($i = max(1, $line - $lineRange); $i <= $endLine; ++$i) {
            $lines .= '<span' . (($i === $line) ? ' class="activeLine"' : '') . '>' . $i . '</span><br>';
        }
        $lines .= '</div>';

        // render container and tokens
        return '<div class="codePreview">' . $lines . $highlighter->render($tokens, false, true, array($line => true)) . "</div>\n";
    }

    /**
     * Render exception
     *
     * @param  object  $e    the exception instance
     * @param  bool        $main render as main exception (h1 title, ...)
     * @return string|null html
     */
    public function renderException($e, $main = false)
    {
        $trace = $e->getTrace();

        $message = '';
        $info = '';

        if ($e instanceof DebugException) {

            // debug exception
            $title = "<em>Debug";
            $isDebugException = true;
            $debugExceptionTitle = $e->getTitle();
            $debugExceptionDump = $e->getMessage();
            if (null !== $debugExceptionTitle) {
                $title .= ":</em> {$debugExceptionTitle}";
            } else {
                $title .= "</em>";
            }
            if ('' !== $debugExceptionDump) {
                $message = '<pre>' . $this->escape($debugExceptionDump) . '</pre>';
            }

        } else {

            // error or other exception
            if ($e instanceof \ErrorException) {
                $title = DebugUtil::getErrorNameByCode($e->getCode());
                if (null == $title) {
                    $title = get_class($e);
                }
                array_shift($trace);
            } else {
                $title = get_class($e);
            }
            $isDebugException = false;
            $message = '<p>' . $this->escape($e->getMessage()) . '</p>';
            $info = "\n<p>in <em>" . $this->renderFilePath($e->getFile()) . "</em> on line <em>" . $e->getLine() . "</em></p>";

        }

        // title, message, file
        $html = "<div class=\"sectionGroup\">
<div class=\"section sectionMajor\">
<h" . ($main ? '1' : '2') . ">{$title}</h" . ($main ? '1' : '2') . ">
{$message}{$info}
</div>\n";

        // code preview
        if (!$isDebugException) {
            $codePreview = $this->renderCodePreview($e->getFile(), $e->getLine(), $main ? 5 : 3);
            if (null !== $codePreview) {
                $html .= "<div class=\"section\">{$codePreview}</div>\n";
                $codePreview = null;
            }
        }

        // trace
        if (!empty($trace)) {
            $html .= $this->renderTrace($trace, $isDebugException);
        }

        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render trace
     *
     * @param  array  $trace                   the trace array
     * @param  bool   $hideFirstFrameArguments do not render arguments of the first frame 1/0
     * @return string html
     */
    public function renderTrace(array $trace, $hideFirstFrameArguments = false)
    {
        $traceCounter = sizeof($trace) - 1;
        $html = "<div class=\"section\">
<table class=\"trace\">
<tbody>\n";

        foreach ($trace as $frameIndex => $frame) {

        $frameUid = $this->getUid();

        // call
        if (isset($frame['type'], $frame['class'])) {
            $call = "{$frame['class']}{$frame['type']}";
        } else {
            $call = '';
        }

        // file and line
        if (isset($frame['file'])) {
            $file = $this->renderFilePath($frame['file']);
            if (isset($frame['line']) && $frame['line'] > 0) {
                $file .= " <em>({$frame['line']})</em>";
            }
        } else {
            $file = '-';
        }

        // row
        $html .= "<tr class=\"trace traceClosed\" id=\"trace_{$frameUid}\" onclick=\"errorHandlerToggleTrace({$frameUid})\">
        <th>{$traceCounter}</th>
        <td><em>{$call}</em>{$frame['function']}<em>(" . (isset($frame['args']) ? sizeof($frame['args']) : 0) . ")</em></td>
        <td>{$file}</td>
    </tr>

    <tr class=\"traceExtra\" id=\"trace_extra_{$frameUid}\">
    <td></td>
    <td colspan=\"2\">\n";

        // code preview
        if (isset($frame['file'], $frame['line'])) {
            $html .= $this->renderCodePreview($frame['file'], $frame['line'], 3);
        }

        // arguments
        if (!empty($frame['args']) && (0 !== $frameIndex || !$hideFirstFrameArguments)) {
            $html .= $this->renderArguments($frame['args']);
        }

        $html .= "
    </td>
    </tr>\n";
        --$traceCounter;

        }

        $html .= "</tbody>
</table>
</div>\n";

        return $html;
    }

    /**
     * Render arguments
     *
     * @param  array  $args array of arguments
     * @return string html
     */
    public function renderArguments(array $args)
    {
        $html = "<table class=\"argumentList\"><tbody>\n";
        for ($i = 0, $argCount = sizeof($args); $i < $argCount; ++$i) {
            $html .= "<tr><th>{$i}</th><td><pre>" . $this->escape(DebugUtil::getDump($args[$i])) . "</pre></td></tr>\n";
        }
        $html .= "</tbody></table>\n";

        return $html;
    }

    /**
     * Render output buffer
     *
     * @param  string|null $outputBuffer
     * @param  int         $maxLen       maximum length of output buffer to be rendered
     * @return string      html
     */
    public function renderOutputBuffer($outputBuffer, $maxLen = 102400)
    {
        // see if buffer is empty
        if (null === $outputBuffer || '' === $outputBuffer) {
            return '';
        }

        // analyse value
        $message = null;
        $show = false;
        if (strlen($outputBuffer) > $maxLen) {
            $message = 'The output buffer contents are too long.';
        } elseif (0 !== preg_match('/[\\x00-\\x09\\x0B\\x0C\\x0E-\\x1F]/', $outputBuffer)) {
            $message = 'The output buffer contains unprintable characters.';
        } else {
            $rows = 1 + min(10, preg_match_all('/\\r\\n|\\n|\\r/', $outputBuffer, $matches));
            $show = true;
        }

        // render
        return "<div class=\"sectionGroup outputBuffer\">
<div class=\"section sectionMajor\">
<h2>Output buffer <em>(" . strlen($outputBuffer) . ")</em></h2>\n"
        . ((null === $message) ? '' : "<p>{$message}</p>\n")
        . ($show ? "<textarea rows=\"{$rows}\" cols=\"80\">" . $this->escape($outputBuffer) . "</textarea>\n" : '')
        . "</div>\n</div>\n";
    }

    /**
     * Render file path
     *
     * @param  string $file
     * @return string html
     */
    protected function renderFilePath($file)
    {
        $root = $this->errorHandler->getRoot();
        $rootLen = strlen($root);
        if ($root === substr($file, 0, $rootLen)) {
            $file = substr($file, $rootLen);
        }

        return $this->escape($file);
    }

    /**
     * Get PHP highlighter
     *
     * @return PhpHighlighter
     */
    protected function getHighlighter()
    {
        if (null === $this->highlighter) {
            $this->highlighter = new PhpHighlighter;
        }

        return $this->highlighter;
    }

    /**
     * Get unique ID
     *
     * @return int
     */
    protected function getUid()
    {
        return $this->uidSeq++;
    }

    /**
     * Dispatch an event
     *
     * @param  string $eventName  partial event name (is prefixed automatically)
     * @param  array  $eventAttrs event attributes
     * @return Event
     */
    protected function dispatchEvent($eventName, $eventAttrs = array())
    {
        $event = new Event($eventAttrs);
        $eventDispatcher = $this->errorHandler->getEventDispatcher();
        if (null !== $eventDispatcher) {
            $eventDispatcher->dispatch("error_handler.web.{$eventName}", $event);
        }

        return $event;
    }

}
