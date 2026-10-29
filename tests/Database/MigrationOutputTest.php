<?php
namespace Tests\Database;

use Framework\Database\Migration\MigrationOutput;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The Migration Output, which prints everything or only the progress
 */
class MigrationOutputTest extends TestCase {

    protected function tearDown(): void {
        MigrationOutput::setProgress(false);
        parent::tearDown();
    }

    /**
     * Returns what the given callback printed
     * @param callable(): void $callback
     * @return string
     */
    private function capture(callable $callback): string {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }



    #[DataProvider("providerProgress")]
    public function testTheProgressIsSet(bool $progress): void {
        MigrationOutput::setProgress($progress);
        $this->assertSame($progress, MigrationOutput::isProgress());
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function providerProgress(): array {
        return [
            "everything"   => [ false ],
            "the progress" => [ true ],
        ];
    }



    #[DataProvider("providerDetail")]
    public function testADetailIsLeftOut(bool $progress, string $expected): void {
        MigrationOutput::setProgress($progress);
        $output = $this->capture(fn() => MigrationOutput::detail("ALTER TABLE a\n"));
        $this->assertSame($expected, $output);
    }

    /**
     * @return array<string,array{bool,string}>
     */
    public static function providerDetail(): array {
        return [
            "shown"           => [ false, "ALTER TABLE a\n" ],
            "progress, hides" => [ true,  "" ],
        ];
    }



    #[DataProvider("providerStep")]
    public function testAStepIsOneLine(
        bool $progress,
        bool $withBreak,
        bool $withDots,
        string $expected,
    ): void {
        MigrationOutput::setProgress($progress);
        $output = $this->capture(fn() => MigrationOutput::step(
            "Updated table a",
            withBreak: $withBreak,
            withDots:  $withDots,
        ));
        $this->assertSame($expected, $output);
    }

    /**
     * @return array<string,array{bool,bool,bool,string}>
     */
    public static function providerStep(): array {
        return [
            "an item"                  => [ false, false, false, "- Updated table a\n" ],
            "with a break"             => [ false, true,  false, "\n- Updated table a\n" ],
            "with dots"                => [ false, false, true,  "- Updated table a ... \n" ],
            "with both"                => [ false, true,  true,  "\n- Updated table a ... \n" ],
            "progress, alone"          => [ true,  false, false, "Updated table a\n" ],
            "progress, without either" => [ true,  true,  true,  "Updated table a\n" ],
        ];
    }



    #[DataProvider("providerGroup")]
    public function testAGroupIsShownWithItsSteps(bool $progress, int $steps, string $expected): void {
        MigrationOutput::setProgress($progress);
        $output = $this->capture(function () use ($steps) {
            MigrationOutput::group("Table migrations");
            for ($i = 1; $i <= $steps; $i++) {
                MigrationOutput::step("Updated table t$i", withBreak: true);
            }
        });
        $this->assertSame($expected, $output);
    }

    /**
     * @return array<string,array{bool,int,string}>
     */
    public static function providerGroup(): array {
        return [
            "progress, two steps"   => [ true,  2, "Table migrations\n   Updated table t1\n   Updated table t2\n" ],
            "progress, no steps"    => [ true,  0, "" ],
            "everything, untouched" => [ false, 1, "\n- Updated table t1\n" ],
        ];
    }

    public function testTheProgressStartsWithoutAGroup(): void {
        MigrationOutput::setProgress(true);
        MigrationOutput::group("Data migrations");
        MigrationOutput::setProgress(true);
        $output = $this->capture(fn() => MigrationOutput::step("Running 1 post deploys"));
        $this->assertSame("Running 1 post deploys\n", $output);
    }



    #[DataProvider("providerDetail")]
    public function testADetailOfACallback(bool $progress, string $expected): void {
        MigrationOutput::setProgress($progress);
        $output = $this->capture(fn() => MigrationOutput::hideDetails(function () {
            print("ALTER TABLE a\n");
        }));
        $this->assertSame($expected, $output);
    }

    public function testAFailingCallbackStillThrows(): void {
        // And the buffer it was hidden in is closed, or the rest would be hidden too
        MigrationOutput::setProgress(true);
        $level = ob_get_level();

        try {
            MigrationOutput::hideDetails(function () {
                print("ALTER TABLE a\n");
                throw new RuntimeException("The migration failed");
            });
            $this->fail("The exception was swallowed");
        } catch (RuntimeException $e) {
            $this->assertSame("The migration failed", $e->getMessage());
        }
        $this->assertSame($level, ob_get_level());
    }
}
