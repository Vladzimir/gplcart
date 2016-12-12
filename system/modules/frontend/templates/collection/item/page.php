<?php
/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 * 
 * To see available variables: <?php print_r(get_defined_vars()); ?>
 * To see the current controller object: <?php print_r($this); ?>
 * To call a controller method: <?php $this->exampleMethod(); ?>
 */
?>
<div class="page item col-md-12">
  <div class="title">
    <a href="<?php echo $this->escape($page['url']); ?>">
      <?php echo $this->truncate($this->escape($page['title']), 50); ?>
    </a>
  </div>
  <p><?php echo $this->escape(strip_tags($page['description'])); ?></p>
</div>
