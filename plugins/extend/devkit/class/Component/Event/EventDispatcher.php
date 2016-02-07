<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [CLASS] Event dispatcher class definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\Event;

/**
 * Event dispatcher class
 */
class EventDispatcher
{

    /**
     * @var array
     * Entry format:
     * event_name => array(
     *      array(type, priority, listener/callback, [methodName]), // type: 0 = listener, 1 = callback
     *      ...
     * )
     */
    protected $eventMap = array();

    /** @var array, entry: event_name => sorted */
    protected $sortMap = array();

    /**
     * Register listener
     *
     * @param EventListenerInterface the listener instance
     * @return EventDispatcher
     */
    public function addListener(EventListenerInterface $listener)
    {
        $events = $listener->getEventMap();
        foreach ($events as $eventName => $eventData) {

            // process event data
            if (is_array($eventData)) {
                $method = (string) $eventData[0];
                $priority = (isset($eventData[1]) ? (int) $eventData[1] : 0);
            } else {
                $method = (string) $eventData;
                $priority = 0;
            }

            // add to map
            $this->sortMap[$eventName] = false;
            $this->eventMap[$eventName][] = array(
                0,
                $priority,
                $listener,
                $method,
            );

        }

        return $this;
    }

    /**
     * Remove listener
     *
     * @param EventListenerInterface the listener instance
     * @return EventDispatcher
     */
    public function removeListener(EventListenerInterface $listener)
    {
        // iterate through the event map
        foreach ($this->eventMap as $eventName => &$eventEntries) {
            foreach ($eventEntries as $eventEntryIndex => $eventEntry) {

                // match type and instance
                if (0 === $eventEntry[0] && $listener === $eventEntry[2]) {
                    unset($eventEntries[$eventEntryIndex]);
                }

                // clean-up
                if (empty($eventEntries)) {
                    unset(
                        $this->eventMap[$eventName],
                        $this->sortMap[$eventName]
                    );
                    break;
                }

            }
        }

        return $this;
    }

    /**
     * Register callback
     *
     * @param  string          $eventName
     * @param  callable        $callback
     * @param  int             $priority
     * @return EventDispatcher
     */
    public function addCallback($eventName, $callback, $priority = 0)
    {
        // add to map
        $this->sortMap[$eventName] = false;
        $this->eventMap[$eventName][] = array(
            1,
            (int) $priority,
            $callback,
        );

        return $this;
    }

    /**
     * Remove callback
     *
     * @param  string          $eventName
     * @param  callable        $callback
     * @return EventDispatcher
     */
    public function removeCallback($eventName, $callback)
    {
        // iterate through the event map
        foreach ($this->eventMap as $eventName => &$eventEntries) {
            foreach ($eventEntries as $eventEntryIndex => $eventEntry) {

                // match type and callback
                if (1 === $eventEntry[0] && $callback === $eventEntry[2]) {

                    // remove
                    unset($eventEntries[$eventEntryIndex]);

                    // clean-up
                    if (empty($eventEntries)) {
                        unset(
                            $this->eventMap[$eventName],
                            $this->sortMap[$eventName]
                        );
                        break;
                    }

                }

            }
        }

        return $this;
    }

    /**
     * See if specified event has at least one listener or callback
     *
     * @param  string $eventName
     * @return bool
     */
    public function hasHandler($eventName)
    {
        return isset($this->eventMap[$eventName]);
    }

    /**
     * Dispatch an event
     *
     * @param  string     $eventName
     * @param  Event|null $event
     * @return Event
     */
    public function dispatch($eventName, Event $event = null)
    {
        // configure event
        if(null === $event) $event = new Event;
        $event->configure($eventName, $this);

        // are there any matching listeners or callbacks?
        if (!isset($this->eventMap[$eventName])) {
            // nothing to call
            return $event;
        }

        // there is at least one listener or callback
        $event->setHandled(true);

        // sort event map?
        if (!$this->sortMap[$eventName]) {
            $this->sortEventMapFor($eventName);
            $this->sortMap[$eventName] = true;
        }

        // dispatch
        foreach ($this->eventMap[$eventName] as $eventEntry) {

            // invoke handler
            if(0 === $eventEntry[0]) $eventEntry[2]->{$eventEntry[3]}($event); // listener
            else call_user_func($eventEntry[2], $event); // callback

            // check propagation
            if ($event->isPropagationStopped()) {
                break;
            }

        }

        // return
        return $event;
    }

    /**
     * Clear specific or all event handlers
     *
     * @param  string|null     $eventName event name or null (= all)
     * @return EventDispatcher
     */
    public function clear($eventName = null)
    {
        // clear
        if (null === $eventName) {
            // all
            $this->eventMap = array();
            $this->sortMap = array();
        } else {
            // specific
            unset(
                $this->eventMap[$eventName],
                $this->sortMap[$eventName]
            );
        }

        return $this;
    }

    /**
     * Sort event map
     *
     * @param string $eventName
     */
    protected function sortEventMapFor($eventName)
    {
        usort($this->eventMap[$eventName], function($a, $b){
            if($a[1] === $b[1]) return 0; // same priority
            if($a[1] > $b[1]) return -1; // a has greater priority than b

            return 1; // a has lesser priority than b
        });
    }

}
