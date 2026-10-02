<?php
namespace Framework\File\Type;

use Framework\File\Storage;
use Framework\File\FileType;
use Framework\File\Image;

/**
 * The File Item
 */
class FileItem {

    public string $name         = "";
    public string $path         = "";
    public string $mvPath       = "";

    public bool   $canSelect    = false;
    public bool   $isBack       = false;
    public bool   $isDir        = false;
    public bool   $isImage      = false;
    public bool   $isTransparent = false;
    public bool   $isFile        = false;
    public bool   $isPDF         = false;
    public bool   $isAudio       = false;
    public bool   $isDocument    = false;

    public string $icon          = "";
    public string $color         = "";
    public string $source        = "";
    public string $url           = "";
    public string $thumb         = "";
    public int    $width         = 0;
    public int    $height        = 0;
    public int    $size          = 0;
    public int    $total         = 0;
    public int    $modifiedTime  = 0;



    /**
     * Creates a File/Document instance
     * @param string $name
     * @param string $path
     * @param bool   $isDir
     * @param string $sourcePath
     * @param string $sourceUrl
     * @param string $thumbPath
     * @param string $thumbUrl
     * @return FileItem
     */
    public static function createFile(
        string $name,
        string $path,
        bool $isDir,
        string $sourcePath,
        string $sourceUrl,
        string $thumbPath,
        string $thumbUrl,
    ): FileItem {
        // An SVG has no thumb, as it can not be resized, and it is drawn at any size anyway
        $isSVG   = !$isDir && FileType::isSVG($name);
        $isImage = !$isDir && FileType::isImage($name) && Storage::fileExists($thumbPath);
        [ $imgWidth, $imgHeight ] = Image::getSize($sourcePath);

        $item = new FileItem();

        $item->name          = $name;
        $item->path          = $path;
        $item->mvPath        = $path !== "" ? $path : "/";

        $item->canSelect     = !$isDir;
        $item->isBack        = false;
        $item->isDir         = $isDir;
        $item->isImage       = $isImage || $isSVG;
        $item->isTransparent = $isSVG || Image::hasTransparency($sourcePath);
        $item->isFile        = !$isImage && !$isSVG;
        $item->isPDF         = FileType::isPDF($name);
        $item->isAudio       = FileType::isAudio($name);
        $item->isDocument    = FileType::isDocument($name);

        $item->icon          = $isDir ? "directory" : FileType::getIcon($name);
        $item->color         = FileType::getColor($item->icon);
        $item->source        = $sourceUrl;
        $item->url           = $sourceUrl;
        $item->thumb         = $isSVG ? $sourceUrl : $thumbUrl;
        $item->width         = $imgWidth;
        $item->height        = $imgHeight;

        // The Directories show what they have inside and the Files their size
        $item->size          = $isDir ? 0 : Storage::getFileSize($sourcePath);
        $item->total         = $isDir ? count(Storage::getAllInDir($sourcePath)) : 0;
        $item->modifiedTime  = Storage::getModifiedTime($sourcePath);
        return $item;
    }

    /**
     * Creates a Back instance
     * @param string $path
     * @return FileItem
     */
    public static function createBack(string $path): FileItem {
        $dir  = Storage::getDirectory($path);
        $item = new FileItem();

        $item->name      = "...";
        $item->path      = $dir !== "." ? $dir : "";
        $item->mvPath    = $dir !== "." ? $dir : "/";
        $item->canSelect = false;
        $item->isBack    = true;
        $item->isFile    = true;
        $item->icon      = "back";
        $item->color     = FileType::getColor($item->icon);
        return $item;
    }
}
