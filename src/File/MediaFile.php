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
use Framework\Utils\Arrays;
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
     * Returns the Media Elements of a Directory, all of them or a page
     * @param string $mediaType Optional.
     * @param string $path      Optional.
     * @param string $basePath  Optional.
     * @param string $sort      Optional.
     * @param int    $start     Optional.
     * @param int    $amount    Optional.
     * @param string $selected  Optional.
     * @return array{list:list<FileItem>,path:string,total:int,hasMore:bool}
     */
    public static function getList(
        string $mediaType = "",
        string $path = "",
        string $basePath = "",
        string $sort = "",
        int $start = 0,
        int $amount = 0,
        string $selected = "",
    ): array {
        $path   = $path !== "" && self::exists($basePath, $path) ? $path : "";
        $source = self::getPath($basePath, $path);
        $files  = Storage::getAllInDir($source);
        $source = Storage::addLastSlash($source);
        $isTime = $sort === "time";

        // The Items are sorted with their names before they are made, as making one reads
        // the File, so only the ones of the page are made
        $entries = [];
        foreach ($files as $file) {
            $fileName = Strings::replace($file, $source, "");
            if (MediaType::isValid($mediaType, $file, $fileName)) {
                $entries[] = [
                    "name"  => $fileName,
                    "isDir" => FileType::isDir($file),
                    "time"  => $isTime ? Storage::getModifiedTime($file) : 0,
                ];
            }
        }
        $entries = Arrays::sortList($entries, function (array $a, array $b) use ($isTime) {
            if ($a["isDir"] !== $b["isDir"]) {
                return $a["isDir"] ? -1 : 1;
            }
            if ($isTime && $a["time"] !== $b["time"]) {
                return $b["time"] <=> $a["time"];
            }
            return strnatcasecmp($a["name"], $b["name"]);
        });

        // The page grows in whole pages until it has the selected Item, so it can be shown
        $total = count($entries);
        $start = max(0, $start);
        if ($amount > 0 && $selected !== "") {
            $index = Arrays::findIndex($entries, "name", $selected);
            if (is_int($index) && $index >= $start + $amount) {
                $amount *= (int)ceil(($index - $start + 1) / $amount);
            }
        }
        if ($amount > 0) {
            $entries = Arrays::slice($entries, $start, $amount);
        }

        $list = new FileList();
        if ($start === 0 && $path !== "" && $path !== "/") {
            $list->addBack($path);
        }
        foreach ($entries as $entry) {
            $fileName = $entry["name"];
            $list->add(
                name       : $fileName,
                path       : Storage::parsePath($path, $fileName),
                isDir      : $entry["isDir"],
                sourcePath : self::getPath($basePath, $path, $fileName),
                sourceUrl  : self::getUrl($basePath, $path, $fileName),
                thumbPath  : self::getThumbPath($basePath, $path, $fileName),
                thumbUrl   : self::getThumbUrl($basePath, $path, $fileName),
            );
        }

        return [
            "list"    => $list->get(),
            "path"    => Storage::removeFirstSlash($path),
            "total"   => $total,
            "hasMore" => $amount > 0 && $start + count($entries) < $total,
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

        MediaSchema::updatePaths($relPath, "", self::$id);
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

        $isDir = FileType::isDir($oldSource);
        if (!Storage::moveFile($oldSource, $newSource)) {
            return false;
        }
        if ($isDir) {
            // The Thumbs of a Directory are in a Directory of the same name, which follows it
            if (Storage::fileExists($oldThumbs)) {
                Storage::deleteDir($newThumbs);
                Storage::createDir(self::getThumbPath($newPath));
                if (!Storage::moveFile($oldThumbs, $newThumbs)) {
                    return false;
                }
            }
        } elseif (FileType::isImage($oldName)) {
            // The File was already moved, so a missing Thumb is made from its new place
            if (!Storage::fileExists($oldThumbs)) {
                if (!Image::resize($newSource, $newThumbs, 200, 200, Image::Resize)) {
                    return false;
                }
            } elseif (!Storage::moveFile($oldThumbs, $newThumbs)) {
                return false;
            }
        }

        MediaSchema::updatePaths($oldRelPath, $newRelPath, self::$id);
        return true;
    }
}
