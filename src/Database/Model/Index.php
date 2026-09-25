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

    // The longest name MySQL and MariaDB take for an index
    public const MaxNameLength = 64;

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
        $this->name     = $name !== "" ? $name : self::getDefaultName($this->columns);
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
     * Returns the Name of an Index over the given Columns
     * @param list<string> $columns
     * @return string
     */
    public static function getDefaultName(array $columns): string {
        // One column is named as the key of a Field would be, and several read as the
        // columns they cover, with the ID of each one dropped: productID and storeID
        // are idx_product_store, which the prefix sets apart from a column
        if (count($columns) === 1) {
            return $columns[0];
        }

        $parts = [];
        foreach ($columns as $column) {
            $parts[] = Strings::stripEnd(Strings::toSnakeCase($column), "_id");
        }
        return "idx_" . Strings::join($parts, "_");
    }

    /**
     * Returns true if the Name is longer than the Database takes
     * @return bool
     */
    public function isNameTooLong(): bool {
        return Strings::length($this->name) > self::MaxNameLength;
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
