<?php
namespace Tests\ImpExp;

use Framework\ImpExp\CSVReader;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CSVReaderTest extends TestCase {
    use TestHelpers;

    private string $path = "";


    protected function setUp(): void {
        $path = tempnam(sys_get_temp_dir(), "csv_reader_test_");
        $this->assertNotFalse($path);
        $this->path = $path . ".csv";
        $this->assertTrue(rename($path, $this->path));

        $file = fopen($this->path, "w");
        $this->assertNotFalse($file);

        fputcsv($file, [ "Name", "Age", "", "Tags" ], escape: "\\");
        fputcsv($file, [ "Alice", "30", "", "one,two" ], escape: "\\");
        fputcsv($file, [ "Bob", "0", "ignored", "" ], escape: "\\");
        fputcsv($file, [ "Cara", "", "", "last" ], escape: "\\");

        fclose($file);
    }

    protected function tearDown(): void {
        if ($this->path !== "" && is_file($this->path)) {
            @unlink($this->path);
        }
    }


    public function testIsAvailable(): void {
        $this->assertTrue(CSVReader::isAvailable("csv"));
        $this->assertTrue(CSVReader::isAvailable("CSV"));
        $this->assertFalse(CSVReader::isAvailable("xls"));
        $this->assertFalse(CSVReader::isAvailable(""));
    }

    public function testIsValid(): void {
        $this->assertTrue((new CSVReader($this->path))->isValid());
        $this->assertFalse((new CSVReader($this->path . ".missing"))->isValid());
    }

    public function testGetHeader(): void {
        $header = (new CSVReader($this->path))->getHeader();

        $this->assertCount(3, $header);
        $this->assertSame(1, $header[0]->key);
        $this->assertSame("Name", $header[0]->value);
        $this->assertSame(2, $header[1]->key);
        $this->assertSame("Age", $header[1]->value);
        $this->assertSame(4, $header[2]->key);
        $this->assertSame("Tags", $header[2]->value);
    }

    public function testGetData(): void {
        $data = (new CSVReader($this->path))->getData(2);

        $this->assertCount(3, $data->columns);
        $this->assertSame(3, $data->amount);
        $this->assertSame("Alice - 30", $data->first);
        $this->assertSame("Cara", $data->last);
    }

    public function testGetDataForInvalidReader(): void {
        $data = (new CSVReader($this->path . ".missing"))->getData();

        $this->assertSame([], $data->columns);
        $this->assertSame(0, $data->amount);
        $this->assertSame("", $data->first);
        $this->assertSame("", $data->last);
    }

    public function testGetDataForEmptyFile(): void {
        file_put_contents($this->path, "");

        $reader = new CSVReader($this->path);
        $data = $reader->getData();

        $this->assertTrue($reader->isValid());
        $this->assertSame([], $reader->getHeader());
        $this->assertSame(0, $data->amount);
        $this->assertSame("", $data->first);
        $this->assertSame("", $data->last);
    }

    public function testGetDataForHeaderOnlyFile(): void {
        $this->writeCsvRows([
            [ "Name", "Age" ],
        ]);

        $data = (new CSVReader($this->path))->getData();

        $this->assertCount(2, $data->columns);
        $this->assertSame(0, $data->amount);
        $this->assertSame("", $data->first);
        $this->assertSame("", $data->last);
    }

    public function testGetDataForSingleDataRow(): void {
        $this->writeCsvRows([
            [ "Name", "Age" ],
            [ "Alice", "30" ],
        ]);

        $data = (new CSVReader($this->path))->getData(2);

        $this->assertSame(1, $data->amount);
        $this->assertSame("Alice - 30", $data->first);
        $this->assertSame("Alice - 30", $data->last);
    }

    public function testGetDataForFileWithoutFinalNewline(): void {
        file_put_contents($this->path, "Name,Age\nAlice,30\nBob,40");

        $data = (new CSVReader($this->path))->getData(2);

        $this->assertSame(2, $data->amount);
        $this->assertSame("Alice - 30", $data->first);
        $this->assertSame("Bob - 40", $data->last);
    }

    public function testIterator(): void {
        $rows = [];
        $keys = [];

        foreach (new CSVReader($this->path) as $key => $row) {
            $keys[] = $key;
            $rows[] = $row;
        }

        $this->assertSame([ 2, 3, 4 ], $keys);
        $this->assertSame([
            [ "Alice", "30", "", "one,two" ],
            [ "Bob", "0", "ignored", "" ],
            [ "Cara", "", "", "last" ],
        ], $rows);
    }

    public function testIteratorForInvalidReader(): void {
        $reader = new CSVReader($this->path . ".missing");

        $this->assertSame([], $reader->current());
        $this->assertSame(0, $reader->key());
        $this->assertFalse($reader->valid());

        $reader->rewind();
        $reader->next();

        $this->assertSame([], $reader->current());
        $this->assertSame(1, $reader->key());
        $this->assertFalse($reader->valid());

        $rows = [];
        foreach ($reader as $row) {
            $rows[] = $row;
        }

        $this->assertSame([], $rows);
    }

    public function testMethodsWithMissingHandle(): void {
        $reader = new CSVReader($this->path);
        $this->setPrivateProperty($reader, "file", null);

        $reader->rewind();
        $reader->next();

        $this->assertSame([], $reader->getHeader());
        $this->assertSame(0, $reader->getData()->amount);
        $this->assertSame([], $reader->current());
        $this->assertFalse($reader->valid());
    }

    public function testPrivateReadersHandleEmptyState(): void {
        $reader = new CSVReader($this->path);
        $this->setPrivateProperty($reader, "file", null);

        $this->assertNull($this->callReaderMethod($reader, "readLine"));
        $this->assertNull($this->callReaderMethod($reader, "readLastLine"));
        $this->assertSame(0, $this->callReaderMethod($reader, "getLinesAmount"));

        file_put_contents($this->path, "");
        $emptyReader = new CSVReader($this->path);
        $this->assertNull($this->callReaderMethod($emptyReader, "readLastLine"));

        file_put_contents($this->path, "\n\n");
        $blankReader = new CSVReader($this->path);
        $this->assertNull($this->callReaderMethod($blankReader, "readLastLine"));
    }


    private function writeCsvRows(array $rows): void {
        $file = fopen($this->path, "w");
        $this->assertNotFalse($file);

        foreach ($rows as $row) {
            fputcsv($file, $row, escape: "\\");
        }

        fclose($file);
    }

    private function callReaderMethod(CSVReader $reader, string $method): mixed {
        $ref = new ReflectionClass($reader);
        return $ref->getMethod($method)->invoke($reader);
    }
}
