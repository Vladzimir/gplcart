<?php if (!empty($images)) { ?>
<div class="panel panel-default">
  <div class="panel-body">
    <div class="row section">
      <div class="col-md-2"><?php echo $images; ?></div>
      <?php if (!empty($category['description_1'])) { ?>
      <div class="col-md-10"><?php echo $this->xss($category['description_1']); ?></div>
      <?php } ?>
    </div>
  </div>
</div>
<?php } ?>
<?php if (!empty($children)) { ?>
<div class="panel panel-default">
  <div class="panel-body">
    <?php echo $children; ?>
  </div>
</div> 
<?php } ?>
<div class="panel panel-default">
  <div class="panel-body">
    <?php if (!empty($products)) { ?>
    <?php echo $navbar; ?>
    <?php echo $products; ?>
    <?php if (!empty($pager)) { ?>
    <div class="row">
      <div class="col-md-12 text-right">
        <?php echo $pager; ?>
      </div>
    </div>
    <?php } ?>
    <?php } else { ?>
    <div class="row">
      <div class="col-md-12">
        <?php echo $this->text('This category has no products yet'); ?>
      </div>
    </div>
    <?php } ?>
    <?php if (!empty($category['description_2'])) { ?>
    <div class="row section description-2">
      <div class="col-md-12">
        <?php echo $category['description_2']; ?>
      </div>
    </div>
    <?php } ?>
  </div>
</div>