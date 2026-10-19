<?php
namespace Framework\Database\Migration;

use Framework\Database\Migration\Migration;
use Framework\File\File;
use Framework\File\Storage;

use Closure;
use Exception;

/**
 * The Media Migration
 */
class MediaMigration {

    /** @var (Closure(File,string):bool)|null */
    private static ?Closure $uploader = null;


    /**
     * Sets the function that uploads a File to a path of the Media and returns if it did
     * @param Closure(File,string):bool $uploader
     * @return void
     */
    public static function setUploader(Closure $uploader): void {
        // The Media lives in the app, so the app says how a File reaches it, and what to do
        // with one that is already there
        self::$uploader = $uploader;
    }

    /**
     * Uploads the given Files of the media Directory of the Migrations to the Media
     * @param string ...$filePaths
     * @return void
     * @throws Exception When there is no uploader or a File can not be uploaded.
     */
    public static function upload(string ...$filePaths): void {
        $uploader = self::$uploader;
        if ($uploader === null) {
            throw new Exception("The Media has no uploader, which is set in the config");
        }

        // The Files are not in the repo but in the media Directory, which the deploy uploads,
        // so each one is given with the path it takes in the Media. One that is not there is
        // skipped, and one that fails stops the Migration, which then runs again next time
        foreach ($filePaths as $filePath) {
            $source = Migration::getMediaPath($filePath);
            if (!Storage::fileExists($source)) {
                print("  - Skipped $filePath, which is not in the media of the Migrations\n");
                continue;
            }

            $fileName = Storage::getBaseName($filePath);
            $path     = Storage::getDirectory($filePath);
            $file     = File::fromContent(Storage::readFile($source), $fileName);
            if (!$uploader($file, $path === "." ? "" : $path)) {
                throw new Exception("The File $filePath was not uploaded");
            }
            print("  - Uploaded $filePath\n");
        }
    }
}
