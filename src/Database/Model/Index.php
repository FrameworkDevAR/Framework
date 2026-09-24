<?php
namespace Framework\Database\Model;

use Framework\Utils\Arrays;
use Framework\Utils\Strings;

use Attribute;

/**
 * The Index Attribute
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Index {

    /** @var list<string> */
    public array $columns  = [];

    public string $name     = "";
    public bool   $isUnique = false;

    // Used internally when parsing the Model
    /** @var list<string> */
    public array $dbColumns = [];



    /**
     * The Index Attribute
     * @param list<string>|string $columns
     * @param bool                $isUnique Optional.
     * @param string              $name     Optional.
     */
    public function __construct(
        array|string $columns,
        bool $isUnique = false,
        string $name = "",
    ) {
        $this->columns  = Arrays::toStrings($columns, withoutEmpty: true);
        $this->isUnique = $isUnique;
        $this->name     = $name !== "" ? $name : Strings::join($this->columns, "_");
    }

    /**
     * Creates an Index
     * @param string       $name
     * @param list<string> $columns
     * @param bool         $isUnique Optional.
     * @return Index
     */
    public static function create(string $name, array $columns, bool $isUnique = false): Index {
        return new self($columns, $isUnique, $name);
    }



    /**
     * Sets the DB Names of the Columns from the Fields, returning the columns no Field has
     * @param list<Field> $fields
     * @return list<string>
     */
    public function setDbColumns(array $fields): array {
        $this->dbColumns = [];
        $missing         = [];

        foreach ($this->columns as $column) {
            $found = false;
            foreach ($fields as $field) {
                if ($field->name === $column) {
                    $this->dbColumns[] = $field->dbName;
                    $found             = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $column;
            }
        }
        return $missing;
    }

    /**
     * Returns true if the Index is the one the Table has
     * @param list<string> $columns
     * @param bool         $isUnique
     * @return bool
     */
    public function isSame(array $columns, bool $isUnique): bool {
        return $this->dbColumns === $columns && $this->isUnique === $isUnique;
    }
}
