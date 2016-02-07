<?php
//Encoding should be UTF-8 [ěščřžýáíé]

/**
 * [INTERFACE] Event listener interface definition
 * @author ShiraNai7 <shira.cz>
 */

namespace Devkit\Component\Event;

/**
 * Event listener interface
 */
interface EventListenerInterface
{

    /**
     * Get event map
     *
     * Return value format:
     *
     * - Event names are used as keys
     * - The entry can be either a string (method name) or array consisting of method_name + priority
     * - The priority defaults to 0
     *
     * Example:
     *
     *  array(
     *      'some.event' => 'onSomeEvent',
     *      'other.event' => array('onOtherEvent', 1),
     *      'yet.another.event' => array('onYetAnotherEvent', 5),
     *  )
     *
     * @return array
     */
    public function getEventMap();

}
