<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Highlighter class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\Highlighter;

use
    Devkit\Component\StringReader\StringReader
;

/**
 * Highlighter class
 */
abstract class AbstractHighlighter
{

    // Token format: array(ID, line, code_string, [extra1, ...])

    /** Control character(s) */
    const T_CONTROL = 0;
    /** Language keyword */
    const T_KEYWORD = 1;
    /** Identifier */
    const T_IDENTIFIER = 2;
    /** Variable */
    const T_VARIABLE = 3;
    /** Comment (extra data: syntax index) */
    const T_COMMENT = 4;
    /** Outer code */
    const T_OUTER = 5;
    /** Tag */
    const T_TAG = 6;
    /** Whitespace */
    const T_WS = 7;
    /** Bad characters */
    const T_BAD = 8;
    /** String */
    const T_STRING = 9;

    /** @var array token names, entry: token_id => token_name */
    protected $tokenNames = array(
        self::T_CONTROL => 'control',
        self::T_KEYWORD => 'keyword',
        self::T_IDENTIFIER => 'identifier',
        self::T_VARIABLE => 'variable',
        self::T_COMMENT => 'comment',
        self::T_OUTER => 'outer',
        self::T_TAG => 'tag',
        self::T_WS => 'ws',
        self::T_BAD => 'bad',
        self::T_STRING => 'str',
    );
    /** @var array token post-process callbacks, entry: token_id => callback(token) */
    protected $tokenPostProcess = array();

    /**
     * @var array token styles
     *
     * Entry format:
     * (all entries are optional)
     *
     * type_idt => array(
     *      color => #000,
     *      italic => 1/0,
     *      bold => 1/0,
     *      underline => 1/0,
     *      background => #fff,
     *      custom => 'custom-css;',
     * )
     */
    protected $styles = array(
        self::T_CONTROL => array('color' => '#000'),
        self::T_KEYWORD => array('color' => '#00b'),
        self::T_IDENTIFIER => array('color' => '#008000'),
        self::T_VARIABLE => array('color' => '#06c'),
        self::T_COMMENT => array('color' => '#f60'),
        self::T_OUTER => array('color' => '#999'),
        self::T_TAG => array('color' => '#000', 'bold' => true),
        self::T_WS => array(),
        self::T_BAD => array('color' => '#f00', 'underline' => true),
        self::T_STRING => array('color' => '#d00'),
    );

    /** @var array map of language keywords, entry: keyword => void */
    protected $keywords = array();
    /** @var array|null code tag syntaxes or null, entry: array(opening_tag, opening_tag_len, closing_tag, closing_tag_len) */
    protected $tags;
    /** @var array variable prefix map, entry: prefix => void */
    protected $vars = array();
    /** @var array control sequence parsers, entry: sequence => callback(input, line, sequence) */
    protected $controls = array();

    /**
     * @var array comment closing syntax map
     * Entry format: opening_syntax => array(
     *      0 => closing_syntax,
     *      1 => closing_syntax_len,
     *      2 => [close on closing tag 1/0]
     *      3 => [custom_token_id]
     * )
     */
    protected $comments = array();

    /**
     * Highlight a code string
     *
     * @param  string     $code          the code
     * @param  bool       $inlineCss     true = use inline css, false = classes only
     * @param  int|null   $maxLine       stop parsing after this line
     * @param  array|null $activeLineMap map of active lines - array(line => void/class) or null
     * @return string
     */
    public function highlight($code, $inlineCss = true, $maxLine = null, array $activeLineMap = null)
    {
        // tokenize
        $tokens = $this->tokenize($code, null, $maxLine);

        // render
        return $this->render($tokens, $inlineCss, true, $activeLineMap);
    }

    /**
     * Render tokenized code
     *
     * @param  array      $tokens        array of tokens
     * @param  bool       $inlineCss     true = use inline css, false = classes only
     * @param  bool       $preTag        wrap the output in a <pre> tag 1/0
     * @param  array|null $activeLineMap map of active lines - array(line => void/class) or null
     * @return string
     */
    public function render(array $tokens, $inlineCss = true, $preTag = true, array $activeLineMap = null)
    {
        if (empty($tokens)) {
            return '';
        }

        $html = '';

        // pre tag
        if ($preTag) {
            $html = '<pre>';
        }

        // render tokens
        $activeLine = null;
        $activeLineMapNotEmpty = !empty($activeLineMap);
        $tokenQueue = array($tokens[0]);
        $nextToken = 1;
        while ($token = array_pop($tokenQueue)) {

            // handle active lines
            if ($activeLineMapNotEmpty) {

                // split multiline tokens
                if (!isset($token[-1]) && (false !== strpos($token[2], "\n") || false !== strpos($token[2], "\r"))) {
                    $tokenContents = preg_split('/\\n|\\r\\n|\\r/', $token[2]);
                    $tokenLastContent = sizeof($tokenContents) - 1;
                    for ($i = $tokenLastContent; isset($tokenContents[$i]); --$i) {
                        $tokenContent = $tokenContents[$i] . (($tokenLastContent !== $i) ? "\n" : '');
                        if ('' !== $tokenContent) {
                            $tokenQueue[] = array(-1 => true, 1 => $token[1] + $i, 2 => $tokenContent) + $token;
                        }
                    }
                    continue;
                }

                // determine current line
                $line = $token[1];

                // close active line
                if (null !== $activeLine && $line !== $activeLine) {
                    $activeLine = null;
                    $html .= '</span>';
                }

                // start active line
                if (isset($activeLineMap[$line]) && null === $activeLine) {
                    $activeLine = $line;
                    $html .= '<span class="activeLine' . (is_string($activeLineMap[$line]) ? ' ' . $activeLineMap[$line] : '') . '">';
                }

            }

            // render token
            $html .= $this->renderToken($token, $inlineCss);

            // add next token
            if (empty($tokenQueue) && isset($tokens[$nextToken])) {
                $tokenQueue[] = $tokens[$nextToken];
                ++$nextToken;
            }

        }

        // close active line
        if (null !== $activeLine) {
            $html .= '</span>';
        }

        // close pre tag
        if ($preTag) {
            $html .= '</pre>';
        }

        // return
        return $html;
    }

    /**
     * Render single token
     *
     * @param  array  $token     the token
     * @param  bool   $inlineCss render inline CSS 1/0
     * @return string
     */
    public function renderToken(array $token, $inlineCss)
    {
        // opening tag
        $html = '<span class="' . $this->tokenNames[$token[0]] . '"';

        // inline css
        if ($inlineCss && !empty($this->styles[$token[0]])) {
            $html .= ' style="' . $this->renderCss($this->styles[$token[0]]) . '"';
        }

        // end opening tag
        $html .= '>';

        // contents
        $html .= htmlspecialchars($token[2], ENT_QUOTES, 'UTF-8');

        // closing tag
        $html .= '</span>';

        // return
        return $html;
    }

    /**
     * Render CSS styles
     *
     * @param  array  $styles
     * @return string inline CSS
     */
    public function renderCss(array $styles)
    {
        $css = '';
        if(isset($styles['color'])) $css .= "color:{$styles['color']};";
        if(isset($styles['italic']) && true === $styles['italic']) $css .= "font-style:italic;";
        if(isset($styles['bold']) && true === $styles['bold']) $css .= "font-weight: bold;";
        if(isset($styles['underline']) && true === $styles['underline']) $css .= "text-decoration:underline;";
        if(isset($styles['background'])) $css .= "backgroud-color:{$styles['background']};";
        if(isset($styles['custom'])) $css .= $styles['custom'];

        return $css;
    }

    /**
     * Get all CSS styles
     *
     * @param  string $prefix prefix for CSS selectors (should include space or " > ")
     * @return string
     */
    public function getCss($prefix = '')
    {
        $css = '';
        foreach ($this->styles as $tokenId => $styles) {
            if (empty($styles)) {
                continue;
            }
            $css .= "{$prefix}span.{$this->tokenNames[$tokenId]} {";
            $css .= $this->renderCss($styles);
            $css .= "}\n";
        }
        $css .= "{$prefix}span.activeLine {display: block;}\n";

        return $css;
    }

    /**
     * Tokenize the code
     *
     * @param  string   $code    the code
     * @param  int|null $tagId   initial tag ID or null
     * @param  int|null $maxLine stop parsing after this line
     * @return array
     */
    public function tokenize($code, $tagId = null, $maxLine = null)
    {
        // initial tag state
        if (null === $tagId) {
            $inTags = (null === $this->tags);
        } else {
            if (!isset($this->tags[$tagId])) {
                throw new \OutOfBoundsException(sprintf('Invalid tag ID "%s"', $tagId));
            }
            $inTags = false;
        }

        // init input reader
        $input = $this->initInputReader($code);
        $input->args['tag_id'] = $tagId;

        // tokenize
        $tokens = array();
        while (!$input->end) {

            if ($inTags) {

                // parse inner
                $inner = $this->parseInner($input, $tokens, $maxLine);
                if (null === $inner) {

                    // stopped
                    break;

                } elseif ($inner) {

                    // closing tag found
                    $inTags = false;
                    $input->args['tag_id'] = null;

                }

            } else {

                // parse outer
                list($inTags, $tagId) = $this->parseOuter($input, $tokens);
                if ($inTags) {
                    $input->args['tag_id'] = $tagId;
                }

            }

        }

        // return
        return $tokens;
    }

    /**
     * Init input reader
     *
     * @param  string       $code
     * @return StringReader
     */
    protected function initInputReader($code)
    {
        return new StringReader($code);
    }

    /**
     * Get last line number
     *
     * @param  array $tokens array of tokens
     * @return int
     */
    public function getLastLine(array $tokens)
    {
        $last = sizeof($tokens) - 1;

        return $tokens[$last][1] + preg_match_all('/\\n|\\r\\n|\\r/', $tokens[$last][2], $matches);
    }

    /**
     * Get tokens from specific range of lines
     *
     * @param  array $tokens    full array of tokens
     * @param  int   $startLine start line
     * @param  int   $endLine   end line
     * @return array
     */
    public function getTokensFromRange(array $tokens, $startLine, $endLine)
    {
        // verify line numbers
        if ($endLine < $startLine) {
            throw new \InvalidArgumentException("Start line number must be >= end line number");
        }

        if ($startLine < 1) {
            $startLine = 1;
        }

        // extraction
        $out = array();
        $gotFirstLine = false;
        for ($i = 0; isset($tokens[$i]);) {

            // search for first line token
            if (!$gotFirstLine) {

                if ($tokens[$i][1] > $startLine || !isset($tokens[$i + 1])) {

                    // find first possible candidate
                    for ($ii = $i - 1; isset($tokens[$ii]); --$ii) {
                        if ($tokens[$ii][1] < $startLine) {
                            break;
                        }
                    }
                    if ($ii < 0) {
                        $ii = 0;
                    }

                    // find intersecting token
                    for (; isset($tokens[$ii]); ++$ii) {
                        $startTokenLineCount = 1 + preg_match_all('/\\n|\\r\\n|\\r/', $tokens[$ii][2], $matches);
                        if ($tokens[$ii][1] + $startTokenLineCount >= $startLine) {
                            break;
                        }
                    }
                    if (!isset($tokens[$ii])) {
                        throw new \RangeException('Unable to find intersecting token - invalid line range?');
                    }

                    // cut intersecting token
                    $startToken = $tokens[$ii];
                    $startToken[2] = implode(
                        "\n",
                        array_slice(
                            preg_split('/\\n|\\r\\n|\\r/', $startToken[2]),
                            -($startToken[1] + $startTokenLineCount - $startLine)
                        )
                    );
                    $startToken[1] = $startLine;

                    if ('' !== $startToken[2]) {
                        $out[] = $startToken;
                    }
                    $i = $ii + 1;

                    $gotFirstLine = true;

                } else {
                    ++$i;
                }

            } else {

                // gather tokens until end line
                if ($tokens[$i][1] > $endLine) {
                    break;
                }
                $out[] = $tokens[$i];
                ++$i;

            }

        }

        // cut last token
        if (null !== ($lastToken = array_pop($out))) {

            $lastToken[2] = preg_split('/\\n|\\r\\n|\\r/', $lastToken[2]);
            $lastTokenLineCount = sizeof($lastToken[2]);

            if ($lastToken[1] + $lastTokenLineCount - 1 > $endLine) {
                array_splice($lastToken[2], 1 + $endLine - $lastToken[1]);
            }

            $lastToken[2] = implode("\n", $lastToken[2]);
            if ('' !== $lastToken[2]) {
                $out[] = $lastToken;
            }

        }

        // return
        return $out;
    }

    /**
     * Parse outer
     *
     * @param StringReader $input input
     * @param array &$output output token array
     * @return array array(found_opening_tag 1/0, found_tag_id/null)
     */
    protected function parseOuter(StringReader $input, &$output)
    {
        $startOffset = $input->i;
        $startLine = $input->line;
        if ($input->isNewline) {
            --$startLine;
        }

        $tagLine = null;
        $tagDetected = false;
        $tagDetectionOffset = 0;
        $tagId = null;

        // scan
        while (!$input->end) {

            // match tags
            for ($i = 0; isset($this->tags[$i]); ++$i) {

                // match first char
                if ($input->char === $this->tags[$i][0][0]) {

                    // match
                    $tagId = $i;
                    $tagLine = $input->line;

                    // match other chars or break
                    if (1 === $this->tags[$i][1]) {

                        // single char tag
                        $tagDetected = true;
                        $input->eat(); // skip last tag char
                        break 2;

                    } else {

                        // push state
                        $tagDetectionOffset = 1;
                        $input->pushState();
                        $input->eat();

                        // match other characters
                        while (!$input->end) {
                            if ($input->shift() === $this->tags[$tagId][0][$tagDetectionOffset]) {
                                if (++$tagDetectionOffset === $this->tags[$tagId][1]) {

                                    // full match
                                    $tagDetected = true;
                                    $input->popState();
                                    break 3;

                                }
                            } else {

                                // no match, bail
                                break;

                            }
                        }

                        // no match
                        $tagId = null;
                        $input->revertState();

                    }

                }
            }

            // advance input
            $input->eat();

        }

        // compute end offset
        if ($tagDetected) {
            $endOffset = $input->i - $this->tags[$tagId][1];
        } else {
            $tagId = null;
            $endOffset = $input->i;
        }

        // outer token
        if ($endOffset !== $startOffset) {
            $output[] = $this->token(self::T_OUTER, $startLine, substr($input->str, $startOffset, $endOffset - $startOffset));
        }

        // tag token
        if ($tagDetected) {
            $output[] = $this->token(self::T_TAG, $tagLine, $this->tags[$tagId][0]);
        }

        // return
        return array($tagDetected, $tagId);
    }

    /**
     * Parse inner
     *
     * Pre-offset: any or after opening tag
     * Post-offset: at end or after closing tag
     *
     * @param StringReader $input input
     * @param array &$output output token array
     * @param int|null stop parsing after this line
     * @return bool|null found closing tag 1/0 or null(= stop parsing)
     */
    protected function parseInner(StringReader $input, &$output, $maxLine = null)
    {
        $tok = null;
        while (!$input->end) {

            // check line
            if (null !== $maxLine && $input->line > $maxLine) {
                return;
            }

            // determine char type
            $charType = $input->charType();

            // parse type
            switch ($charType) {

                // identifier
                case StringReader::CHAR_IDT:
                    $tok = $this->parseIdt($input);
                    break;

                // control characters and numbers
                case StringReader::CHAR_CTRL:
                case StringReader::CHAR_NUM:
                    $tok = $this->parseClosingTag($input, true);
                    if(null === $tok) $this->parseControl($input, $output);
                    break;

                // whitespace
                case StringReader::CHAR_WS:
                    $tok = $this->parseWs($input);
                    break;

                // bad character
                case StringReader::CHAR_OTHER:
                    $tok = $this->parseBad($input);
                    break;

            }

            // handle token
            if (null !== $tok) {

                // add to output
                $output[] = $tok;

                // detect closing tag
                if (self::T_TAG === $tok[0]) {
                    return true;
                }

                // reset token
                $tok = null;

            }

        }

        // closing tag not encountered
        return false;
    }

    /**
     * Detect and parse closing tag
     *
     * Pre-offset: any
     * Post-offset: after closing tag or reverted
     *
     * @param  StringReader $input    input
     * @param  bool         $getToken get token instead of true, null instead of false and do not revert 1/0
     * @return array|bool   token/true or null/false (= not detected)
     */
    protected function parseClosingTag(StringReader $input, $getToken = false)
    {
        // are tags defined?
        if (null === $this->tags) {
            return $getToken ? null : false;
        }

        // check first character
        if ($input->char !== $this->tags[$input->args['tag_id']][2][0]) {
            return $getToken ? null : false;
        }

        // check other characters
        $offset = 0;
        $input->pushState();
        while (!$input->end) {

            if ($input->char === $this->tags[$input->args['tag_id']][2][$offset]) {
                ++$offset;
                if ($offset === $this->tags[$input->args['tag_id']][3]) {

                    // tag detected
                    if ($getToken) {
                        $input->popState();
                        $token = $this->token(self::T_TAG, $input->line, $this->tags[$input->args['tag_id']][2]);
                        $input->eat();

                        return $token;
                    } else {
                        $input->revertState();

                        return true;
                    }

                }
            } else {
                break;
            }

            $input->eat();

        }

        // no detection
        $input->revertState();

        return $getToken ? null : false;
    }

    /**
     * Parse identifier
     *
     * Pre-offset: at idt char
     * Post-offset: at first non-idt char
     *
     * @param  StringReader $input input
     * @return array
     */
    protected function parseIdt(StringReader $input)
    {
        $line = $input->line;

        // gather identifier characters
        $buffer = '';
        while (StringReader::CHAR_IDT === ($charType = $input->charType()) || StringReader::CHAR_NUM === $charType) {
            $buffer .= $input->shift();
        }

        // determine token type
        if(isset($this->vars[$buffer[0]])) $id = self::T_VARIABLE;
        elseif(isset($this->keywords[$buffer])) $id = self::T_KEYWORD;
        else $id = self::T_IDENTIFIER;

        // return token
        return $this->token(
            $id,
            $line,
            $buffer
        );
    }

    /**
     * Parse control character(s)
     *
     * Pre-offset: at control char
     * Post-offset: at first non-control char
     *
     * @param StringReader $input input
     * @param array &$output output token buffer reference
     */
    protected function parseControl(StringReader $input, array &$output)
    {
        $buffer = '';
        $bufferSize = 0;
        $line = $input->line;

        // gather all control characters
        while (!$input->end) {

            // stop on closing tag
            if ($this->parseClosingTag($input)) {
                break;
            }

            // buffer character
            $buffer .= $input->char;
            ++$bufferSize;

            // advance input
            $input->eat();
            if (StringReader::CHAR_CTRL !== ($charType = $input->charType()) && StringReader::CHAR_NUM !== $charType) {
                break;
            }

        }

        // try to match controls
        $offset = 0;
        do {
            for ($i = $bufferSize; $i >= $offset; --$i) {

                $part = substr($buffer, $offset, $i - $offset);
                if (isset($this->controls[$part])) {

                    // rollback if necessary
                    if($i !== $bufferSize) $input->move(-($bufferSize - $i));

                    // invoke callback
                    $token = call_user_func($this->controls[$part], $input, $line, $part);
                    if (null !== $token) {

                        // add unmatched part to the output
                        if (0 !== $offset) {
                            $output[] = $this->token(self::T_CONTROL, $line, substr($buffer, 0, $offset));
                        }

                        // add parsed token to the output
                        $output[] = $token;

                        // exit
                        return;

                    } else {
                        // no token, restore input offset
                        $input->move($bufferSize - $i);
                    }

                }

            }

            ++$offset;

        } while ($offset < $bufferSize);

        // no matches, generic token only
        $output[] = $this->token(self::T_CONTROL, $line, $buffer);
    }

    /**
     * Parse whitespace
     *
     * Pre-offset: at whitespace
     * Post-offset: at first non-whitespace character
     *
     * @param  StringReader $input input
     * @return array
     */
    protected function parseWs(StringReader $input)
    {
        // determine line number
        $line = $input->line;
        if ($input->isNewline) {
            --$line;
        }

        // gather all ws characters
        $buffer = $input->eatType(StringReader::CHAR_WS);

        // return token
        return $this->token(self::T_WS, $line, $buffer);
    }

    /**
     * Parse bad characters
     *
     * Pre-offset: at bad character
     * Post-offset: at first non-bad character
     *
     * @param  StringReader $input input
     * @return array
     */
    protected function parseBad(StringReader $input)
    {
        $line = $input->line;
        $buffer = $input->eatType(StringReader::CHAR_OTHER);

        return $this->token(self::T_BAD, $line, $buffer);
    }

    /**
     * Contruct a token
     *
     * @param  int    $idt            token id
     * @param  int    $line           token start line in code
     * @param  string $code           token code
     * @param  mixed  $extraData1,... extra token data
     * @return array
     */
    protected function token($id, $line, $code)
    {
        // base token
        $tok = array($id, $line, $code);

        // extra data
        if (func_num_args() > 3) {
            $tok = array_merge($tok, array_slice(func_get_args(), 3));
        }

        // post-process
        if (isset($this->tokenPostProcess[$id])) {
            return call_user_func($this->tokenPostProcess[$id], $tok);
        }

        // return
        return $tok;
    }

    /**
     * Parse comment
     *
     * Pre-offset: after last char of opening syntax
     * Post-offset: after closing syntax
     *
     * @param  StringReader $input   input
     * @param  int          $line    line number
     * @param  string       $opening opening syntax string
     * @return array
     */
    protected function parseComment(StringReader $input, $line, $opening)
    {
        if (!isset($this->comments[$opening])) {
            throw new \RuntimeException(sprintf('Cannot parse as comment from "%s" - closing syntax not defined', $opening));
        }

        $buffer = $opening;
        $close = $this->comments[$opening][0];
        $closeLen = $this->comments[$opening][1];
        $closeOnTag = (isset($this->comments[$opening][2]) && true === $this->comments[$opening][2]);
        $closeDetectionOffset = 0;
        $tokenId = (isset($this->comments[$opening][3]) ? $this->comments[$opening][3] : self::T_COMMENT);

        // parse
        while (!$input->end) {

            // detect closing tag
            if ($closeOnTag && $this->parseClosingTag($input)) {
                break;
            }

            // buffer char
            $buffer .= $input->char;

            // detect closing syntax
            if ($input->char === $close[$closeDetectionOffset]) {
                ++$closeDetectionOffset;
                if ($closeLen === $closeDetectionOffset) {
                    // closed
                    $input->eat(); // skip last char
                    break;
                }
            } else $closeDetectionOffset = 0; // reset

            // advance input
            $input->eat();

        }

        // return token
        return $this->token($tokenId, $line, $buffer);
    }

    /**
     * Parse string
     *
     * Pre-offset: after ' or "
     * Post-offset: after ' or "
     *
     * @param  StringReader $input input
     * @param  int          $line  line number
     * @param  string       $quote used quote syntax
     * @return array
     */
    protected function parseString(StringReader $input, $line, $quote)
    {
        $buffer = $quote;
        $escaped = false;

        // parse
        while (!$input->end) {

            // handle char
            switch ($input->char) {

                // escape symbol
                case '\\':
                    $escaped = !$escaped;
                    $buffer .= '\\';
                    break;

                // quote
                case $quote:
                    // end of string?
                    if (!$escaped) {
                        $buffer .= $quote;
                        $input->eat(); // skip quote
                        break 2;
                    }

                // other chars
                default:
                    $escaped = false; // reset escaing
                    $buffer .= $input->char;
                    break;

            }

            // advance input
            $input->eat();

        }

        // return token
        return $this->token(self::T_STRING, $line, $buffer);
    }

}
