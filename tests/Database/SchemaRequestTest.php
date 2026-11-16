<?php
namespace Tests\Database;

use Framework\File\File;
use Framework\IO\Request;
use Framework\Utils\Dictionary;

use Framework\Auth\Schema\CredentialDeviceRequest;
use Framework\Notification\Schema\NotificationQueueRequest;
use Framework\System\NotificationProvider;
use Framework\Database\Type\SchemaRequest;

use Tests\Database\Fixture\CrateRequest;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Schema Request, which reads a property of the class before the request
 */
class SchemaRequestTest extends TestCase {

    /**
     * Returns a Request built over the given data
     * @param array<string,mixed> $data Optional.
     * @return CrateRequest
     */
    private function request(array $data = []): CrateRequest {
        return new CrateRequest(new Request($data));
    }

    /**
     * Returns a Request with every property already set
     * @return CrateRequest
     */
    private function filled(): CrateRequest {
        $result = $this->request([
            "name"     => "fromTheRequest",
            "quantity" => 99,
            "weight"   => 9.9,
            "isOpen"   => "1",
        ]);

        $result->name     = "Crate";
        $result->quantity = 12;
        $result->weight   = 1.5;
        $result->isOpen   = true;
        return $result;
    }



    public function testTheSortAndThePageAreReadFromTheRequest(): void {
        $request = $this->request([
            "orderBy"  => "name",
            "orderAsc" => "1",
            "page"     => "2",
            "amount"   => "50",
        ]);

        $this->assertSame("name", $request->orderBy);
        $this->assertTrue($request->orderAsc);
        $this->assertSame(2, $request->page);
        $this->assertSame(50, $request->amount);
    }

    public function testAPageThatWasNotAskedForIsTheFirst(): void {
        // The property starts at -1, and the request reading it moves it to 0
        $this->assertSame(0, $this->request()->page);
    }

    public function testARequestBuiltOverAnotherSharesTheOneItWasGiven(): void {
        $first  = $this->request([ "orderBy" => "name" ]);
        $second = new CrateRequest($first);

        $this->assertSame($first->getRequest(), $second->getRequest());
        $this->assertSame("name", $second->orderBy);
    }

    public function testARequestBuiltOverNothingHoldsNothing(): void {
        $request = new CrateRequest();

        $this->assertTrue($request->isEmpty());
        $this->assertFalse($request->isNotEmpty());
    }

    public function testARequestBuiltOverSomethingHoldsIt(): void {
        $request = $this->request([ "name" => "Crate" ]);

        $this->assertFalse($request->isEmpty());
        $this->assertTrue($request->isNotEmpty());
    }

    public function testThePropertiesAreListedWithTheRequestItself(): void {
        $expected = [
            "name", "quantity", "weight", "isOpen", "label", "extra",
            "request", "orderBy", "orderAsc", "page", "amount",
        ];

        $this->assertSame($expected, $this->request()->getProperties());
    }



    /**
     * A key, and the value the accessor takes for it
     * @param string $method
     * @param string $key
     * @param mixed  $expected
     * @return void
     */
    #[DataProvider("providerProperty")]
    public function testThePropertyIsPreferredOverTheRequest(
        string $method,
        string $key,
        mixed $expected,
    ): void {
        /** @var callable */
        $callable = [ $this->filled(), $method ];
        $this->assertSame($expected, $callable($key));
    }

    /**
     * Every one of these keys is in the request too, holding something else
     * @return array<string,array{string,string,mixed}>
     */
    public static function providerProperty(): array {
        return [
            "a string"  => [ "getString", "name", "Crate" ],
            "a number"  => [ "getInt", "quantity", 12 ],
            "a float"   => [ "getFloat", "weight", 1.5 ],
            "a boolean" => [ "getBool", "isOpen", true ],
        ];
    }

    /**
     * A key that is only in the request, and the value read from it
     * @param string $method
     * @param string $key
     * @param mixed  $expected
     * @return void
     */
    #[DataProvider("providerRequest")]
    public function testAKeyWithNoPropertyIsReadFromTheRequest(
        string $method,
        string $key,
        mixed $expected,
    ): void {
        $request = $this->request([
            "colour" => "red",
            "size"   => "3",
            "ratio"  => "1.5",
            "flag"   => "1",
        ]);

        /** @var callable */
        $callable = [ $request, $method ];
        $this->assertSame($expected, $callable($key));
    }

    /**
     * @return array<string,array{string,string,mixed}>
     */
    public static function providerRequest(): array {
        return [
            "a string"  => [ "getString", "colour", "red" ],
            "a number"  => [ "getInt", "size", 3 ],
            "a float"   => [ "getFloat", "ratio", 1.5 ],
            "a boolean" => [ "getBool", "flag", true ],
        ];
    }

    /**
     * A key that is in neither, and the default handed back for it
     * @param string $method
     * @param mixed  $default
     * @return void
     */
    #[DataProvider("providerDefault")]
    public function testAKeyThatIsInNeitherHandsBackTheDefault(
        string $method,
        mixed $default,
    ): void {
        /** @var callable */
        $callable = [ $this->request(), $method ];
        $this->assertSame($default, $callable("notAKey", $default));
    }

    /**
     * @return array<string,array{string,mixed}>
     */
    public static function providerDefault(): array {
        return [
            "a string"     => [ "getString", "none" ],
            "no string"    => [ "getString", "" ],
            "a number"     => [ "getInt", 7 ],
            "no number"    => [ "getInt", 0 ],
            "a float"      => [ "getFloat", 1.5 ],
            "no float"     => [ "getFloat", 0.0 ],
            "a boolean"    => [ "getBool", true ],
            "no boolean"   => [ "getBool", false ],
            "a property"   => [ "getProp", "none" ],
            "a number one" => [ "getProp", 7 ],
        ];
    }

    /**
     * A key handed to getProp, and whether it is one it can hand back
     * @param string     $key
     * @param int|string $expected
     * @return void
     */
    #[DataProvider("providerProp")]
    public function testOnlyANumberOrAStringIsHandedBackAsAProperty(
        string $key,
        int|string $expected,
    ): void {
        $this->assertSame($expected, $this->filled()->getProp($key, "none"));
    }

    /**
     * getProp reads the property alone, so a key that is only in the request
     * is one it does not have
     * @return array<string,array{string,int|string}>
     */
    public static function providerProp(): array {
        return [
            "a string"     => [ "name", "Crate" ],
            "a number"     => [ "quantity", 12 ],
            "the sort"     => [ "orderBy", "" ],
            "a boolean"    => [ "isOpen", "none" ],
            "a float"      => [ "weight", "none" ],
            "an object"    => [ "label", "none" ],
            "one it lacks" => [ "notAKey", "none" ],
        ];
    }



    public function testTheFileIsTheOneThePropertyHolds(): void {
        $request = $this->request();
        $request->label = new File("crates/one.png");

        $this->assertSame("crates/one.png", $request->getFile("label")->toString());
    }

    /**
     * A key with no File on it falls back to the upload of that name
     * @param string $key
     * @return void
     */
    #[DataProvider("providerNoFile")]
    public function testAKeyWithNoFileFallsBackToTheUpload(string $key): void {
        $this->assertSame("", $this->request()->getFile($key)->toString());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerNoFile(): array {
        return [
            "a property with none" => [ "label" ],
            "one it lacks"         => [ "notAKey" ],
        ];
    }

    public function testTheDictionaryIsTheOneThePropertyHolds(): void {
        $request = $this->request([ "extra" => [ "colour" => "blue" ] ]);
        $request->extra = new Dictionary([ "colour" => "red" ]);

        $this->assertSame("red", $request->getDictionary("extra")->getString("colour"));
    }

    public function testADictionaryWithNoPropertyIsReadFromTheRequest(): void {
        $request = $this->request([ "sizes" => [ "one" => 1 ] ]);

        $this->assertSame(1, $request->getDictionary("sizes")->getInt("one"));
    }

    /**
     * A key with no Dictionary anywhere, which comes back as an empty one
     * @param string $key
     * @return void
     */
    #[DataProvider("providerNoDictionary")]
    public function testAKeyWithNoDictionaryComesBackEmpty(string $key): void {
        $this->assertSame([], $this->request()->getDictionary($key)->toArray());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function providerNoDictionary(): array {
        return [
            "a property with none" => [ "extra" ],
            "one it lacks"         => [ "notAKey" ],
        ];
    }



    public function testTheRequestIsHandedBackAsADictionaryWithItself(): void {
        $result = $this->filled()->toDictionary();

        $this->assertSame("Crate", $result->getString("name"));
        $this->assertSame(12, $result->getInt("quantity"));
        $this->assertTrue($result->hasValue("request"));
    }

    public function testTheRequestIsLeftOutOfTheArray(): void {
        // The array is what the caller is handed, and the Request it was read
        // from has no business in it
        $result = $this->filled()->toArray();

        $this->assertArrayNotHasKey("request", $result);
        $this->assertSame("Crate", $result["name"]);
        $this->assertSame(12, $result["quantity"]);
        $this->assertSame(1.5, $result["weight"]);
        $this->assertTrue($result["isOpen"]);
        $this->assertNull($result["label"]);
    }

    public function testTheFileAndTheDictionaryAreUnwrappedInTheArray(): void {
        $request = $this->request();
        $request->label = new File("crates/one.png");
        $request->extra = new Dictionary([ "colour" => "red" ]);

        $result = $request->toArray();

        $this->assertSame("crates/one.png", $result["label"]);
        $this->assertSame([ "colour" => "red" ], $result["extra"]);
    }

    public function testTheExtraDataIsAddedToTheArray(): void {
        $result = $this->filled()->toArray([ "extraKey" => "extraValue", "name" => "Box" ]);

        $this->assertSame("extraValue", $result["extraKey"]);
        $this->assertSame("Crate", $result["name"]);
    }

    #[DataProvider("providerGetInputSchema")]
    public function testEachInputGivesItsSchemaByTheNameItIsSentWith(string $className, array $expected): void {
        /** @var class-string<SchemaRequest> $className */
        $this->assertSame($expected, $className::getInputSchema());
    }

    /**
     * @return array<string,array{string,array<string,mixed>}>
     */
    public static function providerGetInputSchema(): array {
        return [
            "no inputs"            => [ SchemaRequest::class, [] ],
            "the ID and an enum"   => [ CredentialDeviceRequest::class, [
                "credentialID" => [ "type" => "integer" ],
                "playerID"     => [ "type" => "string" ],
                "provider"     => [ "type" => "string", "enum" => NotificationProvider::getNames() ],
            ] ],
            "the IDs and the dates" => [ NotificationQueueRequest::class, [
                "notificationQueueID"  => [ "type" => "integer" ],
                "notificationQueueIDs" => [ "type" => "array", "items" => [ "type" => "integer" ] ],
                "search"               => [ "type" => "string" ],
                "fromHour"             => [ "type" => "string" ],
                "fromDate"             => [ "type" => "string", "format" => "date" ],
                "toHour"               => [ "type" => "string" ],
                "toDate"               => [ "type" => "string", "format" => "date" ],
                "results"              => [ "type" => "array", "items" => [ "type" => "string" ] ],
            ] ],
        ];
    }
}
