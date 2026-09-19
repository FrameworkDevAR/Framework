<?php
namespace Framework\Database;

use Framework\Analysis\Attr\NotTested;
use Framework\IO\Search;
use Framework\IO\Select;
use Framework\Database\Database;
use Framework\Database\SchemaModel;
use Framework\Database\Query\Query;
use Framework\Database\Query\Assign;
use Framework\Database\Query\Op;
use Framework\Database\Query\QueryLike;
use Framework\Database\Query\QueryBuilder;
use Framework\Database\Query\SelectionBuilder;
use Framework\Database\Query\ModificationBuilder;
use Framework\Database\Model\SubRequest;
use Framework\Database\Type\SchemaRequest;
use Framework\System\Config;
use Framework\Enum\Enum;
use Framework\Utils\Arrays;
use Framework\Utils\Dictionary;
use Framework\Utils\Strings;

/**
 * The Schema
 * @phpstan-import-type QueryValue from QueryBuilder
 */
class Schema {

    protected static ?SchemaModel $model = null;

    protected static string $modelName    = "";
    protected static string $tableName    = "";
    protected static string $idName       = "";
    protected static string $idDbName     = "";
    protected static string $positionName = "";
    protected static int    $minPosition  = 0;
    protected static bool   $canDelete    = false;



    /**
     * Returns the Model
     * @return SchemaModel
     */
    #[NotTested("It needs a Database")]
    public static function getModel(): SchemaModel {
        if (static::$model === null) {
            static::$model = new SchemaModel();
        }
        return static::$model;
    }

    /**
     * Returns a list of SubRequests
     * @return list<SubRequest>
     */
    #[NotTested("It needs a Database")]
    public static function getSubRequests(): array {
        return [];
    }



    /**
     * Returns true if a Table exists
     * @return bool
     */
    public static function tableExists(): bool {
        return Database::getInstance()->tableExists(static::$tableName);
    }

    /**
     * Returns true if the Schema has a Primary Key
     * @return bool
     */
    #[NotTested("It needs a Database")]
    public static function hasPrimaryKey(): bool {
        $keys = Database::getInstance()->getPrimaryKeys(static::$tableName);
        return count($keys) > 0;
    }

    /**
     * Encrypts the given Value
     * @param string $value
     * @return Assign
     */
    protected static function encrypt(string $value): Assign {
        return Assign::encrypt($value, Config::getDbKey());
    }



    /**
     * Returns the Data of an Entity with the given ID or Query
     * @param QueryLike|Enum|int|string $query
     * @param bool                      $withDeleted Optional.
     * @param bool                      $decrypted   Optional.
     * @return Dictionary
     */
    protected static function getSchemaEntity(
        QueryLike|Enum|int|string $query,
        bool $withDeleted = true,
        bool $decrypted = false,
    ): Dictionary {
        $query   = self::generateQueryID($query, $withDeleted)->limit(1);
        $request = self::requestSchemaData($query, decrypted: $decrypted);
        return $request->getFirst();
    }

    /**
     * Selects the given column from a single table and returns a single value
     * @param QueryLike $query
     * @param string    $column
     * @return string
     */
    protected static function getSchemaValue(QueryLike $query, string $column): string {
        return Query::select($query)->columns($column)->getString($column);
    }

    /**
     * Selects the given column from a single table and returns the entire column
     * @param QueryLike|null $query
     * @param string         $column
     * @param string         $columnKey Optional.
     * @return array<int|string>
     */
    protected static function getSchemaColumn(
        ?QueryLike $query,
        string $column,
        string $columnKey = "",
    ): array {
        $query   = self::generateQuery($query);
        $request = SelectionBuilder::create(static::getModel(), $query)
            ->addFields()
            ->addSelects($column, addMainKey: true)
            ->addJoins()
            ->request()
            ->resolve();

        $result    = [];
        $columnKey = $columnKey !== "" ? $columnKey : Strings::substringAfter($column, ".");
        foreach ($request as $row) {
            if (!isset($row[$columnKey])) {
                continue;
            }
            $value = $row[$columnKey];
            if (!Arrays::isEmpty($value) && !Arrays::contains($result, $value)) {
                if (is_string($value) || is_int($value)) {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Returns a Total using the Joins
     * @param QueryLike|null $query       Optional.
     * @param bool           $withDeleted Optional.
     * @return int
     */
    protected static function getSchemaTotal(
        ?QueryLike $query = null,
        bool $withDeleted = true,
    ): int {
        $query   = self::generateQuery($query, $withDeleted);
        $request = SelectionBuilder::create(static::getModel(), $query)
            ->addSelects("COUNT(*) AS cnt")
            ->addJoins(withSelects: false, onlyUsed: true)
            ->request()
            ->getResult();

        return $request->getFirst()->getInt("cnt");
    }

    /**
     * Returns a Select array
     * @param QueryLike                $query
     * @param list<string>|string      $nameColumn
     * @param string|null              $idColumn       Optional.
     * @param string|null              $descColumn     Optional.
     * @param list<string>|string|null $extraColumn    Optional.
     * @param string|null              $distinctColumn Optional.
     * @param bool                     $useEmpty       Optional.
     * @return list<Select>
     */
    protected static function getSchemaSelect(
        QueryLike $query,
        array|string $nameColumn,
        ?string $idColumn = null,
        ?string $descColumn = null,
        array|string|null $extraColumn = null,
        ?string $distinctColumn = null,
        bool $useEmpty = false,
    ): array {
        $query   = self::generateQuery($query);
        $request = SelectionBuilder::create(static::getModel(), $query)
            ->addSelects($distinctColumn !== null ? "DISTINCT($distinctColumn)" : "")
            ->addFields()
            ->addExpressions()
            ->addJoins()
            ->request()
            ->resolve();

        return Select::create(
            $request,
            keyName:  $idColumn ?? static::$idName,
            valName:  $nameColumn,
            descName: $descColumn,
            extraKey: $extraColumn,
            useEmpty: $useEmpty,
            distinct: true,
        );
    }

    /**
     * Returns the Search results
     * @param QueryLike           $query
     * @param list<string>|string $nameColumn
     * @param string|null         $idColumn   Optional.
     * @param int                 $limit      Optional.
     * @return list<Search>
     */
    protected static function getSchemaSearch(
        QueryLike $query,
        array|string $nameColumn,
        ?string $idColumn = null,
        int $limit = 0,
    ): array {
        // The Query is the caller's, so no limit here leaves the one it arrived with
        $query = self::generateQuery($query);
        if ($limit > 0) {
            $query->limit($limit);
        }
        $request = self::requestSchemaData($query);

        if ($idColumn === null || $idColumn === "") {
            $idColumn = static::$idName;
        }
        return Search::create($request, $idColumn, $nameColumn);
    }



    /**
     * Returns an array of Entities Data
     * @param QueryLike|null       $query              Optional.
     * @param SchemaRequest|null   $sort               Optional.
     * @param array<string,string> $selects            Optional.
     * @param list<string>         $joins              Optional.
     * @param bool                 $decrypted          Optional.
     * @param bool                 $skipSubRequest     Optional.
     * @param list<string>         $withoutSubRequests Optional.
     * @return Dictionary
     */
    protected static function getSchemaEntities(
        ?QueryLike $query = null,
        ?SchemaRequest $sort = null,
        array $selects = [],
        array $joins = [],
        bool $decrypted = false,
        bool $skipSubRequest = false,
        array $withoutSubRequests = [],
    ): Dictionary {
        $query   = self::generateQuerySort($query, $sort);
        $request = self::requestSchemaData(
            $query,
            $selects,
            $joins,
            $decrypted,
            $skipSubRequest,
            $withoutSubRequests,
        );
        return $request;
    }

    /**
     * Requests data to the database
     * @param QueryLike            $query
     * @param array<string,string> $selects            Optional.
     * @param list<string>         $joins              Optional.
     * @param bool                 $decrypted          Optional.
     * @param bool                 $skipSubRequest     Optional.
     * @param list<string>         $withoutSubRequests Optional.
     * @return Dictionary
     */
    private static function requestSchemaData(
        QueryLike $query,
        array $selects = [],
        array $joins = [],
        bool $decrypted = false,
        bool $skipSubRequest = false,
        array $withoutSubRequests = [],
    ): Dictionary {
        $request = SelectionBuilder::create(static::getModel(), $query)
            ->addFields($decrypted)
            ->addExpressions()
            ->addSelects(Arrays::getValues($selects))
            ->addJoins($joins)
            ->addCounts()
            ->request()
            ->resolve(array_keys($selects));

        if (!$skipSubRequest) {
            foreach (static::getSubRequests() as $subRequest) {
                if (Arrays::contains($withoutSubRequests, $subRequest->name)) {
                    continue;
                }
                $request = $subRequest->request($request);
            }
        }
        return new Dictionary($request);
    }

    /**
     * Returns the SQL expression of the Query for Debugging
     * @param QueryLike|null       $query     Optional.
     * @param SchemaRequest|null   $sort      Optional.
     * @param array<string,string> $selects   Optional.
     * @param list<string>         $joins     Optional.
     * @param bool                 $decrypted Optional.
     * @return string
     */
    protected static function getDebugSQL(
        ?QueryLike $query = null,
        ?SchemaRequest $sort = null,
        array $selects = [],
        array $joins = [],
        bool $decrypted = false,
    ): string {
        $query = self::generateQuerySort($query, $sort);
        return SelectionBuilder::create(static::getModel(), $query)
            ->addFields($decrypted)
            ->addExpressions()
            ->addSelects(Arrays::getValues($selects))
            ->addJoins($joins)
            ->addCounts()
            ->toDebugSQL();
    }



    /**
     * Truncates all the Entities
     * @return bool
     */
    protected static function truncateData(): bool {
        return Query::truncate(static::$tableName)->execute() > 0;
    }

    /**
     * Creates a new Entity with data
     * @param array<string,QueryValue> $fields
     * @param int                      $credentialID Optional.
     * @return int
     */
    protected static function createSchemaEntity(
        array $fields,
        int $credentialID = 0,
    ): int {
        return ModificationBuilder::insert(static::getModel())
            ->addFields($fields)
            ->addCreation($credentialID)
            ->addModification()
            ->execute();
    }

    /**
     * Replaces the Data of an Entity
     * @param array<string,QueryValue> $fields
     * @param int                      $credentialID Optional.
     * @return int
     */
    protected static function replaceSchemaEntity(
        array $fields,
        int $credentialID = 0,
    ): int {
        return ModificationBuilder::replace(static::getModel())
            ->addFields($fields)
            ->addModification($credentialID)
            ->execute();
    }

    /**
     * Edits the Data of an Entity
     * @param QueryLike|Enum|int|string $query
     * @param array<string,QueryValue>  $fields
     * @param int                       $credentialID   Optional.
     * @param bool                      $skipTimestamps Optional.
     * @return bool
     */
    protected static function editSchemaEntity(
        QueryLike|Enum|int|string $query,
        array $fields,
        int $credentialID = 0,
        bool $skipTimestamps = false,
    ): bool {
        $query = self::generateQueryID($query, withDeleted: false);
        return ModificationBuilder::update(static::getModel(), $query)
            ->addFields($fields)
            ->addModification($credentialID, $skipTimestamps)
            ->execute() > 0;
    }

    /**
     * Deletes the Data of an Entity
     * @param QueryLike|Enum|int|string $query
     * @param int                       $credentialID Optional.
     * @return bool
     */
    protected static function deleteSchemaEntity(
        QueryLike|Enum|int|string $query,
        int $credentialID = 0,
    ): bool {
        $query = self::generateQueryID($query, withDeleted: false);
        if (static::$canDelete) {
            return self::editSchemaEntity($query, [ "isDeleted" => 1 ], $credentialID);
        }
        return false;
    }

    /**
     * Removes the Data of an Entity
     * @param QueryLike|Enum|int|string $query
     * @return bool
     */
    protected static function removeSchemaEntity(QueryLike|Enum|int|string $query): bool {
        $query = self::generateQueryID($query, withDeleted: false);
        return Query::delete($query)->execute() > 0;
    }



    /**
     * Creates an Entity and ensures the Order
     * @param array<string,QueryValue> $fields
     * @param int                      $credentialID Optional.
     * @param QueryLike|null           $orderQuery   Optional.
     * @return int
     */
    protected static function createSchemaEntityWithOrder(
        array $fields,
        int $credentialID = 0,
        ?QueryLike $orderQuery = null,
    ): int {
        $modification = ModificationBuilder::insert(static::getModel())
            ->addFields($fields)
            ->addCreation($credentialID)
            ->addModification();

        $newPosition = self::ensureSchemaOrder(null, $modification->getFields(), $orderQuery);
        $modification->setField(static::$positionName, $newPosition);
        return $modification->execute();
    }

    /**
     * Edits the Data of an Entity and ensures the Order
     * @param QueryLike|Enum|int|string $query
     * @param array<string,QueryValue>  $fields
     * @param int                       $credentialID   Optional.
     * @param QueryLike|null            $orderQuery     Optional.
     * @param bool                      $skipTimestamps Optional.
     * @return bool
     */
    protected static function editSchemaEntityWithOrder(
        QueryLike|Enum|int|string $query,
        array $fields,
        int $credentialID = 0,
        ?QueryLike $orderQuery = null,
        bool $skipTimestamps = false,
    ): bool {
        $modifyQuery  = self::generateQueryID($query, withDeleted: false);
        $modification = ModificationBuilder::update(static::getModel(), $modifyQuery)
            ->addFields($fields)
            ->addModification($credentialID, $skipTimestamps);

        $newFields = $modification->getFields();
        if ($newFields->has(static::$positionName)) {
            $elem         = self::getSchemaEntity($query);
            $savePosition = self::ensureSchemaOrder($elem, $newFields, $orderQuery);
            $modification->setField(static::$positionName, $savePosition);
        }

        return $modification->execute() > 0;
    }

    /**
     * Deletes the Data of an Entity and ensures the Order
     * @param QueryLike|Enum|int|string $query
     * @param int                       $credentialID Optional.
     * @param QueryLike|null            $orderQuery   Optional.
     * @return bool
     */
    protected static function deleteSchemaEntityWithOrder(
        QueryLike|Enum|int|string $query,
        int $credentialID = 0,
        ?QueryLike $orderQuery = null,
    ): bool {
        $elem = self::getSchemaEntity($query);
        if (self::deleteSchemaEntity($query, $credentialID)) {
            self::ensureSchemaOrder($elem, null, $orderQuery);
            return true;
        }
        return false;
    }

    /**
     * Removes the Data of an Entity and ensures the Order
     * @param QueryLike|Enum|int|string $query
     * @param QueryLike|null            $orderQuery Optional.
     * @return bool
     */
    protected static function removeSchemaEntityWithOrder(
        QueryLike|Enum|int|string $query,
        ?QueryLike $orderQuery = null,
    ): bool {
        $elem = self::getSchemaEntity($query);
        if (self::removeSchemaEntity($query)) {
            self::ensureSchemaOrder($elem, null, $orderQuery);
            return true;
        }
        return false;
    }

    /**
     * Ensures that the Order of the Schema is correct
     * @param Dictionary|null $oldFields
     * @param Dictionary|null $newFields
     * @param QueryLike|null  $query     Optional.
     * @return int
     */
    protected static function ensureSchemaOrder(
        ?Dictionary $oldFields,
        ?Dictionary $newFields,
        ?QueryLike $query = null,
    ): int {
        $isCreate     = Arrays::isEmpty($oldFields)  && !Arrays::isEmpty($newFields);
        $isEdit       = !Arrays::isEmpty($oldFields) && !Arrays::isEmpty($newFields);
        $isDelete     = !Arrays::isEmpty($oldFields) && Arrays::isEmpty($newFields);

        $oldPosition  = $oldFields !== null ? $oldFields->getInt(static::$positionName) : 0;
        $newPosition  = $newFields !== null ? $newFields->getInt(static::$positionName) : 0;
        $nextPosition = self::getNextPosition($query);

        $oldPosition  = $isCreate ? $nextPosition : $oldPosition;
        $newPosition  = $newPosition > 0 ? $newPosition : $nextPosition - ($isEdit ? 1 : 0);
        $savePosition = $newPosition;

        // In a create if the position is the last one, we just set the element to the last position
        if ($isCreate && ($newPosition === 0 || $newPosition >= $nextPosition)) {
            return $nextPosition;
        }

        // If this is an edit and the position didn't change, we don't need to do anything
        if (!$isDelete && $oldPosition === $newPosition) {
            return $newPosition;
        }

        // Apply the minimum position
        if ($newPosition < static::$minPosition) {
            $newPosition  = static::$minPosition;
            $savePosition = $newPosition;
        }

        // On an edit if the position is the last one, set it to 1 lower
        if ($isEdit && $newPosition > $nextPosition) {
            $savePosition = $nextPosition - 1;
        }

        $builder = Query::update(self::generateQuery($query));

        if ($newPosition > $oldPosition) {
            $builder->set(static::$positionName, Assign::decrease(1));
            $builder->where(static::$positionName, Op::GreaterThan, $oldPosition);
            $builder->where(static::$positionName, Op::LessOrEqual, $newPosition);
        } else {
            $builder->set(static::$positionName, Assign::increase(1));
            $builder->where(static::$positionName, Op::GreaterOrEqual, $newPosition);
            $builder->where(static::$positionName, Op::LessThan, $oldPosition);
        }

        $builder->execute();
        return $savePosition;
    }

    /**
     * Gets the Next Position
     * @param QueryLike|null $query Optional.
     * @return int
     */
    private static function getNextPosition(?QueryLike $query = null): int {
        $query = self::generateQuery($query);
        $query->orderBy(static::$positionName, isASC: false);
        $query->limit(1);

        $request = SelectionBuilder::create(static::getModel(), $query)
            ->addSelects(static::$positionName, addMainKey: true)
            ->addJoins(withSelects: false)
            ->request()
            ->getResult();

        return $request->getFirst()->getInt(static::$positionName) + 1;
    }

    /**
     * Ensures that only one Entity has the Unique column set
     * @param QueryLike $query
     * @param string    $column
     * @param int       $id
     * @param int       $oldValue
     * @param int       $newValue
     * @return bool
     */
    protected static function ensureSchemaUniqueData(
        QueryLike $query,
        string $column,
        int $id,
        int $oldValue,
        int $newValue,
    ): bool {
        $query = $query->getQuery();
        if ($newValue !== 0 && $oldValue === 0) {
            $query->where(static::$idDbName, Op::NotEqual, $id);
            $query->where($column, Op::Equal, 1);
            self::editSchemaEntity($query, [ $column => 0 ]);
            return true;
        }
        if ($newValue === 0 && $oldValue !== 0) {
            $query = self::generateQuery($query, withDeleted: true);
            $query->limit(1);
            self::editSchemaEntity($query, [ $column => 1 ]);
            return true;
        }
        return false;
    }



    /**
     * Generates a Query with the ID or returns the Query
     * @param QueryLike|Enum|int|string $queryOrID
     * @param bool                      $withDeleted Optional.
     * @return Query
     */
    private static function generateQueryID(
        QueryLike|Enum|int|string $queryOrID,
        bool $withDeleted = true,
    ): Query {
        if ($queryOrID instanceof QueryLike) {
            $query = $queryOrID->getQuery();
            return self::generateQuery($query, $withDeleted);
        }

        if ($queryOrID instanceof Enum) {
            $value = $queryOrID->toString();
        } else {
            $value = $queryOrID;
        }

        $query = Query::select(static::$tableName);
        $query->where(static::$idDbName, Op::Equal, $value);
        return self::generateQuery($query, $withDeleted);
    }

    /**
     * Generates a Query with Sorting
     * @param QueryLike|null     $query Optional.
     * @param SchemaRequest|null $sort  Optional.
     * @return Query
     */
    private static function generateQuerySort(
        ?QueryLike $query = null,
        ?SchemaRequest $sort = null,
    ): Query {
        $query = self::generateQuery($query);

        if ($sort !== null) {
            if ($sort->orderBy !== "") {
                $query->orderBy($sort->orderBy, $sort->orderAsc);
            }
            if ($sort->page !== -1 && $sort->amount > 0) {
                $query->paginate($sort->page, $sort->amount);
            }
        } elseif (!$query->hasOrder() && static::$idDbName !== "") {
            $query->orderBy(static::$idDbName, isASC: true);
        }
        return $query;
    }

    /**
     * Generates a Query without Deleted if required
     * @param QueryLike|null $query       Optional.
     * @param bool           $withDeleted Optional.
     * @return Query
     */
    private static function generateQuery(
        ?QueryLike $query = null,
        bool $withDeleted = true,
    ): Query {
        if ($query === null) {
            $query = Query::select(static::$tableName);
        } else {
            $query = Query::select($query);
        }

        $isDeleted = static::getModel()->getKey("isDeleted");
        if ($withDeleted && static::$canDelete &&
            !$query->hasWhereColumn($isDeleted) &&
            !$query->hasWhereColumn("isDeleted")
        ) {
            $query->where($isDeleted, Op::Equal, 0);
        }
        return $query;
    }
}
