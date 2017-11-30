<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\models;

use gplcart\core\Hook,
    gplcart\core\Handler;
use gplcart\core\models\Language as LanguageModel;

/**
 * Manages basic behaviors and data related to trigger conditions
 */
class Condition
{

    /**
     * Hook class instance
     * @var \gplcart\core\Hook $hook
     */
    protected $hook;

    /**
     * Language model instance
     * @var \gplcart\core\models\Language $language
     */
    protected $language;

    /**
     * An array of processed conditions and their results
     * @var array
     */
    protected $processed = array();

    /**
     * @param Hook $hook
     * @param LanguageModel $language
     */
    public function __construct(Hook $hook, LanguageModel $language)
    {
        $this->hook = $hook;
        $this->language = $language;
    }

    /**
     * Returns an array of processed condition results
     * @return array
     */
    public function getProcessed()
    {
        return $this->processed;
    }

    /**
     * Whether all conditions are met
     * @param array $trigger
     * @param array $data
     * @return boolean
     */
    public function isMet(array $trigger, array $data)
    {
        $result = null;
        $this->hook->attach('condition.met.before', $trigger, $data, $result, $this);

        if (isset($result)) {
            return (bool) $result;
        }

        if (empty($trigger['data']['conditions'])) {
            return false;
        }

        $result = true;
        foreach ($trigger['data']['conditions'] as $condition) {
            if ($this->callHandler($condition, $data) !== true) {
                $result = false;
                break;
            }
        }

        $this->hook->attach('condition.met.after', $trigger, $data, $result, $this);
        return (bool) $result;
    }

    /**
     * Call a condition handler
     * @param array $condition
     * @param array $data
     * @return boolean
     */
    protected function callHandler(array $condition, array $data)
    {
        try {
            $handlers = $this->getHandlers();
            $result = Handler::call($handlers, $condition['id'], 'process', array($condition, $data, $this));
        } catch (\Exception $ex) {
            return false;
        }

        return $this->processed[$condition['id']] = $result;
    }

    /**
     * Returns an array of condition operators
     * @return array
     */
    public function getOperators()
    {
        return array(
            "<" => $this->language->text('Less than'),
            ">" => $this->language->text('Greater than'),
            "=" => $this->language->text('Equal (is in list)'),
            "<=" => $this->language->text('Less than or equal to'),
            ">=" => $this->language->text('Greater than or equal to'),
            "!=" => $this->language->text('Not equal (is not in list)')
        );
    }

    /**
     * Returns an array of condition handlers
     * @return array
     */
    public function getHandlers()
    {
        $handlers = &gplcart_static('condition.handlers');

        if (isset($handlers)) {
            return $handlers;
        }

        $handlers = (array) gplcart_config_get(GC_FILE_CONFIG_CONDITION);
        $this->hook->attach('condition.handlers', $handlers, $this);
        return $handlers;
    }

}
