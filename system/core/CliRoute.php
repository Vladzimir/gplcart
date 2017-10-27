<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core;

use gplcart\core\helpers\Cli as CliHelper;

/**
 * Routes CLI commands
 */
class CliRoute
{

    /**
     * Hook class instance
     * @var \gplcart\core\Hook $hook
     */
    protected $hook;

    /**
     * CLI class instance
     * @var \gplcart\core\helpers\Cli $cli
     */
    protected $cli;

    /**
     * The current CLI route data
     * @var array
     */
    protected $route = array();

    /**
     * An array of parsed CLI arguments
     * @var array
     */
    protected $arguments = array();

    /**
     * @param CliHelper $cli
     * @param Hook $hook
     */
    public function __construct(CliHelper $cli, Hook $hook)
    {
        $this->cli = $cli;
        $this->hook = $hook;

        if (!empty($_SERVER['argv'])) {
            $this->arguments = $this->cli->parseArguments($_SERVER['argv']);
        }

        $this->hook->attach('construct.cli.route', $this);
    }

    /**
     * Returns the current CLI route
     * @return array
     */
    public function get()
    {
        return $this->route;
    }

    /**
     * Returns an array of arguments of the current command
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Set an array of CLI arguments
     * @param array $arguments
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * Returns an array of CLI routes
     */
    public function getList()
    {
        $routes = &gplcart_static('cli.route.list');

        if (isset($routes)) {
            return $routes;
        }

        $routes = (array) gplcart_config_get(GC_FILE_CONFIG_ROUTE_CLI);
        $this->hook->attach('cli.route.list', $routes, $this);
        return $routes;
    }

    /**
     * Processes the current CLI command
     */
    public function process()
    {
        $routes = $this->getList();
        $command = array_shift($this->arguments);

        if (empty($routes[$command])) {
            exit("Unknown command. Use 'help' command to see what you got");
        }

        $routes[$command]['command'] = $command;
        $routes[$command]['arguments'] = $this->arguments;

        $this->route = $routes[$command];

        Handler::call($this->route, null, 'controller', array($this->arguments));
        exit('The command was completed incorrectly');
    }

}
