<?php
// jadro
require '../require/load.php';
define('_header', '');
SL::init('../');

// kontrola GD
_checkGD("jpg", true);

// inicializace obrazku, nacteni kodu
$imgw = 65;
$imgh = 22;
$img = imagecreate($imgw, $imgh);
if (isset($_GET['n']) and isset($_SESSION[_sessionprefix . 'captcha_code'][(int) $_GET['n']])) {
    list($code, $drawn) = $_SESSION[_sessionprefix . 'captcha_code'][(int) $_GET['n']];
    if ($drawn) die;
    $_SESSION[_sessionprefix . 'captcha_code'][(int) $_GET['n']][1] = true;
} else {
    die;
}

_extend('call', 'captcha.render');

$invert = mt_rand(0, 1) ? -1 : 1;

class linear_perspective
{
    public $cam_location = array('x' => 0, 'y' => 0, 'z' => -250);
    public $cam_rotation = array('x' => -1, 'y' => 0, 'z' => 0);
    public $viewer_position = array('x' => 500, 'y' => -500, 'z' => -80);

    public function getProjection($point)
    {
        $translation = array();
        $projection = array();

        $translation['x'] = cos($this->cam_rotation['y']) * (sin($this->cam_rotation['z']) * ($point['y'] - $this->cam_location['y']) + cos($this->cam_rotation['z']) * ($point['x'] - $this->cam_location['x'])) - sin($this->cam_rotation['y']) * ($point['z'] - $this->cam_location['z']);
        $translation['y'] = sin($this->cam_rotation['x']) * (cos($this->cam_rotation['y']) * ($point['z'] - $this->cam_location['z']) + sin($this->cam_rotation['y']) * (sin($this->cam_rotation['z']) * ($point['y'] - $this->cam_location['y']) + cos($this->cam_rotation['z']) * ($point['x'] - $this->cam_location['x']))) + cos($this->cam_rotation['z']) * (cos($this->cam_rotation['z']) * ($point['y'] - $this->cam_location['y']) - sin($this->cam_rotation['z']) * ($point['x'] - $this->cam_location['x']));
        $translation['z'] = cos($this->cam_rotation['x']) * (cos($this->cam_rotation['y']) * ($point['z'] - $this->cam_location['z']) + sin($this->cam_rotation['y']) * (sin($this->cam_rotation['z']) * ($point['y'] - $this->cam_location['y']) + cos($this->cam_rotation['z']) * ($point['x'] - $this->cam_location['x']))) - sin($this->cam_rotation['z']) * (cos($this->cam_rotation['z']) * ($point['y'] - $this->cam_location['y']) - sin($this->cam_rotation['z']) * ($point['x'] - $this->cam_location['x']));

        $projection['x'] = ($translation['x'] - $this->viewer_position['x']) * ($this->viewer_position['z'] / $translation['z']);
        $projection['y'] = ($translation['y'] - $this->viewer_position['y']) * ($this->viewer_position['z'] / $translation['z']);

        return $projection;
    }
}

function imagelightnessat($img, $x, $y)
{
    if (!is_resource($img)) return 0.0;

    $c = @imagecolorat($img, $x, $y);
    if ($c === false) return false;

    if (imageistruecolor($img)) {
        $red = ($c >> 16) & 0xFF;
        $green = ($c >> 8) & 0xFF;
        $blue = $c & 0xFF;
    } else {
        $i = imagecolorsforindex($img, $c);
        $red = $i['red'];
        $green = $i['green'];
        $blue = $i['blue'];
    }

    $m = min($red, $green, $blue);
    $n = max($red, $green, $blue);
    $lightness = (double) (($m + $n) / 510.0);

    return ($lightness);
}

$perspective = new linear_perspective;

// sizes and offsets
$matrix_dim = array('x' => 114, 'y' => 30);
$captcha_dim = array('x' => 546, 'y' => 120);
$distance = array('x' => 1, 'y' => 1, 'z' => 1);
$metric = array('x' => 10, 'y' => 30, 'z' => 5);
$offset = array('x' => 240, 'y' => $invert === -1 ? -40 : -60);

_extend('call', 'captcha.render.matrix');

// matrix
$matrix = imagecreatetruecolor($matrix_dim['x'], $matrix_dim['y']);
$black = imagecolorexact($matrix, 0, 0, 0);
$white = imagecolorexact($matrix, 255, 255, 255);
$gray = imagecolorexact($matrix, 200, 200, 200);
$gray_dark = imagecolorexact($matrix, 175, 175, 175);
imagefill($matrix, 0, 0, $white);

// random pixels
for($i = 0; $i < 300; ++$i) imagesetpixel($matrix, mt_rand(5, ($matrix_dim['x'] - 6)), mt_rand(5, ($matrix_dim['y'] - 6)), $gray);

// random texts
for ($i = 0; $i < 5; ++$i) {
    imagefttext($matrix, mt_rand(10, 25), mt_rand(45, 135), $matrix_dim['x'] / 5 * $i + mt_rand(-5, 5), 25, $gray_dark, dirname(__file__) . '/cimage.ttf', _captchaCode(3));
}

// text
imagefttext($matrix, 19, 0, 4, 25, $black, dirname(__file__) . '/cimage.ttf', $code);

// compute 3D points
$point = array();
for ($x = 0; $x < $matrix_dim['x']; $x++) {
    for ($y = 0; $y < $matrix_dim['y']; $y++) {
        $lightness = imagelightnessat($matrix, $x, $y);
        $point[$x][$y] = $perspective->getProjection(array('x' => $x * $metric['x'] + $distance['x'], 'y' => $invert * $lightness * $metric['y'] + $distance['y'], 'z' => ($matrix_dim['y'] - $y) * $metric['z'] + $distance['z']));
    }
}
imagedestroy($matrix);

// captcha image
$captcha = imagecreatetruecolor($captcha_dim['x'], $captcha_dim['y']);
if (_template_dark) {
    $black = imagecolorexact($captcha, 255, 255, 255);
    $white = imagecolorexact($captcha, 0, 0, 0);
} else {
    $black = imagecolorexact($captcha, 0, 0, 0);
    $white = imagecolorexact($captcha, 255, 255, 255);
}
imagefill($captcha, 0, 0, $white);

// draw countour lines
if (function_exists('imageantialias')) imageantialias($captcha, true);
for($x = 1; $x < $matrix_dim['x']; $x++)
    for($y = 1; $y < $matrix_dim['y']; $y++) imageline($captcha, -$point[$x - 1][$y - 1]['x'] + $offset['x'], -$point[$x - 1][$y - 1]['y'] + $offset['y'], -$point[$x][$y]['x'] + $offset['x'], -$point[$x][$y]['y'] + $offset['y'], $black);

// resize
$width = 250;
$height = floor($width / ($captcha_dim['x'] / $captcha_dim['y']));
$rcaptcha = imagecreatetruecolor($width, $height);
imagecopyresampled($rcaptcha, $captcha, 0, 0, 0, 0, $width, $height, $captcha_dim['x'], $captcha_dim['y']);

// draw random noise
$light_noise = imagecolorallocatealpha($rcaptcha, 255, 255, 255, 120);
$dark_noise = imagecolorallocatealpha($rcaptcha, 0, 0, 0, 120);
for ($x = 0; $x < $captcha_dim['x']; ++$x) {
    for ($y = 0; $y < $captcha_dim['y']; ++$y) {
        imageline($rcaptcha, $x, $y, $x, $y, mt_rand(0, 1) ? $light_noise : $dark_noise);
    }
}

// output
header('Content-Type: image/png');
imagepng($rcaptcha);
