<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core;

use gplcart\core\Container;

/**
 * Provides methods to work with various system handlers
 */
class Handler
{

    /**
     * Config class instance
     * @var \gplcart\core\Config $config
     */
    protected $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = Container::get('gplcart\\core\\Config');
    }

    /**
     * Call a handler
     * @param array $handlers
     * @param string $handler_id
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function call($handlers, $handler_id, $method, $args = array())
    {
        try {
            $handler = static::get($handlers, $handler_id, $method);
        } catch (\ReflectionException $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        }

        if (empty($handler[0])) {
            throw new \InvalidArgumentException('Invalid handler instance');
        }

        return call_user_func_array($handler, $args);
    }

    /**
     * Returns a handler
     * @param array $handlers
     * @param string $handler_id
     * @param string $name
     * @return boolean|array
     */
    public static function get($handlers, $handler_id, $name)
    {
        if (isset($handler_id)) {
            if (empty($handlers[$handler_id]['handlers'][$name])) {
                return false;
            }
            $handler = $handlers[$handler_id]['handlers'][$name];
        } else {
            if (empty($handlers['handlers'][$name])) {
                return false;
            }
            $handler = $handlers['handlers'][$name];
        }

        $handler[0] = Container::get($handler);
        return $handler;
    }

}
