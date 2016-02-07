<?php global $_lang; ?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $_lang['ipban.title']; ?></title>
</head>

<body>

<div style="text-align:center;">

<h1><?php echo $_lang['ipban.heading']; ?></h1>
<p><?php echo str_replace('*ip*', _userip, $_lang['ipban.text']); ?></p>

</div>

</body>
</html>
