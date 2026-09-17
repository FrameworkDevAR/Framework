<?php
namespace Tests\Database;

use Framework\Auth\Schema\CredentialQuery;
use Framework\Auth\Schema\CredentialDeviceQuery;
use Framework\Database\Query\Query;
use Framework\Database\Query\Exp;
use Framework\Database\Query\Op;

use PHPUnit\Framework\TestCase;

class SchemaQueryTest extends TestCase {

    /**
     * Returns the SQL of the query behind the schema query
     * @param CredentialQuery $query
     * @return string
     */
    private function sql(CredentialQuery $query): string {
        $result = preg_replace('/\s+/', " ", $query->getQuery()->toSQL());
        return trim((string)$result);
    }



    public function testItKnowsTheTableItReads(): void {
        $query = new CredentialQuery();

        $this->assertEquals("credential", $query->getTableName());
        $this->assertEquals("CREDENTIAL_ID", $query->getIDDbName());
    }

    public function testItStartsEmptyAndFillsAsConditionsAreAdded(): void {
        $query = new CredentialQuery();
        $this->assertTrue($query->isEmpty());
        $this->assertFalse($query->isNotEmpty());

        $query->email->equal("ada@example.com");
        $this->assertFalse($query->isEmpty());
        $this->assertTrue($query->isNotEmpty());
    }

    public function testItWrapsARealQuery(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");

        $this->assertInstanceOf(Query::class, $query->getQuery());
        $this->assertStringContainsString("credential.email = ?", $this->sql($query));
    }

    public function testTheTypedColumnsBuildTheConditions(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");
        $query->credentialID->greaterThan(5);

        $this->assertEquals(
            [ "ada@example.com", 5 ],
            $query->getQuery()->getBindings(),
        );
    }

    public function testARawExpressionTakesItsOwnParam(): void {
        $query = new CredentialQuery();
        $query->where(Exp::create("credential.progressValue > ?"));
        $query->addParam(10);

        $this->assertStringContainsString("credential.progressValue > ?", $this->sql($query));
        $this->assertEquals([ 10 ], $query->getQuery()->getBindings());
    }

    public function testAValueInsideAJsonColumnIsAskedForByItsPath(): void {
        $query = new CredentialQuery();
        $query->where(Exp::json("credential.data", "userID"), Op::Equal, 5);

        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(credential.data, ?)) = ?",
            $this->sql($query),
        );
        $this->assertEquals([ "$.userID", 5 ], $query->getQuery()->getBindings());
    }

    public function testAnExpressionIsComparedWithAValue(): void {
        $query = new CredentialQuery();
        $query->where(Exp::create("LOWER(credential.email)"), Op::Equal, "ada@example.com");

        $this->assertStringContainsString("LOWER(credential.email) = ?", $this->sql($query));
        $this->assertEquals([ "ada@example.com" ], $query->getQuery()->getBindings());
    }

    public function testConditionsCanBeGrouped(): void {
        $query = new CredentialQuery();
        $query->startOr();
        $query->email->equal("a@b.c");
        $query->email->equal("d@e.f");
        $query->endOr();

        $this->assertStringContainsString("( credential.email = ? OR credential.email = ? )", $this->sql($query));
    }

    public function testGroupsCanBeNested(): void {
        $query = new CredentialQuery();
        $query->startAnd();
        $query->email->equal("a@b.c");
        $query->endAnd();

        $this->assertStringContainsString("credential.email = ?", $this->sql($query));
    }

    public function testParenthesesCanBeOpenedDirectly(): void {
        $query = new CredentialQuery();
        $query->startParen();
        $query->email->equal("a@b.c");
        $query->endParen();

        $this->assertStringContainsString("( credential.email = ? )", $this->sql($query));
    }

    public function testTheJoinerBetweenConditionsCanBeSet(): void {
        $query = new CredentialQuery();
        $query->email->equal("a@b.c");
        $query->or();
        $query->credentialID->equal(1);

        $this->assertStringContainsString("OR", $this->sql($query));

        $anded = new CredentialQuery();
        $anded->email->equal("a@b.c");
        $anded->and();
        $anded->credentialID->equal(1);

        $this->assertStringContainsString("AND", $this->sql($anded));
    }

    public function testItCanBeLimitedAndPaged(): void {
        $limited = new CredentialQuery();
        $limited->limit(5);

        $paged = new CredentialQuery();
        $paged->paginate(2, 20);

        $this->assertStringContainsString("LIMIT 5", $this->sql($limited));
        $this->assertStringContainsString("LIMIT 40, 20", $this->sql($paged));
    }

    public function testItCanBeOrderedByAnExpression(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");
        $query->orderByExp(Exp::create("CASE WHEN firstName LIKE ? THEN 0 ELSE 1 END", "Ada%"));

        $this->assertStringContainsString(
            "ORDER BY CASE WHEN firstName LIKE ? THEN 0 ELSE 1 END ASC",
            $this->sql($query),
        );
        $this->assertEquals([ "ada@example.com", "Ada%" ], $query->getQuery()->getBindings());
    }

    public function testItCanAskWhetherRowsExistElsewhere(): void {
        // A sub query is consumed by the call, so each one needs its own
        $forExists = new CredentialDeviceQuery();
        $forExists->playerID->equal("abc");
        $exists = new CredentialQuery();
        $exists->whereExists($forExists);

        $forNotExists = new CredentialDeviceQuery();
        $forNotExists->playerID->equal("abc");
        $notExists = new CredentialQuery();
        $notExists->whereNotExists($forNotExists);

        $this->assertStringContainsString("EXISTS (SELECT 1 FROM `credential_device`", $this->sql($exists));
        $this->assertStringContainsString("NOT EXISTS (SELECT 1 FROM `credential_device`", $this->sql($notExists));
    }

    public function testASubQueryCannotBeUsedTwice(): void {
        $devices = new CredentialDeviceQuery();
        $devices->playerID->equal("abc");

        $first = new CredentialQuery();
        $first->whereExists($devices);

        $second = new CredentialQuery();
        $second->whereExists($devices);

        // The call mutates the sub query, so using it again repeats the marker
        // column it added the first time
        $this->assertStringContainsString("EXISTS (SELECT 1 FROM", $this->sql($first));
        $this->assertStringContainsString("EXISTS (SELECT 1, 1 FROM", $this->sql($second));
    }

    public function testAJoinNeedsTheOtherSideToHaveAnId(): void {
        // credential_device has no id of its own, so it can be joined onto but
        // cannot be the thing joined in
        $devices = new CredentialDeviceQuery();
        $devices->join(new CredentialQuery());

        $credential = new CredentialQuery();
        $credential->join(new CredentialDeviceQuery());

        $this->assertStringContainsString(
            "LEFT JOIN credential ON (credential.CREDENTIAL_ID = credential_device.CREDENTIAL_ID)",
            trim((string)preg_replace('/\s+/', " ", $devices->getQuery()->toSQL())),
        );
        $this->assertEquals("SELECT * FROM `credential`", $this->sql($credential));
    }

    public function testTheDebugSqlInlinesTheValues(): void {
        $query = new CredentialQuery();
        $query->email->equal("ada@example.com");

        $this->assertStringContainsString("'ada@example.com'", $query->toDebugSQL());
    }
}
