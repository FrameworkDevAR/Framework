<?php
namespace Tests\ImpExp;

use Framework\ImpExp\CSVWriter;
use Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class CSVWriterTest extends TestCase {
    use TestHelpers;

    public function testWriteHeader(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $result = $writer->writeHeader([
            "name" => "Name",
            "age"  => "Age",
        ]);

        rewind($file);

        $this->assertSame($writer, $result);
        $this->assertSame("Name,Age\n", stream_get_contents($file));
    }

    public function testWriteLine(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $result = $writer->writeHeader([
            "name" => "Name",
            "age"  => "Age",
            "city" => "City",
        ])->writeLine([
            "name" => "Alice",
            "age"  => 30,
            "city" => "Paris",
        ])->writeLine([
            "name" => "Bob",
            "age"  => 0,
        ]);

        rewind($file);

        $this->assertSame($writer, $result);
        $this->assertSame(
            "Name,Age,City\nAlice,30,Paris\nBob,0,\n",
            stream_get_contents($file),
        );
    }

    public function testOutputPathWritesTheHeaderOnce(): void {
        $path = tempnam(sys_get_temp_dir(), "csv");
        $this->assertNotFalse($path);

        try {
            $headers = [ "name" => "Name" ];
            $first   = new CSVWriter("export", "root", outputPath: $path);
            $first->writeHeader($headers)->writeLine([ "name" => "Alice" ])->downloadFile();

            $second = new CSVWriter("export", "root", outputPath: $path);
            $second->writeHeader($headers)->writeLine([ "name" => "Bob" ])->downloadFile();

            $this->assertSame("Name\nAlice\nBob\n", file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testSkipHeaderWritesOnlyTheLines(): void {
        $path = tempnam(sys_get_temp_dir(), "csv");
        $this->assertNotFalse($path);

        try {
            $writer = new CSVWriter("export", "root", outputPath: $path, skipHeader: true);
            $writer->writeHeader([ "name" => "Name" ])->writeLine([ "name" => "Bob" ]);
            $writer->downloadFile();

            $this->assertSame("Bob\n", file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testDownloadFile(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $result = $writer->downloadFile();

        $this->assertSame($writer, $result);
        $this->assertFalse(is_resource($file));
    }

    public function testWriteHeaderWithNoFile(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $this->setPrivateProperty($writer, "file", null);

        $result = $writer->writeHeader([ "name" => "Name" ]);

        rewind($file);

        $this->assertSame($writer, $result);
        $this->assertSame("", stream_get_contents($file));
    }

    public function testWriteLineWithNoFile(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $writer->writeHeader([ "name" => "Name" ]);
        $this->setPrivateProperty($writer, "file", null);

        $result = $writer->writeLine([ "name" => "Alice" ]);

        rewind($file);

        $this->assertSame($writer, $result);
        $this->assertSame("Name\n", stream_get_contents($file));
    }

    public function testDownloadFileWithNoFile(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $this->setPrivateProperty($writer, "file", null);

        $result = $writer->downloadFile();

        $this->assertSame($writer, $result);
        $this->assertTrue(is_resource($file));
        fclose($file);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConstructorStartsDownloadHeadersAndOutputStream(): void {
        $this->expectOutputString("Name\n");

        ob_start();
        $writer = new CSVWriter("export");
        $writer->writeHeader([ "name" => "Name" ]);
        $writer->downloadFile();
    }

    public function testWriteLineFlushesEveryHundredRows(): void {
        $file = fopen("php://memory", "w+");
        $this->assertNotFalse($file);

        $writer = new CSVWriter("export", "root", $file, sendHeaders: false);
        $writer->writeHeader([ "name" => "Name" ]);

        for ($index = 1; $index <= 100; $index += 1) {
            $writer->writeLine([ "name" => "Name {$index}" ]);
        }

        rewind($file);
        $lines = explode("\n", trim(stream_get_contents($file)));

        $this->assertCount(101, $lines);
        $this->assertSame("Name", $lines[0]);
        $this->assertSame("\"Name 100\"", $lines[100]);
    }
}
