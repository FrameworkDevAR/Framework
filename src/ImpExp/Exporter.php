<?php
namespace Framework\ImpExp;

use Framework\Database\Type\SchemaRequest;
use Framework\Intl\NLS;
use Framework\ImpExp\ExporterWriter;
use Framework\ImpExp\XLSXWriter;
use Framework\ImpExp\CSVWriter;
use Framework\Date\Date;
use Framework\Date\Type\DateFormat;

/**
 * The Exporter Wrapper
 */
class Exporter {

    private string         $fileName;
    private int            $total;

    /** @var array<string,string> */
    private array          $headers;

    private ExporterWriter $writer;

    private int $requests = 0;
    private int $page     = 0;
    private int $line     = 0;


    /**
     * Creates a new Exporter instance
     * @param int    $total
     * @param string $title
     * @param string $fileName
     * @param string $lang        Optional.
     * @param bool   $useCSV      Optional.
     * @param bool   $sendHeaders Optional.
     * @param string $outputPath  Optional.
     * @param bool   $skipHeader  Optional.
     */
    public function __construct(
        int $total,
        string $title,
        string $fileName,
        string $lang = "root",
        bool $useCSV = false,
        bool $sendHeaders = true,
        string $outputPath = "",
        bool $skipHeader = false,
    ) {
        $this->fileName  = NLS::getString($fileName, $lang);
        $this->fileName .= "_" . Date::now()->toString(DateFormat::Reverse);

        $this->total    = $total;
        $this->headers  = [];

        if (XLSXWriter::isAvailable() && !$useCSV) {
            $this->writer = new XLSXWriter(
                title:      $title,
                fileName:   $this->fileName,
                lang:       $lang,
                outputPath: $outputPath,
            );
        } else {
            $this->writer = new CSVWriter(
                fileName:    $this->fileName,
                lang:        $lang,
                sendHeaders: $sendHeaders,
                outputPath:  $outputPath,
                skipHeader:  $skipHeader,
            );
        }
    }



    /**
     * Adds multiple Headers
     * @param array<string,string> $headers
     * @return Exporter
     */
    public function addHeaders(array $headers): Exporter {
        foreach ($headers as $key => $value) {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    /**
     * Adds a single Header
     * @param string $key
     * @param string $value
     * @param bool   $condition Optional.
     * @return Exporter
     */
    public function addHeader(
        string $key,
        string $value,
        bool $condition = true,
    ): Exporter {
        if ($condition) {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    /**
     * Removes multiple Headers
     * @param list<string> $headers
     * @return Exporter
     */
    public function removeHeaders(array $headers): Exporter {
        foreach ($headers as $key) {
            unset($this->headers[$key]);
        }
        return $this;
    }

    /**
     * Writes the Header
     * @return Exporter
     */
    public function writeHeader(): Exporter {
        $this->writer->writeHeader($this->headers);
        return $this;
    }



    /**
     * Starts a Chunk to Export
     * @param SchemaRequest $request
     * @param int           $perPage Optional.
     * @return Exporter
     */
    public function startChunk(SchemaRequest $request, int $perPage = 2000): Exporter {
        $request->amount = $perPage;
        $request->page   = $this->page;

        $this->page     += 1;
        $this->requests += $perPage;
        return $this;
    }

    /**
     * Returns true ig the report is complete
     * @return bool
     */
    public function isComplete(): bool {
        return $this->requests >= $this->total || $this->line >= $this->total;
    }

    /**
     * Writes a Line
     * @param array<string,float|int|string> $line
     * @return Exporter
     */
    public function writeLine(array $line): Exporter {
        $this->line += 1;
        $this->writer->writeLine($line);
        return $this;
    }

    /**
     * Closes the File written to the Output Path
     * @return Exporter
     */
    public function save(): Exporter {
        $this->writer->downloadFile();
        return $this;
    }

    /**
     * Downloads the File
     * @return never
     */
    public function download(): never {
        $this->writer->downloadFile();
        $this->terminate();
    }

    /**
     * Terminates the current request
     * @return never
     */
    protected function terminate(): never {
        exit();
    }
}
