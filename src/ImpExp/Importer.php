<?php
namespace Framework\ImpExp;

use Framework\IO\Select;
use Framework\ImpExp\ImporterReader;
use Framework\ImpExp\ImporterData;
use Framework\ImpExp\ImporterRow;
use Framework\ImpExp\InvalidReader;
use Framework\ImpExp\CSVReader;
use Framework\ImpExp\XLSXReader;
use Framework\File\Storage;
use Framework\Utils\Dictionary;

use Iterator;

/**
 * The Importer Wrapper
 * @implements Iterator<int,ImporterRow>
 */
class Importer implements Iterator {

    private ImporterReader $reader;

    /** @var array<string,int> */
    private array $columns = [];

    private int $start    = 0;
    private int $amount   = 0;
    private int $position = 0;



    /**
     * Creates a new Importer instance
     * @param string $path
     */
    public function __construct(string $path) {
        $extension = Storage::getExtension($path);
        if (CSVReader::isAvailable($extension)) {
            $this->reader = new CSVReader($path);
        } elseif (XLSXReader::isAvailable($extension)) {
            $this->reader = new XLSXReader($path);
        } else {
            $this->reader = new InvalidReader();
        }
    }

    /**
     * Returns true if the Importer is valid
     * @return bool
     */
    public function isValid(): bool {
        return $this->reader->isValid();
    }

    /**
     * Returns some data
     * @param int $amount Optional.
     * @return ImporterData
     */
    public function getData(int $amount = 3): ImporterData {
        return $this->reader->getData($amount);
    }

    /**
     * Returns the Header
     * @return list<Select>
     */
    public function getHeader(): array {
        return $this->reader->getHeader();
    }

    /**
     * Sets the Columns
     * @param Dictionary|array<string,int> $columns
     * @return Importer
     */
    public function setColumns(Dictionary|array $columns): Importer {
        if ($columns instanceof Dictionary) {
            $columns = $columns->toStringIntMap();
        }
        foreach ($columns as $key => $index) {
            $this->columns[$key] = $index;
        }
        return $this;
    }

    /**
     * Returns the Columns, with the index of the column of the file of each key
     * @return array<string,int>
     */
    public function getColumns(): array {
        return $this->columns;
    }

    /**
     * Sets the Column Names
     * @param string ...$columnsKeys
     * @return Importer
     */
    public function setColumnNames(string ...$columnsKeys): Importer {
        foreach ($columnsKeys as $index => $key) {
            $this->columns[$key] = (int)$index + 1;
        }
        return $this;
    }


    /**
     * Sets the Range of the rows to iterate, counted from the first row after the header
     * @param int $start
     * @param int $amount
     * @return Importer
     */
    public function setRange(int $start, int $amount): Importer {
        $this->start  = $start;
        $this->amount = $amount;
        return $this;
    }



    /**
     * Starts the Iterator
     * @return void
     */
    #[\Override]
    public function rewind(): void {
        $this->reader->rewind();
        $this->position = 0;
        while ($this->position < $this->start && $this->reader->valid()) {
            $this->next();
        }
    }

    /**
     * Returns the current Row
     * @return ImporterRow
     */
    #[\Override]
    public function current(): ImporterRow {
        $data = $this->reader->current();
        // @phpstan-ignore argument.type
        return new ImporterRow($data, $this->columns);
    }

    /**
     * Returns the current Key
     * @return int
     */
    #[\Override]
    public function key(): int {
        return $this->reader->key();
    }

    /**
     * Moves to the next Row
     * @return void
     */
    #[\Override]
    public function next(): void {
        $this->reader->next();
        $this->position += 1;
    }

    /**
     * Returns true if the current Row is valid
     * @return bool
     */
    #[\Override]
    public function valid(): bool {
        if ($this->amount > 0 && $this->position >= $this->start + $this->amount) {
            return false;
        }
        return $this->reader->valid();
    }
}
