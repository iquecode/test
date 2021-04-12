<?php


class SendFile
{

    
    private $disposition = false;
    private $sec = 0.1;
    private $bytes = 40960;
    private $type = false;
    public function contentDisposition ($file_name = false) {
        $this->disposition = $file_name;
    }

    public function speed ($sec = 0.1, $bytes = 40960) {
        $this->sec = $sec;
        $this->bytes = $bytes;
    }
    
  
    public function contentType ($content_type = null) {
        $this->type = $content_type;
    }

    
    private function name ($file) {
        $info = pathinfo($file);
        return $info['basename'];  
    }

    
    public function send($file_path, $withDisposition=TRUE) {
        
        if (!is_readable($file_path)) {
            throw new \Exception('File not found or inaccessible!');
        }

        $size = filesize($file_path);
        if (!$this->disposition) {
            $this->disposition = $this->name($file_path);
        }
        
        if (!$this->type) {
            $this->type = $this->getContentType($file_path);
        }

        
        $this->cleanAll();
        
        // required for IE, otherwise Content-Disposition may be ignored
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: ' . $this->type);
        if ($withDisposition) {
            header('Content-Disposition: attachment; filename="' . $this->disposition . '"');
        }
        header('Accept-Ranges: bytes');

        // The three lines below basically make the
        // download non-cacheable 
        header("Cache-control: private");
        header('Pragma: private');
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        // multipart-download and download resuming support
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($a, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
            list($range) = explode(",", $range, 2);
            list($range, $range_end) = explode("-", $range);
            $range = intval($range);
            if (!$range_end) {
                $range_end = $size - 1;
            } else {
                $range_end = intval($range_end);
            }

            $new_length = $range_end - $range + 1;
            header("HTTP/1.1 206 Partial Content");
            header("Content-Length: $new_length");
            header("Content-Range: bytes $range-$range_end/$size");
        } else {
            $new_length = $size;
            header("Content-Length: " . $size);
        }

       
        $chunksize = $this->bytes; 
        $bytes_send = 0;
        
        $file = @fopen($file_path, 'rb');
        if ($file) {
            if (isset($_SERVER['HTTP_RANGE'])) {
                fseek($file, $range);
            }

            while (!feof($file) && (!connection_aborted()) && ($bytes_send < $new_length) ) {
                $buffer = fread($file, $chunksize);
                echo($buffer); 
                flush();
                usleep($this->sec * 1000000);
                $bytes_send += strlen($buffer);
            }
            fclose($file);
        } else {
            throw new \Exception('Error - can not open file.');
        }
    }
    
    
    private function getContentType($path) {
        $result = false;
        if (is_file($path) === true) {
            if (function_exists('finfo_open') === true) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if (is_resource($finfo) === true) {
                    $result = finfo_file($finfo, $path);
                }
                finfo_close($finfo);
            } else if (function_exists('mime_content_type') === true) {
                $result = preg_replace('~^(.+);.*$~', '$1', mime_content_type($path));
            } else if (function_exists('exif_imagetype') === true) {
                $result = image_type_to_mime_type(exif_imagetype($path));
            }
        }
        return $result;
    }
    
    
    private function cleanAll() {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
}
