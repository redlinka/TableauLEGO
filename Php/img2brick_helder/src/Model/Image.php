<?php

class Image
{
    public $img_id;
    public $path;
    public $file_name;
    public $size;
    public $width;
    public $height;
    public $mime_type;
    public $img_hash;

    // Constructor
    public function __construct($id, $path, $filename, $size, $width, $height, $mime_type, $hash)
    {
        $this->img_id = $id;
        $this->path = $path;
        $this->file_name = $filename;
        $this->size = $size;
        $this->width = $width;
        $this->height = $height;
        $this->mime_type = $mime_type;
        $this->img_hash = $hash;
    }
}
