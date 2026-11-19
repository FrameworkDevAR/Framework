<?php
namespace Framework\File;

use Framework\File\Storage;
use Framework\Utils\Arrays;
use Framework\Utils\Numbers;
use Framework\Utils\Strings;
use Framework\Utils\URL;

use GdImage;
use Imagick;
use Exception;

/**
 * The Image Utils
 */
class Image {

    // The Resize Types
    public const Resize  = "resize";
    public const Maximum = "maximum";
    public const Thumb   = "thumb";


    /** @var list<int> The image types */
    private static array $imageTypes = [ 1, 2, 3, 15, 16 ];

    /** @var list<int> The transparent image type */
    private static array $transTypes = [ 1, 3 ];



    /**
     * Returns true if the given type is valid
     * @param int $fileType
     * @return bool
     */
    public static function hasType(int $fileType): bool {
        return Arrays::contains(self::$imageTypes, $fileType);
    }

    /**
     * Returns true if the Image type is invalid
     * @param string $fileName
     * @return bool
     */
    public static function isValidType(string $fileName): bool {
        $type = self::getType($fileName);
        return self::hasType($type);
    }

    /**
     * Returns the Type of the Image
     * @param string $fileName
     * @return int
     */
    public static function getType(string $fileName): int {
        if (URL::isValid($fileName)) {
            $fileName = URL::encode($fileName);
        } elseif (!Storage::fileExists($fileName) || Storage::getFileSize($fileName) === 0) {
            return 0;
        }

        $result = exif_imagetype($fileName);
        return $result === false ? 0 : $result;
    }

    /**
     * Returns the Mime Type of the Image
     * @param string $fileName
     * @return string
     */
    public static function getMimeType(string $fileName): string {
        if (!URL::isValid($fileName) && !Storage::fileExists($fileName)) {
            return "";
        }
        $imageType = self::getType($fileName);
        return image_type_to_mime_type($imageType);
    }

    /**
     * Returns the Size of the Image as [ width, height, type ]
     * @param int|string ...$pathParts
     * @return array{int,int,int}
     */
    public static function getSize(int|string ...$pathParts): array {
        $filePath = Storage::parsePath(...$pathParts);
        if (!Storage::fileExists($filePath) || !FileType::isImage($filePath) ||
            Storage::getFileSize($filePath) === 0
        ) {
            return [ 0, 0, 0 ];
        }

        $size = getimagesize($filePath);
        if ($size === false) {
            return [ 0, 0, 0 ];
        }
        return $size;
    }

    /**
     * Returns the Size of the Image as [ width, height, type ]
     * @param string $fileUrl
     * @return array{int,int,int}
     */
    public static function getSizeFromUrl(string $fileUrl): array {
        if ($fileUrl === "") {
            return [ 0, 0, 0 ];
        }

        $fileUrl = URL::encodeSpaces($fileUrl);
        $size    = getimagesize($fileUrl);
        if ($size === false) {
            return [ 0, 0, 0 ];
        }
        return $size;
    }

    /**
     * Returns the Orientation for the given Image
     * @param int|string ...$pathParts
     * @return int
     */
    public static function getOrientation(int|string ...$pathParts): int {
        if (!Storage::fileExists(...$pathParts)) {
            return 0;
        }

        $filePath = Storage::parsePath(...$pathParts);
        $exif     = exif_read_data($filePath);
        if ($exif !== false && isset($exif["Orientation"])) {
            return Numbers::toInt($exif["Orientation"]);
        }
        return 0;
    }

    /**
     * Returns true if the Image has Transparency
     * @param int|string ...$pathParts
     * @return bool
     */
    public static function hasTransparency(int|string ...$pathParts): bool {
        $filePath = Storage::parsePath(...$pathParts);
        if (!Storage::fileExists($filePath) || !FileType::isPNG($filePath)) {
            return false;
        }

        // Imagick reads the type from the contents, as a photo can have the extension of a
        // PNG, and it throws when the file is cut, where GD only warns
        if (class_exists("Imagick")) {
            try {
                $image = new Imagick($filePath);
                return $image->getImageFormat() === "PNG" &&
                    Strings::toLowerCase((string)$image->identifyFormat("%[opaque]")) === "false";
            } catch (Exception $e) {
                return false;
            }
        }

        if (self::getType($filePath) !== IMAGETYPE_PNG) {
            return false;
        }
        $imgData = imagecreatefrompng($filePath);
        if ($imgData === false) {
            return false;
        }

        $width  = imagesx($imgData);
        $height = imagesy($imgData);

        if ($width > 50 || $height > 50) {
            $thumb = imagecreatetruecolor(10, 10);
            if ($thumb === false) {
                return false;
            }
            imagealphablending($thumb, enable: false);
            imagecopyresized($thumb, $imgData, 0, 0, 0, 0, 10, 10, $width, $height);

            $imgData = $thumb;
            $width   = imagesx($imgData);
            $height  = imagesy($imgData);
        }

        for ($i = 0; $i < $width; $i += 1) {
            for ($j = 0; $j < $height; $j += 1) {
                $rgba = imagecolorat($imgData, $i, $j);
                if ((($rgba & 0x7F000000) >> 24) !== 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns the Width of the given Text
     * @param string $text
     * @param string $fontFile
     * @param int    $fontSize
     * @return int
     */
    public static function getTextWidth(string $text, string $fontFile, int $fontSize): int {
        if (!Storage::fileExists($fontFile)) {
            return 0;
        }

        $dimensions = imagettfbbox($fontSize, 0, $fontFile, $text);
        if ($dimensions === false) {
            return 0;
        }

        $bottomLeft = Numbers::toInt($dimensions[0] ?? 0);
        $topRight   = Numbers::toInt($dimensions[4] ?? 0);
        return abs($topRight - $bottomLeft);
    }

    /**
     * Returns the Luminance of the given pixel of an Image, from 0 to 255
     * @param GdImage $image
     * @param int     $x
     * @param int     $y
     * @return float
     */
    public static function getLuma(GdImage $image, int $x, int $y): float {
        $color = imagecolorat($image, $x, $y);
        if ($color === false) {
            return 255;
        }

        // The green is what the eye sees the most and the blue the least
        $red   = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue  = $color & 0xFF;
        return ($red * 3 + $green * 6 + $blue) / 10;
    }

    /**
     * Returns true if the given part of an Image has a pixel darker than the Luminance
     * @param GdImage $image
     * @param int     $fromX
     * @param int     $toX
     * @param int     $fromY
     * @param int     $toY
     * @param float   $maxLuma Optional.
     * @return bool
     */
    public static function hasInk(
        GdImage $image,
        int $fromX,
        int $toX,
        int $fromY,
        int $toY,
        float $maxLuma = 120,
    ): bool {
        for ($y = $fromY; $y <= $toY; $y += 1) {
            for ($x = $fromX; $x <= $toX; $x += 1) {
                if (self::getLuma($image, $x, $y) < $maxLuma) {
                    return true;
                }
            }
        }
        return false;
    }



    /**
     * Resamples the given image
     * @param string   $src
     * @param string   $dst
     * @param int|null $orientation Optional.
     * @return bool
     */
    public static function resample(string $src, string $dst, ?int $orientation = null): bool {
        if ($orientation === null) {
            $orientation = self::getOrientation($src);
        }
        if ($orientation === 0) {
            return false;
        }

        [ $imgWidth, $imgHeight, $imgType ] = self::getSize($src);
        if (!self::hasType($imgType)) {
            return false;
        }

        // Resample
        $srcImage = self::createSrcImage($imgType, $src);
        $dstImage = self::createDstImage($imgType, $imgWidth, $imgHeight);
        if ($srcImage === null || $dstImage === null) {
            return false;
        }
        self::copyImage(
            dstImage:  $dstImage,
            srcImage:  $srcImage,
            xCorner:   0,
            yCorner:   0,
            dstWidth:  $imgWidth,
            dstHeight: $imgHeight,
            srcWidth:  $imgWidth,
            srcHeight: $imgHeight,
        );

        // Update the Orientation
        $dstImage = match ($orientation) {
            3 => imagerotate($dstImage, 180, 0),
            6 => imagerotate($dstImage, -90, 0),
            8 => imagerotate($dstImage, 90, 0),
            default => $dstImage,
        };
        if ($dstImage === false) {
            return false;
        }

        // Create the Image
        self::createImage($imgType, $dstImage, $dst);
        return true;
    }

    /**
     * Resizes an Image
     * @param string $src
     * @param string $dst
     * @param int    $width
     * @param int    $height
     * @param string $action
     * @return bool
     */
    public static function resize(
        string $src,
        string $dst,
        int $width,
        int $height,
        string $action,
    ): bool {
        [ $imgWidth, $imgHeight, $imgType ] = self::getSize($src);
        if (!self::hasType($imgType)) {
            return false;
        }

        $oldWidth  = 0;
        $oldHeight = 0;
        $xCorner   = 0;
        $yCorner   = 0;

        switch ($action) {
        // Resize to a Size respecting aspect ratio
        case self::Resize:
            $oldWidth  = $imgWidth;
            $oldHeight = $imgHeight;

            if ($imgWidth > $imgHeight) {
                $height = Numbers::roundInt($imgHeight * $width / $imgWidth);
            } else {
                $width = Numbers::roundInt($imgWidth * $height / $imgHeight);
            }
            break;

        // Resize if the image is greater
        case self::Maximum:
            if ($imgWidth > $width || $imgHeight > $height) {
                $oldWidth  = $imgWidth;
                $oldHeight = $imgHeight;

                if ($imgWidth > $imgHeight) {
                    $height = Numbers::roundInt($imgHeight * $width / $imgWidth);
                } else {
                    $width = Numbers::roundInt($imgWidth * $height / $imgHeight);
                }
            } else {
                $width     = $imgWidth;
                $height    = $imgHeight;
                $oldWidth  = $imgWidth;
                $oldHeight = $imgHeight;
            }
            break;

        // Resize to a specific Size
        case self::Thumb:
            $xScale = $imgWidth  / $width;
            $yScale = $imgHeight / $height;

            if ($yScale < $xScale) {
                $oldWidth  = Numbers::roundInt($width  * $yScale);
                $oldHeight = Numbers::roundInt($height * $yScale);
                $xCorner   = Numbers::roundInt(($imgWidth - $oldWidth) / 2);
            } else {
                $oldWidth  = Numbers::roundInt($width  * $xScale);
                $oldHeight = Numbers::roundInt($height * $xScale);
                $yCorner   = Numbers::roundInt(($imgHeight - $oldHeight) / 2);
            }
            break;
        }

        // Creation Process
        $srcResize = self::createSrcImage($imgType, $src);
        $dstResize = self::createDstImage($imgType, $width, $height);
        if ($srcResize === null || $dstResize === null) {
            return false;
        }
        self::copyImage(
            dstImage:  $dstResize,
            srcImage:  $srcResize,
            xCorner:   $xCorner,
            yCorner:   $yCorner,
            dstWidth:  $width,
            dstHeight: $height,
            srcWidth:  $oldWidth,
            srcHeight: $oldHeight,
        );

        // Create Image
        self::createImage($imgType, $dstResize, $dst);
        return true;
    }

    /**
     * Resizes and Crops an Image
     * @param string $src
     * @param string $dst
     * @param int    $resWidth
     * @param int    $resHeight
     * @param int    $cropX
     * @param int    $cropY
     * @param int    $cropWidth
     * @param int    $cropHeight
     * @return bool
     */
    public static function resizeCrop(
        string $src,
        string $dst,
        int $resWidth,
        int $resHeight,
        int $cropX,
        int $cropY,
        int $cropWidth,
        int $cropHeight,
    ): bool {
        [ $imgWidth, $imgHeight, $imgType ] = self::getSize($src);
        if (!self::hasType($imgType) ||
            $resWidth <= 0 || $resHeight <= 0 ||
            $cropWidth <= 0 || $cropHeight <= 0
        ) {
            return false;
        }

        // Resize Image
        $srcResize = self::createSrcImage($imgType, $src);
        $dstResize = self::createDstImage($imgType, $resWidth, $resHeight);
        if ($srcResize === null || $dstResize === null) {
            return false;
        }
        self::copyImage(
            dstImage:  $dstResize,
            srcImage:  $srcResize,
            xCorner:   0,
            yCorner:   0,
            dstWidth:  $resWidth,
            dstHeight: $resHeight,
            srcWidth:  $imgWidth,
            srcHeight: $imgHeight,
        );

        // Crop Image
        $dstCrop = imagecreatetruecolor($cropWidth, $cropHeight);
        if ($dstCrop === false) {
            return false;
        }
        $bgColor = imagecolorallocate($dstCrop, 255, 255, 255);
        if ($bgColor === false) {
            return false;
        }
        imagefill($dstCrop, 0, 0, $bgColor);
        imagecopy($dstCrop, $dstResize, -$cropX, -$cropY, 0, 0, $resWidth, $resHeight);

        // Create Image
        self::createImage($imgType, $dstCrop, $dst);
        return true;
    }

    /**
     * Pixelates a part of an Image
     * @param string $src
     * @param string $dst
     * @param int    $x
     * @param int    $y
     * @param int    $width
     * @param int    $height
     * @param int    $margin Optional.
     * @return bool
     */
    public static function pixelate(
        string $src,
        string $dst,
        int $x,
        int $y,
        int $width,
        int $height,
        int $margin = 2,
    ): bool {
        [ $imgWidth, $imgHeight, $imgType ] = self::getSize($src);
        if (!self::hasType($imgType) || $width <= 0 || $height <= 0) {
            return false;
        }

        // The part to hide grows with the margin and stays inside the Image
        $left   = self::toPixels($x - $margin, $imgWidth);
        $top    = self::toPixels($y - $margin, $imgHeight);
        $right  = self::toPixels($x + $width + $margin, $imgWidth);
        $bottom = self::toPixels($y + $height + $margin, $imgHeight);

        $partWidth  = $right - $left;
        $partHeight = $bottom - $top;
        if ($partWidth <= 0 || $partHeight <= 0) {
            return false;
        }

        $image = self::createSrcImage($imgType, $src);
        $part  = imagecreatetruecolor($partWidth, $partHeight);
        if ($image === null || $part === false) {
            return false;
        }

        // The part is pixelated apart and copied back over the Image, as the
        // filter can only be applied to a whole Image
        imagecopy($part, $image, 0, 0, $left, $top, $partWidth, $partHeight);

        // The advanced mode of the filter can not be named, as it is a variadic argument
        $blockSize  = max(3, Numbers::roundInt($partHeight / 6));
        $isAdvanced = true;
        imagefilter($part, IMG_FILTER_PIXELATE, $blockSize, $isAdvanced);
        imagecopy($image, $part, $left, $top, 0, 0, $partWidth, $partHeight);

        return self::createImage($imgType, $image, $dst);
    }

    /**
     * Returns the given percentage of the given size, inside of it
     * @param int $percent
     * @param int $size
     * @return int
     */
    private static function toPixels(int $percent, int $size): int {
        $result = Numbers::roundInt($size * $percent / 100);
        return max(0, min($size, $result));
    }



    /**
     * Returns the Image Content Type
     * @param int $imgType
     * @return string
     */
    public static function getContentType(int $imgType): string {
        return match ($imgType) {
            1  => "image/gif",
            2  => "image/jpeg",
            3  => "image/png",
            15 => "image/wbmp",
            16 => "image/xbm",
            default => "image/unknown",
        };
    }

    /**
     * Returns the Image Extension
     * @param int $imgType
     * @return string
     */
    public static function getExtension(int $imgType): string {
        return match ($imgType) {
            1  => "gif",
            2  => "jpg",
            3  => "png",
            15 => "bmp",
            16 => "xbm",
            default => "unknown",
        };
    }

    /**
     * Creates an Image based on the Type
     * @param int    $imgType
     * @param string $fileName
     * @return GdImage|null
     */
    public static function createSrcImage(int $imgType, string $fileName): ?GdImage {
        if (!Storage::fileExists($fileName)) {
            return null;
        }

        $result = match ($imgType) {
            1  => imagecreatefromgif($fileName),
            2  => imagecreatefromjpeg($fileName),
            3  => imagecreatefrompng($fileName),
            15 => imagecreatefromwbmp($fileName),
            16 => imagecreatefromxbm($fileName),
            default => null,
        };
        return $result === false ? null : $result;
    }

    /**
     * Creates the Destination Image based on the Type
     * @param int $imgType
     * @param int $width
     * @param int $height
     * @return GdImage|null
     */
    public static function createDstImage(int $imgType, int $width, int $height): ?GdImage {
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $result = imagecreatetruecolor($width, $height);
        if ($result === false) {
            return null;
        }

        if (Arrays::contains(self::$transTypes, $imgType)) {
            imagealphablending($result, enable: false);
            imagesavealpha($result, enable: true);
            $transparent = imagecolorallocatealpha($result, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($result, 0, 0, $width, $height, $transparent);
            }
        }
        return $result;
    }

    /**
     * Creates an Image based on the Type
     * @param int         $imgType
     * @param GdImage     $image
     * @param string|null $fileName Optional.
     * @param int         $quality  Optional.
     * @return bool
     */
    public static function createImage(
        int $imgType,
        GdImage $image,
        ?string $fileName = null,
        int $quality = 90,
    ): bool {
        $result = match ($imgType) {
            1  => imagegif($image, $fileName),
            2  => imagejpeg($image, $fileName, $quality),
            3  => imagepng($image, $fileName),
            15 => imagewbmp($image, $fileName),
            16 => imagexbm($image, $fileName),
            default => false,
        };
        return $result !== false;
    }

    /**
     * Copies and Resamples an Image
     * @param GdImage $dstImage
     * @param GdImage $srcImage
     * @param int     $xCorner
     * @param int     $yCorner
     * @param int     $dstWidth
     * @param int     $dstHeight
     * @param int     $srcWidth
     * @param int     $srcHeight
     * @return void
     */
    private static function copyImage(
        GdImage $dstImage,
        GdImage $srcImage,
        int $xCorner,
        int $yCorner,
        int $dstWidth,
        int $dstHeight,
        int $srcWidth,
        int $srcHeight,
    ): void {
        imagecopyresampled(
            $dstImage,
            $srcImage,
            0,
            0,
            $xCorner,
            $yCorner,
            $dstWidth,
            $dstHeight,
            $srcWidth,
            $srcHeight,
        );
    }



    /**
     * Creates a Thumbnail using ImageMagick
     * @param string $src
     * @param string $dst
     * @param int    $width
     * @param int    $height
     * @param string $action
     * @return bool
     */
    public static function thumbnail(
        string $src,
        string $dst,
        int $width,
        int $height,
        string $action,
    ): bool {
        $bestFit = $action === self::Maximum || $action === self::Thumb;

        // Do not resize if the image is smaller than the requested size
        [ $imgWidth, $imgHeight ] = self::getSize($src);
        if ($bestFit && $imgWidth <= $width && $imgHeight <= $height) {
            // If is a thumb, just copy the file
            if ($action === self::Thumb) {
                Storage::copyFile($src, $dst);
            }
            return true;
        }

        // Use the normal resize if Imagick is not available
        if (!class_exists("Imagick")) {
            return self::resize($src, $dst, $width, $height, $action);
        }

        // Try to resize with Imagick
        try {
            $image = new Imagick($src);
            $image->thumbnailImage($width, $height, $bestFit);
            $image->writeImage($dst);
        } catch (Exception $e) {
            $error = $e->getMessage();

            // Try to resize with GD if there is an error with JPEG
            if (Strings::contains($error, "Not a JPEG")) {
                return self::resize($src, $dst, $width, $height, $action);
            }

            // Log the error
            trigger_error("Imagick Error: $error", E_USER_ERROR);
        }
        return true;
    }
}
