<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\controllers\frontend;

use gplcart\core\models\Page as PageModel;

/**
 * Handles incoming requests and outputs data related to pages
 */
class Page extends Controller
{

    /**
     * Page model instance
     * @var \gplcart\core\models\Page $page
     */
    protected $page;

    /**
     * An array of page data
     * @var array
     */
    protected $data_page = array();

    /**
     * @param PageModel $page
     */
    public function __construct(PageModel $page)
    {
        parent::__construct();

        $this->page = $page;
    }

    /**
     * Displays a page
     * @param integer $page_id
     */
    public function indexPage($page_id)
    {
        $this->setPage($page_id);
        $this->setTitleIndexPage();
        $this->setBreadcrumbIndexPage();
        $this->setHtmlFilterIndexPage();
        $this->setMetaIndexPage();

        $this->setData('page', $this->data_page);
        $this->setDataImagesIndexPage();

        $this->outputIndexPage();
    }

    /**
     * Sets a page data
     * @param integer $page_id
     */
    protected function setPage($page_id)
    {
        $this->data_page = $this->page->get($page_id);

        if (empty($this->data_page)) {
            $this->outputHttpStatus(404);
        }

        $this->controlAccessPage();
        $this->preparePage($this->data_page);
    }

    /**
     * Controls access to the page
     */
    protected function controlAccessPage()
    {
        if (empty($this->data_page['status']) && !$this->access('page')) {
            $this->outputHttpStatus(403);
        }

        if ($this->data_page['store_id'] != $this->store_id) {
            $this->outputHttpStatus(404);
        }
    }

    /**
     * Prepare an array of page data
     * @param array $page
     */
    protected function preparePage(array &$page)
    {
        $this->setItemImages($page, 'page', $this->image);
    }

    /**
     * Sets HTML filter on the page
     */
    protected function setHtmlFilterIndexPage()
    {
        $this->setHtmlFilter($this->data_page);
    }

    /**
     * Set meta tags on the page
     */
    protected function setMetaIndexPage()
    {
        $this->setMetaEntity($this->data_page);
    }

    /**
     * Sets the rendered images on the page
     */
    protected function setDataImagesIndexPage()
    {
        $options = array(
            'imagestyle' => $this->configTheme('image_style_page', 5)
        );

        $this->setItemThumb($this->data_page, $this->image, $options);
        $this->setData('images', $this->render('page/images', array('page' => $this->data_page)));
    }

    /**
     * Render and output the page
     */
    protected function outputIndexPage()
    {
        $this->output('page/content');
    }

    /**
     * Sets breadcrumbs on the page
     */
    protected function setBreadcrumbIndexPage()
    {
        $breadcrumb = array(
            'url' => $this->url('/'),
            'text' => $this->text('Home')
        );

        $this->setBreadcrumb($breadcrumb);
    }

    /**
     * Sets titles on the page
     */
    protected function setTitleIndexPage()
    {
        $this->setTitle($this->data_page['title']);
    }

}
