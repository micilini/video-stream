<?php

namespace Micilini\VideoStream\Providers;

class LocalVideoStreamProvider{

    private $path = "";
    private $stream = "";
    private $buffer = 512;
    private $start  = -1;
    private $end    = -1;
    private $size   = null;
    private $httpString = "";
    private $contentType = "video/mp4";
    private $cacheControl = "max-age=2592000, public";
    private $expires = null;
    private $lastModified = null;

    function __construct($filePath, $options){
        $this->path = $filePath;
        $this->httpString = isset($options['is_https']) ? (($options['is_https'] == true) ? "HTTPS/1.1" : "HTTP/1.1") : "HTTP/1.1";
        $this->size = isset($options['video_size']) ? $options['video_size'] : $this->size;
        $this->buffer = isset($options['video_buffer']) ? $options['video_buffer'] : $this->buffer;
        $this->contentType = isset($options['content_type']) ? $options['content_type'] : $this->contentType;
        $this->cacheControl = isset($options['cache_control']) ? $options['cache_control'] : $this->cacheControl;
        $this->expires = isset($options['expires']) ? $options['expires'] : gmdate('D, d M Y H:i:s', time()+2592000).' GMT';
        $this->lastModified = isset($options['last_modified']) ? $options['last_modified'] : gmdate('D, d M Y H:i:s', @filemtime($this->path)).' GMT';
    }
     
    private function open(){
        if (!($this->stream = fopen($this->path, 'rb'))) {
            die('Could not open stream for reading');
        }
    }
     
    private function setHeader(){
        ob_get_clean();
        header("Content-Type: ".$this->contentType);
        header("Cache-Control: ".$this->cacheControl);
        header("Expires: ".$this->expires);
        header("Last-Modified: ".$this->lastModified);
        $this->start = 0;
        $this->size  = ($this->size == null || $this->size <= 0) ? filesize($this->path) : $this->size;
        $this->end   = $this->size - 1;
        header("Accept-Ranges: 0-".$this->end);
         
        if (isset($_SERVER['HTTP_RANGE'])) {
  
            $c_start = $this->start;
            $c_end = $this->end;
 
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header($this->httpString.' 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            if ($range == '-') {
                $c_start = $this->size - substr($range, 1);
            }else{
                $range = explode('-', $range);
                $c_start = $range[0];
                 
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $this->end) ? $this->end : $c_end;
            if ($c_start > $c_end || $c_start > $this->size - 1 || $c_end >= $this->size) {
                header($this->httpString.' 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            $this->start = $c_start;
            $this->end = $c_end;
            $length = $this->end - $this->start + 1;
            fseek($this->stream, $this->start);
            header($this->httpString.' 206 Partial Content');
            header("Content-Length: ".$length);
            header("Content-Range: bytes $this->start-$this->end/".$this->size);
        }
        else
        {
            header("Content-Length: ".$this->size);
        }  
         
    }
    
    private function end(){
        fclose($this->stream);
        exit;
    }
     
    private function stream(){
        $i = $this->start;
        set_time_limit(0);
        while(!feof($this->stream) && $i <= $this->end) {
            $bytesToRead = $this->buffer;
            if(($i+$bytesToRead) > $this->end) {
                $bytesToRead = $this->end - $i + 1;
            }
            $data = fread($this->stream, $bytesToRead);
            echo $data;
            flush();
            $i += $bytesToRead;
        }
    }
     
    function start(){
        $this->open();
        $this->setHeader();
        $this->stream();
        $this->end();
    }

}