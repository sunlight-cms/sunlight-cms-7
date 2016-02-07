<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] String reader class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\StringReader;

/**
 * String reader class
 */
class StringReader
{

    /** No character */
    const CHAR_NONE = 1;
    /** Whitespace character */
    const CHAR_WS = 2;
    /** Numeric character */
    const CHAR_NUM = 3;
    /** Identifier character */
    const CHAR_IDT = 4;
    /** Control character */
    const CHAR_CTRL = 5;
    /** Unmapped character (acii <= 31, excluding whitespace) */
    const CHAR_OTHER = 6;
    /** Unknown character type */
    const CHAR_UNKNOWN = null;

    /** @var array map of whitespace characters */
    public $wsChars = array("\n" => 0, "\r" => 1, "\t" => 2, ' ' => 2);
    /** @var array map of additional characters allowed in identifiers */
    public $idtExtraChars = array('_' => 0, '$' => 1);
    /** @var array of various runtime arguments */
    public $args = array();

    /** @var string the input string */
    public $str;
    /** @var int the input string length*/
    public $len;
    /** @var bool indicate input end*/
    public $end = false;
    /** @var int current input position */
    public $i = 0;
    /** @var string|null current character or null on string end */
    public $char;
    /** @var string|null last character or null on string end */
    public $lastChar;
    /** @var int current input line (if current position is newline, it is already incremented) */
    public $line = 0;
    /** @var bool current position is newline 1/0 */
    public $isNewline = false;
    /** @var bool track current line number 1/0 */
    protected $trackLine;
    /** @var array stored states */
    protected $states = array();

    /**
     * Constructor
     *
     * @param string $str       the input string
     * @param bool   $trackLine track current line number 1/0
     */
    public function __construct($str, $trackLine = true)
    {
        // set vars
        $this->str = $str;
        $this->trackLine = $trackLine;
        $this->len = strlen($str);

        // update states
        if (0 === $this->len) {
            $this->end = true;
        } else {
            $this->char = $str[0];
        }
        if ($trackLine) {
            $this->line = 1;
            if ("\n" === $this->char || "\r" === $this->char) {
                ++$this->line;
                $this->isNewline = true;
            }
        }
    }

    /**
     * Consume character and return next character
     *
     * @return string|null null on end
     */
    public function eat()
    {
        // ended?
        if ($this->end) {
            return;
        }

        // advance position
        ++$this->i;

        // detect end
        if ($this->i >= $this->len) {
            $this->end = true;
            $this->lastChar = $this->char;
            $this->char = null;

            return;
        }

        // refresh current info
        $this->lastChar = $this->char;
        $this->char = $this->str[$this->i];
        if ($this->trackLine) {
            if ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char) {
                ++$this->line;
                $this->isNewline = true;
            } else {
                $this->isNewline = false;
            }
        }

        // return
        return $this->char;
    }

    /**
     * Consume character and return it
     *
     * @return string|null null on input end
     */
    public function shift()
    {
        $this->eat();

        return $this->lastChar;
    }

    /**
     * Get next character from input, no consuming
     *
     * @return string|null null on input end
     */
    public function peek()
    {
        // detect end
        if ($this->end || ($next = $this->i + 1) >= $this->len) {
            return;
        }

        // return char
        return $this->str[$next];
    }

    /**
     * Alter current position
     *
     * @param  int          $offset relative offset, can be negative
     * @return StringReader
     */
    public function move($offset)
    {
        if (0 === $offset) {
            return $this;
        }

        // check
        $newPointer = $this->i + $offset;
        if ($newPointer < 0 || $newPointer > $this->len) {
            throw new StringReaderException(sprintf('Offset "%s" is out of input boundaries', $newPointer));
        }

        // determine line number offset
        if ($this->trackLine) {
            $offsetSlice = (($offset < 0)
                ? substr($this->str, $newPointer, $this->i - $newPointer + 1)
                : substr($this->str, $this->i, $offset + 1)
            );

            $lineOffset = 0;
            $lastChar = ((0 === $newPointer) ? null : $this->str[$newPointer - 1]);
            for ($i = 0; isset($offsetSlice[$i]); ++$i) {
                $char = $offsetSlice[$i];
                if (0 !== $i && ("\n" === $char && "\r" !== $lastChar || "\r" === $char)) {
                    ++$lineOffset;
                }
                $lastChar = $char;
            }
            if(0 !== $lineOffset && $offset < 0) {
                $lineOffset *= -1;
            }
        }

        // set
        $this->i = $newPointer;
        $this->char = ((0 === $this->len || $newPointer === $this->len) ? null : $this->str[$newPointer]);
        $this->lastChar = ((0 === $newPointer) ? null : $this->str[$newPointer - 1]);
        $this->end = (0 === $this->len || $newPointer === $this->len);
        if($this->trackLine) {
            $this->line += $lineOffset;
            $this->isNewline = ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char);
        }

        return $this;
    }

    /**
     * Reset the input state
     *
     * @return StringReader
     */
    public function reset()
    {
        $this->i = 0;
        $this->end = (0 === $this->len);
        $this->char = ($this->end ? null : $this->str[0]);
        $this->lastChar = null;
        $this->states = array();
        $this->tagId = null;
        if($this->trackLine) {
            $this->line = 1;
            if ("\n" === $this->char && $this->lastChar !== "\r" || "\r" === $this->char) {
                ++$this->line;
                $this->isNewline = true;
            } else {
                $this->isNewline = false;
            }
        }

        return $this;
    }

    /**
     * Consume specific character and return next character
     *
     * @param  string $char the character to consume
     * @return string null on input end
     */
    public function eatChar($char)
    {
        if ($char === $this->char) {
            return $this->eat();
        }
        $this->unexpectedCharException($char);
    }

    /**
     * Attempt to consume specific character and return success state
     *
     * @param  string $char the character to consume
     * @return bool   consumed successfully 1/0
     */
    public function eatIfChar($char)
    {
        if ($char === $this->char) {
            $this->eat();

            return true;
        }

        return false;
    }

    /**
     * Consume all character of specified type
     *
     * Pre-offset: any
     * Post-offset: at first invalid character or end
     *
     * @param  int    $type character type (see StringReader::CHAR_X constants)
     * @return string all consumed characters
     */
    public function eatType($type)
    {
        // scan
        $consumed = '';
        while (!$this->end) {

            // check type
            if ($this->charType() !== $type) {
                break;
            }

            // consume
            $consumed .= $this->shift();

        }

        // return
        return $consumed;
    }

    /**
     * Consume all characters of specified types
     *
     * Pre-offset: any
     * Post-offset: at first invalid character or end
     *
     * @param  array  $types map of types (see StringReader::CHAR_X constants)
     * @return string all consumed characters
     */
    public function eatTypes(array $typeMap)
    {
        // scan
        $consumed = '';
        while (!$this->end) {

            // check type
            if (!isset($typeMap[$this->charType()])) {
                break;
            }

            // consume
            $consumed .= $this->shift();

        }

        // return
        return $consumed;
    }

    /**
     * Consume whitespace if any
     *
     * Pre-offset: any
     * Post-offset: at first non-whitespace character or end
     *
     * @param  bool        $newlines consume newline characters (\r or \n)
     * @return string|null returns first non-whitespace character or null (= input end)
     */
    public function eatWs($newlines = true)
    {
        // scan
        while (!$this->end) {

            // check type
            if (!isset($this->wsChars[$this->char]) || !$newlines && ("\n" === $this->char || "\r" === $this->char)) {
                break;
            }

            // consume
            $this->eat();

        }

        // return
        return $this->char;
    }

    /**
     * Consume all characters until the specified delimiters
     *
     * Pre-offset: any
     * Post-offset: at or after first delimiter or at end
     *
     * @param  array  $delimiterMap  map of delimiter characters
     * @param  bool   $skipDelimiter skip the delimiter 1/0
     * @param  bool   $allowEnd      treat end of input as valid delimiter 1/0
     * @return string all consumed characters
     */
    public function eatUntil(array $delimiterMap, $skipDelimiter = true, $allowEnd = false)
    {
        // scan
        $consumed = '';
        while (!$this->end && !isset($delimiterMap[$this->char])) {
            $consumed .= $this->shift();
        }

        // check end
        if ($this->end && !$allowEnd) {
            $this->unexpectedEnd(array_keys($delimiterMap));
        }

        // skip delimiter
        if ($skipDelimiter && !$this->end) {
            $this->eat();
        }

        // return
        return $consumed;
    }

    /**
     * Check character type
     *
     * @param  string|bool|null $char char, null or false (= current char)
     * @param  int              $type the type to match against (see StringReader::CHAR_X constants)
     * @return bool
     */
    public function charIs($type, $char = false)
    {
        return $this->charType($char) === $type;
    }

    /**
     * Check if character is whitespace
     *
     * @param  string|bool|null $char char, null or false (= current char)
     * @return bool
     */
    public function charIsWs($char = false)
    {
        return self::CHAR_WS === $this->charType($char);
    }

    /**
     * Check if character is a number
     *
     * @param  string|bool|null $char char, null or false (= current char)
     * @return bool
     */
    public function charIsNum($char = false)
    {
        return self::CHAR_NUM === $this->charType($char);
    }

    /**
     * Check if character is an identifier character
     *
     * @param  string|bool|null $char char, null or false (= current char)
     * @return bool
     */
    public function charIsIdt($char = false)
    {
        return self::CHAR_IDT === $this->charType($char);
    }

    /**
     * Check if character is a control character
     *
     * @param  string|bool|null $char char, null or false (= current char)
     * @return bool
     */
    public function charIsCtrl($char = false)
    {
        return self::CHAR_CTRL === $this->charType($char);
    }

    /**
     * Get character type
     *
     * @param  string|bool|null $char char, null or false (= current char)
     * @return int
     */
    public function charType($char = false)
    {
        if(false === $char) $char = $this->char;
        if(null === $char) return self::CHAR_NONE;
        if(isset($this->wsChars[$char])) return self::CHAR_WS;
        if(($ord = ord($char)) > 64 && $ord < 91 || $ord > 96 && $ord < 123 || $ord > 126 || isset($this->idtExtraChars[$char])) return self::CHAR_IDT;
        if($ord > 47 && $ord < 58) return self::CHAR_NUM;
        if($ord > 31) return self::CHAR_CTRL;

        return self::CHAR_OTHER;
    }

    /**
     * Get name of character type
     *
     * @param  int    $type character type (see StringReader::CHAR_X constants)
     * @return string
     */
    public function charTypeName($type)
    {
        switch ($type) {
            case self::CHAR_NONE: return 'CHAR_NONE';
            case self::CHAR_WS: return 'CHAR_WS';
            case self::CHAR_NUM: return 'CHAR_NUM';
            case self::CHAR_IDT: return 'CHAR_IDT';
            case self::CHAR_CTRL: return 'CHAR_CTRL';
            case self::CHAR_OTHER: return 'CHAR_OTHER';
        }

        return 'CHAR_UNKNOWN';
    }

    /**
     * Store current input state
     *
     * Don't forget to revertState() or popState() when you are done with it
     * @return StringReader
     */
    public function pushState()
    {
        $this->states[] = array($this->end, $this->i, $this->line, $this->char, $this->lastChar);

        return $this;
    }

    /**
     * Revert to last stored state and pop it
     *
     * @return StringReader
     */
    public function revertState()
    {
        $state = array_pop($this->states);
        if (null === $state) {
            throw new \RuntimeException('Could not revert state');
        }
        list($this->end, $this->i, $this->line, $this->char, $this->lastChar) = $state;

        return $this;
    }

    /**
     * Pop last stored state without reverting to it
     *
     * @return StringReader
     */
    public function popState()
    {
        if (null === array_pop($this->states)) {
            throw new \RuntimeException('No states to pop');
        }

        return $this;
    }

    /**
     * Ensure that the input has ended
     *
     * @throws StringReaderException
     * @return StringReader
     */
    public function expectEnd()
    {
        if (!$this->end) {
            throw new StringReaderException('Expected end of input', $this->line);
        }

        return $this;
    }

    /**
     * Ensure that the input has not ended
     *
     * @throws StringReaderException
     * @return StringReader
     */
    public function expectNotEnd()
    {
        if ($this->end) {
            $this->unexpectedEndException();
        }

        return $this;
    }

    /**
     * Ensure that the character matches the expectation
     *
     * @param  string           $expectedChar expected character
     * @param  int|null         $testedLine   line number or null (= current)
     * @param  string|bool|null $testedChar   char to verify, null or false (= current char)
     * @return StringReader
     */
    public function expectChar($expectedChar, $testedLine = null, $testedChar = false)
    {
        if ($expectedChar !== $testedChar) {
            $this->unexpectedCharException($expectedChar, $testedLine, $testedChar);
        }

        return $this;
    }

    /**
     * Ensure that the character is of given type
     *
     * @param  int              $type       the type to expect (see StringReader::CHAR_X constants)
     * @param  int|null         $testedLine line number or null (= current)
     * @param  string|bool|null $testedChar char to verify, null or false (= current char)
     * @return StringReader
     */
    public function expectCharType($expectedType, $testedLine = null, $testedChar = false)
    {
        if ($this->charType($testedChar) !== $expectedType) {
            $this->unexpectedCharTypeException($expectedType, $testedLine, $testedChar);
        }

        return $this;
    }

    /**
     * Throw unexpected end of input exception
     *
     * @throws StringReaderException
     * @param  array|string|null     $expected what was expected as string or array of options, or null
     */
    public function unexpectedEndException($expected = null)
    {
        // prepare message
        $message = 'Unexpected end of input';

        // add expectations
        if (null !== $expected) {
            $message .= ', expected ' . self::formatExceptionOptions($expected);
        }

        // throw
        throw new StringReaderException($message, $this->line);
    }

    /**
     * Throw unexpected character exception
     *
     * @throws StringReaderException
     * @param  array|string|null     $expected expected character as string or array of strings, or null
     * @param  int|null              $line     line number or null (= current)
     * @param  string|bool|null      $char     the unexpected char, null (= end of input) or false (= current char)
     */
    public function unexpectedCharException($expected = null, $line = null, $char = false)
    {
        // prepare message
        $message = 'Unexpected ';

        // add char
        if(false === $char) $char = $this->char;
        if(null === $char) $message .= 'end of input';
        else $message .= "'{$char}'";

        // add expectations
        if (null !== $expected) {
            $message .= ', expected ' . self::formatExceptionOptions($expected);
        }

        // throw
        throw new StringReaderException($message, (null === $line) ? $this->line : $this->line);
    }

    /**
     * Throw unexpected character type exception
     *
     * @throws StringReaderException
     * @param  array|int|null        $expected expected character type as integer or array of integers, or null
     * @param  int|null              $line     line number or null (= current)
     * @param  string|bool|null      $char     the unexpected char, null or false (= current char)
     */
    public function unexpectedCharTypeException($expected = null, $line = null, $char = false)
    {
        // get char and its type
        if(false === $char) $char = $this->char;
        $type = $this->charType($char);

        // prepare message
        $message = 'Unexpected ' . $this->charTypeName($type);
        if (null !== $char && self::CHAR_UNKNOWN !== $type && self::CHAR_OTHER !== $type) {
            $message .= " ('{$char}')";
        }

        // add expectations
        if (null !== $expected) {
            $expectedArr = (array) $expectedArr;
            foreach ($expectedArr as &$expectedType) {
                $expectedType = $this->charTypeName($expectedType);
            }
            $message .= ', expected ' . self::formatExceptionOptions($expectedArr);
        }

        // throw
        throw new StringReaderException($message, (null === $line) ? $this->line : $this->line);
    }

    /**
     * Format list of options for exception messages
     *
     * @param  array|string|null $options options as string or array of options, or null
     * @return string
     */
    public static function formatExceptionOptions($options)
    {
        // return empty string for null
        if (null === $options) {
            return '';
        }

        // format for array
        if (is_array($options)) {

            // add options
            $out = '';
            for ($i = 0, $last = sizeof($options) - 1; $i <= $last; ++$i) {

                // add delimiter
                if (0 !== $i) {
                    if($last === $i) $out .= ' or ';
                    else $out .= ', ';
                }

                // add option
                $out .= "'{$options[$i]}'";

            }

            // return
            return $out;

        }

        // format for string
        return "'" . (string) $options . "'";
    }

}
