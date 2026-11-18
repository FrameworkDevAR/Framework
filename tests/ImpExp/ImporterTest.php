<?php
namespace Tests\ImpExp;

use Framework\ImpExp\Importer;
use Framework\Utils\Dictionary;

use PHPUnit\Framework\TestCase;

class ImporterTest extends TestCase {

    private string $path = "";


    protected function setUp(): void {
        $path = tempnam(sys_get_temp_dir(), "importer_test_");
        $this->assertNotFalse($path);
        $this->path = $path . ".csv";
        $this->assertTrue(rename($path, $this->path));

        $this->writeCsvRows([
            [ "Name", "Age", "", "Tags" ],
            [ "Alice", "30", "", "one,two" ],
            [ "Bob", "0", "ignored", "" ],
            [ "Cara", "", "", "last" ],
        ]);
    }

    protected function tearDown(): void {
        if ($this->path !== "" && is_file($this->path)) {
            @unlink($this->path);
        }
    }


    public function testIsValid(): void {
        $this->assertTrue((new Importer($this->path))->isValid());
        $this->assertFalse((new Importer($this->path . ".xlsx"))->isValid());
        $this->assertFalse((new Importer($this->path . ".missing"))->isValid());
    }

    public function testGetHeader(): void {
        $header = (new Importer($this->path))->getHeader();

        $this->assertCount(3, $header);
        $this->assertSame(1, $header[0]->key);
        $this->assertSame("Name", $header[0]->value);
        $this->assertSame(2, $header[1]->key);
        $this->assertSame("Age", $header[1]->value);
        $this->assertSame(4, $header[2]->key);
        $this->assertSame("Tags", $header[2]->value);
    }

    public function testGetData(): void {
        $data = (new Importer($this->path))->getData(2);

        $this->assertCount(3, $data->columns);
        $this->assertSame(3, $data->amount);
        $this->assertSame("Alice - 30", $data->first);
        $this->assertSame("Cara", $data->last);
    }

    public function testSetColumnsWithArray(): void {
        $importer = (new Importer($this->path))->setColumns([
            "name" => 1,
            "tags" => 4,
        ]);

        $importer->rewind();
        $row = $importer->current();

        $this->assertSame("Alice", $row->getString("name"));
        $this->assertSame([ "one", "two" ], $row->getList("tags"));
        $this->assertSame([ "name" => 1, "tags" => 4 ], $importer->getColumns());
    }

    public function testSetColumnsWithDictionary(): void {
        $importer = (new Importer($this->path))->setColumns(new Dictionary([
            "name" => 1,
            "age"  => 2,
        ]));

        $importer->rewind();
        $importer->next();
        $row = $importer->current();

        $this->assertSame("Bob", $row->getString("name"));
        $this->assertSame(0, $row->getInt("age"));
    }

    public function testSetColumnNames(): void {
        $importer = (new Importer($this->path))->setColumnNames("name", "age", "ignored", "tags");

        $importer->rewind();
        $row = $importer->current();

        $this->assertSame("Alice", $row->getString("name"));
        $this->assertSame(30, $row->getInt("age"));
        $this->assertSame(30.0, $row->getFloat("age"));
        $this->assertSame("", $row->getString("missing"));
    }

    public function testIteratorMethods(): void {
        $importer = (new Importer($this->path))->setColumnNames("name", "age", "ignored", "tags");

        $importer->rewind();
        $this->assertTrue($importer->valid());
        $this->assertSame(2, $importer->key());
        $this->assertSame("Alice", $importer->current()->getString("name"));

        $importer->next();
        $this->assertTrue($importer->valid());
        $this->assertSame(3, $importer->key());
        $this->assertSame("Bob", $importer->current()->getString("name"));

        $importer->next();
        $importer->next();
        $this->assertFalse($importer->valid());
    }

    public function testSetRangeIteratesOnlyTheGivenRows(): void {
        $importer = (new Importer($this->path))->setColumnNames("name")->setRange(1, 1);

        $names = [];
        foreach ($importer as $index => $row) {
            $names[$index] = $row->getString("name");
        }
        $this->assertSame([ 3 => "Bob" ], $names);

        $importer->setRange(2, 0);
        $names = [];
        foreach ($importer as $row) {
            $names[] = $row->getString("name");
        }
        $this->assertSame([ "Cara" ], $names);
    }

    public function testInvalidImporter(): void {
        $importer = new Importer($this->path . ".missing");
        $data = $importer->getData();

        $this->assertFalse($importer->isValid());
        $this->assertSame([], $importer->getHeader());
        $this->assertSame(0, $data->amount);
        $this->assertSame("", $data->first);
        $this->assertSame("", $data->last);

        $importer->rewind();
        $importer->next();

        $this->assertSame(0, $importer->key());
        $this->assertFalse($importer->valid());
        $this->assertSame([], $importer->current()->toArray());
    }


    /**
     * @param list<list<string>> $rows
     */
    private function writeCsvRows(array $rows): void {
        $file = fopen($this->path, "w");
        $this->assertNotFalse($file);

        foreach ($rows as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
    }
}
