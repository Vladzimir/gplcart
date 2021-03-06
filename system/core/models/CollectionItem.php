<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\models;

use Exception;
use gplcart\core\Config;
use gplcart\core\Handler;
use gplcart\core\Hook;
use gplcart\core\interfaces\Crud as CrudInterface;
use gplcart\core\models\Collection as CollectionModel;
use gplcart\core\models\Translation as TranslationModel;

/**
 * Manages basic behaviors and data related to collection items
 */
class CollectionItem implements CrudInterface
{

    /**
     * Database class instance
     * @var \gplcart\core\Database $db
     */
    protected $db;

    /**
     * Hook class instance
     * @var \gplcart\core\Hook $hook
     */
    protected $hook;

    /**
     * Translation UI model instance
     * @var \gplcart\core\models\Translation $translation
     */
    protected $translation;

    /**
     * Collection model instance
     * @var \gplcart\core\models\Collection $collection
     */
    protected $collection;

    /**
     * @param Hook $hook
     * @param Config $config
     * @param TranslationModel $translation
     * @param CollectionModel $collection
     */
    public function __construct(Hook $hook, Config $config, TranslationModel $translation, CollectionModel $collection)
    {
        $this->hook = $hook;
        $this->db = $config->getDb();
        $this->collection = $collection;
        $this->translation = $translation;
    }

    /**
     * Loads a collection item from the database
     * @param integer $id
     * @return array
     */
    public function get($id)
    {
        $result = null;
        $this->hook->attach('collection.item.get.before', $id, $result, $this);

        if (isset($result)) {
            return $result;
        }

        $sql = 'SELECT * FROM collection_item WHERE collection_item_id=?';
        $result = $this->db->fetch($sql, array($id), array('unserialize' => 'data'));

        $this->hook->attach('collection.item.get.after', $id, $result, $this);
        return $result;
    }

    /**
     * Returns an array of collection items or counts them
     * @param array $options
     * @return array|integer
     */
    public function getList(array $options = array())
    {
        $options += array('language' => $this->translation->getLangcode());

        $result = &gplcart_static(gplcart_array_hash(array('collection.item.list' => $options)));

        if (isset($result)) {
            return $result;
        }

        $this->hook->attach('collection.item.list.before', $options, $result, $this);

        if (isset($result)) {
            return $result;
        }

        $sql = 'SELECT ci.*, c.status AS collection_status, c.store_id,
                c.type, COALESCE(NULLIF(ct.title, ""), c.title) AS collection_title';

        if (!empty($options['count'])) {
            $sql = 'SELECT COUNT(ci.collection_item_id)';
        }

        $sql .= ' FROM collection_item ci
                  LEFT JOIN collection c ON(ci.collection_id=c.collection_id)
                  LEFT JOIN collection_translation ct ON(ct.collection_id = c.collection_id AND ct.language=?)';

        $conditions = array($options['language']);

        if (isset($options['collection_item_id'])) {
            $sql .= ' WHERE ci.collection_item_id = ?';
            $conditions[] = $options['collection_item_id'];
        } else {
            $sql .= ' WHERE ci.collection_item_id IS NOT NULL';
        }

        if (isset($options['entity_id'])) {
            $sql .= ' AND ci.entity_id = ?';
            $conditions[] = $options['entity_id'];
        }

        if (isset($options['status'])) {
            $sql .= ' AND c.status = ?';
            $sql .= ' AND ci.status = ?';
            $conditions[] = (int) $options['status'];
            $conditions[] = (int) $options['status'];
        }

        if (isset($options['store_id'])) {
            $sql .= ' AND c.store_id = ?';
            $conditions[] = $options['store_id'];
        }

        if (isset($options['collection_id'])) {
            $sql .= ' AND ci.collection_id = ?';
            $conditions[] = $options['collection_id'];
        }

        $allowed_order = array('asc', 'desc');
        $allowed_sort = array('weight', 'status', 'collection_id');

        if (isset($options['sort'])
            && in_array($options['sort'], $allowed_sort)
            && isset($options['order'])
            && in_array($options['order'], $allowed_order)) {
            $sql .= " ORDER BY ci.{$options['sort']} {$options['order']}";
        } else {
            $sql .= " ORDER BY ci.weight DESC";
        }

        if (!empty($options['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $options['limit']));
        }

        if (empty($options['count'])) {
            $result = $this->db->fetchAll($sql, $conditions, array('index' => 'collection_item_id', 'unserialize' => 'data'));
        } else {
            $result = (int) $this->db->fetchColumn($sql, $conditions);
        }

        $this->hook->attach('collection.item.list.after', $options, $result, $this);
        return $result;
    }

    /**
     * Adds a collection item
     * @param array $data
     * @return integer
     */
    public function add(array $data)
    {
        $result = null;
        $this->hook->attach('collection.item.add.before', $data, $result, $this);

        if (isset($result)) {
            return (int) $result;
        }

        $result = (int) $this->db->insert('collection_item', $data);
        $this->hook->attach('collection.item.add.after', $data, $result, $this);
        return (int) $result;
    }

    /**
     * Deletes a collection item
     * @param int|array $condition
     * @return boolean
     */
    public function delete($condition)
    {
        $result = null;
        $this->hook->attach('collection.item.delete.before', $condition, $result, $this);

        if (isset($result)) {
            return (bool) $result;
        }

        if (!is_array($condition)) {
            $condition = array('collection_item_id' => $condition);
        }

        $result = (bool) $this->db->delete('collection_item', $condition);
        $this->hook->attach('collection.item.delete.after', $condition, $result, $this);
        return (bool) $result;
    }

    /**
     * Updates a collection item
     * @param integer $id
     * @param array $data
     * @return boolean
     */
    public function update($id, array $data)
    {
        $result = null;
        $this->hook->attach('collection.item.update.before', $id, $data, $result, $this);

        if (isset($result)) {
            return (bool) $result;
        }

        $result = (bool) $this->db->update('collection_item', $data, array('collection_item_id' => $id));
        $this->hook->attach('collection.item.update.after', $id, $data, $result, $this);
        return (bool) $result;
    }

    /**
     * Returns an array of collection item entities
     * @param array $conditions
     * @return array
     */
    public function getItems(array $conditions = array())
    {
        $list = $this->getList($conditions);

        if (empty($list)) {
            return array();
        }

        $handler_id = null;

        $items = array();
        foreach ((array) $list as $item) {
            $handler_id = $item['type'];
            $items[$item['entity_id']] = $item;
        }

        $handlers = $this->collection->getHandlers();

        $entity_conditions = array(
            'status' => isset($conditions['status']) ? $conditions['status'] : null,
            $handlers[$handler_id]['entity'] . '_id' => array_keys($items)
        );

        $entities = $this->getListEntities($handler_id, $entity_conditions);

        if (empty($entities)) {
            return array();
        }

        foreach ($entities as $entity_id => &$entity) {
            if (isset($items[$entity_id])) {
                $entity['weight'] = $items[$entity_id]['weight'];
                $entity['collection_item'] = $items[$entity_id];
                $entity['collection_handler'] = $handlers[$handler_id];
            }
        }

        gplcart_array_sort($entities);
        return $entities;
    }

    /**
     * Returns a single entity item
     * @param array $conditions
     * @return array
     */
    public function getItem(array $conditions = array())
    {
        $list = $this->getItems($conditions);

        if (empty($list)) {
            return $list;
        }

        return reset($list);
    }

    /**
     * Returns an array of entities for the given collection ID
     * @param string $collection_id
     * @param array $arguments
     * @return array
     */
    public function getListEntities($collection_id, array $arguments)
    {
        try {
            $handlers = $this->collection->getHandlers();
            return Handler::call($handlers, $collection_id, 'list', array($arguments));
        } catch (Exception $ex) {
            trigger_error($ex->getMessage());
            return array();
        }
    }

    /**
     * Returns the next possible weight for a collection item
     * @param integer $collection_id
     * @return integer
     */
    public function getNextWeight($collection_id)
    {
        $sql = 'SELECT MAX(weight) FROM collection_item WHERE collection_id=?';
        $weight = (int) $this->db->fetchColumn($sql, array($collection_id));
        return ++$weight;
    }

}
