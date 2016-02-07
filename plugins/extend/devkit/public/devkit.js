$(document).ready(function () {

    var toolbarHeight = 35;


    /* ----- body padding ----- */
    var body = $('body');
    if (parseInt(body.css('paddingBottom')) < toolbarHeight || parseInt(body.css('marginBottom')) < toolbarHeight) {
        body.css('paddingBottom', toolbarHeight + 'px');
    }

    /* ----- toggleable content ----- */

    var currentContent = null;

    $('#devkit-toolbar > div.devkit-toggleable').click(function () {
        var content = $(this).next('div.devkit-content');
        if (content.is(':visible')) {
            hideContent(content);
        } else {
            showContent(content);
        }

        return false;
    });

    $('#devkit-toolbar .devkit-selectable').focus(function () {
        var selectable = $(this);
        setTimeout(function () { selectable.select(); }, 100);
    });

    function showContent(content)
    {
        if (null !== currentContent && content.get(0) !== currentContent.get(0)) {
            currentContent.hide()
        }
        content.show();
        currentContent = content;
        updateContentHeight();
    }

    function hideContent(content)
    {
        content.hide();
        currentContent = null;
    }

    function updateContentHeight()
    {
        if (null !== currentContent) {
            currentContent.height($(window).height() - toolbarHeight);
        }
    }

    $(window).resize(updateContentHeight);


    /* ----- hide/show content ----- */

    $('#devkit-toolbar div.devkit-hideshow').click(function () {
        var content = $(this).next('div.devkit-hideshow-target');
        if (content.length > 0) {
            if (content.is(':animated')) {
                content.stop(true, true);
            }
            if (content.is(':visible')) {
                content.slideUp(200);
            } else {
                content.slideDown(200);
            }
        }
    });


    /* ----- close button ----- */

    $('#devkit-toolbar > div.devkit-close').click(function () {
        $('#devkit-toolbar').remove();
    });

});