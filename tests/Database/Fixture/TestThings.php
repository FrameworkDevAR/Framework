<?php
namespace Tests\Database\Fixture;

use Tests\Database\Fixture\Schema\TestThingColumn;
use Tests\Database\Fixture\Schema\TestThingEntity;
use Tests\Database\Fixture\Schema\TestThingQuery;
use Tests\Database\Fixture\Schema\TestThingRequest;
use Tests\Database\Fixture\Schema\TestThingSchema;

/**
 * The Things, the way an App writes the class over a generated Schema
 *
 * Everything the generator writes is protected, so the calls of an App go
 * through a class of its own like this one.
 */
class TestThings extends TestThingSchema {

    /**
     * Adds a Thing, at the end of the order
     *
     * The generated create takes the secret as a string, so the write of an
     * encrypted column goes through the Schema itself, which is where the
     * value can be an expression instead
     * @param string $name
     * @param string $secret    Optional.
     * @param int    $isDefault Optional.
     * @return int
     */
    public static function add(string $name, string $secret = "", int $isDefault = 0): int {
        return self::createSchemaEntityWithOrder([
            "name"      => $name,
            "secret"    => self::encrypt($secret),
            "isDefault" => $isDefault,
        ]);
    }

    /**
     * Returns every Thing, each with its parts
     * @param bool         $skipSubRequest     Optional.
     * @param list<string> $withoutSubRequests Optional.
     * @return list<TestThingEntity>
     */
    public static function getAll(
        bool $skipSubRequest = false,
        array $withoutSubRequests = [],
    ): array {
        return self::getEntityList(
            skipSubRequest:     $skipSubRequest,
            withoutSubRequests: $withoutSubRequests,
        );
    }

    /**
     * Returns a page of the Things, in the order that was asked for
     * @param int    $page
     * @param int    $amount
     * @param string $orderBy  Optional.
     * @param bool   $orderAsc Optional.
     * @return list<TestThingEntity>
     */
    public static function getPage(
        int $page,
        int $amount,
        string $orderBy = "",
        bool $orderAsc = true,
    ): array {
        $request           = new TestThingRequest();
        $request->page     = $page;
        $request->amount   = $amount;
        $request->orderBy  = $orderBy;
        $request->orderAsc = $orderAsc;
        return self::getEntityList(request: $request);
    }

    /**
     * Moves a Thing to the given position
     * @param int $testThingID
     * @param int $position
     * @return bool
     */
    public static function move(int $testThingID, int $position): bool {
        return self::editEntity($testThingID, position: $position);
    }

    /**
     * Writes the secret of a Thing, which the database keeps encrypted
     * @param int    $testThingID
     * @param string $secret
     * @return bool
     */
    public static function setSecret(int $testThingID, string $secret): bool {
        return self::editEntity($testThingID, secret: self::encrypt($secret));
    }

    /**
     * Moves the default from whichever Thing holds it to the given one
     * @param int $testThingID
     * @param int $oldValue
     * @param int $newValue
     * @return bool
     */
    public static function setDefault(int $testThingID, int $oldValue, int $newValue): bool {
        return self::ensureUniqueData(
            new TestThingQuery(),
            TestThingColumn::IsDefault,
            $testThingID,
            $oldValue,
            $newValue,
        );
    }

    /**
     * Marks a Thing as deleted, closing the gap its position leaves
     * @param int  $testThingID
     * @param bool $skipOrder   Optional.
     * @return bool
     */
    public static function drop(int $testThingID, bool $skipOrder = false): bool {
        return self::deleteEntity($testThingID, $skipOrder);
    }

    /**
     * Takes a Thing out of the table, closing the gap its position leaves
     * @param int $testThingID
     * @return bool
     */
    public static function remove(int $testThingID): bool {
        return self::removeEntity($testThingID);
    }
}
