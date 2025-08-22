<?php
$path = @$_GET['path'];
$mapname = @$_GET['name'];

if(!preg_match('/\d[-.0-9]*\/[a-z]+/', $path)) die;
if(!preg_match('/[a-zA-Z0-9]+/', $mapname)) die;

$info = "data/$mapname.json";
$prefix = "data/$path/$mapname";
$hashes = "$prefix-hashes.json";
$hashes_patched = "$prefix-hashes-patched.json";
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
    
    $displacement = "data/$name-$mapname-displacement.json";
    if(file_exists($displacement))
    {
      include '.imagewrite.php';
      
      $displacement = json_decode(file_get_contents($displacement), true);
      unset($displacement[""]);
      
      $layer_info = json_decode(file_get_contents("data/$name.json"), true);
      $sourcewidth = $layer_info['width'];
      $sourceheight = $layer_info['height'];
      
      function transform_point($x, $y, &$newx, &$newy, $src, $dst)
      {
        global $displacement;
        
        
        // Method using barycentric coordinates
                                
        // Get the closest 3 points
        uksort($displacement, function($id1, $id2) use ($displacement, $x, $y, $src, $dst)
        {
          $dx1 = $displacement[$id1][$src + 0] - $x;
          $dy1 = $displacement[$id1][$src + 1] - $y;
          
          $dx2 = $displacement[$id2][$src + 0] - $x;
          $dy2 = $displacement[$id2][$src + 1] - $y;
          
          $v1 = $dx1 * $dx1 + $dy1 * $dy1;
          $v2 = $dx2 * $dx2 + $dy2 * $dy2;
          
          if($v1 < $v2) return -1;
          if($v1 > $v2) return 1;
          return 0;
        });
        
        // These points form a triangle
        $a = reset($displacement);
        $b = next($displacement);
        while($c = next($displacement))
        {
          $det1 = ($b[$src + 1] - $c[$src + 1]) * ($a[$src + 0] - $c[$src + 0]) + ($c[$src + 0] - $b[$src + 0]) * ($a[$src + 1] - $c[$src + 1]);
          $det2 = ($b[$dst + 1] - $c[$dst + 1]) * ($a[$dst + 0] - $c[$dst + 0]) + ($c[$dst + 0] - $b[$dst + 0]) * ($a[$dst + 1] - $c[$dst + 1]);
          
          if($det1 * $det2 <= 0)
          {
            // The triangles have different orientation
            continue;
          }
          
          // Get the barycentric coordinates in the source triangle
          $d1 = $x - $c[$src + 0];
          $d2 = $y - $c[$src + 1];
          $t1 = (($b[$src + 1] - $c[$src + 1]) * $d1 + ($c[$src + 0] - $b[$src + 0]) * $d2) / $det1;
          $t2 = (($c[$src + 1] - $a[$src + 1]) * $d1 + ($a[$src + 0] - $c[$src + 0]) * $d2) / $det1;
          $t3 = 1 - $t1 - $t2;
          
          // Map them to the target triangle
          $newx = $a[$dst + 0] * $t1 + $b[$dst + 0] * $t2 + $c[$dst + 0] * $t3;
          $newy = $a[$dst + 1] * $t1 + $b[$dst + 1] * $t2 + $c[$dst + 1] * $t3;
          return true;
        }
        return false;
        
        
        /*        
        // Method using weighted average displacement
        
        $sumx = 0;
        $sumy = 0;
        $sumweight = 0;                         
                        
        foreach($displacement as $id => $point)
        {
          $dx = $point[$dst + 0] - $point[$src + 0];
          $dy = $point[$dst + 1] - $point[$src + 1];
          
          $weight = 1 / pow(hypot($x - $point[$src + 0], $y - $point[$src + 1]), 10);
          
          $sumx += $dx * $weight;
          $sumy += $dy * $weight;
          $sumweight += $weight;                                                  
        }
        
        $newx = $x + $sumx / $sumweight;
        $newy = $y + $sumy / $sumweight;
        */                                                        
      }
      
      $minx = $mapwidth;
      $miny = $mapheight;
      $maxx = 0;
      $maxy = 0;
      
      function test_bounds($x, $y)
      {
        global $displacement, $minx, $miny, $maxx, $maxy;
        
        transform_point($x, $y, $newx, $newy, 0, 2);
        
        if($newx < $minx) $minx = $newx;
        if($newy < $miny) $miny = $newy;
        if($newx > $maxx) $maxx = $newx;
        if($newy > $maxy) $maxy = $newy;
      }
      
      for($px = 0; $px < $sourcewidth; $px += 10)
      {
        test_bounds($px, 0);
        test_bounds($px, $sourceheight - 1);
      }
      for($py = 0; $py < $sourceheight; $py += 10)
      {
        test_bounds(0, $py);
        test_bounds($sourcewidth - 1, $py);
      }
      
      // Draw within the boundaries
      $x = $minx;
      $y = $miny;
      $width = $maxx - $x;
      $height = $maxy - $y;
      
      // TEST //
      /*$x = 4047.9648598578;
      $y = 4518.9264327975;
      $width = 11755.074764637;
      $height = 16446.135398666;*/
      
      $bmpwidth = 64;
      $bmpheight = floor($bmpwidth / $sourcewidth * $sourceheight);
        
      $blur_scale = ($width * $height) / ($sourcewidth * $sourceheight);
      
      function process_map($out, &$scale)
      {
        global $bmpwidth, $bmpheight, $sourcewidth, $sourceheight, $width, $height, $x, $y;
        
        if($out)
        {
          // Prepare scale coefficient
          $coef = 127 / $scale;
        }
        
        for($py = $bmpheight - 1; $py >= 0; $py--)
        {
          $imagey = $py / $bmpheight * $height + $y;
          
          for($px = 0; $px < $bmpwidth; $px++)
          {
            //bmppixel($out, $px * 16, $py * 16, 0);
            
            /*$one = ($px + $py) % 2 == 1 ? 100 : -100;
            
            bmppixel($out, 127 + $one, 127 + $one, 0);*/
            
            $imagex = $px / $bmpwidth * $width + $x;
            
            // Transform back to the source image
            transform_point($imagex, $imagey, $sourcex, $sourcey, 2, 0);
            
            // Express in global coordinates
            $sourcex = $sourcex / $sourcewidth * $width + $x;
            $sourcey = $sourcey / $sourceheight * $height + $y;
            
            // Displacement offset
            $dx = $sourcex - $imagex;
            $dy = $sourcey - $imagey;
            
            if($out)
            {
              // Rescale and emit
              $dx = 127 + round($dx * $coef);
              $dy = 127 + round($dy * $coef);
              bmppixel($out, max(0, min(255, $dx)), max(0, min(255, $dy)), 0);
            }else{
              // Get greatest distance
              $d = max(abs($dx), abs($dy));
              if($d > $scale) $scale = $d;
            }
          }
          if($out)
          {
            bmprow($out, $bmpwidth);
          }
        }
      }
      
      // First pass - get the greatest offset size
      $offset_scale = 0;
      process_map(null, $offset_scale);
      //echo "($offset_scale)";
      $offset_scale = 3000;
?>
<filter id="f<?=$name?>" x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" filterUnits="userSpaceOnUse">
<feImage x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" image-rendering="optimizeQuality" xlink:href="data:image/bmp;base64,<?php

// Second pass - output pixels according to the scale
$out = bmpopen('php://output', $bmpwidth, $bmpheight);
process_map($out, $offset_scale);

?>" preserveAspectRatio="none" result="map"/>
<!--<feGaussianBlur in="map" stdDeviation="<?=4 * $blur_scale?>"/>-->

<feGaussianBlur in="map" stdDeviation="<?=4 * $blur_scale?>" out="mapblur"/>
<feDisplacementMap in="SourceGraphic" in2="mapblur" scale="<?=2 * $offset_scale?>" xChannelSelector="R" yChannelSelector="G" color-interpolation-filters="sRGB"/>
<feGaussianBlur stdDeviation="<?=4 * $blur_scale?>"/>

</filter>
<?php
      $attrs .= ' filter="url(#f'.$name.')"';
    }else if(file_exists("data/$path/$name-LightMask.webp"))
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
      if(!empty($attrs)) echo "<g$attrs>";        
?>
<use x="<?=$x?>" y="<?=$y?>" width="<?=$width?>" height="<?=$height?>" href="<?=$href?>#root" xlink:href="<?=$href?>#root"/>
<?php
      if(!empty($attrs)) echo "</g>";
    }
  }
}

if(file_exists($hashes))
{
  $hashes = json_decode(file_get_contents($hashes), true);
  $linesH = @json_decode(@file_get_contents("$prefix-linesH.json"), true);
  $linesV = @json_decode(@file_get_contents("$prefix-linesV.json"), true);

  unset($hashes[""]);
  
  if(file_exists($hashes_patched))
  {
    $hashes_patched = json_decode(file_get_contents($hashes_patched), true);
    unset($hashes_patched[""]);
    foreach($hashes_patched as $id => $hash)
    {
      $hashes[$id] = $hash;
    }
  }
  
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