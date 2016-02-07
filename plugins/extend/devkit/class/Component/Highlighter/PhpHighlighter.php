<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] PHP highlighter class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\Highlighter;

use
    Devkit\Component\StringReader\StringReader
;

/**
 * PHP highlighter class
 */
class PhpHighlighter extends AbstractHighlighter
{

    /** Doc comment */
    const T_DOC_COMMENT = 100;
    /** Heredoc string */
    const T_HEREDOC = 101;
    /** Nowdoc string */
    const T_NOWDOC = 102;

    /** {@inheritdoc} */
    protected $keywords = array(
        'abstract' => 0,    'and' => 0,         'array' => 0,       'as' => 0,
        'break' => 0,       'case' => 0,        'catch' => 0,       'class' => 0,
        'clone' => 0,       'const' => 0,       'continue' => 0,    'declare' => 0,
        'default' => 0,     'do' => 0,          'else' => 0,        'elseif' => 0,
        'enddeclare' => 0,  'endfor' => 0,      'endforeach' => 0,  'endif' => 0,
        'endswitch' => 0,   'endwhile' => 0,    'extends' => 0,     'final' => 0,
        'for' => 0,         'foreach' => 0,     'function' => 0,    'global' => 0,
        'goto' => 0,        'if' => 0,          'implements' => 0,  'interface' => 0,
        'instanceof' => 0,  'namespace' => 0,   'new' => 0,         'or' => 0,
        'private' => 0,     'protected' => 0,   'public' => 0,      'static' => 0,
        'switch' => 0,      'throw' => 0,       'try' => 0,         'use' => 0,
        'var' => 0,         'while' => 0,       'xor' => 0,         'self' => 0,
        'parent' => 0,      'true' => 0,        'false' => 0,       'null' => 0,
        'print' => 0,       'echo' => 0,        'exit' => 0,        'die' => 0,
        'return' => 0,      'isset' => 0,       'unset' => 0,       'list' => 0,
        'finally' => 0,     'yield' => 0,       'insteadof',        'trait' => 0,

    );

    /** {@inheritdoc} */
    protected $tags = array(
        array('<?php', 5, '?>', 2),
        array('<?=', 3, '?>', 2),
        array('<?', 2, '?>', 2),
    );

    /** {@inheritdoc} */
    protected $vars = array('$' => 0);

    /** {@inheritdoc} */
    protected $comments = array(
        '//' => array("\n", 1, true),
        '#' => array("\n", 1, true),
        '/*' => array('*/', 2, false),
        '/**' => array('*/', 2, false, self::T_DOC_COMMENT),
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
            '#' => array($this, 'parseComment'),
            '/*' => array($this, 'parseComment'),
            '/**' => array($this, 'parseComment'),
            '<<<' => array($this, 'parseHeredoc'),
        );

        // token names
        $this->tokenNames += array(
            self::T_DOC_COMMENT => 'doc_comment',
            self::T_HEREDOC => 'heredoc',
            self::T_NOWDOC => 'nowdoc',
        );

        // token styles
        $this->styles = array(
            self::T_DOC_COMMENT => $this->styles[self::T_COMMENT],
            self::T_COMMENT => array('color' => '#9a9a9a'),
            self::T_HEREDOC => $this->styles[self::T_STRING],
            self::T_NOWDOC => $this->styles[self::T_STRING],
        ) + $this->styles;
    }

    /**
     * Parse heredoc/nowdoc syntax
     *
     * @param  StringReader $input
     * @return array|null
     */
    protected function parseHeredoc(StringReader $input)
    {
        $startLine = $input->line;
        $startIndex = $input->i - 3;

        // detect nowdoc syntax
        if ('\'' === $input->char) {
            $isNowdoc = true;
            $input->eat();
        } else {
            $isNowdoc = false;
        }

        // parse identifier
        $idt = '';
        while (StringReader::CHAR_IDT === $input->charType()) {
            $idt .= $input->shift();
        }

        if ('' === $idt) {
            return;
        }
        if ($isNowdoc) {
            if ('\'' !== $input->char) {
                return;
            }
            $input->eat();
        }

        // determine end sequence
        $line = '';
        if("\r" === $input->char) $line .= $input->shift();
        if("\n" === $input->char) $line .= $input->shift();
        if ('' === $line) {
            return;
        }
        $end = "{$line}{$idt}";

        // find end
        $endLen = strlen($end);
        $endOffset = 0;
        while (!$input->end) {

            // end detection
            if ($input->char === $end[$endOffset]) {
                ++$endOffset;
                if ($endLen === $endOffset) {

                    $input->eat();

                    // skip semicolon
                    if (';' === $input->char) {
                        $input->eat();
                    }

                    // validate end of line
                    for ($ii = 0; isset($line[$ii]); ++$ii) {
                        if ($line[$ii] !== $input->char) {
                            $ii = false;
                            break;
                        }
                        $input->eat();
                    }

                    // full end match?
                    if (false !== $ii) {
                        break;
                    } else {
                        $endOffset = 0;
                    }

                }
            } elseif (0 !== $endOffset) {
                $endOffset = 0;
                continue;
            }

            $input->eat();

        }

        // return token
        return $this->token(
            $isNowdoc ? self::T_NOWDOC : self::T_HEREDOC,
            $startLine,
            substr($input->str, $startIndex, $input->i - $startIndex)
        );
    }

}
