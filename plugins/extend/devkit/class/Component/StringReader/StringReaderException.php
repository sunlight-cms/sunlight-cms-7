<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Exception class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\StringReader;

/**
 * Exception class
 */
class StringReaderException extends \Exception
{

    /** @var int input line number */
    protected $inputLine;

    /**
     * Constructor
     *
     * @param string $msg       the message
     * @param int    $inputLine line number (0 = unknown)
     */
    public function __construct($msg, $inputLine = 0)
    {
        parent::__construct($msg);
        $this->inputLine = $inputLine;
    }

    /**
     * Get input line number
     *
     * @return int
     */
    public function getInputLine()
    {
        return $this->inputLine;
    }

    /**
     * Convert exception to a RuntimeException
     *
     * @param  string            $idt related script identifier (e.g. filename)
     * @return \RuntimeException
     */
    public function getAsRuntime($idt)
    {
        return new \RuntimeException(
            sprintf('%s in "%s" on line %s', $this->getMessage(), $idt, $this->inputLine),
            0,
            $this
        );
    }

}
