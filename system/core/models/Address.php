<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace core\models;

use core\classes\Tool;
use core\Model;
use core\models\Country as ModelsCountry;

/**
 * Manages basic behaviors and data related to user addresses
 */
class Address extends Model
{

    /**
     * Country model instance
     * @var \core\models\Country $country
     */
    protected $country;

    /**
     * Constructor
     * @param ModelsCountry $country
     */
    public function __construct(ModelsCountry $country)
    {
        parent::__construct();

        $this->country = $country;
    }

    /**
     * Loads an address from the database
     * @param integer $address_id
     * @return array
     */
    public function get($address_id)
    {
        $this->hook->fire('get.address.before', $address_id);

        $sql = 'SELECT a.*, c.name AS country_name,'
                . ' c.native_name AS country_native_name,'
                . ' c.format AS country_format, s.name AS state_name,'
                . ' ci.name AS city_name FROM address a'
                . ' LEFT JOIN country c ON(a.country=c.code)'
                . ' LEFT JOIN state s ON(a.state_id=s.state_id)'
                . ' LEFT JOIN city ci ON(a.city_id=ci.city_id)'
                . ' WHERE a.address_id = ?';
        
        $options = array('unserialize' => array('data', 'country_format'));
        $address = $this->db->fetch($sql, array($address_id), $options);

        $this->hook->fire('get.address.after', $address_id, $address);
        return $address;
    }

    /**
     * Adds a new address
     * @param array $data
     * @return integer
     */
    public function add(array $data)
    {
        $this->hook->fire('add.address.before', $data);

        if (empty($data)) {
            return false;
        }

        $data += array('created' => GC_TIME);
        $data['address_id'] = $this->db->insert('address', $data);

        $this->hook->fire('add.address.after', $data);
        return $data['address_id'];
    }

    /**
     * Returns an array of addresses with translated address fields
     * @param integer $user_id
     * @param boolean $status
     * @return array
     */
    public function getTranslatedList($user_id, $status = true)
    {
        $conditions = array('user_id' => $user_id, 'status' => $status);
        $list = $this->getList($conditions);

        $addresses = array();
        foreach ($list as $address_id => $address) {
            $addresses[$address_id] = $this->getTranslated($address, true);
        }

        return $addresses;
    }

    /**
     * Returns an array of translated address fields
     * @param array $address
     * @param boolean $both
     * @return array
     */
    public function getTranslated($address, $both = false)
    {
        $default = $this->country->defaultFormat();
        $format = Tool::merge($default, $address['country_format']);

        $results = array();
        foreach ($address as $key => $value) {
            if (empty($format[$key]) || empty($value)) {
                continue;
            }

            if ($key === 'country') {
                $value = $address['country_native_name'];
            }

            if ($key === 'state_id') {
                $value = $address['state_name'];
            }

            if ($both) {
                $results[$format[$key]['name']] = $value;
                continue;
            }

            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Returns a list of addresses for a given user
     * @return array
     */
    public function getList(array $data = array())
    {
        $sql = 'SELECT a.*, ci.city_id, COALESCE(ci.name, ci.city_id) AS city_name,'
                . ' c.name AS country_name, ci.status AS city_status,'
                . ' c.native_name AS country_native_name, c.format AS country_format,'
                . ' s.name AS state_name'
                . ' FROM address a'
                . ' LEFT JOIN country c ON(a.country=c.code)'
                . ' LEFT JOIN state s ON(a.state_id=s.state_id)'
                . ' LEFT JOIN city ci ON(a.city_id=ci.city_id)'
                . ' WHERE a.address_id > 0';

        $where = array();

        if (isset($data['status'])) {

            // TODO: optimize this. Select addresses if no countries at all 
            $countries = $this->country->getList();

            if (!empty($countries)) {
                $sql .= ' AND c.status = ?';
                $where[] = (int) $data['status'];
            }

            //$sql .= ' AND s.status = ?';
            //$where[] = (int) $data['status'];
        }

        if (isset($data['user_id'])) {
            $sql .= ' AND a.user_id = ?';
            $where[] = $data['user_id'];
        }

        $sql .= ' ORDER BY a.created ASC';
        
        
        $options = array(
            'index' => 'address_id',
            'unserialize' => array('data', 'country_format')
        );
        
        $results = $this->db->fetchAll($sql, $where, $options);

        // TODO: Fix this shit. Everything should be done in the SQL above
        $list = array();
        foreach ($results as $address_id => $address) {
            // City ID can be not numeric (user input). Make this check in the query?
            if (!empty($data['status']) && empty($address['city_status']) && is_numeric($address['city_id'])) {
                continue;
            }
            
            $list[$address_id] = $address;
        }

        $this->hook->fire('address.list', $data, $list);
        return $list;
    }

    /**
     * Returns a string containing formatted geocode query
     * @param array $data
     * @return string
     */
    public function getGeocodeQuery(array $data)
    {
        $fields = $this->getGeocodeFields();

        $components = array();
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $components[] = $data[$field];
            }
        }

        $this->hook->fire('geocode.query', $data, $fields, $components);
        return implode(',', $components);
    }

    /**
     * Deletes an address
     * @param integer $address_id
     * @return boolean
     */
    public function delete($address_id)
    {
        $this->hook->fire('delete.address.before', $address_id);

        if (empty($address_id)) {
            return false;
        }

        if (!$this->canDelete($address_id)) {
            return false;
        }

        $conditions = array('address_id' => $address_id);
        $result = $this->db->delete('address', $conditions);
        $this->hook->fire('delete.address.after', $address_id, $result);

        return (bool) $result;
    }

    /**
     * Whether the address can be deleted
     * @param integer $address_id
     * @return boolean
     */
    public function canDelete($address_id)
    {
        return !$this->isReferenced($address_id);
    }

    /**
     * Returns a number of addresses that a user can have
     * @return integer
     */
    public function getLimit()
    {
        return (int) $this->config->get('user_address_limit', 6);
    }

    /**
     * Reduces max number of addresses that a user can have
     * @param integer $user_id
     * @return boolean
     */
    public function controlLimit($user_id)
    {
        $limit = $this->getLimit();
        $existing = $this->getList(array('user_id' => $user_id));

        $count = count($existing);

        if (empty($limit) || $count <= $limit) {
            return false;
        }

        $delete = array_slice($existing, 0, ($count - $limit));

        foreach ($delete as $address) {
            $this->delete($address['address_id']);
        }

        return true;
    }

    /**
     * Returns true if the address has no references
     * @param integer $address_id
     * @return boolean
     */
    public function isReferenced($address_id)
    {
        $sql = 'SELECT order_id FROM orders WHERE shipping_address=?';
        return (bool) $this->db->fetch($sql, array($address_id));
    }

    /**
     * Updates an address
     * @param integer $address_id
     * @param array $data
     * @return boolean
     */
    public function update($address_id, array $data)
    {
        $this->hook->fire('update.address.before', $address_id, $data);

        if (empty($address_id)) {
            return false;
        }

        $conditions = array('address_id' => $address_id);
        $result = $this->db->update('address', $data, $conditions);

        $this->hook->fire('update.address.after', $address_id, $data, $result);
        return (bool) $result;
    }

    /**
     * Returns an array of address fields to be used to format geocoding query
     * @return array
     */
    protected function getGeocodeFields()
    {
        return array('address_1', 'state_id', 'city_id', 'country');
    }

}
