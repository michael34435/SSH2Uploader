<?php

/**
* For zip files using Zip funcs
*/
class ZipTool
{

    // provide other function to access it
    private $res = null;
    private $zip = null;
    private $name = null;
    private $dirName = null;

    // create an empty zip file
    public function __construct()
    {
        $this->zip = new ZipArchive;
        $this->name = 'tmp/tmp.zip';

        // delete if exists
        if(file_exists($this->name))
            @unlink($this->name);

        // create resource
        $this->res = $this->zip->open($this->name, ZipArchive::CREATE);
    }

    // close stream when release all resource
    public function __destruct()
    {
        $this->CloseZip();
    }

    // add new file to an exist zip archive
    public function addNewFile($src, $mode = "folder")
    {

        if($this->res !== TRUE) 
            return false;
        
        if(strtolower($mode) == "batch") {
            $files = explode('|', $src);
            foreach ($files as $key => $value) {
                $split = preg_split('/(\\\|\/)/', $value);
                $this->zip->addFile($value, preg_replace("/\\\/", "/", $split[count(preg_split('/(\\\|\/)/', $value))-1]));
            }
        } elseif(strtolower($mode) == "folder") {
            if(is_dir($src)) {
                $this->dirName = $src;
                $files = glob($src . '/*');
                foreach ($files as $key => $value) {
                    if(!is_dir($value)) {
                        $split = preg_split('/(\\\|\/)/', $value);
                        $this->zip->addFile($value, preg_replace("/\\\/", "/", $split[count(preg_split('/(\\\|\/)/', $value))-1]));
                    } else {
                        $this->addFolder($value);
                    }
                }
            } else {
                exit;
            }
        } else {
            $this->zip->addFile($src);
        }

        // auto release
        $this->CloseZip();
    }

    public function getFileName()
    {
        return $this->name;
    }

    private function CloseZip()
    {
        @$this->zip->close();
    }

    private function addFolder($src)
    {
        if (is_dir($src)) {
            $files = glob($src . '/*');
            $dir = preg_replace('/' . quotemeta($this->dirName) . '/', '', $src);
            $dir = substr($dir, 1); 
            $this->zip->addEmptyDir($dir);  

            foreach ($files as $key => $value) {
                if(!is_dir($value)) {
                    $split = preg_split('/(\\\|\/)/', $value);
                    $this->zip->addFile($value, preg_replace("/\\\/", "/", $dir . '\\' . $split[count(preg_split('/(\\\|\/)/', $value))-1]));
                } else {
                    $this->addFolder($value);
                }
            }
        }
    }
}
?>