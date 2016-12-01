<div class="panel panel-default">
  <div class="panel-body table-responsive">
    <?php if(empty($phpinfo)) { ?>
    <table class="table report-status">
      <?php foreach ($statuses as $status_id => $status) { ?>
      <tr class="<?php echo ((empty($status['status']) || is_array($status['status'])) && $status['severity'] !== 'info') ? $this->escape($status['severity']) : ''; ?>">
        <td class="col-md-3">
          <?php echo $this->escape($status['title']); ?>
          <?php if (!empty($status['description'])) { ?>
          <p class="small"><?php echo $this->xss($status['description']); ?></p>
          <?php } ?>
        </td>
        <td class="col-md-9">
          <?php if (empty($status['details'])) { ?>
          <?php echo $this->truncate($status['status']); ?>
          <?php } else { ?>
          <a data-toggle="collapse" href="#status-details-<?php echo $status_id; ?>">
            <?php echo $this->truncate($status['status']); ?>
          </a>
          <?php } ?>
          <?php if($status_id == 'php_version') { ?>
          <a href="<?php echo $this->url('', array('phpinfo' => 1)); ?>"><?php echo $this->text('Show info'); ?></a>
          <?php } ?>
          <?php if (!empty($status['details'])) { ?>
          <div class="collapse" id="status-details-<?php echo $status_id; ?>">
            <ul class="list-unstyled">
              <?php foreach ($status['details'] as $status_message) { ?>
              <li><?php echo $this->xss($status_message); ?></li>
              <?php } ?>
            </ul>
          </div>
          <?php } ?>
        </td>
      </tr>
      <?php } ?>
    </table>
    <?php } else { ?>
    <div class="phpinfo"><?php echo $phpinfo; ?></div>
    <?php } ?>
  </div>
</div>