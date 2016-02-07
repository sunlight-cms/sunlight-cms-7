<?php

/**
 * Admin BREAD class
 * @author ShiraNai7 <shira.cz>
 */

abstract class AdminBread
{

    // action response codes

    /** Action response code - access denied */
    const ACTION_DENIED = 1;
    /** Action response code - error */
    const ACTION_ERR = 2;
    /** Action response code - not found */
    const ACTION_NOT_FOUND = 3;
    /** Action response code - done */
    const ACTION_DONE = 4;
    /** Action response code - redirect */
    const ACTION_REDIR = 5;

    // public config

    /** @var string */
    public $module;
    /** @var string|null */
    public $querystring;
    /** @var string */
    public $table;
    /** @var string */
    public $tableAlias = 't';
    /** @var string */
    public $primary = 'id';
    /** @var string */
    public $uid;

    // internal config

    /** @var string */
    protected $path;
    /** @var bool */
    protected $useRedirectionConstant = true;

    // action config

    /** @var array */
    protected $defaultAction = array('list');
    /** @var string */
    protected $actionParam = 'action';
    /**@var string */
    protected $prevActionParam = 'prev';

    /**
     * @var array
     *
     * Entry format: idt => array(
     *  title => string,
     *  callback => callback(params, action, &bread_inst),
     *  [min_params => int],
     * )
     *
     * Return value: array(params/null, string_content/int_code/null)
     *
     * Global params:
     *
     *  on_before   callback to run just before the action - callback(&params, &action, bread_inst)
     *              return value is null (= continue) or same format as for regular actions (= surpresses the action - is not called)
     *
     *
     */
    protected $actions = array(

        'list' => array(
            'title' => '%s - listing',
            'callback' => array(__class__, 'listAction'),
            'query' => 'SELECT %columns% FROM %table% %table_alias% WHERE (%cond%)',
            'columns' => array('t.id'),
            'paginator' => true,
            'paginator_size' => 10,
            'query_cond' => '1',
            'query_cond_params' => array(),
            'query_orderby' => null,
            'template' => 'list', // params: result, count, paging, self
        ),

        'edit' => array(
            'title' => '%s - editing',
            'min_params' => 2,
            'callback' => array(__class__, 'editAction'),
            'create' => false,
            'handler' => null, // handler callback(args => array(&success, create, data, params, action, bread_inst)), returns array for DB::update, null for custom handling or array of messages if not successfull
            'template' => 'edit', // params: create = false, data, self, submit_text, submit_trigger
        ),

        'create' => array(
            'title' => '%s - creating',
            'callback' => array(__class__, 'editAction'),
            'create' => true,
            'handler' => null, // handler callback(args => array(&success, create, data, params, action, bread_inst, &insert_id)), returns array for DB::insert, null for custom handling (use &insert_id from args) or array of messages if not successfull
            'template' => 'edit', // params: create = true, data, self, submit_text, submit_trigger
            'continue_to' => 'edit', // action to continue to after successfull creation
            'initial_data' => array(),
        ),

        'del' => array(
            'title' => '%s - deleting',
            'min_params' => 2,
            'callback' => array(__class__, 'deleteAction'),
            'handler' => null, // null (simple row delete) or callback(args => array(data, params, action, bread, &messages))
            'template' => 'del', // params: data, self, submit_text, submit_trigger
            'extra_columns' => array(), // extra columns to fetch, primary column is automatically loaded
        ),

    );

    // runtime

    /**
     * @var array|null|bool
     */
    protected $transCache;

    /**
     * @var array|null
     */
    private $formatSqlParams;

    /**
     * Class constructor
     */
    public function __construct()
    {
        // setup the instance
        $this->setup();

        // generate UID from config
        if (null === $this->uid) {
            $this->uid = sprintf('_bread_%x', crc32("{$this->table}\${$this->tableAlias}\${$this->primary}\$" . get_class($this)));
        }
    }

    /**
     * Setup the instance
     */
    abstract protected function setup();

    /**
     * Run
     * @param  string|null $actionString the action string or null (= fetch from request)
     * @param  string|null $actionUrl    the action URL or null (= auto)
     * @param  string|null $actionPrev   previous action or null (= fetch from request)
     * @return string
     */
    public function run($actionString = null, $actionUrl = null, $actionPrev = null)
    {
        global $_lang;

        // run action
        $ok = false;
        do {

            // fetch action params
            if (null !== $actionString) {
                $params = explode('/', $actionString);
            } elseif (isset($_GET[$this->actionParam])) {
                $params = explode('/', $actionString = strval($_GET['action']));
            } else {
                $params = $this->defaultAction;
                $actionString = implode('/', $params);
            }

            // get action definition
            if (!isset($this->actions[$params[0]])) {
                break;
            }
            $action = $this->actions[$params[0]];

            // verify param count
            if (isset($action['min_params']) && sizeof($params) < $action['min_params']) {
                break;
            }

            // determine previous action
            if (null === $actionPrev) {
                $actionPrev = (isset($_GET[$this->prevActionParam]) ? $_GET[$this->prevActionParam] : null);
            }

            // set url param
            if (null === $actionUrl) {
                $params['url'] = $this->url(
                    $params[0],
                    array_slice($params, 1),
                    $actionPrev
                );
            } else {
                $params['url'] = $actionUrl;
            }

            // set action params
            $params['action'] = $actionString;
            $params['action_prev'] = $actionPrev;

            // call
            $response = null;
            if (isset($action['on_before'])) $response = call_user_func_array($action['on_before'], array(&$params, &$action, $this));
            if (null === $response) $response = call_user_func_array($action['callback'], array($params, $action, $this));

            // handle response
            if (is_array($response)) {

                list($responseParams, $responseContent) = $response;

                // handle code
                if (is_int($responseContent)) {
                    switch ($responseContent) {

                        case self::ACTION_DENIED: $responseContent = _formMessage(2, $_lang['global.accessdenied']); break;
                        case self::ACTION_ERR: $responseContent = _formMessage(2, isset($responseParams['msg']) ? $responseParams['msg'] : $_lang['global.error']); break;
                        case self::ACTION_NOT_FOUND: $responseContent = _formMessage(2, "{$_lang['global.error']} - {$_lang['global.nokit']}"); break;
                        case self::ACTION_DONE: $responseContent = _formMessage(1, $_lang['global.done']); break;

                        case self::ACTION_REDIR:
                            if ($this->useRedirectionConstant) define('_redirect_to', $responseParams['url']);
                            return
                                (isset($responseParams['msg'])
                                    ? _formMessage(
                                        isset($responseParams['msg_type']) ? $responseParams['msg_type'] : 1,
                                        $this->trans($responseParams['msg'], isset($responseParams['msg_params']) ? $responseParams['msg_params'] : null)
                                    )
                                    : ''
                                )
                                . '<p><img class="icon" src="images/icons/edit.png" alt="continue" /><strong><a href="' . _htmlStr($responseParams['url']) . '">' . $_lang['global.continue'] . ' &gt;</a></strong></p>'
                            ;

                    }
                }

                // merge params
                if (null !== $responseParams) {
                    $params = array_merge($params, $responseParams);
                }

            } else {

                // invalid response
                break;

            }

            // ok
            $ok = true;

        } while (false);

        // failure?
        if (!$ok) {
            return _formMessage(3, $_lang['global.badinput']);
        }

        // finish layout and return
        return $this->wrap($responseContent, $params);
    }

    /**
     * Wrap action response in layout
     *
     * Supported parameters:
     * ----------------------
     * backlink                 url to use for backlink
     * backlink_action          action to use for backlink
     * backlink_action_params   backlink action parameters or null
     * backlink_action_prev     previous action string for the backlink action or null
     *
     * title            non-translated title to use
     * title_params     parameters for title translations
     * item_name        item name for the default action title (surpressed by 'title' param)
     *
     * info             html for paragraph after the title
     * info_border      add 'bborder' class to the paragraph
     * messages         array of messages - array(array(type1, text1, [trans_params1], [raw 1/0]), ...)/
     *
     * @param  string      $content
     * @param  array       $params
     * @param  string|null $itemName
     * @return string
     */
    protected function wrap($content, array $params)
    {
        $out = '';
        global $_lang;

        // compose title
        if (isset($params['title'])) {
            $title = $this->trans(
                $params['title'],
                isset($params['title_params']) ? $params['title_params'] : null
            );
        } else {
            $title = $this->trans(
                $this->actions[$params[0]]['title'],
                array($this->trans(isset($params['item_name']) ? $params['item_name'] : 'item'))
            );
        }

        // determine backlink
        if (isset($params['backlink'])) {

            // provided url
            $backlink = $params['backlink'];

        } elseif (isset($params['backlink_action'])) {

            // link to action
            $backlink = $this->url(
                $params['backlink_action'],
                isset($params['backlink_action_params']) ? $params['backlink_action_params'] : null,
                isset($params['backlink_action_prev']) ? $params['backlink_action_prev'] : null
            );
        } elseif (!empty($_GET[$this->prevActionParam])) {

            // link to prev action from request
            $backlink = $this->rawUrl(
                $_GET[$this->prevActionParam],
                null
            );

        } else {

            // no link
            $backlink = null;

        }

        // add backlink
        if (null !== $backlink) {
            $out .= "<a class='backlink' href='" . _htmlStr($backlink) . "'>&lt; {$_lang['global.return']}</a>\n";
        }

        // add title
        $out .= "<h1>" . _htmlStr($title) . "</h1>\n";

        // add info
        if (isset($params['info'])) {
            $out .= "<p" . ((!isset($params['info_border']) || true === $params['info_border']) ? " class='bborder'" : '') . ">{$params['info']}</p>\n";
        }

        // add messages
        if (!empty($params['messages'])) {
            foreach ($params['messages'] as $message) {
                if (!isset($message[3]) || !$message[3]) {
                    $messageText = _htmlStr($this->trans($message[1], isset($message[2]) ? $message[2] : null));
                } else {
                    $messageText = $message[1];
                }
                $out .= _formMessage(
                    $message[0],
                    $messageText
                );
            }
        }

        // add content
        $out .= "\n{$content}\n";

        // return
        return $out;
    }

    /**
     * Translate a string
     * @param  string     $str    the string
     * @param  array|null $params parameters for formatting or null
     * @return string
     */
    public function trans($str, array $params = null)
    {
        // load translations
        if (null === $this->transCache) {
            $transFile = $this->resource('trans', _active_language, 'php');
            if (file_exists($transFile)) $this->transCache = include $transFile;
            elseif (file_exists($transFile = $this->resource('trans', 'default', 'php'))) $this->transCache = include $transFile;
            else $this->transCache = false;
        }

        // translate string
        if (false !== $this->transCache && isset($this->transCache[$str])) {
            $str = $this->transCache[$str];
        }

        // format and return
        if (null !== $params) return vsprintf($str, $params);
        return $str;
    }

    /**
     * Translate and render a string
     * @param  string     $str    the string
     * @param  array|null $params parameters for formatting or null
     * @return null       prints the result
     */
    public function transRender($str, array $params = null)
    {
        echo _htmlStr($this->trans($str, $params));
    }

    /**
     * Render template
     * @param  string     $_template template name
     * @param  array|null $_params   template parameters
     * @return string
     */
    public function render($_template, array $_params = null)
    {
        // find template
        $_templatePath = $this->resource('tpl', $_template, 'php');
        if (file_exists($_templatePath)) {

            // prepare
            ob_start();
            extract($_params, EXTR_SKIP);
            global $_lang;

            // render
            include $_templatePath;

            // return
            return ob_get_clean();

        }

        // not found
        return '{' . _htmlStr($_templatePath) . '}';
    }

    /**
     * Render action link
     *
     * Supported options in $extra:
     * -----------------------------
     * icon_full (0)    treat $icon as full path instead of filename in admin/images/icons 1/0
     * new_window (0)   add target="_blank" to the link 1/0
     * class (-)        class string to add to the link
     * is_url (0)       treat $action as full url and ignore $params and $prev 1/0
     *
     * @param  string|null $icon    admin icon name (without extension) or null for no icon
     * @param  string      $caption button caption
     * @param  string      $action  the action
     * @param  array|null  $params  action params
     * @param  string|null $prev    previous action or null
     * @param  array|null  $extra   extra options or null
     * @return null        prints the result
     */
    public function renderLink($icon, $caption, $action, array $params = null, $prev = null, array $extra = null)
    {
        // compose link href
        if (isset($extra['is_url']) && $extra['is_url']) $href = $action;
        else $href = $this->url($action, $params, $prev);

        // render
        echo "<a href='" . _htmlStr($href) . "'"
            . ((isset($extra, $extra['new_window']) && $extra['new_window']) ? " target='_blank'" : '')
            . ((isset($extra, $extra['class'])) ? " class='" . _htmlStr($extra['class']) . "'" : '')
            . ">"
            . ((null !== $icon) ? "<img class='icon' src='" . ((isset($extra, $extra['icon_full']) && $extra['icon_full']) ? _htmlStr($icon) : "./images/icons/{$icon}.png") . "' />" : '')
            . $caption
            . "</a>"
        ;
    }

    /**
     * Escape and render string
     * @param  string $str the string
     * @return null   prints the result
     */
    public function renderStr($str)
    {
        echo _htmlStr($str);
    }

    /**
     * Compose resource path
     * @param  string      $dirname directory
     * @param  string      $file    file name
     * @param  string|null $ext     file extension
     * @return string
     */
    public function resource($dirname, $file, $ext = null)
    {
        return $this->path
            . DIRECTORY_SEPARATOR . $dirname
            . DIRECTORY_SEPARATOR . $file
            . ((null === $ext) ? '' : ".{$ext}")
        ;
    }

    /**
     * Compose URL
     * @param  string      $action the action
     * @param  array|null  $params action params
     * @param  string|null $prev   previous action or null
     * @param  array|null  $query  extra query data or null
     * @return string
     */
    public function url($action, array $params = null, $prev = null, array $query = null)
    {
        return 'index.php?p=' . $this->module . ((null === $this->querystring) ? '' : '&' . $this->querystring)
            . '&' . $this->actionParam . '=' . $action
            . ((empty($params)) ? '' : '/' . urlencode(implode('/', $params)))
            . ((null === $prev) ? '' : '&' . $this->prevActionParam . '=' . urlencode($prev))
            . (empty($query) ? '' : '&' . http_build_query($query, '', '&'))
        ;
    }

    /**
     * Compose raw URL
     * @param  string      $actionString
     * @param  string|null $prevActionString
     * @param  array|null  $query            extra query data or null
     * @return string
     */
    public function rawUrl($actionString, $prevActionString = null, array $query = null)
    {
        return 'index.php?p=' . $this->module . ((null === $this->querystring) ? '' : '&' . $this->querystring)
            . '&' . $this->actionParam . '=' . urlencode($actionString)
            . ((null === $prevActionString) ? '' : '&' . $this->prevActionParam . '=' . urlencode($prevActionString))
            . (empty($query) ? '' : '&' . http_build_query($query, '', '&'))
        ;
    }

    /**
     * Format table name
     * @param  string $table
     * @return string
     */
    public function formatTable($table)
    {
        return _mysql_prefix . '-' . $table;
    }

    /**
     * Format SQL query
     * @param  string $sql    the SQL query
     * @param  array  $params param => value pairs
     * @return string
     */
    public function formatSql($sql, array $params)
    {
        $this->formatSqlParams = $params;
        $sql = preg_replace_callback('/%(?P<lit>[a-zA-Z0_9\\-.]+)%|@(?P<val>[a-zA-Z0_9\\-.]+)@/m', array($this, 'formatSqlParam'), $sql);
        $this->formatSqlParams = null;

        return $sql;
    }

    /**
     * Format SQL query parameter (callback)
     * @param  array  $match
     * @return string
     */
    public function formatSqlParam(array $match)
    {
        if (isset($match['lit']) && '' !== $match['lit']) {
            if (isset($this->formatSqlParams[$match['lit']])) {
                $param = $this->formatSqlParams[$match['lit']];
                if (is_array($param)) return implode(',', $param);
                return $param;
            } else {
                throw new RuntimeException("Parameter '{$match['lit']}' is undefined");
            }
        } else {
            if (isset($this->formatSqlParams[$match['val']]) || array_key_exists($match['val'], $this->formatSqlParams)) {
                return DB::val($this->formatSqlParams[$match['val']], true);
            } else {
                throw new RuntimeException("Parameter '{$match['val']}' is undefined");
            }
        }
    }

    /**
     * List action
     * @param  array      $params
     * @param  array      $action
     * @param  AdminBread $bread
     * @return array
     */
    public static function listAction(array $params, array $action, AdminBread $bread)
    {
        /* ----- prepare query ----- */

        // format condition
        if ('1' !== $action['query_cond']) {
            $cond = $bread->formatSql($action['query_cond'], $action['query_cond_params']);
        } else {
            $cond = $action['query_cond'];
        }

        // format sql
        $sql = $bread->formatSql($action['query'], array(
            'columns' => $action['columns'],
            'table' => '`' . $bread->formatTable($bread->table) . "`",
            'table_alias' => $bread->tableAlias,
            'cond' => $cond,
        ));

        // add order by
        if (!empty($action['query_orderby'])) {
            $sql .= " ORDER BY {$action['query_orderby']}";
        }

        /* ----- init paginator ----- */

        if ($action['paginator']) {
            $total = DB::query_row('SELECT COUNT(*) total FROM `' . $bread->formatTable($bread->table) . '` ' . $bread->tableAlias . ' WHERE ' . $cond);
            $paging = _resultPaging(_htmlStr($params['url']), $action['paginator_size'], intval($total['total']));
            $sql .= " {$paging[1]}";
        } else {
            $paging = null;
        }

        /* ----- fetch data ----- */

        $result = DB::query($sql);
        if (false === $result) {
            return array(null, self::ACTION_ERR);
        }

        /* ----- render ----- */

        $out = $bread->render($action['template'], array(
            'result' => $result,
            'count' => DB::size($result),
            'paging' => $paging,
            'self' => $params['action'],
        ));

        DB::free($result);

        // return
        return array(null, $out);
    }

    /**
     * Edit/create action
     * @param  array      $params
     * @param  array      $action
     * @param  AdminBread $bread
     * @return array
     */
    public static function editAction(array $params, array $action, AdminBread $bread)
    {
        $messages = array();
        $create = (isset($action['create']) && true === $action['create']);
        $trigger = "_edit_{$bread->uid}";

        $createMsgKey = "{$bread->uid}_edit_created";
        if (!$create) {
            $updateMsgKey = "{$bread->uid}_edit_updated";
        }

        /* ----- load data ----- */

        if (!$create) {

            // verify ID
            if (!isset($params[1])) {
                return array(
                    array('msg' => 'Missing parameter 1 for ' . __METHOD__),
                    self::ACTION_ERR,
                );
            }

            // process ID
            $id = (int) $params[1];

            // load data
            $data = DB::query_row("SELECT {$bread->tableAlias}.* FROM `" . $bread->formatTable($bread->table) . "` {$bread->tableAlias} WHERE {$bread->tableAlias}.{$bread->primary}={$id}");
            if (false === $data) {
                return array(null, self::ACTION_NOT_FOUND);
            }

        } else {

            // initial data
            $data = $action['initial_data'];

        }

        /* ----- invoke handler ----- */

        if (isset($_POST[$trigger])) {

            // check
            if (null === $action['handler']) {
                return array(
                    array('msg' => 'Missing handler for ' . __METHOD__),
                    self::ACTION_ERR,
                );
            }

            // invoke
            $success = false;
            $insertId = false;
            $handlerResult = call_user_func($action['handler'], array(
                'success' => &$success,
                'create' => $create,
                'data' => $data,
                'params' => $params,
                'action' => $action,
                'bread' => $bread,
                'messages' => &$messages,
                'insert_id' => &$insertId,
            ));

            // handle result
            if ($success) {

                // ok, insert or update
                if ($create) {

                    // insert
                    if (is_array($handlerResult)) {
                        $insertId = DB::insert($bread->formatTable($bread->table), $handlerResult, true);
                    }

                    if (false !== $insertId) {
                        return array(
                            array(
                                'url' => _url . '/admin/' . $bread->url($action['continue_to'], array($insertId), $params['action_prev'], array($createMsgKey => time())),
                                'msg' => 'Item created',
                            ),
                            self::ACTION_REDIR,
                        );
                    } else {
                        return array(null, self::ACTION_ERR);
                    }

                } else {

                    // update
                    if (is_array($handlerResult)) {

                        // native
                        if (empty($handlerResult)) {
                            $changes = false;
                            $update = true;
                        } else {
                            $changes = true;
                            $update = DB::update($bread->formatTable($bread->table), "{$bread->primary}={$id}", $handlerResult);
                        }
                        if (false !== $update) {
                            $data = $handlerResult + $data;
                            if (empty($messages)) $messages[] = array(1, $changes ? 'Item updated' : 'No changes');
                            $_POST = array();
                        } else {
                            return array(null, self::ACTION_ERR);
                        }

                    } else {

                        // custom - reload using redirection
                        return array(
                            array(
                                'url' => _url . '/admin/' . $bread->url($params[0], array($id), $params['action_prev'], array($updateMsgKey => time())),
                                'msg' => 'Item updated',
                            ),
                            self::ACTION_REDIR,
                        );

                    }

                }

            } else {

                // error
                if (is_array($handlerResult)) {
                    $messages = $handlerResult;
                } else {
                    $messages[] = array(2, 'An error occured while ' . ($create ? 'creating' : 'updating'));
                }

            }

        }

        /* ----- render ----- */

        // get message
        if (!$create && empty($messages) && isset($_GET[$updateMsgKey]) && time() - intval($_GET[$updateMsgKey]) < 15) {
            // updated
            $messages[] = array(1, 'Item updated');
        } elseif (!$create && empty($messages) && isset($_GET[$createMsgKey]) && time() - intval($_GET[$createMsgKey]) < 15) {
            // created
            $messages[] = array(1, 'Item created');
        }

        // render
        return array(
            array('messages' => $messages),
            $bread->render($action['template'], array(
                'create' => $create,
                'data' => $data,
                'self' => $params['action'],
                'submit_text' => $GLOBALS['_lang'][$create ? 'global.create' : 'global.save'],
                'submit_trigger' => $trigger,
            )),
        );
    }

    /**
     * Delete action
     * @param  array      $params
     * @param  array      $action
     * @param  AdminBread $bread
     * @return array
     */
    public static function deleteAction(array $params, array $action, AdminBread $bread)
    {
        $messages = array();
        $trigger = "_del_{$bread->uid}";

        /* ----- load data ----- */

        // verify ID
        if (!isset($params[1])) {
            return array(
                array('msg' => 'Missing parameter 1 for ' . __METHOD__),
                self::ACTION_ERR,
            );
        }

        // process ID
        $id = (int) $params[1];

        // load data
        $sql = $bread->formatSql("SELECT %columns% FROM `" . $bread->formatTable($bread->table) . "` {$bread->tableAlias} WHERE {$bread->tableAlias}.{$bread->primary}=@id@", array(
            'columns' => array_merge(array($bread->primary), $action['extra_columns']),
            'id' => $id,
        ));
        $data = DB::query_row($sql);
        if (false === $data) {
            return array(null, self::ACTION_NOT_FOUND);
        }

        /* ----- delete ----- */

        if (isset($_POST[$trigger])) {

            // handler or simple delete
            if (null !== $action['handler']) {

                // use handler
                $success = call_user_func($action['handler'], array(
                    'data' => $data,
                    'params' => $params,
                    'action' => $action,
                    'bread' => $bread,
                    'messages' => &$messages,
                ));

            } else {

                // simple delete
                $success = DB::query($bread->formatSql("DELETE FROM `" . $bread->formatTable($bread->table) . "` WHERE {$bread->primary}=@id@ LIMIT 1", array('id' => $id)));

            }

            // handle result
            if ($success) {
                return array(
                    array('messages' => $messages),
                    self::ACTION_DONE,
                );

            } else {

                $messages[] = array(2, $GLOBALS['_lang']['global.error']);

            }

        }

        /* ----- render ----- */

        return array(
            array('messages' => $messages),
            $bread->render($action['template'], array(
                'data' => $data,
                'self' => $params['action'],
                'submit_text' => $GLOBALS['_lang']['admin.content.redir.act.wipe.submit'],
                'submit_trigger' => $trigger,
            )),
        );
    }

}
