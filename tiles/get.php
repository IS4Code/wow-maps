<?php

$size = @$_GET['size'];
$hash = @$_GET['hash'];

if(!preg_match('/\d+/', $size)) die;
if(!preg_match('/[a-fA-F0-9]+/', $hash)) die;

$prefix = substr($hash, 0, 2);

$path = "$size/$prefix/$hash";

$jpeg = "$path.jpg";
$png = "$path.png";
$webp = "$path.webp";

$format = $_SERVER['PATH_INFO'];

if($format !== '/webp' || filesize($webp) >= filesize($png))
{
  unset($webp);
}else if($format !== '/png')
{
  unset($png);
}

function output($path, $mime)
{
  global $hash, $format;

  if(!file_exists($path))
  {
    http_response_code(404);
    exit;
  }
  
  $expires = 24 * 60 * 60;
  
  header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + $expires));
  header("Cache-Control: max-age=$expires");
  header("ETag: \"$hash$format\"");
  
  header("Content-Type: $mime");
  readfile($path);
}


if(isset($webp))
{
  output($webp, "image/webp");
}else if(isset($png))
{
  output($png, "image/png");
}else{
  output($jpeg, "image/jpeg");
}

?>