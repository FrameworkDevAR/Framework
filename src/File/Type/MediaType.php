<?php
namespace Framework\File\Type;

use Framework\File\FileType;

/**
 * The Media Types used by the System
 */
class MediaType {

    public const Any   = "";
    public const Media = "media";
    public const Image = "image";
    public const Logo  = "logo";
    public const Video = "video";
    public const Audio = "audio";
    public const PDF   = "pdf";
    public const Text  = "text";
    public const File  = "file";


    /**
     * Returns true if the given file is valid for the given type
     * @param string $type
     * @param string $file
     * @param string $name
     * @return bool
     */
    public static function isValid(string $type, string $file, string $name): bool {
        if ($type === self::Any && !FileType::isHidden($name)) {
            return true;
        }
        if ($type === self::Media &&
            (FileType::isImage($name) || FileType::isVideo($name))
        ) {
            return true;
        }
        if ($type === self::Image && FileType::isImage($name)) {
            return true;
        }
        if ($type === self::Logo && (FileType::isImage($name) || FileType::isSVG($name))) {
            return true;
        }
        if ($type === self::Video && FileType::isVideo($name)) {
            return true;
        }
        if ($type === self::Audio && FileType::isAudio($name)) {
            return true;
        }
        if ($type === self::Text && FileType::isText($name)) {
            return true;
        }
        if ($type === self::PDF && FileType::isPDF($name)) {
            return true;
        }
        if ($type === self::File) {
            return true;
        }
        if (FileType::isDir($file)) {
            return true;
        }
        return false;
    }
}
