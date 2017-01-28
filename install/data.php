<?php
/* --  kontrola jadra, priprava  -- */
if (!defined('_core')) {
    exit;
}
$dbver = _checkVersion('database', null, true);
$dbver = $dbver[0];
$sql_error = false;

/* --  spusteni sql dotazu  -- */

$sql = array();

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` tinytext NOT NULL,
  `title_seo` varchar(255) NOT NULL,
  `keywords` varchar(128) NOT NULL DEFAULT '',
  `description` varchar(128) NOT NULL DEFAULT '',
  `perex` text NOT NULL,
  `picture_uid` varchar(13) DEFAULT NULL,
  `content` longtext NOT NULL,
  `infobox` text NOT NULL,
  `author` int(11) NOT NULL,
  `home1` int(11) NOT NULL,
  `home2` int(11) NOT NULL,
  `home3` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `visible` tinyint(1) NOT NULL,
  `public` tinyint(1) NOT NULL,
  `comments` tinyint(1) NOT NULL,
  `commentslocked` tinyint(1) NOT NULL,
  `confirmed` tinyint(1) NOT NULL,
  `showinfo` tinyint(1) NOT NULL,
  `readed` int(11) NOT NULL,
  `rateon` tinyint(1) NOT NULL,
  `ratenum` int(11) NOT NULL,
  `ratesum` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `title_seo` (`title_seo`),
  KEY `author` (`author`),
  KEY `home1` (`home1`),
  KEY `home2` (`home2`),
  KEY `home3` (`home3`),
  KEY `time` (`time`),
  KEY `visible` (`visible`),
  KEY `public` (`public`),
  KEY `confirmed` (`confirmed`),
  KEY `ratenum` (`ratenum`),
  KEY `ratesum` (`ratesum`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-boxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ord` int(11) NOT NULL,
  `title` varchar(96) NOT NULL,
  `content` text NOT NULL,
  `visible` tinyint(1) NOT NULL,
  `public` tinyint(1) NOT NULL,
  `column` varchar(64) NOT NULL,
  `class` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ord` (`ord`),
  KEY `visible` (`visible`),
  KEY `public` (`public`),
  KEY `column` (`column`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4";

$sql[] = "INSERT INTO `" . _mysql_prefix . "-boxes` (`id`, `ord`, `title`, `content`, `visible`, `public`, `column`, `class`) VALUES
(1, 1, 'Menu', '[hcm]menu,1,20[/hcm]', 1, 1, '1', NULL),
(2, 2, 'Vyhledávání', '[hcm]search[/hcm]', 1, 1, '1', NULL),
(3, 3, '', '<br /><p class=\"center\"><a href=''http://sunlight.shira.cz/'' title=''redakční systém zdarma''>\r\n<img src=''http://sunlight.shira.cz/ikona.png'' alt=''sunlight.shira.cz'' style=''width:88px;height:31px;border:0;'' />\r\n</a></p>', 1, 1, '1', NULL)";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(32) NOT NULL,
  `descr` varchar(128) NOT NULL DEFAULT '',
  `level` int(11) NOT NULL,
  `icon` varchar(16) NOT NULL,
  `color` varchar(16) NOT NULL DEFAULT '',
  `blocked` tinyint(1) NOT NULL,
  `reglist` tinyint(1) NOT NULL,
  `administration` tinyint(1) NOT NULL,
  `adminsettings` tinyint(1) NOT NULL,
  `adminusers` tinyint(1) NOT NULL,
  `admingroups` tinyint(1) NOT NULL,
  `admincontent` tinyint(1) NOT NULL,
  `adminsection` tinyint(1) NOT NULL,
  `admincategory` tinyint(1) NOT NULL,
  `adminbook` tinyint(1) NOT NULL,
  `adminseparator` tinyint(1) NOT NULL,
  `admingallery` tinyint(1) NOT NULL,
  `adminlink` tinyint(1) NOT NULL,
  `adminintersection` tinyint(1) NOT NULL,
  `adminforum` tinyint(1) NOT NULL,
  `adminpluginpage` tinyint(1) NOT NULL,
  `adminart` tinyint(1) NOT NULL,
  `adminallart` tinyint(1) NOT NULL,
  `adminchangeartauthor` tinyint(1) NOT NULL,
  `adminconfirm` tinyint(1) NOT NULL,
  `adminneedconfirm` tinyint(1) NOT NULL,
  `adminpoll` tinyint(1) NOT NULL,
  `adminpollall` tinyint(1) NOT NULL,
  `adminsbox` tinyint(1) NOT NULL,
  `adminbox` tinyint(1) NOT NULL,
  `adminfman` tinyint(1) NOT NULL,
  `adminfmanlimit` tinyint(1) NOT NULL,
  `adminfmanplus` tinyint(1) NOT NULL,
  `adminhcmphp` tinyint(1) NOT NULL,
  `adminbackup` tinyint(1) NOT NULL,
  `adminrestore` tinyint(1) NOT NULL,
  `adminmassemail` tinyint(1) NOT NULL,
  `adminbans` tinyint(1) NOT NULL,
  `adminposts` tinyint(1) NOT NULL,
  `changeusername` tinyint(1) NOT NULL,
  `postcomments` tinyint(1) NOT NULL,
  `unlimitedpostaccess` tinyint(1) NOT NULL,
  `locktopics` tinyint(1) NOT NULL,
  `stickytopics` tinyint(1) NOT NULL,
  `movetopics` tinyint(1) NOT NULL,
  `artrate` tinyint(1) NOT NULL,
  `pollvote` tinyint(1) NOT NULL,
  `selfdestruction` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `level` (`level`),
  KEY `blocked` (`blocked`),
  KEY `reglist` (`reglist`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=11";

$sql[] = "INSERT INTO `" . _mysql_prefix . "-groups` (`id`, `title`, `descr`, `level`, `icon`, `color`, `blocked`, `reglist`, `administration`, `adminsettings`, `adminusers`, `admingroups`, `admincontent`, `adminsection`, `admincategory`, `adminbook`, `adminseparator`, `admingallery`, `adminlink`, `adminintersection`, `adminforum`, `adminpluginpage`, `adminart`, `adminallart`, `adminchangeartauthor`, `adminconfirm`, `adminneedconfirm`, `adminpoll`, `adminpollall`, `adminsbox`, `adminbox`, `adminfman`, `adminfmanlimit`, `adminfmanplus`, `adminhcmphp`, `adminbackup`, `adminrestore`, `adminmassemail`, `adminbans`, `adminposts`, `changeusername`, `postcomments`, `unlimitedpostaccess`, `locktopics`, `stickytopics`, `movetopics`, `artrate`, `pollvote`, `selfdestruction`) VALUES
(1, 'Hlavní administrátoři', '', 10000, 'redstar.png', '', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1),
(2, 'Neregistrovaní', '', 0, '', '', 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 1),
(3, 'Čtenáři', '', 1, '', '', 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 1),
(5, 'Administrátoři', '', 1000, 'orangestar.png', '', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 1, 0, 1, 1, 1, 1, 1, 0, 0, 1, 1, 0),
(10, 'Moderátoři', '', 600, 'greenstar.png', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(9, 'Redaktoři', '', 500, 'bluestar.png', '', 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 0)";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `home` int(11) NOT NULL,
  `ord` int(11) NOT NULL,
  `title` varchar(64) NOT NULL,
  `prev` tinytext NOT NULL,
  `full` tinytext NOT NULL,
  `in_storage` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `home` (`home`),
  KEY `full` (`full`(8)),
  KEY `in_storage` (`in_storage`),
  KEY `ord` (`ord`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-iplog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(15) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `time` int(11) NOT NULL,
  `var` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `type` (`type`),
  KEY `time` (`time`),
  KEY `var` (`var`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-pm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` int(11) NOT NULL,
  `sender_readtime` int(11) NOT NULL DEFAULT '0',
  `sender_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `receiver` int(11) NOT NULL,
  `receiver_readtime` int(11) NOT NULL DEFAULT '0',
  `receiver_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sender` (`sender`),
  KEY `receiver` (`receiver`),
  KEY `update_time` (`update_time`),
  KEY `sender_deleted` (`sender_deleted`),
  KEY `receiver_deleted` (`receiver_deleted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-polls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author` int(11) NOT NULL,
  `question` varchar(96) NOT NULL,
  `answers` text NOT NULL,
  `locked` tinyint(1) NOT NULL,
  `votes` tinytext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `author` (`author`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) NOT NULL,
  `home` int(11) NOT NULL,
  `xhome` int(11) NOT NULL,
  `subject` varchar(48) NOT NULL,
  `text` text NOT NULL,
  `author` int(11) NOT NULL,
  `guest` varchar(24) NOT NULL,
  `time` int(11) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `bumptime` int(11) NOT NULL DEFAULT '0',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `flag` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bumptime` (`bumptime`),
  KEY `type` (`type`),
  KEY `home` (`home`),
  KEY `xhome` (`xhome`),
  KEY `author` (`author`),
  KEY `time` (`time`),
  KEY `sticky` (`sticky`),
  KEY `flag` (`flag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-redir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `old` varchar(255) NOT NULL,
  `new` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `old` (`old`),
  KEY `active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-root` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(96) NOT NULL,
  `title_seo` varchar(255) NOT NULL,
  `keywords` varchar(128) NOT NULL DEFAULT '',
  `description` varchar(128) NOT NULL DEFAULT '',
  `type` tinyint(4) NOT NULL,
  `type_idt` varchar(16) DEFAULT NULL,
  `intersection` int(11) NOT NULL,
  `intersectionperex` text NOT NULL,
  `ord` float NOT NULL,
  `content` longtext NOT NULL,
  `visible` tinyint(1) NOT NULL,
  `public` tinyint(1) NOT NULL,
  `level` int(11) NOT NULL DEFAULT '0',
  `autotitle` tinyint(1) NOT NULL DEFAULT '0',
  `events` varchar(255) DEFAULT NULL,
  `var1` int(11) NOT NULL,
  `var2` int(11) NOT NULL,
  `var3` int(11) NOT NULL,
  `var4` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `title_seo` (`title_seo`),
  KEY `level` (`level`),
  KEY `type` (`type`),
  KEY `intersection` (`intersection`),
  KEY `ord` (`ord`),
  KEY `visible` (`visible`),
  KEY `public` (`public`),
  KEY `autotitle` (`autotitle`),
  KEY `var1` (`var1`),
  KEY `var2` (`var2`),
  KEY `var3` (`var3`),
  KEY `var4` (`var4`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2";

$sql[] = "INSERT INTO `" . _mysql_prefix . "-root` (`id`, `title`, `title_seo`, `keywords`, `description`, `type`, `type_idt`, `intersection`, `intersectionperex`, `ord`, `content`, `visible`, `public`, `level`, `autotitle`, `events`, `var1`, `var2`, `var3`, `var4`) VALUES
(1, 'Úvod', 'index', '', '', 1, NULL, -1, '', 1, '<p>Instalace redakčního systému SunLight CMS 7.5.4 byla úspěšně dokončena!<br />Nyní se již můžete <a href=\"admin/index.php?_formData[username]=admin\">přihlásit do administrace</a> (heslo bylo zvoleno při instalaci).</p>\r\n\r\n<p>Podporu, diskusi a doplňky ke stažení naleznete na oficiálních webových stránkách <a href=\"http://sunlight.shira.cz/\">sunlight.shira.cz</a>.</p>', 1, 1, 0, 1, NULL, 0, 0, 0, 0)";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-sboxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(64) NOT NULL,
  `locked` tinyint(1) NOT NULL,
  `public` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-settings` (
  `var` varchar(24) NOT NULL,
  `val` text NOT NULL,
  PRIMARY KEY (`var`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$sql[] = "INSERT INTO `" . _mysql_prefix . "-settings` (`var`, `val`) VALUES
('postsendexpire', '50'),
('pollvoteexpire', '604800'),
('artreadexpire', '18000'),
('maxloginexpire', '900'),
('maxloginattempts', '20'),
('pagingmode', '2'),
('profileemail', '0'),
('wysiwyg', '0'),
('captcha', '1'),
('template', 'default'),
('title', " . DB::val($title) . "),
('description', " . DB::val($descr) . "),
('commentsperpage', '10'),
('smileys', '1'),
('postadmintime', '172800'),
('keywords', " . DB::val($keywords) . "),
('adminscheme', '0'),
('dbversion', " . DB::val($dbver) . "),
('atreplace', '[zavinac]'),
('bbcode', '1'),
('defaultgroup', '3'),
('mailerusefrom', '0'),
('showpages', '4'),
('ulist', '0'),
('registration', '1'),
('language', 'default'),
('modrewrite', '0'),
('titleseparator', '-'),
('url', " . DB::val($url) . "),
('notpublicsite', '0'),
('comments', '1'),
('artrateexpire', '604800'),
('lightbox', '1'),
('rss', '1'),
('messages', '1'),
('messagesperpage', '30'),
('search', '1'),
('banned', ''),
('author', ''),
('titletype', '2'),
('adminlinkprivate', '0'),
('language_allowcustom', '0'),
('lostpass', '1'),
('registration_grouplist', '0'),
('favicon', '0'),
('.rules', ''),
('printart', '1'),
('extratopicslimit', '12'),
('rsslimit', '30'),
('sboxmemory', '20'),
('ratemode', '2'),
('time_format', 'j.n.Y G:i'),
('uploadavatar', '1'),
('galuploadresize_w', '750'),
('galuploadresize_h', '565'),
('codemirror', '1'),
('show_avatars', '0'),
('accactexpire', '1200'),
('registration_confirm', '0'),
('sysmail', ''),
('lostpassexpire', '1800'),
('cacheid', '0'),
('.admin_index_custom', ''),
('.admin_index_custom_pos', '1'),
('index_page_id', '1'),
('adminscheme_mode', '0'),
('extend_enabled', '1'),
('article_pic_w', '200'),
('article_pic_h', '200'),
('topic_hot_ratio', '20'),
('install_check', '1'),
('ajaxfm', '0'),
('proxy_mode', '0'),
('cron_auto',  '1'),
('.cron_auth',  ''),
('.cron_times', 'a:0:{}'),
('maintenance_interval',  '259200'),
('thumb_cleanup_threshold',  '604800'),
('thumb_touch_threshold',  '43200')";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-user-activation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(23) NOT NULL,
  `expire` int(11) NOT NULL,
  `group` int(11) NOT NULL,
  `username` varchar(24) NOT NULL,
  `password` varchar(32) NOT NULL,
  `salt` varchar(8) NOT NULL,
  `massemail` tinyint(1) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `email` tinytext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`),
  KEY `expire` (`expire`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

$sql[] = "CREATE TABLE `" . _mysql_prefix . "-users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group` int(11) NOT NULL,
  `levelshift` tinyint(1) NOT NULL,
  `username` varchar(24) NOT NULL,
  `publicname` varchar(24) NOT NULL,
  `password` varchar(32) NOT NULL,
  `salt` varchar(8) NOT NULL,
  `logincounter` int(11) NOT NULL,
  `registertime` int(11) NOT NULL,
  `activitytime` int(11) NOT NULL,
  `blocked` tinyint(1) NOT NULL,
  `massemail` tinyint(1) NOT NULL,
  `wysiwyg` tinyint(1) NOT NULL,
  `language` varchar(12) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `email` tinytext NOT NULL,
  `avatar` varchar(13) DEFAULT NULL,
  `avatar_mode` tinyint(4) NOT NULL DEFAULT '0',
  `web` tinytext NOT NULL,
  `skype` tinytext NOT NULL,
  `msn` tinytext NOT NULL,
  `icq` int(11) NOT NULL,
  `jabber` tinytext NOT NULL,
  `note` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group` (`group`),
  KEY `logincounter` (`logincounter`),
  KEY `registertime` (`registertime`),
  KEY `activitytime` (`activitytime`),
  KEY `blocked` (`blocked`),
  KEY `massemail` (`massemail`),
  KEY `username` (`username`(4)),
  KEY `email` (`email`(4)),
  KEY `publicname` (`publicname`(4))
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2";

$sql[] = "INSERT INTO `" . _mysql_prefix . "-users` (`id`, `group`, `levelshift`, `username`, `publicname`, `password`, `salt`, `logincounter`, `registertime`, `activitytime`, `blocked`, `massemail`, `wysiwyg`, `language`, `ip`, `email`, `avatar`, `avatar_mode`, `web`, `skype`, `msn`, `icq`, `jabber`, `note`) VALUES
(0, 1, 1, 'admin', 'Admin', " . DB::val($pass[0]) . ", " . DB::val($pass[1]) . ", 0, " . time() . ", " . time() . ", 0, 1, 1, '', '', " . DB::val($email) . ", NULL, 0, '', '', '', 0, '', '')";

foreach ($sql as $line) {
    DB::query($line, true);
    if (DB::error() != false) {
        $sql_error = DB::error();
        break;
    }
}
