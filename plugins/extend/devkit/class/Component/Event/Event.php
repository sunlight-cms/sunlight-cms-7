<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Event class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\Event;

/**
 * Event class
 */
class Event
{

    /** @var string */
    protected $name;
    /** @var bool */
    protected $propagationStopped = false;
    /** @var bool */
    protected $handled = false;
    /** @var EventDispatcher */
    protected $dispatcher;
    /** @var array */
    protected $attrs = array();

    /**
     * Constructor
     *
     * @param array|null $attrs event attributes
     */
     public function __construct(array $attrs = null)
     {
        if (null !== $attrs) {
            $this->attrs = $attrs;
        }
     }

    /**
     * Stop event propagation
     *
     * @return Event
     */
    public function stopPropagation()
    {
        $this->propagationStopped = true;

        return $this;
    }

    /**
     * See if the event propagation has been stopped
     *
     * @return bool
     */
    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    /**
     * Set handled status
     *
     * @param  bool  $handled
     * @return Event
     */
    public function setHandled($handled)
    {
        $this->handled = $handled;

        return $this;
    }

    /**
     * See if the event has been handled
     *
     * @return bool
     */
    public function isHandled()
    {
        return $this->handled;
    }

     /**
     * Set event name and dispatcher
      *
     * @param string          $name
     * @param EventDispatcher $dispatcher
     */
    public function configure($name, EventDispatcher $dispatcher)
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Get event name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set event name
     *
     * @param  string $name
     * @return Event
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get event dispatcher
     *
     * @return EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set event dispatcher
     *
     * @param  EventDispatcher $dispatcher
     * @return Event
     */
    public function setDispatcher(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    /**
     * Set attribute
     *
     * @param  string $name  attribute name
     * @param  mixed  $value attribute value
     * @return Event
     */
    public function set($name, $value)
    {
        $this->attrs[$name] = $value;

        return $this;
    }

    /**
     * Set all attributes
     *
     * @param  array $attrs array of attributes
     * @param  bool  $merge merge with current 1/0
     * @return Event
     */
    public function setAttrs(array $attrs, $merge = true)
    {
        if($merge) $this->attrs = $attrs + $this->attrs;
        else $this->attrs = $attrs;
        return $this;
    }

    /**
     * Magic attribute setter
     *
     * @param string $name
     * @param string $value
     */
    public function __set($name, $value)
    {
        $this->attrs[$name] = $value;
    }

    /**
     * Get attribute
     *
     * @param  string $name      attribute name
     * @param  mixed  $default   default attribute value
     * @param  bool   $exception throw exception on unknown attribute 1/0
     * @return mixed
     */
    public function get($name, $default = null, $exception = true)
    {
        // find
        if (isset($this->attrs[$name]) || array_key_exists($name, $this->attrs)) {
            return $this->attrs[$name];
        }

        // not found
        if ($exception) {
            throw new \OutOfBoundsException(sprintf('Attribute "%s% is not defined', $name));
        }

        return $default;
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public function getAll()
    {
        return $this->attrs;
    }

    /**
     * Magic attribute getter
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        // find
        if (isset($this->attrs[$name]) || array_key_exists($name, $this->attrs)) {
            return $this->attrs[$name];
        }

        // not found
        throw new \OutOfBoundsException(sprintf('Attribute "%s% is not defined', $name));
    }

    /**
     * Remove attribute
     *
     * @param  string $name attribute name
     * @return Event
     */
    public function remove($name)
    {
        unset($this->attrs[$name]);

        return $this;
    }

    /**
     * Magic attribute remover
     *
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->attrs[$name]);
    }

}
