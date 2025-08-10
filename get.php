<?php
$path = @$_GET['path'];
$mapname = @$_GET['name'];

if(!preg_match('/\d[-.0-9]*\/[a-z]+/', $path)) die;
if(!preg_match('/[a-zA-Z0-9]+/', $mapname)) die;

$info = "data/$mapname.json";
$prefix = "data/$path/$mapname";
$hashes = "$prefix-hashes.json";
$layers = "$prefix-layers.json";

if(!file_exists($info) || (!file_exists($hashes) && !file_exists($layers)))
{
  http_response_code(404);
  exit;
}

$info = json_decode(file_get_contents($info), true);
$mapwidth = $info['width'];
$mapheight = $info['height'];

$expires = 24 * 60 * 60;
header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + $expires));
header("Cache-Control: max-age=$expires");

header("Content-Type: image/svg+xml");

?><?xml version="1.0" encoding="utf-8" standalone="no"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="root" width="<?=$mapwidth?>" height="<?=$mapheight?>" viewBox="0 0 <?=$mapwidth?> <?=$mapheight?>" preserveAspectRatio="none">
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
    
    if(substr($href, -4) != '.svg')
    {
      $name = $href;
    }else{
      $name = substr($href, 0, -4);
    }
    
    $attrs = '';
    
    if(file_exists("data/$path/$name-LightMask.webp"))
    {
?>
<filter id="f<?=$name?>">
<feComponentTransfer color-interpolation-filters="sRGB" result="outer">
<feFuncR type="linear" slope="3"/>
<feFuncG type="linear" slope="3"/>
<feFuncB type="linear" slope="3"/>
</feComponentTransfer>
<feImage x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$name?>-LightMask" xlink:href="<?=$name?>-LightMask" preserveAspectRatio="none" result="mask"/>
<feComponentTransfer in="mask" result="invmask">
<feFuncA type="linear" slope="-1" intercept="1"/>
</feComponentTransfer>
<feComposite in="SourceGraphic" in2="mask" operator="in" result="innerMasked"/>
<feComposite in="outer" in2="invmask" operator="in" result="outerMasked"/>
<feBlend in="innerMasked" in2="outerMasked"/>
</filter>
<?php
      $attrs .= ' filter="url(#f'.$name.')"';
    }
    if(file_exists("data/$path/$name-AlphaMask.webp"))
    {
?>
<mask id="m<?=$name?>">
<image x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$name?>-AlphaMask" xlink:href="<?=$name?>-AlphaMask" preserveAspectRatio="none" decoding="async"/>
</mask>
<?php
      $attrs .= ' mask="url(#m'.$name.')"';
    }else if(file_exists("data/$path/$name-GlobalAlphaMask.webp"))
    {
?>
<mask id="m<?=$name?>">
<image x="0" y="0" width="<?=$mapwidth?>" height="<?=$mapheight?>" href="<?=$name?>-GlobalAlphaMask" xlink:href="<?=$name?>-GlobalAlphaMask" preserveAspectRatio="none" decoding="async"/>
</mask>
<?php
    }
    
    if(substr($href, -4) != '.svg')
    {
?>
<image x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>" xlink:href="<?=$href?>"<?=$attrs?> preserveAspectRatio="none" decoding="async"/>
<?php
    }else{
?>
<use x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>#root" xlink:href="<?=$href?>#root"<?=$attrs?>/>
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
    
    $href = str_repeat("../", substr_count($path, "/") + 1)."tiles/256/$hash";
?>
<image x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>" xlink:href="<?=$href?>" preserveAspectRatio="none" decoding="async"/>
<?php
  }
}
?>
</svg>