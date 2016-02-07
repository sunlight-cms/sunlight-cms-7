<?php
/* ----  inicializace jadra  ---- */

require './require/load.php';
SL::init('./');

if (!_printart) {
    exit;
}

/* ----  vystup  ---- */

if (_publicAccess(!_notpublicsite) and isset($_GET['id'])) {

    $id = intval($_GET['id']);

    // nacteni dat clanku
    $query = DB::query("SELECT art.*,cat.title_seo AS cat_title_seo FROM `" . _mysql_prefix . "-articles` AS art JOIN `" . _mysql_prefix . "-root` AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
    if (DB::size($query) != 0) {

        // rozebrani dat, test pristupu
        $query = DB::row($query);
        $access = _articleAccess($query);
        $artlink = _linkArticle($id, $query['title_seo'], $query['cat_title_seo']);
        $url = _url . "/" . $artlink;
        define('_indexOutput_url', $artlink);

        // vypsani obsahu
        if ($access == 1) {

            // vlozeni zacatku hlavicky
            require _indexroot . "require/headstart.php";

?>
<link href="<?php echo _indexroot; ?>plugins/templates/<?php echo _template; ?>/style/print.css" type="text/css" rel="stylesheet" />
<link href="<?php echo _indexroot; ?>plugins/templates/<?php echo _template; ?>/style/system.css" type="text/css" rel="stylesheet" />
<title><?php echo $query['title'] . " " . _titleseparator . " " . _title; ?></title>
</head>

<body onload="setTimeout('this.print();', 500);">

<p id="informations"><?php echo "<strong>" . $_lang['global.source'] . ":</strong> <a href='" . $url . "'>" . $url . "</a>" . _template_listinfoseparator . "<strong>" . $_lang['article.posted'] . ":</strong> " . _formatTime($query['time']) . _template_listinfoseparator . "<strong>" . $_lang['article.author'] . ":</strong> " . _linkUser($query['author'], null, true, true); ?></p>

<h1><?php echo $query['title']; ?></h1>
<p><?php echo (isset($query['picture_uid']) ? "<img class='list-perex-image' src='" . _pictureStorageGet(_indexroot . 'pictures/articles/', null, $query['picture_uid'], 'jpg') . "' alt='" . $query['title'] . "' />" : '') . $query['perex']; ?></p>
<?php echo _parseHCM($query['content']); ?>

</body>
</html><?php

        }

    }

}
