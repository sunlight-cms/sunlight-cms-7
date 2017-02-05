<?php

// kontrola jadra
if (!defined('_core')) {
    return;
}

/* ----- inicializace ----- */

// class autoloader
SL::$classLoader->registerBaseNamespace('Devkit', __DIR__ . DIRECTORY_SEPARATOR . 'class', true);

// vytvorit sql logger
$sqlLogger = new DevkitDebuggerSqlLogger;

// napojit sql logger
DB::$logger = array($sqlLogger, 'log');

// vytvorit event dispatcher
$eventDispatcher = new Devkit\Component\Event\EventDispatcher;

// vytvorit error handler
$errorHandler = new Devkit\Component\ErrorHandler\ErrorHandler;
$errorHandler
    ->setRoot(realpath(_indexroot))
    ->setEventDispatcher($eventDispatcher)
    ->setDebug(true)
    ->register()
;
SL::$registry['devkit_error_handler'] = $errorHandler;

// zaregistrovat sql logger
$eventDispatcher
    ->addCallback(
        'error_handler.web.debug.extras',
        array($sqlLogger, 'showInDebugScreen')
    )
;

/* ----- extend ----- */

_extend('regm', array(

    // logovani _mail()
    'sys.mail' => function ($args) {

        $time = _formatTime(time());
        $args['handled'] = true;

        file_put_contents(_indexroot . 'mail.log', <<<ENTRY
Time: {$time}
Recipient: {$args['to']}
Subject: {$args['subject']}
{$args['headers']}

{$args['message']}

=====================================
=====================================





ENTRY
        , FILE_APPEND);

    },

    // css (web)
    'tpl.head.meta' => function ($args) {
        $args['output'] .= "\n<link rel='stylesheet' type='text/css' href='" . _indexroot . "plugins/extend/devkit/public/devkit.css' />";
    },

    // js (web)
    'tpl.head' => function ($args) {
        $args['output'] .= "\n<script type='text/javascript' src='" . _indexroot . "plugins/extend/devkit/public/devkit.js'></script>";
    },

    // css + js (admin
    'admin.start' => function () {
        $GLOBALS['admin_extra_css'][] = "<link rel='stylesheet' type='text/css' href='" . _indexroot . "plugins/extend/devkit/public/devkit.css' />";
        $GLOBALS['admin_extra_js'][] = "<script type='text/javascript' src='" . _indexroot . "plugins/extend/devkit/public/devkit.js'></script>";
    },

));


/* ----- tridy ---- */

/**
 * Devkit class
 */
abstract class Devkit
{

    /**
     * Debug hodnoty
     *
     * @param mixed $value
     * @param int $maxLevel
     * @param int $maxStringLen
     */
    public static function debug($value, $maxLevel = 5, $maxStringLen = 128)
    {
        SL::$registry['devkit_error_handler']->onException(
            new Devkit\Component\ErrorHandler\DebugException(
                is_object($value) ? get_class($value) : gettype($value),
                Devkit\Util\DebugUtil::getDump($value, $maxLevel, $maxStringLen)
            )
        );
        die(1);
    }

}

/**
 * Devkit debugger SQL logger
 */
class DevkitDebuggerSqlLogger
{

    /** @var array */
    protected $log = array();

    /**
     * Zalogovat SQL dotaz
     */
    public function log($query)
    {
        $this->log[] = _cutStr($query, 2048, false);
    }

    /**
     * Ziskat aktualni log
     *
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Zobrazit SQL log v ladence
     *
     * @param Devkit\Component\Event\Event $event
     */
    public function showInDebugScreen(Devkit\Component\Event\Event $event)
    {
        $html =  '<div class="sectionGroup">
<div class="section sectionMajor">
<h2>SQL log <em>(' . sizeof($this->log) . ')</em></h2>
<pre>
';
        foreach ($this->log as $index => $query) {
            $html .= ($index + 1) . ': ' . _htmlStr($query) . "\n\n";
        }
        $html .= '</pre>
</div>
</div>
';
        $event->set('html', $html);
    }

}

/**
 * Devkit debugger output handler
 */
class DevkitDebuggerOutputHandler
{

    /** @var DevkitDebuggerSqlLogger */
    protected $logger;

    /**
     * Konstruktor
     *
     * @param DevkitDebuggerSqlLogger $logger
     */
    public function __construct(DevkitDebuggerSqlLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Zpracovat vystup
     */
    public function __invoke($contents)
    {
        return str_replace('</body>', $this->toolbarOutput() . '</body>', $contents);
    }

    /**
     * Sestavit vystup pro toolbar
     *
     * @return string
     */
    protected function toolbarOutput()
    {
        $now = microtime(true);

        // ziskat sql log
        $sqlLog = $this->logger->getLog();

        // zjistit neoptimalizovane query
        $sqlLogSlow = array();
        $explainExtraSlowIndicators = array('Using temporary' => 0);
        foreach ($sqlLog as $sqlIndex => $sql) {
            if (1 !== preg_match('/^(?!\s*EXPLAIN)(\s*[a-z_]+)*\s*SELECT/i', $sql)) {
                continue;
            }
            $explainQuery = DB::query('EXPLAIN ' . $sql, true, false);
            if ($explainQuery) {
                while ($explainRow = DB::row($explainQuery)) {
                    $explainExtra = preg_split('/\\s*;\\s*/', $explainRow['Extra']);
                    for ($i = 0; isset($explainExtra[$i]); ++$i) {
                        if (isset($explainExtraSlowIndicators[$explainExtra[$i]])) {
                            $sqlLogSlow[$sqlIndex] = $explainRow['Extra'];
                            break 2;
                        }
                    }
                }
                DB::free($explainQuery);
            }
        }
        $sqlLogSlowCount = sizeof($sqlLogSlow);

        // vystup
        $out = '<div id="devkit-toolbar">';

        // info
        $out .= '<div class="devkit-section devkit-info">' . _systemversion . ' ' . SL::$states[_systemstate] . _systemstate_revision . '</div>';

        // cas
        $out .= '<div class="devkit-section devkit-time">' . round(($now - SL::$start) * 1000) . 'ms</div>';

        // pamet
        $out .= '<div class="devkit-section devkit-memory">' . number_format(round(memory_get_peak_usage() / 1048576), 1, '.', ',') . 'MB</div>';

        // databaze
        $out .= '<div class="devkit-section devkit-database devkit-toggleable">'
            . sizeof($sqlLog)
            . ((0 !== $sqlLogSlowCount) ? ' <span class="devkit-blood">(' . $sqlLogSlowCount . ' slow)</span>' : '')
            . '</div>'
        ;
        $out .= '<div class="devkit-content"><div><div class="devkit-heading">SQL log</div><ol>';
        foreach ($sqlLog as $sqlIndex => $sql) {
            if (isset($sqlLogSlow[$sqlIndex])) {
                $out .= '<li class="devkit-slow-query" title="' . _htmlStr($sqlLogSlow[$sqlIndex]) . '"';
            } else {
                $out .= '<li';
            }
            $out .= '><input type="text" size="' . strlen($sql) . '" class="devkit-selectable" value="' ._htmlStr($sql) . "\" readonly></li>\n";
        }
        $out .= '</ol></div></div>';

        // request
        $out .= '<div class="devkit-section devkit-request devkit-toggleable">' . '$_GET(' . sizeof($_GET) . ') $_POST(' . sizeof($_POST) . ') $_COOKIE(' . sizeof($_COOKIE) . ') $_SESSION(' . sizeof($_SESSION) . ')</div>';
        $out .= '<div class="devkit-content"><div>';
        foreach (array('_GET', '_POST', '_COOKIE', '_SESSION') as $globalVarName) {
            $globalVarSize = sizeof($GLOBALS[$globalVarName]);
            if (0 === $globalVarSize) {
                continue;
            }
            $out .= '<div class="devkit-heading devkit-hideshow">$' . $globalVarName . ' (' . $globalVarSize . ')</div>';
            if ($globalVarSize > 0) {
                $out .= '<div class="devkit-request-dump devkit-hideshow-target">' . $this->dump($GLOBALS[$globalVarName]) . '</div>';
            }
        }
        $out .= '</div></div>';

        // login
        $out .= '<a href="' . _indexroot . 'index.php?m=login"><div class="devkit-section devkit-login">' . (_loginindicator ? _loginname : '---') . '</div></a>';

        // close
        $out .= '<div class="devkit-close">×</div>';

        $out .= '</div>';
        return $out;
    }

    /**
     * Vydumpovat hodnotu
     *
     * @param mixed $value
     * @param int $level
     * @return string
     */
    protected function dump($value, $level = 0)
    {
        if (is_object($value)) {
            return 'object(' . get_class($value) . ')';
        } elseif (is_array($value)) {
            if ($level > 9) {
                return 'array(' . sizeof($value) . ')';
            }
            $out = "array(" . sizeof($value) . ") {\n";
            $padding = str_repeat('    ', $level + 1);
            foreach ($value as $key => $val) {
                $out .= $padding . _htmlStr($key) . ' => ' . $this->dump($val, $level + 1) . "\n";
            }
            if ($level > 0) {
                $out .= str_repeat('    ', $level);
            }
            $out .= '}';

            return $out;
        } elseif (is_string($value)) {
            return 'string(' . strlen($value) . ') &quot;' . _htmlStr(_cutStr($value, 192)) . "&quot;";
        } elseif (is_int($value)) {
            return 'int(' . $value . ')';
        } elseif (is_float($value)) {
            return 'float(' . $value . ')';
        } elseif (is_bool($value)) {
            return 'bool(' . ($value ? 'true' : 'false') . ')';
        } else {
            return gettype($value);
        }
    }

}

/* ----- konfigurace ----- */

// output buffering a handler
if (!defined('_header') || '' === _header) {
    $scriptPath = realpath($_SERVER['SCRIPT_FILENAME']);
    $rootPath = realpath(_indexroot);

    $allowedScriptPaths = array(
        $rootPath . DIRECTORY_SEPARATOR . 'index.php',
        $rootPath . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'index.php',
    );

    if (in_array($scriptPath, $allowedScriptPaths, true)) {
        $outputHandler = new DevkitDebuggerOutputHandler($sqlLogger);
        ob_start($outputHandler);
    }
}
