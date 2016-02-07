<?php
/* ---  inicializace jadra  --- */
require '../../require/load.php';
define('_header', 'Content-type: application/javascript; charset=UTF-8');
define('_no_session', true);
SL::init('../../');

// gzip komprese, pokud je dostupna a neni jiz pouzita
if (function_exists('ob_gzhandler') && 'ob_gzhandler' !== ini_get('output_handler')) {
    ob_start('ob_gzhandler');
}

// cache
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 604800) . " GMT");

?>//<script>

/**
 * Systemove funkce
 */

//soub. manazer - zaskrtnout vse, odskrtnout vse, invertovat
function _sysFmanSelect(number, action)
{
var tmp=1;
  while (tmp<=number) {
    switch (action) {
    case 1: document.filelist['f'+tmp].checked = true; break;
    case 2: document.filelist['f'+tmp].checked = false; break;
    case 3: document.filelist['f'+tmp].checked = !document.filelist['f'+tmp].checked; break;
    }
  tmp+=1;
  }
return false;
}

//soub. manazer - presunout vybrane
function _sysFmanMoveSelected()
{
    var newdir=prompt("<?php echo $_lang['admin.fman.selected.move.prompt']; ?>:", '');
    if (newdir!="" && newdir!=null) {
    document.filelist.action.value='move';
    document.filelist.param.value=newdir;
    document.filelist.submit();
    }
}

//soub. manazer - smazat vybrane
function _sysFmanDeleteSelected()
{
    if (confirm("<?php echo $_lang['admin.fman.selected.delete.confirm']; ?>")) {
    document.filelist.action.value='deleteselected';
    document.filelist.submit();
    }
}

//soub. manazer - pridat vyber do galerie
function _sysFmanAddSelectedToGallery()
{
    document.filelist.action.value='addtogallery_showform';
    document.filelist.submit();
}

//soub. manazer - upload souboru
var _totalfiles=1;
function _sysFmanAddFile()
{
    var newfile=document.createElement('span');
    newfile.id="file"+_totalfiles;
    newfile.innerHTML="<br /><input type='file' name='upf"+_totalfiles+"[]' multiple='multiple' /> <a href=\"#\" onclick=\"return _sysFmanRemoveFile("+_totalfiles+");\"><?php echo $_lang['global.cancel']; ?></a>";
    document.getElementById("fmanFiles").appendChild(newfile);
    _totalfiles+=1;

    return false;
}

function _sysFmanRemoveFile(id)
{
    document.getElementById("fmanFiles").removeChild(document.getElementById("file"+id));
}

//galerie - prochazeni slozek
function _sysGalBrowse(path)
{
    gal_currentpath=path;
    $('#gallery-browser').load(
        'remote/galbrowser.php',
        'dir='+encodeURIComponent(path),
        function(){ fancybox_scan(); }
    );

    return false;
}

//galerie - vlozeni obrazku
function _sysGalSelect(imgpath)
{
    gal_dialog_imgpath=imgpath;
    if (!document.addform.autoprev.checked) {
        dialog=document.getElementById('gallery-browser-dialog');
        if (dialog!=null) {document.body.removeChild(dialog);}
        browser=document.getElementById('gallery-browser');
        browser_pos=_sysFindPos(browser);
        dialog=document.createElement('div');
        dialog.id='gallery-browser-dialog';
        dialog.style.left=browser_pos[0]+(browser.offsetWidth-350)/2+'px';
        dialog.style.top=browser_pos[1]+(browser.offsetHeight-48)/2+'px';
        dialog.innerHTML="<div><span><?php echo $_lang['admin.content.manageimgs.insert.browser.useas']; ?>:</span><a href='#' onclick=\"return _sysGalSelectButton(0);\"><?php echo $_lang['admin.content.manageimgs.full']; ?></a><a href='#' onclick=\"return _sysGalSelectButton(1);\"><?php echo $_lang['admin.content.manageimgs.prev']; ?></a><a href='#' onclick=\"return _sysGalSelectButton(2);\"><?php echo $_lang['global.cancel2']; ?></a></div>";
        document.body.appendChild(dialog);
    } else {
        _sysGalSelectButton(0);
    }

    return false;
}

//galerie - tlacitko dialogu
function _sysGalSelectButton(n)
{
    switch (n) {
        case 0:
        document.addform.full.value=gal_dialog_imgpath;
        break;

        case 1:
        if (document.addform.autoprev.checked) {_sysDisableField(false, 'addform', 'prev'); document.addform.autoprev.checked=false;}
        document.addform.prev.value=gal_dialog_imgpath;
        break;
    }
    dialog=document.getElementById('gallery-browser-dialog');
    if (dialog!=null) {document.body.removeChild(dialog);}

    return false;
}

//galerie - zachovani cesty po odeslani formulare
function _sysGalTransferPath(form)
{
  if (typeof(window['gal_currentpath'])!='undefined') {
    form.action=form.action+'&browserpath='+escape(gal_currentpath);
  }
}

//najit pozici prvku
function _sysFindPos(obj)
{
    var curleft = curtop = 0;
    if (obj.offsetParent) {
        do {
           curleft += obj.offsetLeft;
           curtop += obj.offsetTop;
        } while (obj = obj.offsetParent);
    }

    return [curleft,curtop];
}
