<?php
namespace Framework\ImpExp;

use Framework\File\Storage;
use Framework\Intl\NLS;
use Framework\ImpExp\ExporterWriter;
use Framework\Utils\Arrays;

/**
 * The CSV Writer
 */
class CSVWriter implements ExporterWriter {

    /** @var array<string,string> */
    private array  $headers = [];
    private string $fileName;
    private string $lang;

    /** @var resource|null */
    private mixed  $file = null;
    private int    $line = 0;
    private bool   $hasHeader = false;



    /**
     * Creates a new CSVWriter instance
     * @param string        $fileName
     * @param string        $lang        Optional.
     * @param resource|null $file        Optional.
     * @param bool          $sendHeaders Optional.
     * @param string        $outputPath  Optional.
     */
    public function __construct(
        string $fileName,
        string $lang = "root",
        mixed $file = null,
        bool $sendHeaders = true,
        string $outputPath = "",
    ) {
        $this->fileName = $fileName;
        $this->lang     = $lang;

        if (is_resource($file)) {
            $this->file = $file;
        } elseif ($outputPath !== "") {
            // A file that already has rows was started by an earlier call, which wrote the
            // header, so the new rows are added after the last one
            $this->hasHeader = Storage::getFileSize($outputPath) > 0;
            $sendHeaders     = false;

            $handle = fopen($outputPath, "a");
            if ($handle !== false) {
                $this->file = $handle;
            }
        } else {
            $handle = fopen("php://output", "w");
            if ($handle !== false) {
                $this->file = $handle;
            }
        }

        if ($sendHeaders) {
            $this->start();
        }
    }

    /**
     * Starts the Writer
     * @return void
     */
    private function start(): void {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header("Content-Type: application/csv");
        header("Content-Disposition: attachment; filename=\"{$this->fileName}.csv\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        flush();
    }



    /**
     * Writes the Header
     * @param array<string,string> $headers
     * @return CSVWriter
     */
    #[\Override]
    public function writeHeader(array $headers): CSVWriter {
        $this->headers = $headers;
        if ($this->file !== null && !$this->hasHeader) {
            $values = NLS::getAll(Arrays::getValues($headers), $this->lang);
            fputcsv($this->file, $values);
        }
        return $this;
    }

    /**
     * Writes a Line
     * @param array<string,float|int|string> $line
     * @return CSVWriter
     */
    #[\Override]
    public function writeLine(array $line): CSVWriter {
        if ($this->file === null) {
            return $this;
        }

        $values = [];
        foreach (array_keys($this->headers) as $key) {
            $values[] = $line[$key] ?? "";
        }

        $this->line += 1;
        fputcsv($this->file, $values);
        if ($this->line % 100 === 0) {
            flush();
        }
        return $this;
    }

    /**
     * Downloads the File
     * @return CSVWriter
     */
    #[\Override]
    public function downloadFile(): CSVWriter {
        if ($this->file !== null) {
            fclose($this->file);
            flush();
        }
        return $this;
    }
}
