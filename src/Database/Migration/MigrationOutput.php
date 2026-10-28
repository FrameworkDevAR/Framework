<?php
namespace Framework\Database\Migration;

/**
 * The Migration Output
 */
class MigrationOutput {

    private static bool $progress = false;


    /**
     * Sets if only the progress is shown
     * @param bool $progress
     * @return void
     */
    public static function setProgress(bool $progress): void {
        self::$progress = $progress;
    }

    /**
     * Returns true if only the progress is shown
     * @return bool
     */
    public static function isProgress(): bool {
        return self::$progress;
    }



    /**
     * Prints a text that the progress leaves out
     * @param string $text
     * @return void
     */
    public static function detail(string $text): void {
        if (!self::$progress) {
            print($text);
        }
    }

    /**
     * Prints a step of the Migration, which the progress shows alone in its line
     * @param string $step
     * @param bool   $withBreak Optional.
     * @param bool   $withDots  Optional.
     * @return void
     */
    public static function step(
        string $step,
        bool $withBreak = false,
        bool $withDots = false,
    ): void {
        if (self::$progress) {
            print("$step\n");
            return;
        }

        $break = $withBreak ? "\n" : "";
        $dots  = $withDots ? " ... " : "";
        print("{$break}- {$step}{$dots}\n");
    }

    /**
     * Runs the given callback hiding what it prints when only the progress is shown
     * @param callable(): void $callback
     * @return void
     */
    public static function hideDetails(callable $callback): void {
        if (!self::$progress) {
            $callback();
            return;
        }

        ob_start();
        try {
            $callback();
        } finally {
            ob_end_clean();
        }
    }
}
