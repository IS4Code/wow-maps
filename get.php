<?php
$version = @$_GET['version'];
$name = @$_GET['name'];

if(!preg_match('/\d[.0-9]*/', $version)) die;
if(!preg_match('/[a-zA-Z]+/', $name)) die;

$main = "data/$name.json";
$prefix = "data/$version/$name";

if(!file_exists($main))
{
  http_response_code(404);
  exit;
}

$info = json_decode(file_get_contents($main), true);
$width = $info['width'];
$height = $info['height'];
$hashes = json_decode(file_get_contents("$prefix-hashes.json"), true);
$linesH = @json_decode(@file_get_contents("$prefix-linesH.json"), true);
$linesV = @json_decode(@file_get_contents("$prefix-linesV.json"), true);

$expires = 24 * 60 * 60;
header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + $expires));
header("Cache-Control: max-age=$expires");

header("Content-Type: image/svg+xml");

?><?xml version="1.0" encoding="utf-8" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="<?=$width?>" height="<?=$height?>" viewBox="0 0 <?=$width?> <?=$height?>" preserveAspectRatio="none">
<?php

unset($hashes[""]);

function tiles_cmp($id1, $id2)
{
  if(!preg_match('/map(\d+)_(\d+)/', $id1, $matches1)) return 0;
  if(!preg_match('/map(\d+)_(\d+)/', $id2, $matches2)) return 0;
  
  $v1 = intval($matches1[1]) - intval($matches1[2]);
  $v2 = intval($matches2[1]) - intval($matches2[2]);
  
  if($v1 < $v2) return -1;
  if($v1 > $v2) return 1;
  return 0;
}

uksort($hashes, 'tiles_cmp');

foreach($hashes as $id => $hash)
{
  if(!preg_match('/map(\d+)_(\d+)/', $id, $matches)) continue;
  
  $x = $matches[1] * 256;
  $y = $matches[2] * 256;
  
  $width = 256;
  $height = 256;
  
  if(@$linesH[$id])
  {
    $height += 1.5;
    $y -= 1.5;
  }else if(@$linesV[$id])
  {
    $width += 1.5;
  }
  
  $href = "../tiles/256/$hash";
?>
<image x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>" xlink:href="<?=$href?>" preserveAspectRatio="none" decoding="async"/>
<?php
}
?>
</svg>