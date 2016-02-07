<?php
/*---[ XList 1.3 by ShiraNai7  ]---*/

/*--- kontrola jadra ---*/
if (!defined('_core')) {
    exit;
}

/*--- funkce patrici modulu ---*/
function _tmp_hcm_xlistReplaceParamTags($input)
{
    $data = $GLOBALS['_hcm_xlist_data'];

    return str_replace(array("@filename", "@name", "@dir"), array($data[2], $data[3], $data[1]), $input);
}

function _tmp_hcm_xlistReplaceTemplateMatch($match)
{
    list(SL::$hcmUid, $rdirs, $item, $item_noext, $counter, $count, $end) = $GLOBALS['_hcm_xlist_data'];
    $params = _parseStr($match[1]);
    $return = "";
    switch ($params[0]) {
        case "dir":
            $return = $rdirs;
            break;
        case "link":
            $return = _htmlStr($rdirs . $item);
            break;
        case "link64":
            $return = urlencode(base64_encode($rdirs . $item));
            break;
        case "text64":
            if (isset($params[1])) {
                $return = urlencode(base64_encode(_tmp_hcm_xlistReplaceParamTags($params[1])));
            }
            break;
        case "name":
            $return = $item_noext;
            break;
        case "filename":
            $return = $item;
            break;
        case "filesize":
            if (isset($params[1])) {
                $fname = $rdirs . _tmp_hcm_xlistReplaceParamTags($params[1]);
            } else {
                $fname = $rdirs . $item;
            }
            $return = round(@filesize($fname) / 1024);
            break;
        case "filetime":
            if (isset($params[1])) {
                $fname = $rdirs . _tmp_hcm_xlistReplaceParamTags($params[1]);
            } else {
                $fname = $rdirs . $item;
            }
            $return = _formatTime(@filemtime($fname));
            break;
        case "uid":
            $return = SL::$hcmUid;
            break;
        case "preg":
            if (count($params) == 4) {
                $return = @preg_replace($params[1], $params[2], _tmp_hcm_xlistReplaceParamTags($params[3]));
            }
            break;
        case "data":
            if (isset($params[1])) {
                $fname = $rdirs . _tmp_hcm_xlistReplaceParamTags($params[1]) . ".txt";
                if (@file_exists($fname)) {
                    $return = @file_get_contents($fname);
                }
            }
            break;
        case "step":
            if (count($params) > 2 and $params[1] != 0) {
                $divide = (($counter + 1) / $params[1]);
                if ($counter != $end and (intval($divide) == $divide)) {
                    $return = $params[2];
                } elseif (isset($params[3]) and $counter != $end) {
                    $return = $params[3];
                } elseif ($counter == $end and isset($params[4])) {
                    $return = $params[4];
                }
            }
            break;
    }

    return $return;
}

/*--- definice funkce modulu ---*/
function _HCM_xlist($adresar = "", $maska_souboru = "", $razeni = 0, $strankovani = 0, $maska = "", $zobrazeni_strankovani = null, $kod_pred = null, $kod_za = null)
{
    //nacteni parametru
    $output = "";
    $rdir = _removeSlashesFromEnd($adresar);
    $rdirs = $rdir . "/";
    $rmask = $maska_souboru;
    if ($rmask == "*") {
        $rmask = 1;
    } elseif ($rmask == "%") {
        $rmask = 2;
    } else {
        $rmask = explode(";", $rmask);
    }
    $rsort = $razeni;
    $rpaging = intval($strankovani);
    if ($rpaging <= 0) {
        $rpaging = false;
    }
    $rtemplate = $maska;

    //nepovinne
    if (isset($zobrazeni_strankovani)) {
        $rpaging_pos = intval($zobrazeni_strankovani);
        if ($rpaging_pos < 0 or $rpaging_pos > 3) {
            $rpaging_pos = 0;
        }
    } else {
        $rpaging_pos = 0;
    }

    if (isset($kod_pred)) {
        $rcover_top = $kod_pred . "\n";
    } else {
        $rcover_top = "";
    }
    if ($rpaging != false) {
        $rcover_top = "<div class='anchor'><a name='hcm_xlist" . SL::$hcmUid . "'></a></div>\n" . $rcover_top;
    }

    if (isset($kod_za)) {
        $rcover_bottom = $kod_za . "\n";
    } else {
        $rcover_bottom = "";
    }

    //otevreni adresare
    $rhandle = @opendir($rdir);

    if ($rhandle) {

        //nacteni polozek
        $ritems = array();
        $rsort_data = array();
        while (false !== ($item = readdir($rhandle))) {
            $rinfo = pathinfo($item);
            if (isset($rinfo['extension'])) {
                $rext = $rinfo['extension'];
            } else {
                $rext = "";
            }
            if ($item == "." or $item == ".." or ($rmask != "*" and (($rmask == 2 and !@is_dir($rdirs . $item)) or (is_array($rmask) and !in_array(mb_strtolower($rext), $rmask))))) {
                continue;
            }
            $ritems[] = $item;
            if ($rsort == 3 or $rsort == 4) {
                $rsort_data[] = @filemtime($rdirs . $item);
            }
        }
        closedir($rhandle);
        $count = count($ritems);

        //serazeni polozek
        switch ($rsort) {
            case 1:
                natsort($ritems);
                break;
            case 2:
                natsort($ritems);
                $ritems = array_reverse($ritems, false);
                break;
            case 3:
                asort($rsort_data);
                break;
            case 4:
                arsort($rsort_data);
                break;

            case 5:
                $rrandom = array_rand($ritems, $count);
                $rsort_data = array();
                foreach ($rrandom as $key) {
                    $rsort_data[$key] = null;
                }
                break;

        }

        //sjednoceni poli pri razeni podle $rsort_data
        if ($rsort == 3 or $rsort == 4 or $rsort == 5) {
            $ritems_new = array();
            foreach ($rsort_data as $key => $val) {
                $ritems_new[] = $ritems[$key];
            }
            $ritems = $ritems_new;
        }

        //inicializace strankovani
        if ($rpaging != false) {
            $rpaging_data = _resultPaging(_indexOutput_url, $rpaging, $count, "", "#hcm_xlist" . SL::$hcmUid, "hcm_xlist" . SL::$hcmUid . "p");
        }

        //vypis horniho strankovani a obalu
        if ($rpaging != false and ($rpaging_pos == 1 or $rpaging_pos == 2)) {
            $output .= $rpaging_data[0];
        }
        $output .= $rcover_top;

        //vypis polozek
        $counter = 0;
        if ($rpaging == false) {
            $end = $count - 1;
        }
        foreach ($ritems as $item) {

            //efekty strankovani
            if ($rpaging != false) {
                $end = $rpaging_data[6];
                if ($counter > $end) {
                    break;
                }
                if (!_resultPagingIsItemInRange($rpaging_data, $counter)) {
                    $counter++;
                    continue;
                }
            }

            //odrezani pripony
            if (!is_array($rmask) and $rmask == "%") {
                $dotpos = false;
            } else {
                $dotpos = mb_strrpos($item, ".");
            }
            if ($dotpos == false) {
                $item_noext = $item;
            } else {
                $item_noext = mb_substr($item, 0, $dotpos);
            }

            //nahrazeni znacek, vypis kodu
            $GLOBALS['_hcm_xlist_data'] = array(SL::$hcmUid, $rdirs, $item, $item_noext, $counter, $count, $end);
            $output .= preg_replace_callback('|\[tag\](.*?)\[/tag\]|s', '_tmp_hcm_xlistReplaceTemplateMatch', $rtemplate) . "\n";

            $counter++;
        }
        unset($GLOBALS['_hcm_xlist_data']);

        //vypis dolniho obalu a strankovani
        $output .= $rcover_bottom;
        if ($rpaging != false and ($rpaging_pos == 0 or $rpaging_pos == 2)) {
            $output .= $rpaging_data[0];
        }

    } else {
        $output = "Nelze otevrit adresar.";
    }

    return $output;
}
