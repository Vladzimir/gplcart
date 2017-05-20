<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\models;

use gplcart\core\Model,
    gplcart\core\Cache;
use gplcart\core\models\Trigger as TriggerModel,
    gplcart\core\models\Currency as CurrencyModel;

/**
 * Manages basic behaviors and data related to price rules
 */
class PriceRule extends Model
{

    /**
     * Currency model instance
     * @var \gplcart\core\models\Currency $currency
     */
    protected $currency;

    /**
     * Trigger model instance
     * @var \gplcart\core\models\Trigger $trigger
     */
    protected $trigger;

    /**
     * @param CurrencyModel $currency
     * @param TriggerModel $trigger
     */
    public function __construct(CurrencyModel $currency, TriggerModel $trigger)
    {
        parent::__construct();

        $this->trigger = $trigger;
        $this->currency = $currency;
    }

    /**
     * Returns an array of rules or total number of rules
     * @param array $data
     * @return array|integer
     */
    public function getList(array $data = array())
    {
        $price_rules = &Cache::memory(array(__METHOD__ => $data));

        if (isset($price_rules)) {
            return $price_rules;
        }

        $sql = 'SELECT *';

        if (!empty($data['count'])) {
            $sql = 'SELECT COUNT(price_rule_id)';
        }

        $sql .= ' FROM price_rule';
        $where = array();

        if (!empty($data['price_rule_id'])) {
            $ids = (array) $data['price_rule_id'];
            $placeholders = rtrim(str_repeat('?, ', count($ids)), ', ');
            $sql .= ' WHERE price_rule_id IN(' . $placeholders . ')';
            $where = array_merge($where, $ids);
        } else {
            $sql .= ' WHERE price_rule_id > 0';
        }

        if (!empty($data['trigger_id'])) {
            $ids = (array) $data['trigger_id'];
            $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
            $sql .= ' AND trigger_id IN(' . $placeholders . ')';
            $where = array_merge($where, $ids);
        }

        if (isset($data['name'])) {
            $sql .= ' AND name LIKE ?';
            $where[] = "%{$data['name']}%";
        }

        if (isset($data['code'])) {
            $sql .= ' AND code LIKE ?';
            $where[] = "%{$data['code']}%";
        }

        if (isset($data['value'])) {
            $sql .= ' AND value = ?';
            $where[] = (int) $data['value'];
        }

        if (isset($data['value_type'])) {
            $sql .= ' AND value_type = ?';
            $where[] = $data['value_type'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND status = ?';
            $where[] = (int) $data['status'];
        }

        if (isset($data['currency'])) {
            $sql .= ' AND currency = ?';
            $where[] = $data['currency'];
        }

        $orders = array('asc', 'desc');
        $sorts = array('price_rule_id', 'name', 'code',
            'value', 'value_type', 'weight', 'status', 'currency', 'trigger_id');

        if ((isset($data['sort']) && in_array($data['sort'], $sorts)) && (isset($data['order']) && in_array($data['order'], $orders, true))) {
            $sql .= " ORDER BY {$data['sort']} {$data['order']}";
        } else {
            $sql .= ' ORDER BY weight ASC';
        }

        if (!empty($data['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $data['limit']));
        }

        if (!empty($data['count'])) {
            return (int) $this->db->fetchColumn($sql, $where);
        }

        $options = array('index' => 'price_rule_id');
        $price_rules = $this->db->fetchAll($sql, $where, $options);

        $this->hook->fire('price.rule.list', $data, $price_rules, $this);
        return $price_rules;
    }

    /**
     * Loads a price rule from the database
     * @param integer $price_rule_id
     * @return array
     */
    public function get($price_rule_id)
    {
        $this->hook->fire('price.rule.get.before', $price_rule_id, $this);

        $sql = 'SELECT * FROM price_rule WHERE price_rule_id=?';
        $price_rule = $this->db->fetch($sql, array($price_rule_id));

        $this->hook->fire('price.rule.get.after', $price_rule, $this);
        return $price_rule;
    }

    /**
     * Adds a price rule
     * @param array $data
     * @return boolean|integer
     */
    public function add(array $data)
    {
        $this->hook->fire('price.rule.add.before', $data, $this);

        if (empty($data)) {
            return false;
        }

        $data['price_rule_id'] = $this->db->insert('price_rule', $data);

        $this->hook->fire('price.rule.add.after', $data, $this);
        return $data['price_rule_id'];
    }

    /**
     * Updates a price rule
     * @param integer $price_rule_id
     * @param array $data
     * @return boolean
     */
    public function update($price_rule_id, array $data)
    {
        $this->hook->fire('price.rule.update.before', $price_rule_id, $data, $this);

        if (empty($price_rule_id)) {
            return false;
        }

        $conditions = array('price_rule_id' => $price_rule_id);
        $result = $this->db->update('price_rule', $data, $conditions);

        $this->hook->fire('price.rule.update.after', $price_rule_id, $data, $result, $this);
        return (bool) $result;
    }

    /**
     * Increments a number of usages by 1
     * @param integer $price_rule_id
     * @return boolean
     */
    public function setUsed($price_rule_id)
    {
        $sql = 'UPDATE price_rule SET used=used + 1 WHERE price_rule_id=?';
        return (bool) $this->db->run($sql, array($price_rule_id))->rowCount();
    }

    /**
     * Performs simple rule code validation
     * @param integer $price_rule_id
     * @param string $code
     * @return boolean
     */
    public function codeMatches($price_rule_id, $code)
    {
        $sql = 'SELECT price_rule_id'
                . ' FROM price_rule'
                . ' WHERE code=? AND price_rule_id=? AND status=?';

        $params = array($code, $price_rule_id, 1);
        return (bool) $this->db->fetchColumn($sql, $params);
    }

    /**
     * Deletes a price value
     * @param integer $price_rule_id
     * @return boolean
     */
    public function delete($price_rule_id)
    {
        $this->hook->fire('price.rule.delete.before', $price_rule_id, $this);

        if (empty($price_rule_id)) {
            return false;
        }

        $conditions = array('price_rule_id' => $price_rule_id);
        $result = $this->db->delete('price_rule', $conditions);

        $this->hook->fire('price.rule.delete.after', $price_rule_id, $result, $this);
        return (bool) $result;
    }

    /**
     * Applies all suited rules and calculates totals
     * @param integer $total
     * @param array $data
     * @param array $components
     * @return array
     */
    public function calculate(&$total, $data, &$components = array())
    {
        $options = array(
            'status' => 1,
            'store_id' => $data['store_id']
        );

        foreach ($this->getTriggered($options, $data) as $rule) {
            $this->calculateComponent($total, $data, $components, $rule);
        }

        return array('total' => $total, 'components' => $components);
    }

    /**
     * Calculates a price rule component
     * @param integer $amount
     * @param array $data
     * @param array $components
     * @param array $rule
     * @return integer
     */
    protected function calculateComponent(&$amount, $data, &$components, $rule)
    {
        $rule_id = $rule['price_rule_id'];

        if ($rule['code'] !== '') {
            if (!isset($data['order']['data']['pricerule_code']) || !$this->codeMatches($rule_id, $data['order']['data']['pricerule_code'])) {
                $components[$rule_id] = array('rule' => $rule, 'price' => 0);
                return $amount;
            }
        }

        if ($rule['value_type'] === 'percent') {
            $value = $amount * ((float) $rule['value'] / 100);
            $components[$rule_id] = array('rule' => $rule, 'price' => $value);
            $amount += $value;
            return $amount;
        }

        if ($data['currency'] != $rule['currency']) {
            $converted = $this->currency->convert(abs($rule['value']), $rule['currency'], $data['currency']);
            $rule['value'] = ($rule['value'] < 0) ? -$converted : $converted;
        }

        $components[$rule_id] = array('rule' => $rule, 'price' => $rule['value']);
        $amount += $rule['value'];

        $this->hook->fire('price.rule.calculate.component', $amount, $data, $components, $rule, $this);
        return $amount;
    }

    /**
     * Returns an array of suitable rules for a given context
     * @param array $data
     * @return array
     */
    public function getTriggered(array $options, array $data)
    {
        $options['trigger_id'] = $this->trigger->getFired($options, $data);

        if (empty($options['trigger_id'])) {
            return array();
        }

        $coupons = 0;
        $results = array();

        foreach ((array) $this->getList($options) as $id => $rule) {

            if ($rule['code'] !== '') {
                $coupons++;
            }

            if ($coupons <= 1) {
                $results[$id] = $rule;
            }
        }

        // Coupons always go last
        uasort($results, function ($a) {
            return $a['code'] === '' ? -1 : 1;
        });

        return $results;
    }

}
