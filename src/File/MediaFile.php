<?php
namespace Framework\File;

use Framework\File\Storage;
use Framework\File\File;
use Framework\File\FileType;
use Framework\File\Image;
use Framework\File\Type\MediaType;
use Framework\File\Type\FileItem;
use Framework\System\Config;
use Framework\System\Path;
use Framework\System\MediaSchema;
use Framework\Utils\Strings;

/**
 * The Media Files
 */
class MediaFile {

    private static int $id = 0;

    /**
     * Sets the Current ID
     * @param int $id
     * @return void
     */
    public static function setID(int $id): void {
        self::$id = $id > 0 ? $id : 0;
    }

    /**
     * Returns the Folders of the given ID, which a flat Media does not have, as an App
     * can keep its files in the source itself
     * @param int $id
     * @return list<int>
     */
    private static function getIDParts(int $id): array {
        if (Config::isFileFlatMedia()) {
            return [];
        }
        return [ $id ];
    }

    /**
     * Returns the given Path Parts without the parts that go up or stay, as a path that
     * came in a request could reach a file outside the Media
     * @param array<int|string> $pathParts
     * @return list<int|string>
     */
    private static function cleanParts(array $pathParts): array {
        $result = [];
        foreach ($pathParts as $pathPart) {
            if (is_int($pathPart)) {
                $result[] = $pathPart;
                continue;
            }
            foreach (Strings::split($pathPart, "/") as $part) {
                if ($part !== "" && $part !== "." && $part !== "..") {
                    $result[] = $part;
                }
            }
        }
        return $result;
    }

    /**
     * Returns the Url of the given File of the Media shared by every ID
     * @param int|string ...$pathParts
     * @return string
     */
    public static function getSharedUrl(int|string ...$pathParts): string {
        return Path::getSourceUrl(...self::getIDParts(0), ...self::cleanParts($pathParts));
    }

    /**
     * Returns the Thumb Url of the given File of the Media shared by every ID
     * @param int|string ...$pathParts
     * @return string
     */
    public static function getSharedThumbUrl(int|string ...$pathParts): string {
        return Path::getThumbsUrl(...self::getIDParts(0), ...self::cleanParts($pathParts));
    }



    /**
     * Returns the Source Path using the ID
     * @param int|string ...$pathParts
     * @return string
     */
    public static function getPath(int|string ...$pathParts): string {
        return Path::getSourcePath(...self::getIDParts(self::$id), ...self::cleanParts($pathParts));
    }

    /**
     * Returns the Thumbs Path using the ID
     * @param int|string ...$pathParts
     * @return string
     */
    public static function getThumbPath(int|string ...$pathParts): string {
        return Path::getThumbsPath(...self::getIDParts(self::$id), ...self::cleanParts($pathParts));
    }

    /**
     * Returns the Source Url
     * @param int|string ...$pathParts
     * @return string
     */
    public static function getUrl(int|string ...$pathParts): string {
        return Path::getSourceUrl(...self::getIDParts(self::$id), ...self::cleanParts($pathParts));
    }

    /**
     * Returns the Thumb Url
     * @param int|string ...$pathParts
     * @return string
     */
    public static function getThumbUrl(int|string ...$pathParts): string {
        return Path::getThumbsUrl(...self::getIDParts(self::$id), ...self::cleanParts($pathParts));
    }

    /**
     * Returns true if given Source File exists
     * @param int|string ...$pathParts
     * @return bool
     */
    public static function exists(int|string ...$pathParts): bool {
        $path = self::getPath(...$pathParts);
        return Storage::fileExists($path);
    }



    /**
     * Returns all the Media Elements
     * @param string $mediaType Optional.
     * @param string $path      Optional.
     * @param string $basePath  Optional.
     * @return array{list:list<FileItem>,path:string}
     */
    public static function getList(
        string $mediaType = "",
        string $path = "",
        string $basePath = "",
    ): array {
        $path   = $path !== "" && self::exists($basePath, $path) ? $path : "";
        $source = self::getPath($basePath, $path);
        $files  = Storage::getAllInDir($source);
        $source = Storage::addLastSlash($source);
        $list   = new FileList();

        foreach ($files as $file) {
            $fileName = Strings::replace($file, $source, "");
            if (MediaType::isValid($mediaType, $file, $fileName)) {
                $list->add(
                    name       : $fileName,
                    path       : Storage::parsePath($path, $fileName),
                    isDir      : FileType::isDir($file),
                    sourcePath : self::getPath($basePath, $path, $fileName),
                    sourceUrl  : self::getUrl($basePath, $path, $fileName),
                    thumbPath  : self::getThumbPath($basePath, $path, $fileName),
                    thumbUrl   : self::getThumbUrl($basePath, $path, $fileName),
                );
            }
        }
        if ($path !== "" && $path !== "/") {
            $list->addBack($path);
        }

        return [
            "list" => $list->getSorted(),
            "path" => Storage::removeFirstSlash($path),
        ];
    }

    /**
     * Creates a Directory
     * @param int|string ...$pathParts
     * @return bool
     */
    public static function createDir(int|string ...$pathParts): bool {
        $source = self::getPath(...$pathParts);
        $thumbs = self::getThumbPath(...$pathParts);
        return Storage::createDir($source) && Storage::createDir($thumbs);
    }

    /**
     * Uploads a File
     * @param File       $file
     * @param int|string ...$pathParts
     * @return bool
     */
    public static function uploadFile(File $file, int|string ...$pathParts): bool {
        $fileName = $file->getName();
        $tmpFile  = $file->getTmpName();
        $source   = self::getPath(...$pathParts);

        if (!Storage::uploadFile($source, $fileName, $tmpFile)) {
            return false;
        }
        if (!FileType::isImage($fileName)) {
            return true;
        }

        $pathParts[] = $fileName;
        $src = self::getPath(...$pathParts);
        $dst = self::getThumbPath(...$pathParts);
        return Image::resize($src, $dst, 200, 200, Image::Resize);
    }

    /**
     * Deletes a Media Element
     * @param int|string ...$pathParts
     * @return bool
     */
    public static function deletePath(int|string ...$pathParts): bool {
        $relPath = Storage::parsePath(...$pathParts);
        $source  = self::getPath(...$pathParts);
        $thumbs  = self::getThumbPath(...$pathParts);

        if (!Storage::deleteDir($source) || !Storage::deleteDir($thumbs)) {
            return false;
        }

        MediaSchema::updatePaths($relPath, "");
        return true;
    }



    /**
     * Renames a File/Directory
     * @param string $path
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    public static function renamePath(
        string $path,
        string $oldName,
        string $newName,
    ): bool {
        return self::updatePath($path, $path, $oldName, $newName);
    }

    /**
     * Moves a File/Directory
     * @param string $oldPath
     * @param string $newPath
     * @param string $name
     * @return bool
     */
    public static function movePath(
        string $oldPath,
        string $newPath,
        string $name,
    ): bool {
        return self::updatePath($oldPath, $newPath, $name, $name);
    }

    /**
     * Moves or Renames a File/Directory
     * @param string $oldPath
     * @param string $newPath
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    private static function updatePath(
        string $oldPath,
        string $newPath,
        string $oldName,
        string $newName,
    ): bool {
        $oldRelPath = Storage::removeFirstSlash(Storage::parsePath($oldPath, $oldName));
        $newRelPath = Storage::removeFirstSlash(Storage::parsePath($newPath, $newName));
        $oldSource  = self::getPath($oldPath, $oldName);
        $newSource  = self::getPath($newPath, $newName);
        $oldThumbs  = self::getThumbPath($oldPath, $oldName);
        $newThumbs  = self::getThumbPath($newPath, $newName);

        if (!Storage::moveFile($oldSource, $newSource)) {
            return false;
        }
        if (FileType::isImage($oldName)) {
            // The File was already moved, so a missing Thumb is made from its new place
            if (!Storage::fileExists($oldThumbs)) {
                if (!Image::resize($newSource, $newThumbs, 200, 200, Image::Resize)) {
                    return false;
                }
            } elseif (!Storage::moveFile($oldThumbs, $newThumbs)) {
                return false;
            }
        }

        MediaSchema::updatePaths($oldRelPath, $newRelPath);
        return true;
    }
}
