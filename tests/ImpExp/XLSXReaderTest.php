<?php
namespace Tests\ImpExp;

use Framework\ImpExp\XLSXReader;

use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class XLSXReaderTest extends TestCase {

    private string $path = "";


    protected function setUp(): void {
        $path = tempnam(sys_get_temp_dir(), "xlsx_reader_test_");
        $this->assertNotFalse($path);
        $this->path = $path . ".xlsx";
        $this->assertTrue(unlink($path));

        $this->writeRows([
            [ "Name", "Age", "", "Tags", "Score" ],
            [ "Alice", 30, "", "one,two", 10.5 ],
            [ "Bob", 0, "ignored", "", 0 ],
            [ "Cara", "", "", "last", 2.25 ],
        ]);
    }

    protected function tearDown(): void {
        if ($this->path !== "" && is_file($this->path)) {
            @unlink($this->path);
        }
    }


    public function testCountRowsCountsTheHeaderAndEveryRow(): void {
        $this->assertSame(4, XLSXReader::countRows($this->path));
    }

    public function testCountRowsOfASheetLongerThanAChunk(): void {
        // The XML of the sheet is read in chunks, and a row split between two is counted once
        $rows = [[ "Name", "Email" ]];
        for ($i = 1; $i <= 5000; $i += 1) {
            $rows[] = [ "Name $i", "name-$i@example.com" ];
        }
        $this->writeRows($rows);

        $this->assertSame(5001, XLSXReader::countRows($this->path));
    }

    public function testCountRowsOfAFileThatIsNotAnExcel(): void {
        $this->assertSame(0, XLSXReader::countRows($this->path . ".missing"));
        $this->assertSame(0, XLSXReader::countRows(__FILE__));
    }

    public function testIsAvailable(): void {
        $this->assertTrue(XLSXReader::isAvailable("xlsx"));
        $this->assertTrue(XLSXReader::isAvailable("xls"));
        $this->assertTrue(XLSXReader::isAvailable("XLSX"));
        $this->assertFalse(XLSXReader::isAvailable("csv"));
        $this->assertFalse(XLSXReader::isAvailable(""));
    }

    public function testIsValid(): void {
        $this->skipIfGeneratedXlsxCannotBeRead();

        $this->assertTrue((new XLSXReader($this->path))->isValid());
        $this->assertFalse((new XLSXReader($this->path . ".missing"))->isValid());
    }

    public function testGetHeader(): void {
        $this->skipIfGeneratedXlsxCannotBeRead();

        $header = (new XLSXReader($this->path))->getHeader();

        $this->assertCount(4, $header);
        $this->assertSame(1, $header[0]->key);
        $this->assertSame("Name", $header[0]->value);
        $this->assertSame(2, $header[1]->key);
        $this->assertSame("Age", $header[1]->value);
        $this->assertSame(4, $header[2]->key);
        $this->assertSame("Tags", $header[2]->value);
        $this->assertSame(5, $header[3]->key);
        $this->assertSame("Score", $header[3]->value);
    }

    public function testGetData(): void {
        $this->skipIfGeneratedXlsxCannotBeRead();

        $data = (new XLSXReader($this->path))->getData(2);

        $this->assertCount(4, $data->columns);
        $this->assertSame(3, $data->amount);
        $this->assertSame("Alice - 30", $data->first);
        $this->assertSame("Cara", $data->last);
    }

    public function testGetDataForInvalidReader(): void {
        $reader = new XLSXReader($this->path . ".missing");
        $data = $reader->getData();

        $this->assertFalse($reader->isValid());
        $this->assertSame([], $data->columns);
        $this->assertSame(0, $data->amount);
        $this->assertSame("", $data->first);
        $this->assertSame("", $data->last);
    }

    public function testIterator(): void {
        $this->skipIfGeneratedXlsxCannotBeRead();

        $rows = [];
        $keys = [];

        foreach (new XLSXReader($this->path) as $key => $row) {
            $keys[] = $key;
            $rows[] = $row;
        }

        $this->assertSame([ 2, 3, 4 ], $keys);
        $this->assertSame([
            [ "Alice", "30", "", "one,two", "10.5" ],
            [ "Bob", "0", "ignored", "", "0" ],
            [ "Cara", "", "", "last", "2.25" ],
        ], $rows);
    }

    public function testIteratorForInvalidReader(): void {
        $reader = new XLSXReader($this->path . ".missing");

        $this->assertSame([], $reader->current());
        $this->assertSame(0, $reader->key());
        $this->assertFalse($reader->valid());

        $reader->rewind();
        $reader->next();

        $this->assertSame([], $reader->current());
        $this->assertSame(0, $reader->key());
        $this->assertFalse($reader->valid());
    }

    public function testPrivateParseRowHandlesFormulaCells(): void {
        $reader = new XLSXReader($this->path . ".missing");
        $row = new Row([
            new FormulaCell("=A1", null, "Computed"),
            ...Row::fromValues([ "plain", "skipped" ])->getCells(),
        ]);

        $this->assertSame([ "Computed", "plain" ], $this->callReaderMethod($reader, "parseRow", $row, 2));
    }

    public function testPrivateGetRowAsString(): void {
        $reader = new XLSXReader($this->path . ".missing");
        $row = Row::fromValues([ "Alice", "", 30, "ignored" ]);

        $this->assertSame("Alice - 30", $this->callReaderMethod($reader, "getRowAsString", $row, 3));
        $this->assertSame("", $this->callReaderMethod($reader, "getRowAsString", $row, 0));
    }

    /**
     * @param list<list<float|int|string>> $rows
     */
    private function writeRows(array $rows): void {
        $writer = new Writer();
        $writer->openToFile($this->path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    private function callReaderMethod(XLSXReader $reader, string $method, mixed ...$args): mixed {
        $ref = new ReflectionClass($reader);
        return $ref->getMethod($method)->invoke($reader, ...$args);
    }

    private function skipIfGeneratedXlsxCannotBeRead(): void {
        $reader = new Reader();

        try {
            $reader->open($this->path);
            $sheet = $reader->getSheetIterator()->current();
            if ($sheet === null) {
                $this->markTestSkipped("OpenSpout cannot read the generated XLSX fixture.");
            }

            $iterator = $sheet->getRowIterator();
            $iterator->rewind();
            $iterator->current();
            $reader->close();
        } catch (\Throwable $e) {
            $this->markTestSkipped("OpenSpout cannot read the generated XLSX fixture: " . $e->getMessage());
        }
    }
}
