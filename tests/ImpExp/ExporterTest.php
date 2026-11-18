<?php
namespace Tests\ImpExp;

use Framework\Database\Type\SchemaRequest;
use Framework\ImpExp\CSVWriter;
use Framework\ImpExp\Exporter;
use Framework\ImpExp\XLSXWriter;

use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class ExporterTest extends TestCase {
    use TestHelpers;


    /** @var resource|null */
    private mixed $file = null;


    protected function tearDown(): void {
        if (is_resource($this->file)) {
            fclose($this->file);
        }
    }


    public function testConstructorUsesCSVWriter(): void {
        $exporter = new Exporter(10, "Export", "export", useCSV: true, sendHeaders: false);

        $this->assertInstanceOf(CSVWriter::class, $this->getPrivateProperty($exporter, "writer"));
    }

    public function testConstructorUsesXLSXWriter(): void {
        $exporter = new Exporter(10, "Export", "export");

        $this->assertInstanceOf(XLSXWriter::class, $this->getPrivateProperty($exporter, "writer"));
    }

    public function testHeaders(): void {
        $exporter = $this->newExporter(total: 10);
        $result   = $exporter->addHeaders([
            "name" => "Name",
            "age"  => "Age",
        ])
            ->addHeader("city", "City")
            ->addHeader("hidden", "Hidden", false)
            ->removeHeaders([ "age" ])
            ->writeHeader();

        $this->assertSame($exporter, $result);
        $this->assertSame("Name,City\n", $this->getOutput());
    }

    public function testStartChunk(): void {
        $request = new SchemaRequest();
        $exporter = $this->newExporter(total: 10);

        $first = $exporter->startChunk($request, 3);
        $this->assertSame($exporter, $first);
        $this->assertSame(3, $request->amount);
        $this->assertSame(0, $request->page);
        $this->assertFalse($exporter->isComplete());

        $exporter->startChunk($request, 7);
        $this->assertSame(7, $request->amount);
        $this->assertSame(1, $request->page);
        $this->assertTrue($exporter->isComplete());
    }

    public function testWriteLine(): void {
        $exporter = $this->newExporter(total: 2)
            ->addHeaders([
                "name" => "Name",
                "age"  => "Age",
            ])->writeHeader();

        $first = $exporter->writeLine([
            "name" => "Alice",
            "age"  => 30,
        ]);
        $this->assertSame($exporter, $first);
        $this->assertFalse($exporter->isComplete());

        $second = $exporter->writeLine([
            "name" => "Bob",
            "age"  => 0,
        ]);

        $this->assertSame($exporter, $second);
        $this->assertTrue($exporter->isComplete());
        $this->assertSame("Name,Age\nAlice,30\nBob,0\n", $this->getOutput());
    }

    public function testSaveWritesTheOutputPath(): void {
        $path = tempnam(sys_get_temp_dir(), "csv");
        $this->assertNotFalse($path);

        try {
            $exporter = new Exporter(1, "Export", "export", useCSV: true, outputPath: $path);
            $exporter->addHeader("name", "Name")->writeHeader()->writeLine([ "name" => "Alice" ]);

            $this->assertSame($exporter, $exporter->save());
            $this->assertSame("Name\nAlice\n", file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testDownload(): void {
        $exporter = $this->newDownloadExporter(total: 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("terminated");

        $exporter->download();
    }


    private function newExporter(int $total): Exporter {
        $this->file = fopen("php://memory", "w+");
        $this->assertNotFalse($this->file);

        $ref = new ReflectionClass(Exporter::class);
        $exporter = $ref->newInstanceWithoutConstructor();

        $this->setPrivateProperty($exporter, "total", $total);
        $this->setPrivateProperty($exporter, "headers", []);
        $this->setPrivateProperty($exporter, "writer", new CSVWriter("export", "root", $this->file, sendHeaders: false));
        $this->setPrivateProperty($exporter, "requests", 0);
        $this->setPrivateProperty($exporter, "page", 0);
        $this->setPrivateProperty($exporter, "line", 0);

        return $exporter;
    }

    private function newDownloadExporter(int $total): ExporterTestExporter {
        $this->file = fopen("php://memory", "w+");
        $this->assertNotFalse($this->file);

        $ref = new ReflectionClass(ExporterTestExporter::class);
        $exporter = $ref->newInstanceWithoutConstructor();

        $this->setPrivateProperty($exporter, "total", $total);
        $this->setPrivateProperty($exporter, "headers", []);
        $this->setPrivateProperty($exporter, "writer", new CSVWriter("export", "root", $this->file, sendHeaders: false));
        $this->setPrivateProperty($exporter, "requests", 0);
        $this->setPrivateProperty($exporter, "page", 0);
        $this->setPrivateProperty($exporter, "line", 0);

        return $exporter;
    }

    private function getOutput(): string {
        $this->assertIsResource($this->file);
        rewind($this->file);
        return stream_get_contents($this->file);
    }

    private function getPrivateProperty(object $obj, string $name): mixed {
        $ref = new ReflectionClass($obj);
        $property = $ref->getProperty($name);
        return $property->getValue($obj);
    }

    private function setPrivateProperty(object $obj, string $name, mixed $value): void {
        $ref = new ReflectionClass($obj);
        while (!$ref->hasProperty($name)) {
            $parent = $ref->getParentClass();
            $this->assertNotFalse($parent);
            $ref = $parent;
        }

        $property = $ref->getProperty($name);
        $property->setValue($obj, $value);
    }
}

class ExporterTestExporter extends Exporter {

    protected function terminate(): never {
        throw new RuntimeException("terminated");
    }
}
