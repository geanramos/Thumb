<?php

/*
Title: Thumb.php
URL: http://github.com/jamiebicknell/Thumb.php
Author: Jamie Bicknell
Twitter: @jamiebicknell
*/

define('THUMB_CACHE',           './cache/');    // Path to cache directory (must be writeable)
define('THUMB_CACHE_AGE',       86400);         // Duration of cached files in seconds
define('THUMB_BROWSER_CACHE',   true);          // Browser cache true or false
define('SHARPEN_MIN',           12);            // Minimum sharpen value
define('SHARPEN_MAX',           28);            // Maximum sharpen value

$src = isset($_GET['src']) ? $_GET['src'] : false;
$size = isset($_GET['size']) ? str_replace(array('<','x'),'',$_GET['size'])!='' ? $_GET['size'] : 100 : 100;
$crop = isset($_GET['crop']) ? max(0,min(1,$_GET['crop'])) : 1;
$trim = isset($_GET['trim']) ? max(0,min(1,$_GET['trim'])) : 0;
$zoom = isset($_GET['zoom']) ? max(0,min(1,$_GET['zoom'])) : 0;
$align = isset($_GET['align']) ? $_GET['align'] : false;
$sharpen = isset($_GET['sharpen']) ? max(0,min(100,$_GET['sharpen'])) : 0;

if(!is_writable(THUMB_CACHE)) {
    die('Cache not writable');
}
if(parse_url($src,PHP_URL_SCHEME)||!file_exists($src)||!in_array(strtolower(substr(strrchr($src,'.'),1)),array('gif','jpg','jpeg','png'))) {
    die('File cannot be found');
}

$file_salt = 'v1.0';
$file_size = filesize($src);
$file_time = filemtime($src);
$file_date = gmdate('D, d M Y H:i:s T',$file_time);
$file_type = strtolower(substr(strrchr($src,'.'),1));
$file_hash = md5($file_salt . $_SERVER['QUERY_STRING'] . $file_time);
$file_name = THUMB_CACHE . $file_hash . '.img.txt';

if(!file_exists(THUMB_CACHE . 'index.html')) {
    touch(THUMB_CACHE . 'index.html');
}
if(time()-THUMB_CACHE_AGE>filemtime(THUMB_CACHE . 'index.html')) {
    $files = glob(THUMB_CACHE . '*.img.txt');
    if(is_array($files)&&count($files)>0) {
        foreach($files as $file) {
            if(time()-THUMB_CACHE_AGE>filemtime($file)) {
                unlink($file);
            }
        }
    }
    touch(THUMB_CACHE . 'index.html');
}

if(THUMB_BROWSER_CACHE&&(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])||isset($_SERVER['HTTP_IF_NONE_MATCH']))) {
    if($_SERVER['HTTP_IF_MODIFIED_SINCE']==$file_date&&$_SERVER['HTTP_IF_NONE_MATCH']==$file_hash) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
        die();
    }
}

if(!file_exists($file_name)) {
    list($w,$h) = explode('x',str_replace('<','',$size));
    $w = ($w!='') ? floor(max(8,min(1500,$w))) : '';
    $h = ($h!='') ? floor(max(8,min(1500,$h))) : '';
    if(strstr($size,'<')) {
        $h = $w;
        $crop = 0;
        $trim = 1;
    }
    elseif(!strstr($size,'x')) {
        $h = $w;
    }
    elseif($w==''||$h=='') {
        $crop = 0;
        $trim = 1;
    }
    list($w0,$h0,$type) = getimagesize($src);
    if($crop) {
        $w1 = (($w0/$h0)>($w/$h)) ? ceil($w0*$h/$h0) : $w;
        $h1 = (($w0/$h0)<($w/$h)) ? ceil($h0*$w/$w0) : $h;
        if(!$zoom) {
            if($h0<$h||$w0<$w) {
                $w1 = $w0;
                $h1 = $h0;
            }
        }
    }
    else {
        $w = ($w=='') ? floor(($w0*$h)/$h0) : $w;
        $h = ($h=='') ? floor(($h0*$w)/$w0) : $h;
        $w1 = (($w0/$h0)<($w/$h)) ? ceil($w0*$h/$h0) : $w;
        $h1 = (($w0/$h0)>($w/$h)) ? ceil($h0*$w/$w0) : $h;
        if(!$zoom) {
            if($h0<$h&&$w0<$w) {
                $w1 = $w0;
                $h1 = $h0;
            }
        }
    }
    if($trim) {
        $w = (($w0/$h0)>($w/$h)) ? min($w,$w1) : $w1;
        $h = (($w0/$h0)<($w/$h)) ? min($h,$h1) : $h1;
    }
    if($sharpen) {
        $matrix = array (
            array(-1,-1,-1),
            array(-1,SHARPEN_MAX-($sharpen*(SHARPEN_MAX-SHARPEN_MIN))/100,-1),
            array(-1,-1,-1));
        $divisor = array_sum(array_map('array_sum',$matrix));
    }
    $x = strpos($align,'l')!==false ? 0 : (strpos($align,'r')!==false ? $w-$w1 : ($w-$w1)/2);
    $y = strpos($align,'t')!==false ? 0 : (strpos($align,'b')!==false ? $h-$h1 : ($h-$h1)/2);
    $im = imagecreatetruecolor($w,$h);
    $bg = imagecolorallocate($im,255,255,255);
    imagefill($im,0,0,$bg);
    switch($type) {
        case 1:
            $oi = imagecreatefromgif($src);
            imagecopyresampled($im,$oi,$x,$y,0,0,$w1,$h1,$w0,$h0);
            if($sharpen) {
                imageconvolution($im,$matrix,$divisor,0);
            }
            imagegif($im,$file_name);
            break;
        case 2:
            $oi = imagecreatefromjpeg($src);
            imagecopyresampled($im,$oi,$x,$y,0,0,$w1,$h1,$w0,$h0);
            if($sharpen) {
                imageconvolution($im,$matrix,$divisor,0);
            }
            imagejpeg($im,$file_name,100);
            break;
        case 3:
            imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));
            imagesavealpha($im,true);
            $oi = imagecreatefrompng($src);
            imagecopyresampled($im,$oi,$x,$y,0,0,$w1,$h1,$w0,$h0);
            if($sharpen) {
                imageconvolution($im,$matrix,$divisor,0);
            }
            imagepng($im,$file_name);
            break;
    }
    imagedestroy($im);
    imagedestroy($oi);
}

header('Content-Type: image/' . $file_type);
header('Content-Length: ' . filesize($file_name));
header('Last-Modified: ' . $file_date);
header('ETag: ' . $file_hash);
header('Cache-Control: public');
readfile($file_name);

?>