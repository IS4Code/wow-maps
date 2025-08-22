<?php

class base64filter extends php_user_filter
{
  // Remaining data before a 3-byte boundary
  private $buffer = "";
  
  public function filter($in, $out, &$consumed, $closing)
  {
    while($bucket = stream_bucket_make_writeable($in))
    {
      $this->buffer .= $bucket->data;
      $consumed += $bucket->datalen;
      
      // Length up to last 3-byte boundary
      $len = strlen($this->buffer);
      $len -= $len % 3;
      
      if($len == 0)
      {
        // Not enough data
        continue;
      }
      
      // Encode whole data and append
      $bucket->data = base64_encode(substr($this->buffer, 0, $len));
      $bucket->datalen = $len / 3 * 4;
      stream_bucket_append($out, $bucket);
      
      // Store the remaining bytes into buffer
      $this->buffer = substr($this->buffer, $len);
    }
    
    if($closing && $this->buffer !== "")
    {
      // Encode the remaining data (with padding)
      $bucket = stream_bucket_new($this->params, base64_encode($this->buffer));
      stream_bucket_append($out, $bucket);
      $this->buffer = "";
    }
    
    return PSFS_PASS_ON;
  }
}

stream_filter_register('base64', 'base64filter');

function bmpopen($path, $width, $height)
{
  $out = fopen($path, 'w');
  
  // Pass stream in params due to bug #73586
  stream_filter_append($out, 'base64', STREAM_FILTER_WRITE, $out);
    
  fwrite($out, "BM");
  
  // 24 bits per pixel, row size rounded up to a multiple of 4 bytes
  $stride = $width * 3 + 3;
  $stride -= $stride % 4;
  
  $datasize = $stride * $height;
  
  $size =
    14 // BMP header
    + 40 // DIB header
    + $datasize;
  
  // Size, reserved, data offset
  fwrite($out, pack('V3', $size, 0, 14 + 40));
  
  // DIB header
  fwrite($out, pack('V3v2V6', 40, $width, $height, 1, 24, 0, $datasize, 3779, 3779, 0, 0));
  
  return $out;
}

function bmppixel($stream, $r, $g, $b)
{
  fwrite($stream, pack('C3', $b, $g, $r));
}

function bmprow($stream, $width)
{
  $padding = (4 - ($width * 3) % 4) % 4;
  fwrite($stream, str_repeat("\0", $padding));
}
