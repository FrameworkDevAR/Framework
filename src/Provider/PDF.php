<?php
namespace Framework\Provider;

use Framework\File\FilePath;
use Framework\File\FileType;
use Framework\File\Image;
use Framework\File\Storage;
use Framework\Utils\Strings;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The PDF Provider
 */
class PDF {

    /**
     * Creates a PDF from the given HTML and returns its content
     * @param string $html
     * @param string $title           Optional.
     * @param bool   $withPageNumbers Optional.
     * @param string $fontFamily      Optional.
     * @return string
     */
    public static function create(
        string $html,
        string $title = "",
        bool $withPageNumbers = false,
        string $fontFamily = "",
    ): string {
        $options = new Options();
        $options->setIsRemoteEnabled(isRemoteEnabled: true);
        $options->setFontDir(FilePath::getSystemTempPath());
        $options->setFontCache(FilePath::getSystemTempPath());

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper("A4");
        $pdf->render();

        if ($title !== "") {
            $pdf->addInfo("Title", $title);
        }

        if ($withPageNumbers) {
            $pdf->getCanvas()->page_text(
                x:     540.0,
                y:     812.0,
                text:  "{PAGE_NUM} / {PAGE_COUNT}",
                font:  $pdf->getFontMetrics()->getFont($fontFamily) ?? "",
                size:  9.0,
                color: [ 0.4, 0.4, 0.4 ],
            );
        }

        return $pdf->output();
    }



    /**
     * Returns the Image at the given url as a data uri
     * @param string $url
     * @return string
     */
    public static function getImageData(string $url): string {
        $content = Storage::readUrl($url);
        if ($content === "") {
            return "";
        }

        // The size of an SVG can not be read, so its type is known from its extension
        $mimeType = Image::getMimeType($url);
        if (FileType::isSVG($url)) {
            $mimeType = FileType::getMimeType($url);
        } elseif ($mimeType === "") {
            $mimeType = "image/png";
        }

        $base64 = Strings::base64Encode($content);
        return "data:{$mimeType};base64,{$base64}";
    }

    /**
     * Returns the Image at the given url cropped to its content as a data uri
     * @param string $url
     * @param int    $padding Optional.
     * @return string
     */
    public static function getCroppedImageData(string $url, int $padding = 8): string {
        // An SVG is not an image that can be opened, so it is given whole
        // rather than tried, which would warn on every one
        if (FileType::isSVG($url)) {
            return self::getImageData($url);
        }

        $content = Storage::readUrl($url);
        if ($content === "") {
            return "";
        }

        $image = imagecreatefromstring($content);
        if ($image === false) {
            return self::getImageData($url);
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $minX   = $width;
        $minY   = $height;
        $maxX   = 0;
        $maxY   = 0;

        // Find the bounding box of the non-white pixels
        for ($y = 0; $y < $height; $y += 1) {
            for ($x = 0; $x < $width; $x += 1) {
                $rgb = imagecolorat($image, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $grn = ($rgb >> 8) & 0xFF;
                $blu = $rgb & 0xFF;
                if ($red < 240 || $grn < 240 || $blu < 240) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        // There are no dark pixels
        if ($maxX < $minX || $maxY < $minY) {
            return self::getImageData($url);
        }

        // Add a small padding to the content bounding box
        $minX = max(0, $minX - $padding);
        $minY = max(0, $minY - $padding);
        $maxX = min($width - 1, $maxX + $padding);
        $maxY = min($height - 1, $maxY + $padding);

        // Enforce a minimum size, half of the original
        $centerX = ($minX + $maxX) / 2;
        $centerY = ($minY + $maxY) / 2;
        $cropW   = max($maxX - $minX + 1, intdiv($width, 2));
        $cropH   = max($maxY - $minY + 1, intdiv($height, 2));

        // Respect the original aspect ratio
        if ($cropW / $cropH > $width / $height) {
            $cropH = (int)round($cropW * $height / $width);
        } else {
            $cropW = (int)round($cropH * $width / $height);
        }

        // Center the crop and keep it inside the image
        $cropX = (int)round($centerX - $cropW / 2);
        $cropY = (int)round($centerY - $cropH / 2);
        $cropX = max(0, min($cropX, $width - $cropW));
        $cropY = max(0, min($cropY, $height - $cropH));

        $cropped = imagecrop($image, [
            "x"      => $cropX,
            "y"      => $cropY,
            "width"  => $cropW,
            "height" => $cropH,
        ]);
        if ($cropped === false) {
            return self::getImageData($url);
        }

        ob_start();
        imagepng($cropped);
        $result = ob_get_clean();
        if ($result === false) {
            return self::getImageData($url);
        }

        $base64 = Strings::base64Encode($result);
        return "data:image/png;base64,{$base64}";
    }
}
