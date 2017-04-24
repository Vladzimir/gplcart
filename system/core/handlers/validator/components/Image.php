<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\handlers\validator\components;

use gplcart\core\Handler;
use gplcart\core\models\Image as ImageModel;
use gplcart\core\handlers\validator\Component as ComponentValidator;

/**
 * Provides methods to validate image style data
 */
class Image extends ComponentValidator
{

    /**
     * Image model instance
     * @var \gplcart\core\models\Image $image
     */
    protected $image;

    /**
     * Constructor
     * @param ImageModel $image
     */
    public function __construct(ImageModel $image)
    {
        parent::__construct();

        $this->image = $image;
    }

    /**
     * Performs full image style data validation
     * @param array $submitted
     * @param array $options
     * @return boolean|array
     */
    public function style(array &$submitted, array $options = array())
    {
        $this->options = $options;
        $this->submitted = &$submitted;

        $this->validateImageStyle();
        $this->validateNameComponent();
        $this->validateStatusComponent();
        $this->validateActionsImageStyle();

        $this->unsetSubmitted('update');
        return $this->getResult();
    }

    /**
     * Validates an image style to be updated
     * @return boolean|null
     */
    protected function validateImageStyle()
    {
        $id = $this->getUpdatingId();

        if ($id === false) {
            return null;
        }

        $imagestyle = $this->image->getStyle($id);

        if (empty($imagestyle)) {
            $this->setErrorUnavailable('update', $this->language->text('Image style'));
            return false;
        }

        $this->setSubmitted('update', $imagestyle);
        return true;
    }

    /**
     * Validates image actions
     * @return boolean|null
     */
    public function validateActionsImageStyle()
    {
        $field = 'actions';
        $label = $this->language->text('Actions');
        $actions = $this->getSubmitted($field);

        if ($this->isUpdating() && !isset($actions)) {
            return null;
        }

        if (empty($actions)) {
            $this->setErrorRequired($field, $label);
            return false;
        }

        $modified = $errors = array();
        foreach ($actions as $line => $action) {

            $parts = array_map('trim', explode(' ', trim($action)));
            $action_id = array_shift($parts);
            $value = array_filter(explode(',', implode('', $parts)));

            if (!$this->validateActionImageStyle($action_id, $value)) {
                $errors[] = $line + 1;
                continue;
            }

            $modified[$action_id] = array('value' => $value, 'weight' => $line);
        }

        if (!empty($errors)) {
            $vars = array('@num' => implode(',', $errors));
            $error = $this->language->text('Error on line @num', $vars);
            $this->setError($field, $error);
        }

        if ($this->isError()) {
            return false;
        }

        $this->setSubmitted('actions', $modified);
        return true;
    }

    /**
     * Calls an appropriate validator method for the given action ID
     * @param string $action_id
     * @param array $value
     * @return boolean
     */
    protected function validateActionImageStyle($action_id, array &$value)
    {
        $handler = $this->image->getActionHandler($action_id);

        if (empty($handler)) {
            return false;
        }
        $callback = Handler::get($handler, null, 'validate');
        return call_user_func_array($callback, array(&$value));
    }

    /**
     * Validates "Crop" action
     * @param array $value
     * @return boolean
     */
    public function validateActionCropImageStyle(array $value)
    {
        return count(array_filter(array_slice($value, 0, 4), 'ctype_digit')) == 4;
    }

    /**
     * Validates "Resize", "Fit to ..." actions
     * @param array $value
     * @return boolean
     */
    public function validateActionResizeImageStyle(array $value)
    {
        return count($value) == 1 && is_numeric($value[0]);
    }

    /**
     * Validates "Thumbnail" action
     * @param array $value
     * @return boolean
     */
    public function validateActionThumbnailImageStyle(array $value)
    {
        return count(array_filter(array_slice($value, 0, 2), 'ctype_digit')) == 2;
    }

}