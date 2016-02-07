<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] JavaScript highlighter class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\Highlighter;

/**
 * JavaScript highlighter class
 */
class JavascriptHighlighter extends AbstractHighlighter
{

    /**
     * {@inheritdoc}
     */
    protected $syntaxIdt = array('_' => 0, '$' => 1);

    /**
     * {@inheritdoc}
     */
    protected $keywords = array(
        'true' => 0,        'false' => 0,       'null' => 0,    'break' => 0,
        'if' => 0,          'case' => 0,        'catch' => 0,   'continue' => 0,
        'debugger' => 0,    'default' => 0,     'delete' => 0,  'do' => 0,
        'else' => 0,        'finally' => 0,     'for' => 0,     'function' => 0,
        'in' => 0,          'instanceof' => 0,  'new' => 0,     'return' => 0,
        'switch' => 0,      'this' => 0,        'throw' => 0,   'try' => 0,
        'typeof' => 0,      'var' => 0,         'void' => 0,    'while' => 0,
        'with' => 0,        'class' => 0,       'enum' => 0,    'export' => 0,
        'extends' => 0,     'import' => 0,      'super' => 0,   'implements' => 0,
        'interface' => 0,   'let' => 0,         'package' => 0, 'private' => 0,
        'protected' => 0,   'public' => 0,      'static' => 0,  'yield' => 0,
    );

    /**
     * {@inheritdoc}
     */
    protected $comments = array(
        '//' => array("\n", 1),
        '/*' => array('*/', 2),
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        // setup controls
        $this->controls = array(
            '"' => array($this, 'parseString'),
            '\'' => array($this, 'parseString'),
            '//' => array($this, 'parseComment'),
            '/*' => array($this, 'parseComment'),
        );
    }

}
