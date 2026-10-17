<?php
namespace Tests\Discovery;

use Framework\Discovery\Attr\ConsoleCommand;

use Tests\Discovery\Fixture\Commands;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;

/**
 * The Console Command Attribute
 */
class ConsoleCommandTest extends TestCase {

    #[\Override]
    protected function setUp(): void {
        Commands::reset();
    }

    /**
     * Returns a Command pointing at one of the fixture handlers
     * @param string $handler
     * @return ConsoleCommand
     */
    private function command(string $handler): ConsoleCommand {
        $command = new ConsoleCommand("test");
        return $command->setHandler(new ReflectionMethod(Commands::class, $handler));
    }



    /**
     * The name a command is listed under
     * @param string $name
     * @param string $alias
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerGetName")]
    public function testTheNameIsShownWithItsAlias(string $name, string $alias, string $expected): void {
        $this->assertSame($expected, (new ConsoleCommand($name, $alias))->getName());
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerGetName(): array {
        return [
            "no alias"     => [ "build", "", "build" ],
            "an alias"     => [ "migrate", "m", "migrate (m)" ],
            "a dashed one" => [ "version", "-v", "version (-v)" ],
        ];
    }

    /**
     * Whether the given name reaches the command
     * @param string $name
     * @param string $alias
     * @param string $given
     * @param bool   $expected
     * @return void
     */
    #[DataProvider("providerShouldInvoke")]
    public function testEitherTheNameOrTheAliasInvokesIt(
        string $name,
        string $alias,
        string $given,
        bool $expected,
    ): void {
        $this->assertSame($expected, (new ConsoleCommand($name, $alias))->shouldInvoke($given));
    }

    /**
     * @return array<string,array{string,string,string,bool}>
     */
    public static function providerShouldInvoke(): array {
        return [
            "the name"       => [ "migrate", "m", "migrate", true ],
            "the alias"      => [ "migrate", "m", "m", true ],
            "another name"   => [ "migrate", "m", "migrations", false ],
            "a longer alias" => [ "migrate", "m", "mi", false ],
            "another case"   => [ "migrate", "m", "Migrate", false ],

            // Every command without an alias would answer to "" otherwise
            "nothing given"  => [ "migrate", "", "", false ],
            "nothing either" => [ "migrate", "m", "", false ],
        ];
    }

    /**
     * Whether the command is one only the Framework itself shows
     * @param bool $isPrivate
     * @return void
     */
    #[DataProvider("providerIsPrivate")]
    public function testAPrivateCommandSaysSo(bool $isPrivate): void {
        $this->assertSame($isPrivate, (new ConsoleCommand("docs", isPrivate: $isPrivate))->isPrivate);
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function providerIsPrivate(): array {
        return [ "private" => [ true ], "public" => [ false ] ];
    }



    /**
     * The usage line built from the handler's parameters
     * @param string $handler
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerGetArguments")]
    public function testTheArgumentsAreListedFromTheHandler(string $handler, string $expected): void {
        $this->assertSame($expected, $this->command($handler)->getArguments());
    }

    /**
     * A boolean is listed as a bare flag, since it needs no value
     * @return array<string,array{string,string}>
     */
    public static function providerGetArguments(): array {
        return [
            "three of them" => [ "withArgs", "--name=<value> --amount=<value> --force" ],
            "one float"     => [ "withFloat", "--rate=<value>" ],
            "camel case"    => [ "withCamelCase", "--userName=<value>" ],
            "none at all"   => [ "noArgs", "" ],
        ];
    }

    public function testWithoutAHandlerThereIsNothingToList(): void {
        $command = new ConsoleCommand("test");

        $this->assertSame("", $command->getArguments());
        $this->assertFalse($command->invoke([]));
    }



    /**
     * What the handler was given for the arguments written on the console
     * @param string              $handler
     * @param list<string>        $arguments
     * @param array<string,mixed> $expected
     * @return void
     */
    #[DataProvider("providerInvoke")]
    public function testTheHandlerIsCalledWithTheGivenArguments(
        string $handler,
        array $arguments,
        array $expected,
    ): void {
        $this->assertTrue($this->command($handler)->invoke($arguments));
        $this->assertTrue(Commands::$wasCalled);
        $this->assertSame($expected, Commands::$received);
    }

    /**
     * The dashes are optional, the name is matched without its case, and what
     * was not given is left to the handler's own defaults
     * @return array<string,array{string,list<string>,array<string,mixed>}>
     */
    public static function providerInvoke(): array {
        $filled  = fn(mixed $amount, mixed $force) => [ "name" => "bob", "amount" => $amount, "force" => $force ];
        $default = $filled(0, false);

        return [
            "all of them"      => [ "withArgs", [ "--name=bob", "--amount=12", "--force" ], $filled(12, true) ],
            "only the needed"  => [ "withArgs", [ "--name=bob" ], $default ],
            "one it wants not" => [ "withArgs", [ "--name=bob", "--colour=red" ], $default ],
            "the last wins"    => [ "withArgs", [ "--name=ann", "--name=bob" ], $default ],
            "no dashes"        => [ "withArgs", [ "name=bob" ], $default ],
            "upper case"       => [ "withArgs", [ "--NAME=bob" ], $default ],
            "mixed case"       => [ "withArgs", [ "--Name=bob" ], $default ],
            "an empty value"   => [ "withArgs", [ "--name=" ], [ "name" => "", "amount" => 0, "force" => false ] ],

            // The parameter is spelled in camel case and the console in either
            "camel case"       => [ "withCamelCase", [ "--username=bob" ], [ "userName" => "bob" ] ],
            "as it is written" => [ "withCamelCase", [ "--userName=bob" ], [ "userName" => "bob" ] ],

            // A number reaching a string parameter comes back as it was written
            "a number as text" => [ "withArgs", [ "--name=2024" ], [ "name" => "2024", "amount" => 0, "force" => false ] ],
            "a decimal as text" => [ "withArgs", [ "--name=4.0" ], [ "name" => "4.0", "amount" => 0, "force" => false ] ],
            "a version as text" => [ "withArgs", [ "--name=4.10" ], [ "name" => "4.10", "amount" => 0, "force" => false ] ],

            // A bare flag is true whatever the parameter asks for, which a
            // string one is handed as "1"
            "a bare name"      => [ "withArgs", [ "--name" ], [ "name" => "1", "amount" => 0, "force" => false ] ],

            "an integer"       => [ "withArgs", [ "--name=bob", "--amount=12" ], $filled(12, false) ],
            "a zero"           => [ "withArgs", [ "--name=bob", "--amount=0" ], $filled(0, false) ],
            "a negative"       => [ "withArgs", [ "--name=bob", "--amount=-3" ], $filled(-3, false) ],

            // Written as one cast for every number, 1.5 arrived as 1
            "a decimal"        => [ "withFloat", [ "--rate=1.5" ], [ "rate" => 1.5 ] ],
            "a whole float"    => [ "withFloat", [ "--rate=2" ], [ "rate" => 2.0 ] ],

            "a bare flag"      => [ "withArgs", [ "--name=bob", "--force" ], $filled(0, true) ],
            "true"             => [ "withArgs", [ "--name=bob", "--force=true" ], $filled(0, true) ],
            "upper true"       => [ "withArgs", [ "--name=bob", "--force=TRUE" ], $filled(0, true) ],
            "false"            => [ "withArgs", [ "--name=bob", "--force=false" ], $filled(0, false) ],
            "upper false"      => [ "withArgs", [ "--name=bob", "--force=False" ], $filled(0, false) ],
        ];
    }

    public function testAHandlerTakingNothingIsCalledWithNothing(): void {
        $this->assertTrue($this->command("noArgs")->invoke([]));
        $this->assertTrue(Commands::$wasCalled);
    }

    /**
     * The ways of writing an argument that leave the handler uncalled
     * @param string       $handler
     * @param list<string> $arguments
     * @return void
     */
    #[DataProvider("providerRefused")]
    public function testARequiredArgumentThatIsMissingStopsIt(string $handler, array $arguments): void {
        $this->assertFalse($this->command($handler)->invoke($arguments));
        $this->assertFalse(Commands::$wasCalled);
    }

    /**
     * @return array<string,array{string,list<string>}>
     */
    public static function providerRefused(): array {
        return [
            "nothing given"    => [ "withArgs", [] ],
            "only the others"  => [ "withArgs", [ "--amount=12", "--force" ] ],
            "one it wants not" => [ "withArgs", [ "--colour=red" ] ],

            // Only "--" is stripped, so this is read as an argument called "-name"
            "a single dash"    => [ "withArgs", [ "-name=bob" ] ],
            "three dashes"     => [ "withArgs", [ "---name=bob" ] ],
        ];
    }
}
