<?php
$version = @$_GET['version'];
$name = @$_GET['name'];

if(!preg_match('/\d[.0-9]*/', $version)) die;
if(!preg_match('/[a-zA-Z0-9]+/', $name)) die;

$info = "data/$name.json";
$prefix = "data/$version/$name";
$hashes = "$prefix-hashes.json";
$layers = "$prefix-layers.json";

if(!file_exists($info) || (!file_exists($hashes) && !file_exists($layers)))
{
  http_response_code(404);
  exit;
}

$info = json_decode(file_get_contents($info), true);
$width = $info['width'];
$height = $info['height'];

$expires = 24 * 60 * 60;
header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + $expires));
header("Cache-Control: max-age=$expires");

header("Content-Type: image/svg+xml");

?><?xml version="1.0" encoding="utf-8" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="<?=$width?>" height="<?=$height?>" viewBox="0 0 <?=$width?> <?=$height?>" preserveAspectRatio="none">
<?php

if(file_exists($layers))
{
  $layers = json_decode(file_get_contents($layers), true);

  unset($layers[""]);
  
  function layers_cmp($id1, $id2)
  {
    global $layers;
    
    $v1 = $layers[$id1][2] * $layers[$id1][3];
    $v2 = $layers[$id2][2] * $layers[$id2][3];
    
    if($v1 < $v2) return 1;
    if($v1 > $v2) return -1;
    return 0;
  }
  
  uksort($layers, 'layers_cmp');
  
  foreach($layers as $href => $data)
  {
    list($x, $y, $width, $height) = $data;
    
    if(file_exists("data/$version/{$href}Mask.webp"))
    {
?>
<filter id="f<?=$href?>">
<feComponentTransfer color-interpolation-filters="sRGB" result="outer">
<feFuncR type="linear" slope="3"/>
<feFuncG type="linear" slope="3"/>
<feFuncB type="linear" slope="3"/>
</feComponentTransfer>
<feImage x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>Mask" xlink:href="<?=$href?>Mask" preserveAspectRatio="none" result="mask"/>
<feComponentTransfer in="mask" result="invmask">
<feFuncA type="linear" slope="-1" intercept="1"/>
</feComponentTransfer>
<feComposite in="SourceGraphic" in2="mask" operator="in" result="innerMasked"/>
<feComposite in="outer" in2="invmask" operator="in" result="outerMasked"/>
<feBlend in="innerMasked" in2="outerMasked"/>
</filter>
<image x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>" xlink:href="<?=$href?>" filter="url(#f<?=$href?>)" preserveAspectRatio="none" decoding="async"/>
<?php
    }else{
?>
<image x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>" xlink:href="<?=$href?>" preserveAspectRatio="none" decoding="async"/>
<?php
    }
  }
}

if(file_exists($hashes))
{
  $hashes = json_decode(file_get_contents($hashes), true);
  $linesH = @json_decode(@file_get_contents("$prefix-linesH.json"), true);
  $linesV = @json_decode(@file_get_contents("$prefix-linesV.json"), true);

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
}
?>
</svg>