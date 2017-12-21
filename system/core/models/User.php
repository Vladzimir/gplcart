<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\models;

use gplcart\core\Hook,
    gplcart\core\Config;
use gplcart\core\models\Address as AddressModel,
    gplcart\core\models\UserRole as UserRoleModel;
use gplcart\core\helpers\Session as SessionHelper;

/**
 * Manages basic behaviors and data related to users
 */
class User
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
     * Config class instance
     * @var \gplcart\core\Config $config
     */
    protected $config;

    /**
     * Address model instance
     * @var \gplcart\core\models\Address $address ;
     */
    protected $address;

    /**
     * User role model instance
     * @var \gplcart\core\models\UserRole $role
     */
    protected $role;

    /**
     * Session class instance
     * @var \gplcart\core\helpers\Session $session
     */
    protected $session;

    /**
     * The current user ID
     * @var integer
     */
    protected $uid;

    /**
     * @param Hook $hook
     * @param Config $config
     * @param AddressModel $address
     * @param UserRoleModel $role
     * @param SessionHelper $session
     */
    public function __construct(Hook $hook, Config $config, AddressModel $address,
            UserRoleModel $role, SessionHelper $session)
    {
        $this->hook = $hook;
        $this->config = $config;
        $this->db = $this->config->getDb();

        $this->role = $role;
        $this->address = $address;
        $this->session = $session;

        $this->uid = $this->getId();
    }

    /**
     * Adds a user
     * @param array $data
     * @return integer
     */
    public function add(array $data)
    {
        $result = null;
        $this->hook->attach('user.add.before', $data, $result, $this);

        if (isset($result)) {
            return (int) $result;
        }

        if (empty($data['name'])) {
            $data['name'] = strtok($data['email'], '@');
        }

        $data['created'] = $data['modified'] = GC_TIME;
        $data['hash'] = gplcart_string_hash($data['password']);
        $result = $data['user_id'] = $this->db->insert('user', $data);

        $this->setAddress($data);

        $this->hook->attach('user.add.after', $data, $result, $this);
        return (int) $result;
    }

    /**
     * Adds/updates addresses for the user
     * @param array $data
     * @return boolean
     */
    protected function setAddress(array $data)
    {
        if (empty($data['addresses'])) {
            return false;
        }

        foreach ($data['addresses'] as $address) {
            if (empty($address['address_id'])) {
                $address['user_id'] = $data['user_id'];
                $this->address->add($address);
            } else {
                $this->address->update($address['address_id'], $address);
            }
        }

        return true;
    }

    /**
     * Updates a user
     * @param integer $user_id
     * @param array $data
     * @return boolean     *
     */
    public function update($user_id, array $data)
    {
        $result = null;
        $this->hook->attach('user.update.before', $user_id, $data, $result, $this);

        if (isset($result)) {
            return (bool) $result;
        }

        $data['modified'] = GC_TIME;

        if (!empty($data['password'])) {
            $data['hash'] = gplcart_string_hash($data['password']);
        }

        if ($this->isSuperadmin($user_id)) {
            $data['status'] = 1;
        }

        $updated = $this->db->update('user', $data, array('user_id' => $user_id));
        $data['user_id'] = $user_id;
        $updated += (int) $this->setAddress($data);

        $result = $updated > 0;
        $this->hook->attach('user.update.after', $user_id, $data, $result, $this);
        return (bool) $result;
    }

    /**
     * Deletes a user
     * @param integer $user_id
     * @param bool $check
     * @return boolean
     */
    public function delete($user_id, $check = true)
    {
        $result = null;
        $this->hook->attach('user.delete.before', $user_id, $check, $result, $this);

        if (isset($result)) {
            return (bool) $result;
        }

        if ($check && !$this->canDelete($user_id)) {
            return false;
        }

        $result = (bool) $this->db->delete('user', array('user_id' => $user_id));

        if ($result) {
            $this->deleteLinked($user_id);
        }

        $this->hook->attach('user.delete.after', $user_id, $check, $result, $this);
        return (bool) $result;
    }

    /**
     * Deletes all database records related to the user ID
     * @param int $user_id
     */
    protected function deleteLinked($user_id)
    {
        $conditions = array('user_id' => $user_id);

        $this->db->delete('cart', $conditions);
        $this->db->delete('review', $conditions);
        $this->db->delete('history', $conditions);
        $this->db->delete('address', $conditions);
        $this->db->delete('wishlist', $conditions);
        $this->db->delete('rating_user', $conditions);
        $this->db->delete('dashboard', $conditions);
    }

    /**
     * Whether the user can be deleted
     * @param integer $user_id
     * @return boolean
     */
    public function canDelete($user_id)
    {
        if ($this->isSuperadmin($user_id)) {
            return false;
        }

        $sql = 'SELECT * FROM orders WHERE user_id=?';
        $result = $this->db->fetchColumn($sql, array($user_id));
        return empty($result);
    }

    /**
     * Whether the user is super admin
     * @param integer|null $user_id
     * @return boolean
     */
    public function isSuperadmin($user_id = null)
    {
        if (isset($user_id)) {
            return $this->getSuperadminId() == $user_id;
        }

        return $this->getSuperadminId() == $this->uid;
    }

    /**
     * Returns the current user ID from the session
     * @return integer
     */
    public function getId()
    {
        return (int) $this->getSession('user_id');
    }

    /**
     * Returns the current user role ID from the session
     * @return integer
     */
    public function getRoleId()
    {
        return (int) $this->getSession('role_id');
    }

    /**
     * Whether a user has an access to do something
     * @param string $permission
     * @param null|int $user_id
     * @return boolean
     */
    public function access($permission, $user_id = null)
    {
        if ($this->isSuperadmin($user_id)) {
            return true;
        }

        if ($permission === '__superadmin') {
            return false;
        }

        return in_array($permission, $this->getPermissions($user_id));
    }

    /**
     * Returns user permissions
     * @param null|integer $user_id
     * @return array
     */
    public function getPermissions($user_id = null)
    {
        static $permissions = array();

        if (!isset($user_id)) {
            $user_id = $this->uid;
        }

        if (isset($permissions[$user_id])) {
            return $permissions[$user_id];
        }

        $user = $this->get($user_id);

        if (empty($user['role_id'])) {
            return $permissions[$user_id] = array();
        }

        $role = array();
        if (isset($user['role_permissions'])) {
            $role['permissions'] = $user['role_permissions'];
        } else {
            $role = $this->role->get($user['role_id']);
        }

        if (empty($role['permissions'])) {
            return $permissions[$user_id] = array();
        }

        return $permissions[$user_id] = (array) $role['permissions'];
    }

    /**
     * Loads a user
     * @param integer $user_id
     * @return array
     */
    public function get($user_id)
    {
        $result = &gplcart_static("user.get.$user_id");

        if (isset($result)) {
            return (array) $result;
        }

        $this->hook->attach('user.get.before', $user_id, $result, $this);

        if (isset($result)) {
            return $result;
        }

        if (empty($user_id)) {
            return $result = array();
        }

        $list = $this->getList(array('user_id' => $user_id));

        $result = array();
        if (is_array($list) && count($list) == 1) {
            $result = reset($list);
        }

        $this->hook->attach('user.get.after', $user_id, $result, $this);
        return $result;
    }

    /**
     * Loads a user by an email
     * @param string $email
     * @return array
     */
    public function getByEmail($email)
    {
        $list = $this->getList(array('email' => $email));
        return is_array($list) && count($list) == 1 ? reset($list) : array();
    }

    /**
     * Returns the current user from the session
     * @param array|string $key
     * @return mixed
     */
    public function getSession($key = null)
    {
        $user = $this->session->get('user', array());

        if (isset($key)) {
            return gplcart_array_get($user, $key);
        }

        return $user;
    }

    /**
     * Returns an array of users or counts them
     * @param array $data
     * @return array|integer
     */
    public function getList(array $data = array())
    {
        $sql = 'SELECT u.*, r.redirect AS role_redirect, r.status AS role_status,'
                . 'r.name AS role_name, r.permissions AS role_permissions';

        if (!empty($data['count'])) {
            $sql = 'SELECT COUNT(u.user_id)';
        }

        $sql .= ' FROM user u'
                . ' LEFT JOIN role r ON(u.role_id=r.role_id)';

        $conditions = array();

        if (isset($data['user_id'])) {
            $sql .= ' WHERE u.user_id = ?';
            $conditions[] = (int) $data['user_id'];
        } else {
            $sql .= ' WHERE u.user_id IS NOT NULL';
        }

        if (isset($data['name'])) {
            $sql .= ' AND u.name LIKE ?';
            $conditions[] = "%{$data['name']}%";
        }

        if (isset($data['email'])) {
            $sql .= ' AND u.email = ?';
            $conditions[] = $data['email'];
        }

        if (isset($data['email_like'])) {
            $sql .= ' AND u.email LIKE ?';
            $conditions[] = "%{$data['email_like']}%";
        }

        if (isset($data['role_id'])) {
            $sql .= ' AND u.role_id = ?';
            $conditions[] = (int) $data['role_id'];
        }

        if (isset($data['store_id'])) {
            $sql .= ' AND u.store_id = ?';
            $conditions[] = (int) $data['store_id'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND u.status = ?';
            $conditions[] = (int) $data['status'];
        }

        $allowed_order = array('asc', 'desc');
        $allowed_sort = array('name', 'email', 'role_id',
            'store_id', 'status', 'created', 'user_id');

        if (isset($data['sort'])//
                && in_array($data['sort'], $allowed_sort)//
                && isset($data['order'])//
                && in_array($data['order'], $allowed_order)) {
            $sql .= " ORDER BY u.{$data['sort']} {$data['order']}";
        } else {
            $sql .= " ORDER BY u.modified DESC";
        }

        if (!empty($data['limit'])) {
            $sql .= ' LIMIT ' . implode(',', array_map('intval', $data['limit']));
        }

        if (!empty($data['count'])) {
            return (int) $this->db->fetchColumn($sql, $conditions);
        }

        $options = array(
            'index' => 'user_id',
            'unserialize' => array('data', 'role_permissions'));

        $list = $this->db->fetchAll($sql, $conditions, $options);
        $this->hook->attach('user.list', $list, $this);
        return $list;
    }

    /**
     * Generates a random password
     * @return string
     */
    public function generatePassword()
    {
        $hash = crypt(gplcart_string_random(), gplcart_string_random());
        return gplcart_string_encode($hash);
    }

    /**
     * Returns min and max allowed password length
     * @return array
     */
    public function getPasswordLength()
    {
        return array(
            $this->config->get('user_password_min_length', 8),
            $this->config->get('user_password_max_length', 255)
        );
    }

    /**
     * Whether the password matches the user's password stored in the database
     * @param string $password
     * @param array $user
     * @return boolean
     */
    public function passwordMatches($password, array $user)
    {
        if (empty($user['hash'])) {
            return false;
        }

        $expected = gplcart_string_hash($password, $user['hash'], 0);
        return gplcart_string_equals($user['hash'], $expected);
    }

    /**
     * Returns reset password lifespan in seconds
     * @return integer
     */
    public function getResetPasswordLifespan()
    {
        return (int) $this->config->get('user_reset_password_lifespan', 24 * 60 * 60);
    }

    /**
     * Returns UID for super-admin
     * @return int
     */
    public function getSuperadminId()
    {
        return (int) $this->config->get('user_superadmin', 1);
    }

}
