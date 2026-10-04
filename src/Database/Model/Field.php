<?php
namespace Framework\Database\Model;

use Framework\Database\SchemaModel;
use Framework\Database\Query\QueryBuilder;
use Framework\Database\Model\FieldType;
use Framework\File\FilePath;
use Framework\File\MediaFile;
use Framework\Date\Type\DateType;
use Framework\Utils\Arrays;
use Framework\Utils\JSON;
use Framework\Utils\Numbers;
use Framework\Utils\Strings;

use Attribute;

/**
 * The Field Attribute
 * @phpstan-import-type QueryValue from QueryBuilder
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Field {

    // Makes the field a primary key and auto-increment
    public bool $isID = false;

    // By default, when using isID on a number field, it will be auto-incremented
    // Using this property will prevent the auto-increment
    public bool $notAutoInc = false;

    // Makes the field a primary key in SQL
    public bool $isPrimary = false;

    // Makes the field an index key in SQL
    public bool $isKey = false;

    // The field is the key of a parent Model and is used in several Schema functions
    public bool $isParent = false;

    // The field values are unique which adds special functions in the Schema
    public bool $isUnique = false;

    // Used to skip the prefix when doing a Join
    public bool $isCode = false;

    // Used to indicate that the Field is for the Position and should follow the Position rules.
    public bool $isPosition = false;

    // Used to indicate the minimum position of the field when isPosition is true.
    public int $minPosition = 0;


    // Marks the field as being the ID of a different Model
    // Is not used in the code, but it can be used to create a DER
    public string $belongsTo = "";

    // Name of the column in the Model it belongs to
    // If not set, it will be the same as the name of the field
    // Is not used in the code, but it can be used to create a DER
    public string $otherField = "";


    // Indicates the length of the number or string which determines the SQL type
    // For numbers the length is 10 making the type as int(10)
    // For strings the length is 255 making the type as varchar(255)
    public int $length = 0;

    // Indicates that the integer can be negative.
    public bool $isSigned = false;

    // When the type is float, this indicates the number of decimals.
    public int $decimals = 2;


    // Uses the type text in MySQL instead of varchar for strings.
    public bool $isText = false;

    // Uses the type longtext in MySQL instead of varchar for strings.
    public bool $isLongText = false;

    // Indicates that the string is encrypted in the database.
    public bool $isEncrypt = false;


    // Indicates that the string is a relative path to a file.
    // The file path is updated automatically when the file is moved.
    public bool $isFile = false;

    // Indicates that there might be a file path in the string or text.
    // The file paths are updated automatically when the file is moved.
    public bool $hasFile = false;

    // Indicates that the files are stored as a simple array of file paths as a JSON.
    public bool $jsonFiles = false;

    // Used to generate the path and url of the file.
    public string $filePath = "";


    // Used to convert a date to a timeStamp that comes from an input.
    public string $dateInput = "";

    // Used with the 'dateInput' to indicate if the hour of the date is at:
    // 'Start', 'Middle' or 'End'
    public DateType $dateType = DateType::None;

    // Used with the 'dateInput' to indicate that the hour comes from an input.
    public string $hourInput = "";


    /**
     * The Field Attribute
     * @param bool              $isID        Optional.
     * @param bool              $notAutoInc  Optional.
     * @param bool              $isPrimary   Optional.
     * @param bool              $isKey       Optional.
     * @param bool              $isParent    Optional.
     * @param bool              $isUnique    Optional.
     * @param bool              $isCode      Optional.
     * @param bool              $isPosition  Optional.
     * @param int               $minPosition Optional.
     * @param class-string|null $belongsTo   Optional.
     * @param string            $otherField  Optional.
     * @param int               $length      Optional.
     * @param bool              $isSigned    Optional.
     * @param int               $decimals    Optional.
     * @param bool              $isText      Optional.
     * @param bool              $isLongText  Optional.
     * @param bool              $isEncrypt   Optional.
     * @param bool              $isFile      Optional.
     * @param bool              $hasFile     Optional.
     * @param bool              $jsonFiles   Optional.
     * @param string            $filePath    Optional.
     * @param DateType          $dateType    Optional.
     * @param string            $dateInput   Optional.
     * @param string            $hourInput   Optional.
     */
    public function __construct(
        bool $isID = false,
        bool $notAutoInc = false,
        bool $isPrimary = false,
        bool $isKey = false,
        bool $isParent = false,
        bool $isUnique = false,
        bool $isCode = false,
        bool $isPosition = false,
        int $minPosition = 0,
        ?string $belongsTo = null,
        string $otherField = "",
        int $length = 0,
        bool $isSigned = false,
        int $decimals = 2,
        bool $isText = false,
        bool $isLongText = false,
        bool $isEncrypt = false,
        bool $isFile = false,
        bool $hasFile = false,
        bool $jsonFiles = false,
        string $filePath = "",
        DateType $dateType = DateType::None,
        string $dateInput = "",
        string $hourInput = "",
    ) {
        $this->isID        = $isID;
        $this->notAutoInc  = $notAutoInc;
        $this->isPrimary   = $isID || $isPrimary;
        $this->isKey       = $isKey;
        $this->isParent    = $isParent;
        $this->isUnique    = $isUnique;
        $this->isCode      = $isCode;
        $this->isPosition  = $isPosition;
        $this->minPosition = $minPosition;

        // Foreign key
        $this->belongsTo   = SchemaModel::getBaseModelName($belongsTo);
        $this->otherField  = $otherField;

        // Number types
        $this->length      = $length;
        $this->isSigned    = $isSigned;
        $this->decimals    = $decimals;

        // Text types
        $this->isText      = $isText;
        $this->isLongText  = $isLongText;
        $this->isEncrypt   = $isEncrypt;

        // File types
        $this->isFile      = $isFile;
        $this->hasFile     = $hasFile;
        $this->jsonFiles   = $jsonFiles;
        $this->filePath    = $filePath;

        // Date types
        $this->dateType    = $dateType;
        $this->dateInput   = $dateInput;
        $this->hourInput   = $hourInput;
    }



    // Used internally when parsing the Model
    public FieldType $type       = FieldType::String;
    public string    $name       = "";
    public string    $dbName     = "";
    public string    $prefixName = "";
    public string    $enumClass  = "";
    public bool      $isStatus   = false;


    /**
     * Creates a Field
     * @param string    $name       Optional.
     * @param string    $dbName     Optional.
     * @param string    $prefixName Optional.
     * @param FieldType $type       Optional.
     * @param bool      $isID       Optional.
     * @param bool      $isPrimary  Optional.
     * @param bool      $isKey      Optional.
     * @param int       $length     Optional.
     * @param bool      $isSigned   Optional.
     * @param int       $decimals   Optional.
     * @param string    $filePath   Optional.
     * @param bool      $isStatus   Optional.
     * @return Field
     */
    public static function create(
        string $name = "",
        string $dbName = "",
        string $prefixName = "",
        FieldType $type = FieldType::String,
        bool $isID = false,
        bool $isPrimary = false,
        bool $isKey = false,
        int $length = 0,
        bool $isSigned = false,
        int $decimals = 2,
        string $filePath = "",
        bool $isStatus = false,
    ): Field {
        $result = new self(
            isID:      $isID,
            isPrimary: $isPrimary,
            isKey:     $isKey,
            length:    $length,
            isSigned:  $isSigned,
            decimals:  $decimals,
            filePath:  $filePath,
        );

        $result->type       = $type;
        $result->name       = $name;
        $result->dbName     = $dbName !== "" ? $dbName : $name;
        $result->prefixName = $prefixName !== "" ? $prefixName : $name;
        $result->isStatus   = $isStatus;
        return $result;
    }

    /**
     * Sets the Data from the Model
     * @param string $name
     * @param string $typeName
     * @param bool   $isEnum
     * @return Field
     */
    public function setData(
        string $name,
        string $typeName,
        bool $isEnum,
    ): Field {
        $this->name   = $name;
        $this->dbName = $name;

        if ($isEnum) {
            $this->type      = FieldType::Enum;
            $this->enumClass = $typeName;
        } elseif ($typeName === "string") {
            if ($this->isText) {
                $this->type = FieldType::Text;
            } elseif ($this->isLongText) {
                $this->type = FieldType::LongText;
            } elseif ($this->isEncrypt) {
                $this->type = FieldType::Encrypt;
            } elseif ($this->isFile) {
                $this->type = FieldType::File;
            } else {
                $this->type = FieldType::String;
            }
        } else {
            $this->type = FieldType::fromType($typeName);
        }

        return $this;
    }

    /**
     * Sets the Database Name of the Field
     * @return string
     */
    public function setDbName(): string {
        $this->dbName = SchemaModel::getDbFieldName($this->name);
        return $this->dbName;
    }



    /**
     * Returns true if the field is a Schema ID
     * @return bool
     */
    public function isSchemaID(): bool {
        return $this->name !== $this->dbName;
    }

    /**
     * Returns true if the field is an Auto Increment
     * @return bool
     */
    public function isAutoInc(): bool {
        return $this->isID && !$this->notAutoInc && $this->type === FieldType::Number;
    }

    /**
     * Returns the Field Type from the Data
     * @param bool $withLength Optional.
     * @return string
     */
    public function getType(bool $withLength = true): string {
        $type       = "unknown";
        $length     = 0;
        $attributes = "";
        $default    = null;

        switch ($this->type) {
        case FieldType::None:
            break;

        case FieldType::Date:
            $length     = $this->length > 0 ? $this->length : 10;
            $type       = $length > 10 ? "bigint" : "int";
            $attributes = "unsigned NOT NULL";
            $default    = 0;
            break;
        case FieldType::JSON:
        case FieldType::Array:
            $type       = "mediumtext";
            $attributes = "NULL";
            break;

        case FieldType::Boolean:
            $type       = "tinyint";
            $length     = 1;
            $attributes = "unsigned NOT NULL";
            $default    = 0;
            break;
        case FieldType::Number:
            $type    = "int";
            $length  = $this->length > 0 ? $this->length : 10;
            $default = 0;

            if ($length < 3) {
                $type = "tinyint";
            } elseif ($length < 5) {
                $type = "smallint";
            } elseif ($length < 8) {
                $type = "mediumint";
            } elseif ($length > 10) {
                $type = "bigint";
            }

            if ($this->isAutoInc()) {
                $attributes = "unsigned NOT NULL AUTO_INCREMENT";
                $default    = null;
            } else {
                $attributes = $this->isSigned ? "NOT NULL" : "unsigned NOT NULL";
            }
            break;
        case FieldType::Float:
            $type       = "bigint";
            $length     = 20;
            $attributes = $this->isSigned ? "NOT NULL" : "unsigned NOT NULL";
            $default    = 0;
            break;

        case FieldType::Enum:
        case FieldType::String:
        case FieldType::File:
            $type       = "varchar";
            $length     = $this->length > 0 ? $this->length : 255;
            $attributes = "NOT NULL";
            $default    = "";
            break;
        case FieldType::Text:
            $type       = "text";
            $attributes = "NULL";
            break;
        case FieldType::LongText:
            $type       = "longtext";
            $attributes = "NULL";
            break;
        case FieldType::Encrypt:
            $type       = "varbinary";
            $length     = $this->length > 0 ? $this->length : 255;
            $attributes = "NOT NULL";
            break;
        }

        $result = $type;
        if ($withLength && $length > 0) {
            $result = "{$type}({$length}) $attributes";
        } elseif ($attributes !== "") {
            $result = "$type $attributes";
        }

        if ($result !== "unknown" && $default !== null) {
            $result .= " DEFAULT '{$default}'";
        }
        return $result;
    }

    /**
     * Returns the Field Values from the given Data
     * @param array<string,mixed> $data
     * @return array<string,int|string|float|bool|array<int|string,mixed>>
     */
    public function toValues(array $data): array {
        $key    = $this->prefixName;
        $string = isset($data[$key]) ? Strings::toString($data[$key]) : "";
        $number = isset($data[$key]) ? Numbers::toInt($data[$key])    : 0;
        $result = [];

        // Set the main value of the field
        $result[$key] = match ($this->type) {
            FieldType::None     => "",

            FieldType::Date     => $number,
            FieldType::Enum     => $string,
            FieldType::JSON,
            FieldType::Array    => JSON::decodeAsArray($string),

            FieldType::Boolean  => !Arrays::isEmpty($data, $key),
            FieldType::Number   => $number,
            FieldType::Float    => Numbers::toFloat($number, $this->decimals),

            FieldType::String,
            FieldType::Text,
            FieldType::LongText => $string,
            FieldType::File     => $string,
            FieldType::Encrypt  => isset($data["{$key}Decrypt"]) ?
                Strings::toString($data["{$key}Decrypt"]) : "",
        };

        // Set additional values for the File type
        if ($this->type === FieldType::File) {
            if ($this->filePath !== "") {
                $result["{$key}Url"] = "";
                if ($string !== "") {
                    $result["{$key}Url"] = FilePath::getUrl($this->filePath, $string);
                }
            } else {
                $result["{$key}Url"]   = "";
                $result["{$key}Thumb"] = "";
                if ($string !== "") {
                    $result["{$key}Url"]   = MediaFile::getSharedUrl($string);
                    $result["{$key}Thumb"] = MediaFile::getSharedThumbUrl($string);
                }
            }
        }

        return $result;
    }



    /**
     * Returns the Data to build a Field
     * @return array<string,mixed>
     */
    public function toBuildData(): array {
        $result = [
            "name" => $this->name,
            "type" => $this->type,
        ];
        if ($this->name !== $this->dbName) {
            $result["dbName"] = $this->dbName;
        }
        if ($this->isID) {
            $result["isID"] = $this->isID;
        }
        if ($this->decimals !== 2) {
            $result["decimals"] = $this->decimals;
        }
        if ($this->filePath !== "") {
            $result["filePath"] = $this->filePath;
        }
        return $result;
    }

    /**
     * Returns the Data for the Schema JSON
     * @return array{name:string,type:string,length?:int,isPrimary?:bool,isKey?:bool}
     */
    public function toSchemaJSON(): array {
        $result = [
            "name" => $this->dbName,
            "type" => $this->type->getName(),
        ];

        // The defaults are left out, so a column carries only what is true of it
        if ($this->length > 0) {
            $result["length"] = $this->length;
        }
        if ($this->isPrimary || $this->isID) {
            $result["isPrimary"] = true;
        }
        if ($this->isKey) {
            $result["isKey"] = true;
        }
        return $result;
    }

    /**
     * Returns the Data for the Schema JSON Foreign
     * @return array{fromField:string,toTable:string,toField:string}
     */
    public function toSchemaForeign(): array {
        return [
            "fromField" => $this->dbName,
            "toTable"   => SchemaModel::getDbTableName($this->belongsTo),
            "toField"   => $this->otherField !== "" ?
                SchemaModel::getDbFieldName($this->otherField) :
                $this->dbName,
        ];
    }
}
