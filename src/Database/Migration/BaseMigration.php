<?php
namespace Framework\Database\Migration;

/**
 * What every Migration file has, whichever of its steps it declares
 */
interface BaseMigration {

    /**
     * Returns a title for the Migration
     * @return string
     */
    public static function getTitle(): string;
}
