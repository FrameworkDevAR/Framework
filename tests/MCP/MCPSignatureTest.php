<?php
namespace Tests\MCP;

use Framework\Auth\Schema\CredentialDeviceRequest;
use Framework\IO\Request;
use Framework\MCP\MCPSignature;
use Framework\Date\Date;
use Framework\System\NotificationProvider;
use Framework\MCP\Attr\MCPTool;
use Framework\System\Access;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;

class TestSignatureTools {
    public static function none(): void {
    }

    #[MCPTool(Access::API, "The text of the attribute")]
    public static function described(): void {
    }

    /**
     * Lists the Tasks, 50 at a time
     * unless an amount is given
     * @return void
     */
    public static function longer(): void {
    }

    public static function request(Request $request): void {
    }

    public static function schema(CredentialDeviceRequest $request): void {
    }

    /**
     * Moves a Task
     * @param int       $taskID The ID of the Task.
     * @param string    $name   Optional. The new name.
     * @param list<int> $tagIDs Optional.
     * @param bool      $isDone Optional.
     * @return void
     */
    public static function typed(int $taskID, ?string $name = null, array $tagIDs = [], bool $isDone = false): void {
    }

    public static function plain(float $amount): void {
    }

    /**
     * Gets the Devices of a Provider
     * @param NotificationProvider $provider
     * @param Date|null            $fromDate Optional.
     * @return void
     */
    public static function getDevices(NotificationProvider $provider, ?Date $fromDate = null): void {
    }

    /**
     * Searches the Devices
     * @param CredentialDeviceRequest $request
     * @param int                     $authorID Optional. The admin that asks for it.
     * @return void
     */
    public static function searchDevices(CredentialDeviceRequest $request, int $authorID = 0): void {
    }

    public static function unknown(Request $request, int $extra): void {
    }

    public static function other(object $value): void {
    }
}

/**
 * The MCP Signature
 */
class MCPSignatureTest extends TestCase {

    #[DataProvider("providerGetArguments")]
    public function testTheArgumentsArePassedAsTheToolTakesThem(string $method, ?string $expected): void {
        $reflection = new ReflectionMethod(TestSignatureTools::class, $method);
        $this->assertSame($expected, MCPSignature::getArguments($reflection));
    }

    /**
     * @return array<string,array{string,string|null}>
     */
    public static function providerGetArguments(): array {
        return [
            "no params"             => [ "none", "" ],
            "the Request"           => [ "request", "\$request" ],
            "a Schema Request"      => [
                "schema",
                "\\Framework\\Auth\\Schema\\CredentialDeviceRequest::fromRequest(\$request)",
            ],
            "params of their own"   => [
                "typed",
                "taskID: \$request->getInt(\"taskID\"), " .
                "name: \$request->has(\"name\") ? \$request->getString(\"name\") : null, " .
                "tagIDs: \$request->has(\"tagIDs\") ? \$request->getInts(\"tagIDs\") : [], " .
                "isDone: \$request->has(\"isDone\") ? \$request->getBool(\"isDone\") : false",
            ],
            "a float"               => [ "plain", "amount: \$request->getFloat(\"amount\")" ],
            "the Request and more"  => [ "unknown", "\$request, extra: \$request->getInt(\"extra\")" ],
            "an Enum and a Date"    => [
                "getDevices",
                "provider: \\" . NotificationProvider::class . "::fromValue(\$request->getString(\"provider\")), " .
                    "fromDate: \$request->has(\"fromDate\") ? \$request->getDate(\"fromDate\") : null",
            ],
            "a Schema and more"     => [
                "searchDevices",
                "\\Framework\\Auth\\Schema\\CredentialDeviceRequest::fromRequest(\$request), " .
                    "authorID: \$request->has(\"authorID\") ? \$request->getInt(\"authorID\") : 0",
            ],
            "a type with no getter" => [ "other", null ],
        ];
    }

    #[DataProvider("providerGetInputSchema")]
    public function testTheSchemaComesFromTheParamsOrTheRequest(string $method, array $expected): void {
        $reflection = new ReflectionMethod(TestSignatureTools::class, $method);
        $this->assertEquals($expected, MCPSignature::getInputSchema($reflection));
    }

    /**
     * @return array<string,array{string,array<string,mixed>}>
     */
    public static function providerGetInputSchema(): array {
        return [
            "no params"           => [ "none", [ "type" => "object", "properties" => (object)[] ] ],
            "the Request"         => [ "request", [ "type" => "object", "properties" => (object)[] ] ],
            "a Schema Request"    => [ "schema", [
                "type"       => "object",
                "properties" => (object)CredentialDeviceRequest::getInputSchema(),
            ] ],
            "a list with more"    => [ "searchDevices", [
                "type"       => "object",
                "properties" => (object)(CredentialDeviceRequest::getInputSchema() + MCPSignature::getListSchema() + [
                    "authorID" => [ "type" => "integer", "description" => "The admin that asks for it." ],
                ]),
            ] ],
            "an Enum and a Date"  => [ "getDevices", [
                "type"       => "object",
                "properties" => (object)[
                    "provider" => [ "type" => "string", "enum" => NotificationProvider::getNames() ],
                    "fromDate" => [ "type" => "string", "format" => "date" ],
                ],
                "required"   => [ "provider" ],
            ] ],
            "params of their own" => [ "typed", [
                "type"       => "object",
                "properties" => (object)[
                    "taskID" => [ "type" => "integer", "description" => "The ID of the Task." ],
                    "name"   => [ "type" => "string", "description" => "The new name." ],
                    "tagIDs" => [ "type" => "array", "items" => [ "type" => "integer" ] ],
                    "isDone" => [ "type" => "boolean" ],
                ],
                "required"   => [ "taskID" ],
            ] ],
        ];
    }

    #[DataProvider("providerGetRequired")]
    public function testGetRequired(string $method, array $expected): void {
        $reflection = new ReflectionMethod(TestSignatureTools::class, $method);
        $this->assertSame($expected, MCPSignature::getRequired($reflection));
    }

    /**
     * @return array<string,array{string,list<string>}>
     */
    public static function providerGetRequired(): array {
        return [
            "none"              => [ "none", [] ],
            "an Enum"           => [ "getDevices", [ "provider" ] ],
            "of the params"     => [ "typed", [ "taskID" ] ],
        ];
    }

    #[DataProvider("providerGetDescription")]
    public function testGetDescription(string $method, string $expected): void {
        $reflection = new ReflectionMethod(TestSignatureTools::class, $method);
        $this->assertSame($expected, MCPSignature::getDescription($reflection));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerGetDescription(): array {
        return [
            "the doc comment" => [ "typed", "Moves a Task" ],
            "the attribute"   => [ "described", "The text of the attribute" ],
            "several lines"   => [ "longer", "Lists the Tasks, 50 at a time unless an amount is given" ],
            "none"            => [ "none", "" ],
        ];
    }
}
