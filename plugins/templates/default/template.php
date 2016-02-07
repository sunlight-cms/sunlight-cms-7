<?php if (!defined('_core')) exit; ?><!DOCTYPE html>
<html>
<head>
<?php _templateHead() ?>
</head>

<body>

<div id="header">
    <h2><a href="./"><?php echo _title ?></a></h2>
    <h3><?php echo _description ?></h3>
    <?php _templateUserMenu() ?>
</div>

<div id="content">

    <div id="colOne">
    <?php _templateBoxes() ?>
    </div>

    <div id="colTwo"><div class="bg2">
    <?php _templateContent() ?>
    </div></div>

</div>

<div id="footer">
    <p><a href="http://www.freecsstemplates.org/">Free CSS Templates</a> <?php _templateLinks(true) ?></p>
</div>

</body>
</html>
