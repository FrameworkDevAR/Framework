<?php
namespace Framework\ImpExp;

use Framework\ImpExp\Schema\ImpExpEntity;

/**
 * The Import and Export Storage
 */
interface ImpExpStorage {

    /**
     * Stores the IDs of a new Export with an empty file
     * @param ImpExpEntity $impExp
     * @param list<int>    $ids
     * @return bool
     */
    public function createExport(ImpExpEntity $impExp, array $ids): bool;

    /**
     * Returns the IDs of the Export
     * @param ImpExpEntity $impExp
     * @return list<int>
     */
    public function getExportIDs(ImpExpEntity $impExp): array;

    /**
     * Writes the given block at the given byte of the file of the Export
     * @param ImpExpEntity $impExp
     * @param string       $filePath
     * @param int          $offset
     * @return bool
     */
    public function appendExport(ImpExpEntity $impExp, string $filePath, int $offset): bool;

    /**
     * Stores the file of a new Import
     * @param ImpExpEntity $impExp
     * @param string       $filePath
     * @return bool
     */
    public function createImport(ImpExpEntity $impExp, string $filePath): bool;

    /**
     * Writes the stored file of the Import or Export in the given path
     * @param ImpExpEntity $impExp
     * @param string       $filePath
     * @return bool
     */
    public function readFile(ImpExpEntity $impExp, string $filePath): bool;

    /**
     * Deletes the stored files of the Import or Export
     * @param ImpExpEntity $impExp
     * @return bool
     */
    public function delete(ImpExpEntity $impExp): bool;
}
