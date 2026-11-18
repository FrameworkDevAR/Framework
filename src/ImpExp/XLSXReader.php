<?php
namespace Framework\ImpExp;

use Framework\IO\Select;
use Framework\ImpExp\ImporterReader;
use Framework\ImpExp\ImporterData;
use Framework\Utils\Arrays;
use Framework\Utils\Strings;

use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use OpenSpout\Reader\XLSX\RowIterator;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell\FormulaCell;

use Exception;
use ZipArchive;

/**
 * The XLSX Reader
 */
class XLSXReader implements ImporterReader {

    private Reader       $reader;
    private ?Sheet       $sheet;
    private ?RowIterator $iterator = null;



    /**
     * Creates a new XLSXReader instance
     * @param string $path
     */
    public function __construct(string $path) {
        try {
            $options = new Options();
            $options->SHOULD_FORMAT_DATES = true;

            $this->reader = new Reader($options);
            $this->reader->open($path);
            $this->sheet = $this->reader->getSheetIterator()->current();
        } catch (Exception $e) {
            $this->sheet = null;
        }
    }

    /**
     * Returns true if the XLSXReader is available
     * @param string $extension
     * @return bool
     */
    public static function isAvailable(string $extension): bool {
        return (
            Strings::isEqual($extension, "xlsx") ||
            Strings::isEqual($extension, "xls")
        ) && class_exists(Reader::class);
    }



    /**
     * Returns the amount of rows of the first sheet of the given file, with its header
     * @param string $path
     * @return int
     */
    public static function countRows(string $path): int {
        // The rows are counted in the XML of the sheet, without reading their cells, so it
        // takes an instant even when the file is too big for the Reader to read in a Request
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return 0;
        }
        $stream = $zip->getStream(self::getFirstSheetPath($zip));
        if ($stream === false) {
            $zip->close();
            return 0;
        }

        // A tag can be split between two chunks, so only the ones that start before the end
        // of the chunk are counted, and the end is kept for the next one, where it is whole
        $total  = 0;
        $buffer = "";
        while (!feof($stream)) {
            $buffer .= (string)fread($stream, 65536);
            $limit   = feof($stream) ? strlen($buffer) : max(0, strlen($buffer) - 16);
            preg_match_all('/<(?:\w+:)?row[\s>]/', $buffer, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as [ , $offset ]) {
                if ($offset < $limit) {
                    $total += 1;
                }
            }
            $buffer = substr($buffer, $limit);
        }
        fclose($stream);
        $zip->close();
        return $total;
    }

    /**
     * Returns the path of the first sheet of the Workbook inside the file
     * @param ZipArchive $zip
     * @return string
     */
    private static function getFirstSheetPath(ZipArchive $zip): string {
        $workbook  = (string)$zip->getFromName("xl/workbook.xml");
        $relations = (string)$zip->getFromName("xl/_rels/workbook.xml.rels");
        if (preg_match('/<(?:\w+:)?sheet\s[^>]*r:id="([^"]+)"/', $workbook, $sheet) !== 1) {
            return "xl/worksheets/sheet1.xml";
        }

        $pattern = '/<Relationship\s[^>]*Id="' . preg_quote($sheet[1], "/") . '"[^>]*>/';
        if (preg_match($pattern, $relations, $relation) !== 1 ||
            preg_match('/Target="([^"]+)"/', $relation[0] ?? "", $target) !== 1
        ) {
            return "xl/worksheets/sheet1.xml";
        }

        // The Target is relative to the folder of the Workbook, unless it starts with a slash
        if (str_starts_with($target[1], "/")) {
            return substr($target[1], 1);
        }
        return "xl/{$target[1]}";
    }

    /**
     * Returns true if the Reader is valid
     * @return bool
     */
    #[\Override]
    public function isValid(): bool {
        return $this->sheet !== null;
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
        if ($this->sheet === null) {
            return $data;
        }

        $lastRow = null;
        foreach ($this->sheet->getRowIterator() as $index => $row) {
            if ($index < 2) {
                continue;
            }

            if ($index === 2) {
                $data->first = $this->getRowAsString($row, $amount);
                $data->last  = $data->first;
            }
            $data->amount += 1;

            $lastRow = $row;
        }
        if ($lastRow !== null) {
            $data->last = $this->getRowAsString($lastRow, $amount);
        }

        $this->reader->close();
        return $data;
    }

    /**
     * Returns the Header
     * @return list<Select>
     */
    #[\Override]
    public function getHeader(): array {
        $columns = [];
        if ($this->sheet === null) {
            return $columns;
        }

        $iterator = $this->sheet->getRowIterator();
        $iterator->rewind();
        $headerRow = $this->parseRow($iterator->current());

        foreach ($headerRow as $key => $value) {
            if ($value !== "") {
                $columns[] = new Select($key + 1, $value);
            }
        }
        return $columns;
    }

    /**
     * Returns the Content of the Row
     * @param Row $row
     * @param int $amount Optional.
     * @return list<string>
     */
    private function parseRow(Row $row, int $amount = 0): array {
        $cells  = $row->getCells();
        $index  = 0;
        $result = [];

        foreach ($cells as $cell) {
            if ($cell instanceof FormulaCell) {
                $result[] = Strings::toString($cell->getComputedValue());
            } else {
                $result[] = Strings::toString($cell->getValue());
            }

            if ($amount > 0 && $index >= $amount - 1) {
                break;
            }
            $index += 1;
        }
        return $result;
    }

    /**
     * Returns the Row as String
     * @param Row $row
     * @param int $amount Optional.
     * @return string
     */
    private function getRowAsString(Row $row, int $amount = 0): string {
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
        if ($this->sheet === null) {
            return;
        }

        $this->iterator = $this->sheet->getRowIterator();
        $this->iterator->rewind();
        $this->iterator->next();
    }

    /**
     * Returns the current Row
     * @return list<string>
     */
    #[\Override]
    public function current(): array {
        if ($this->iterator !== null) {
            return $this->parseRow($this->iterator->current());
        }
        return [];
    }

    /**
     * Returns the current Key
     * @return int
     */
    #[\Override]
    public function key(): int {
        if ($this->iterator !== null) {
            return $this->iterator->key();
        }
        return 0;
    }

    /**
     * Moves to the next Row
     * @return void
     */
    #[\Override]
    public function next(): void {
        if ($this->iterator !== null) {
            $this->iterator->next();
        }
    }

    /**
     * Returns true if the current Row is valid
     * @return bool
     */
    #[\Override]
    public function valid(): bool {
        if ($this->iterator !== null) {
            return $this->iterator->valid();
        }
        return false;
    }
}
