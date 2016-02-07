<?php
/* ---  kontrola jadra  --- */
if (!defined('_core')) {
    exit;
}

/* ---  priprava  --- */
if (isset($_GET['q'])) {
    $search_query = trim($_GET['q']);
    $root = (isset($_GET['root']) ? '1' : '0');
    $art = (isset($_GET['art']) ? '1' : '0');
    $post = (isset($_GET['post']) ? '1' : '0');
    $image = (isset($_GET['img']) ? '1' : '0');
} else {
    $search_query = '';
    $root = 1;
    $art = 1;
    $post = 1;
    $image = 0;
}

/* ---  modul  --- */
if (_template_autoheadings == 1) {
    $module .= "<h1>" . $_lang['mod.search'] . "</h1>";
}

$module .= "
<p class='bborder'>" . $_lang['mod.search.p'] . "</p>

<form action='index.php' method='get'>
<input type='hidden' name='m' value='search' />
" . _xsrfProtect() . "
<input type='text' name='q' class='inputmedium' value='" . _htmlStr($search_query) . "' /> <input type='submit' value='" . $_lang['mod.search.submit'] . "' /><br />
" . $_lang['mod.search.where'] . ":&nbsp;
<label><input type='checkbox' name='root' value='1'" . _checkboxActivate($root) . " /> " . $_lang['mod.search.where.root'] . "</label>&nbsp;
<label><input type='checkbox' name='art' value='1'" . _checkboxActivate($art) . " /> " . $_lang['mod.search.where.articles'] . "</label>&nbsp;
<label><input type='checkbox' name='post' value='1'" . _checkboxActivate($post) . " /> " . $_lang['mod.search.where.posts'] . "</label>&nbsp;
<label><input type='checkbox' name='img' value='1'" . _checkboxActivate($image) . " /> " . $_lang['mod.search.where.images'] . "</label>
</form>

";

/* ---  vyhledavani --- */
if ($search_query != '' && _xsrfCheck(true)) {
    if (mb_strlen($search_query) >= 3) {

        // priprava
        $search_query_sql = DB::esc('%' . $search_query . '%');
        $results = array(); // polozka: array(link, titulek, perex)
        $public = !_loginindicator;

        // funkce na skladani vyhledavaciho dotazu
        function _tmpSearchQuery($alias, $cols)
        {
            $output = '(';
            for ($i = 0, $last = (sizeof($cols) - 1); isset($cols[$i]); ++$i) {
                $output .= $alias . '.' . $cols[$i] . ' LIKE \'' . $GLOBALS['search_query_sql'] . '\'';
                if ($i !== $last) $output .= ' OR ';
            }
            $output .= ')';

            return $output;
        }

        // vyhledani stranek
        if ($root) {
            $q = DB::query('SELECT page.id,page.title,page.title_seo,page.intersectionperex FROM `' . _mysql_prefix . '-root` page WHERE visible=1 AND ' . ($public ? 'page.public=1 AND ' : '') . _tmpSearchQuery('page', array('title', 'title_seo', 'keywords', 'description', 'intersectionperex', 'content')) . ' LIMIT 20');
            while($r = DB::row($q)) {
                $results[] = array(_linkRoot($r['id'], $r['title_seo']), $r['title'], strip_tags($r['intersectionperex']));
            }
            DB::free($q);
        }

        // vyhledani clanku
        if ($art) {

            // zakladni dostaz
            $sql = 'SELECT art.id,art.title,art.title_seo,art.perex,cat1.title_seo AS cat_title_seo FROM `' . _mysql_prefix . '-articles` AS art';

            // joiny na kategorie
            for($i = 1; $i <= 3; ++$i) $sql .= ' LEFT JOIN `' . _mysql_prefix . '-root` AS cat' . $i . ' ON(art.home' . $i . '=cat' . $i . '.id)';

            // zakladni podminky
            $sql .= ' WHERE art.visible=1 AND art.confirmed=1' . ($public ? ' AND art.public=1' : '') . ' AND (cat1.visible=1 OR cat2.visible=1 OR cat3.visible=1)';

            // podminky kvuli verejnosti kategorie a jejich rozcestniku
            if ($public) $sql .= ' AND (cat1.public=1 OR cat2.public=1 OR cat3.public=1)';

            // podminky vyhledavani
            $sql .= ' AND ' . _tmpSearchQuery('art', array('title', 'title_seo', 'perex', 'keywords', 'description', 'content'));

            // vykonani a nacteni vysledku
            $q = DB::query($sql . 'ORDER BY time DESC LIMIT 30');
            while($r = DB::row($q)) {
                $results[] = array(_linkArticle($r['id'], $r['title_seo'], $r['cat_title_seo']), $r['title'], _cutStr(strip_tags($r['perex']), 255, false));
            }
            DB::free($q);

        }

        // vyhledani prispevku
        if ($post) {

            // zaklad dotazu
            $base_sql = 'SELECT post.id,post.home,post.xhome,post.text,post.subject,post.author,post.guest,post.time';
            $base_sql_from = ' FROM `' . _mysql_prefix . '-posts` AS post';

            // cyklus podle typu
            $types = array(1, 2, 3, 5);
            for ($i = 0; isset($types[$i]); ++$i) {

                // sestaveni dotazu
                switch ($types[$i]) {

                        // komentar sekce / prispevek knihy / prispevek tematu na foru
                    case 1:
                    case 3:
                    case 5:
                        $sql = $base_sql . ',page.title,page.title_seo' . (($types[$i] === 5) ? ',topic.subject AS topic_subject' : '') . $base_sql_from . ' JOIN `' . _mysql_prefix . '-root` AS page ON (post.home=page.id)';
                        if ($types[$i] === 5) $sql .= ' LEFT JOIN `' . _mysql_prefix . '-posts` AS topic ON(topic.type=5 AND topic.id=post.xhome)';
                        $sql .= ' WHERE post.type=' . $types[$i] . ' AND page.visible=1';
                        if ($public) $sql .= ' AND page.public=1';
                        break;

                        // komentar clanku
                    case 2:
                        $sql = $base_sql . ',art.title,art.title_seo,cat1.title_seo AS cat_title_seo' . $base_sql_from . ' JOIN `' . _mysql_prefix . '-articles` AS art ON (art.id=post.home)';
                        for($ii = 1; $ii <= 3; ++$ii) $sql .= ' LEFT JOIN `' . _mysql_prefix . '-root` AS cat' . $ii . ' ON(cat' . $ii . '.id=art.home' . $ii . ')';
                        $sql .= 'WHERE post.type=' . $types[$i] . ' AND art.visible=1 AND ' . ($public ? 'art.public=1 AND ' : '');
                        $sql .= '(cat1.visible=1 OR cat2.visible=1 OR cat3.visible=1)';
                        if ($public) $sql .= ' AND (cat1.public=1 OR cat2.public=1 OR cat3.public=1)';
                        break;

                }

                // vykonani dotazu
                $q = DB::query($sql . ' AND ' . _tmpSearchQuery('post', array('subject', 'text')) . ' ORDER BY id DESC LIMIT 15');
                while ($r = DB::row($q)) {

                    // nacteni titulku, odkazu a strany
                    $page = null;
                    $post_anchor = true;
                    switch ($types[$i]) {

                            // komentar sekce / prispevek knihy
                        case 1:
                        case 3:
                            $link = _linkRoot($r['home'], $r['title_seo']);
                            if ($r['subject'] === '' || $r['subject'] === '-') $title = $r['title'];
                            else $title = $r['subject'];
                            $page = _resultPagingGetItemPage(_commentsperpage, "posts", "id>" . $r['id'] . " AND type=" . $types[$i] . " AND xhome=-1 AND home=" . $r['home']);
                            break;

                            // komentar clanku
                        case 2:
                            $link = _linkArticle($r['home'], $r['title_seo'], $r['cat_title_seo']);
                            if ($r['subject'] === '' || $r['subject'] === '-') $title = $r['title'];
                            else $title = $r['subject'];
                            $page = _resultPagingGetItemPage(_commentsperpage, "posts", "id>" . $r['id'] . " AND type=2 AND xhome=-1 AND home=" . $r['home']);
                            break;

                            // prispevek na foru
                        case 5:
                            if ($r['xhome'] != -1) {
                                $link = 'index.php?m=topic&amp;id=' . $r['xhome'];
                                $page = _resultPagingGetItemPage(_commentsperpage, "posts", "id<" . $r['id'] . " AND type=5 AND xhome=" . $r['xhome'] . " AND home=" . $r['home']);
                                $title = $r['topic_subject'];
                            } else {
                                $link = 'index.php?m=topic&amp;id=' . $r['id'];
                                $title = $r['subject'];
                                $post_anchor = false;
                            }
                            break;

                    }

                    // sestaveni infa
                    if ($r['author'] == -1) $info = "<span class='post-author-guest'>" . $r['guest'] . '</span>';
                    else $info = _linkUser($r['author'], null, true, true);
                    $info .= ', ' . _formatTime($r['time']);

                    // pridani do vysledku
                    $results[] = array((isset($page) ? _addGetToLink($link, 'page=' . $page) : $link) . ($post_anchor ? '#post-' . $r['id'] : ''), $title, _cutStr(strip_tags(_parsePost($r['text'])), 255), $info);

                }
                DB::free($q);

            }

        }

        // vyhledani obrazku
        if ($image) {

            // zaklad dotazu
            $sql = 'SELECT img.id,img.prev,img.full,img.ord,img.home,img.title,gal.title AS gal_title,gal.title_seo,gal.var2 FROM `' . _mysql_prefix . '-images` AS img';

            // join na galerii
            $sql .= ' JOIN `' . _mysql_prefix . '-root` AS gal ON(gal.id=img.home)';

            // podminky
            $sql .= ' WHERE gal.visible=1';
            if ($public) $sql .= ' AND gal.public=1';
            $sql .= ' AND ' . _tmpSearchQuery('img', array('title'));

            // vykonani a nacteni vysledku
            $q = DB::query($sql . ' LIMIT 20');
            while ($r = DB::row($q)) {
                $link = _addGetToLink(_linkRoot($r['home'], $r['title_seo']), 'page=' . _resultPagingGetItemPage($r['var2'], "images", "ord<" . $r['ord'] . " AND home=" . $r['home']));
                $results[] = array($link, $r['gal_title'], (($r['title'] !== '') ? $r['title'] . '<br />' : '') . _galleryImage($r, 'search', 128, 128));
            }
            DB::free($q);

        }

        // extend
        _extend('call', 'mod.search.results', array(
            'results' => &$results,
            'query' => $search_query,
            'query_sql' => $search_query_sql,
        ));

        // vypis vysledku
        if (count($results) != 0) {
            foreach ($results as $item) {
                $module .= "
<h2 class='list-title'><a href='" . $item[0] . "'>" . $item[1] . "</a></h2>
<p class='list-perex'>" . $item[2] . "</p>
";
                if (isset($item[3])) $module .= "<div class='list-info'>" . $item[3] . "</div>\n";
            }
        } else {
            $module .= "<br />" . _formMessage(1, $_lang['mod.search.noresult']);
        }

    } else {
        $module .= "<br />" . _formMessage(2, $_lang['mod.search.minlength']);
    }
}
