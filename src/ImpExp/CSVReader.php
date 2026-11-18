<?php
namespace Framework\ImpExp;

use Framework\IO\Select;
use Framework\ImpExp\ImporterReader;
use Framework\ImpExp\ImporterData;
use Framework\Utils\Arrays;
use Framework\Utils\CSV;
use Framework\Utils\Strings;

/**
 * The CSV Reader
 */
class CSVReader implements ImporterReader {

    private bool $isValid = false;

    /** @var resource|null */
    private mixed $file = null;

    /** @var list<string> */
    private array $currentRow = [];

    private int $position = 0;



    /**
     * Creates a new CSVReader instance
     * @param string $path
     */
    public function __construct(string $path) {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $file = fopen($path, "r");
        if ($file !== false) {
            $this->file    = $file;
            $this->isValid = true;
        }
    }

    /**
     * Closes the file if open
     */
    public function __destruct() {
        if (is_resource($this->file)) {
            fclose($this->file);
        }
    }

    /**
     * Returns true if the CSVReader is available
     * @param string $extension
     * @return bool
     */
    public static function isAvailable(string $extension): bool {
        return Strings::isEqual($extension, "csv");
    }



    /**
     * Returns true if the Reader is valid
     * @return bool
     */
    #[\Override]
    public function isValid(): bool {
        return $this->isValid;
    }

    /**
     * Returns some data
     * @param int $amount Optional.
     * @return ImporterData
     */
    #[\Override]
    public function getData(int $amount = 3): ImporterData {
        $data = new ImporterData(
            columns: $this->getHeader(),
            amount:  0,
            first:   "",
            last:    "",
        );
        if ($this->file === null) {
            return $data;
        }

        rewind($this->file);
        $this->readLine();

        $firstRow = $this->readLine();
        if ($firstRow === null) {
            return $data;
        }

        $lastRow = $this->readLastLine() ?? $firstRow;

        $data->amount = $this->getLinesAmount();
        $data->first  = $this->getRowAsString($firstRow, $amount);
        $data->last   = $this->getRowAsString($lastRow, $amount);
        return $data;
    }

    /**
     * Returns the Header
     * @return list<Select>
     */
    #[\Override]
    public function getHeader(): array {
        $columns = [];
        if ($this->file === null) {
            return $columns;
        }

        rewind($this->file);
        $headerRow = $this->readLine();
        if ($headerRow === null) {
            return $columns;
        }

        foreach ($headerRow as $key => $value) {
            if ($value !== "") {
                $columns[] = new Select($key + 1, $value);
            }
        }
        return $columns;
    }



    /**
     * Reads one line from the CSV file
     * @return list<string>|null
     */
    private function readLine(): ?array {
        if ($this->file === null) {
            return null;
        }

        $row = fgetcsv($this->file, escape: "\\");
        if ($row === false) {
            return null;
        }
        return $this->parseRow($row);
    }

    /**
     * Reads the last line from the CSV file
     * @return list<string>|null
     */
    private function readLastLine(): ?array {
        if ($this->file === null) {
            return null;
        }

        $stats = fstat($this->file);
        if ($stats === false || $stats["size"] === 0) {
            return null;
        }

        $position = $stats["size"] - 1;
        $line     = "";

        // Ignore trailing line breaks so the last row can be found even when the
        // file ends with one or more newlines.
        while ($position >= 0) {
            fseek($this->file, $position);
            $char = fgetc($this->file);

            if ($char !== "\n" && $char !== "\r") {
                break;
            }
            $position -= 1;
        }

        // Walk backward until the previous line break, prepending each character
        // so the final string remains in normal left-to-right order.
        while ($position >= 0) {
            fseek($this->file, $position);
            $char = fgetc($this->file);

            if ($char === "\n" || $char === "\r") {
                break;
            }

            $line      = $char . $line;
            $position -= 1;
        }

        if ($line === "") {
            return null;
        }

        // Parse only the last physical CSV line without scanning the full file.
        return $this->parseRow(CSV::parse($line));
    }

    /**
     * Returns the amount of data lines in the CSV file
     * @return int
     */
    private function getLinesAmount(): int {
        if ($this->file === null) {
            return 0;
        }

        $stats = fstat($this->file);
        if ($stats === false || $stats["size"] === 0) {
            return 0;
        }

        rewind($this->file);

        $lines = 0;
        $lastChar = "";
        while (!feof($this->file)) {
            $chunk         = (string)fread($this->file, 8192);
            $lines        += Strings::countOccurrences($chunk, "\n");
            $lastChunkChar = substr($chunk, -1);
            $lastChar      = $lastChunkChar !== "" ? $lastChunkChar : $lastChar;
        }

        if ($lastChar !== "\n" && $lastChar !== "\r") {
            $lines += 1;
        }

        return max(0, $lines - 1);
    }

    /**
     * Returns the Content of the Row
     * @param array<int,string|null> $row
     * @param int                    $amount Optional.
     * @return list<string>
     */
    private function parseRow(array $row, int $amount = 0): array {
        $result = [];
        foreach ($row as $index => $value) {
            if ($amount > 0 && $index >= $amount) {
                break;
            }
            $result[] = Strings::toString($value);
        }
        return $result;
    }

    /**
     * Returns the Row as String
     * @param list<string> $row
     * @param int          $amount Optional.
     * @return string
     */
    private function getRowAsString(array $row, int $amount = 0): string {
        $values = $this->parseRow($row, $amount);
        $fields = [];
        for ($i = 0; $i < $amount; $i += 1) {
            if (isset($values[$i]) && !Arrays::isEmpty($values, $i)) {
                $fields[] = $values[$i];
            }
        }
        return Strings::join($fields, " - ");
    }



    /**
     * Starts the Iterator
     * @return void
     */
    #[\Override]
    public function rewind(): void {
        $this->currentRow = [];
        $this->position   = 1;

        if ($this->file === null) {
            return;
        }

        rewind($this->file);
        $this->readLine();
        $this->next();
    }

    /**
     * Returns the current Row
     * @return list<string>
     */
    #[\Override]
    public function current(): array {
        return $this->currentRow;
    }

    /**
     * Returns the current Key
     * @return int
     */
    #[\Override]
    public function key(): int {
        return $this->position;
    }

    /**
     * Moves to the next Row
     * @return void
     */
    #[\Override]
    public function next(): void {
        if ($this->file === null) {
            $this->currentRow = [];
            return;
        }

        $row = $this->readLine();
        if ($row === null) {
            $this->currentRow = [];
            return;
        }

        $this->position  += 1;
        $this->currentRow = $row;
    }

    /**
     * Returns true if the current Row is valid
     * @return bool
     */
    #[\Override]
    public function valid(): bool {
        return $this->currentRow !== [];
    }
}
