<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Debug exception class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\ErrorHandler;

/**
 * Debug exception class
 */
class DebugException extends \Exception
{

    /** @var string|null */
    private $title;

    /**
     * Constructor
     *
     * @param string|null     $title    title
     * @param string          $dump     dumped value
     * @param int             $code     code
     * @param object|null $previous previous exception
     */
    public function __construct($title = null, $dump = '', $code = 0, $previous = null)
    {
        parent::__construct($dump, $code, $previous);
        $this->title = $title;
    }

    /**
     * Get title
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->title;
    }

}
