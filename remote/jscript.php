<?php

/* ---  inicializace jadra  --- */
require '../require/load.php';
define('_header', 'Content-type: application/javascript; charset=UTF-8');
define('_no_session', true);
SL::init('../');

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

//otevreni okna
function _sysOpenWindow(url, width, height)
{
    return !window.open(url, "_blank", "width="+width+",height="+height+",toolbar=0,location=0,status=0,menubar=0,resizable=1,scrollbars=1");
}

//script loader
function _sysScriptLoader(url)
{
    //smazani predchoziho loaderu
    var head=document.getElementsByTagName('head')[0];
    var dataLoader=document.getElementById('scriptLoader');
    if (dataLoader) {head.removeChild(dataLoader);}

    //vytvoreni noveho elementu script
    var script=document.createElement('script');
    script.id='scriptLoader';
    script.src =url+'&r='+Math.random();

    //vlozeni skriptu
    head.appendChild(script);
}

//vypnuti prvku formulare
function _sysDisableField(checked, form, field)
{
    document[form][field].disabled = checked;
}

//systemova zprava
function _sysAlert(id)
{
    switch (id) {
    case 1: text="<?php echo $_lang['javascript.alert.someempty']; ?>"; break;
    case 2: text="<?php echo $_lang['javascript.alert.toolong']; ?>"; break;
    }
    alert(text);
}

//potvrzeni
function _sysConfirm()
{
return confirm("<?php echo $_lang['javascript.confirm']; ?>");
}

//nahrazeni znaku zavinace
function _sysMai_lto(f)
{
    var re = "<?php echo _atreplace; ?>";
    var addr = f.innerHTML.replace(re,'@');
    f.href = 'mai'+'lt'+'o:'+addr;

    return true;
}

//pridani smajlu
function _sysAddSmiley(fid, aid, id)
{
    // get textarea, set focus
    var txtarea = $(document[fid][aid]);
    txtarea.focus();

    // insert text
    txtarea.replaceSelectedText(' *'+id+'* ');

    return false;
}

//vlozeni bbcode zagu
function _sysAddBBCode(fid, aid, tag, pair)
{
    // get textarea, set focus
    var txtarea = $(document[fid][aid]);
    txtarea.focus();

    var text = txtarea.extractSelectedText(); // get selected text
    var text = '['+tag+']'+(pair ? text+'[/'+tag+']' : ''); // process text

    // insert text
    txtarea.insertText(text, txtarea.getSelection().start, true);
    if (pair) {
        var pos = txtarea.getSelection().start - 3 - tag.length;
        txtarea.setSelection(pos, pos);
    }

    return false;
}

//omezeni textarea
function _sysLimitTextArea(area, limit)
{
    var text = $(area).val();
    if (text.length > limit) {
        $(area).val(text.substr(0, limit));
        $(area).focus();
        area.scrollTop = area.scrollHeight;
    }
}

//nahled prispevku
function _sysPostPreview(button, formName, areaName)
{
    var form = document[formName];
    var area = form[areaName];
    var container = $(form).children('p.post-form-preview');

    if (1 !== container.length) {
        // cara
        var hr = document.createElement('div');
        hr.className = 'hr';
        $(hr).appendTo(form);

        // kontejner
        container = document.createElement('p');
        container.className = 'post-form-preview';
        container = $(container).appendTo(form);
    } else {
        container.empty();
    }

    $(button).attr('disabled', true);
    $(document.createTextNode('<?php echo $_lang['global.loading'] ?>')).appendTo(container);

    container.load(
        sl_indexroot+'postprev.php',
        {content: $(area).val()},
        function(){
            $(button).attr('disabled', false);
        }
    );
}

<?php

$jslibPaths = array(
    _indexroot . 'remote/jslib/jquery.js',
    _indexroot . 'remote/jslib/rangyinputs.js',
    _indexroot . 'remote/jslib/swfobject.js',
);
echo _extend('buffer', 'sys.jslib', array('paths' => &$jslibPaths));

foreach ($jslibPaths as $jslibPath) {
    include $jslibPath;
    echo "\n";
}
