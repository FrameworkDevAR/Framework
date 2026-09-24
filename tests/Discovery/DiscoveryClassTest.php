<?php
// spell-checker: ignore  ings
namespace Tests\Discovery;

use Framework\Discovery\Attr\Priority;
use Framework\Discovery\Type\DiscoveryClass;

use Tests\Discovery\Fixture\BaseThing;
use Tests\Discovery\Fixture\Thing;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionClass;

/**
 * The DiscoveryClass wrapper
 */
class DiscoveryClassTest extends TestCase {

    /**
     * A class given by name and the same one given as a reflection
     * @param string $className
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerNames")]
    public function testAClassIsTakenEitherWay(string $className, string $expected): void {
        foreach ([ new DiscoveryClass($className), new DiscoveryClass(new ReflectionClass($className)) ] as $class) {
            $this->assertTrue($class->isNotEmpty());
            $this->assertFalse($class->isEmpty());
            $this->assertSame($expected, $class->getName());
            $this->assertSame("\\$expected", $class->getFullyQualifiedName());
        }
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerNames(): array {
        return [
            "a class"     => [ Thing::class, Thing::class ],
            "its parent"  => [ BaseThing::class, BaseThing::class ],
            "the wrapper" => [ DiscoveryClass::class, DiscoveryClass::class ],
        ];
    }

    /**
     * Where the class was written and what it was written under
     * @param string $className
     * @param string $fileName
     * @param string $namespace
     * @return void
     */
    #[DataProvider("providerWhere")]
    public function testTheClassKnowsWhereItComesFrom(
        string $className,
        string $fileName,
        string $namespace,
    ): void {
        $class = new DiscoveryClass($className);

        $this->assertStringEndsWith($fileName, $class->getFileName());
        $this->assertSame($namespace, $class->getNamespaceName());
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerWhere(): array {
        return [
            "a fixture"   => [ Thing::class, "tests/Discovery/Fixture/Thing.php", "Tests\\Discovery\\Fixture" ],
            "its parent"  => [ BaseThing::class, "tests/Discovery/Fixture/BaseThing.php", "Tests\\Discovery\\Fixture" ],
            "the wrapper" => [ DiscoveryClass::class, "src/Discovery/Type/DiscoveryClass.php", "Framework\\Discovery\\Type" ],
        ];
    }



    public function testAnEmptyClassAnswersEverythingWithNothing(): void {
        $class = new DiscoveryClass();

        $this->assertTrue($class->isEmpty());
        $this->assertFalse($class->isNotEmpty());
        $this->assertSame("", $class->getName());
        $this->assertSame("", $class->getFullyQualifiedName());
        $this->assertSame("", $class->getFileName());
        $this->assertSame("", $class->getNamespaceName());
        $this->assertSame([], $class->getMethods());
        $this->assertSame([], $class->getProperties());
        $this->assertSame([], $class->getPropertiesBaseFirst());
        $this->assertSame("", $class->getConstant("Name"));
        $this->assertNull($class->getConstructor());
        $this->assertNull($class->getAttribute(Priority::class));
        $this->assertSame([], $class->getAttributes(Priority::class));
        $this->assertNull($class->newInstance());
        $this->assertNull($class->newInstanceWithoutConstructor());
        $this->assertTrue($class->getParentClass()->isEmpty());
    }

    /**
     * A constant of a class, and what comes back for it
     * @param string $className
     * @param string $name
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerGetConstant")]
    public function testAConstantIsReadFromTheClass(
        string $className,
        string $name,
        string $expected,
    ): void {
        $this->assertSame($expected, (new DiscoveryClass($className))->getConstant($name));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerGetConstant(): array {
        return [
            "one that is there"     => [ Thing::class, "Name", "A Thing" ],
            "one that is not"       => [ Thing::class, "Other", "" ],
            "one of another class"  => [ BaseThing::class, "Name", "" ],
        ];
    }

    /**
     * The parent of the given class, empty when it has none
     * @param string $className
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerParent")]
    public function testTheParentIsWrappedInItsTurn(string $className, string $expected): void {
        $parent = (new DiscoveryClass($className))->getParentClass();

        $this->assertSame($expected, $parent->getName());
        $this->assertSame($expected === "", $parent->isEmpty());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerParent(): array {
        return [
            "a class with one"    => [ Thing::class, BaseThing::class ],
            "a class without one" => [ BaseThing::class, "" ],
            "the parent's parent" => [ DiscoveryClass::class, "" ],
        ];
    }



    /**
     * The full name behind a short one written in a use statement
     * @param list<string> $uses
     * @param string       $name
     * @param string       $expected
     * @return void
     */
    #[DataProvider("providerUseClassName")]
    public function testTheUseIsFoundByItsShortName(array $uses, string $name, string $expected): void {
        $class = new DiscoveryClass(Thing::class, $uses);

        $this->assertSame($expected, $class->getUseClassName($name));
    }

    /**
     * The comparison is on the part after the last slash, not on the end of it
     * @return array<string,array{list<string>,string,string}>
     */
    public static function providerUseClassName(): array {
        $uses = [ "Framework\\Utils\\Strings", "Framework\\Discovery\\Attr\\Priority" ];

        return [
            "the first"       => [ $uses, "Strings", "Framework\\Utils\\Strings" ],
            "the second"      => [ $uses, "Priority", "Framework\\Discovery\\Attr\\Priority" ],
            "not one of them" => [ $uses, "Numbers", "" ],
            "half a name"     => [ $uses, "ings", "" ],
            "the whole thing" => [ $uses, "Framework\\Utils\\Strings", "" ],
            "nothing given"   => [ $uses, "", "" ],
            "no uses at all"  => [ [], "Strings", "" ],
        ];
    }



    /**
     * The priority a class is sorted by
     * @param string $className
     * @param int    $expected
     * @param bool   $hasAttribute
     * @return void
     */
    public function testEveryAttributeOfANameIsRead(): void {
        // A repeatable attribute is read whole, where getAttribute takes the first
        $this->assertCount(1, (new DiscoveryClass(Thing::class))->getAttributes(Priority::class));
        $this->assertSame([], (new DiscoveryClass(BaseThing::class))->getAttributes(Priority::class));
    }

    #[DataProvider("providerPriority")]
    public function testThePriorityIsReadFromTheAttribute(
        string $className,
        int $expected,
        bool $hasAttribute,
    ): void {
        $class = new DiscoveryClass($className);

        $this->assertSame($expected, $class->getPriority());
        $this->assertSame($hasAttribute, $class->getAttribute(Priority::class) !== null);
    }

    /**
     * @return array<string,array{string,int,bool}>
     */
    public static function providerPriority(): array {
        return [
            "with the attribute" => [ Thing::class, Priority::High, true ],
            "without it"         => [ BaseThing::class, Priority::Normal, false ],
            "a Framework class"  => [ DiscoveryClass::class, Priority::Normal, false ],
        ];
    }



    /**
     * The properties of a class, in the order they are read in
     * @param string       $className
     * @param list<string> $ownFirst
     * @param list<string> $baseFirst
     * @return void
     */
    #[DataProvider("providerProperties")]
    public function testThePropertiesComeBackInBothOrders(
        string $className,
        array $ownFirst,
        array $baseFirst,
    ): void {
        $class  = new DiscoveryClass($className);
        $names  = [];
        $bases  = [];
        foreach ($class->getProperties() as $property) {
            $names[] = $property->getName();
        }
        foreach ($class->getPropertiesBaseFirst() as $property) {
            $bases[] = $property->getName();
        }

        $this->assertSame($ownFirst, $names);
        $this->assertSame($baseFirst, $bases);
    }

    /**
     * Reflection lists the class before what it inherits, and the other walks up
     * @return array<string,array{string,list<string>,list<string>}>
     */
    public static function providerProperties(): array {
        return [
            "a class with a parent" => [
                Thing::class,
                [ "colour", "size", "hidden", "id", "name" ],
                [ "id", "name", "colour", "size", "hidden" ],
            ],
            "a class without one"   => [
                BaseThing::class,
                [ "id", "name" ],
                [ "id", "name" ],
            ],
        ];
    }

    /**
     * The methods and the constructor a class declares
     * @param string       $className
     * @param list<string> $methods
     * @param int|null     $parameters
     * @return void
     */
    #[DataProvider("providerMethods")]
    public function testTheMethodsAreTheOnesItDeclares(
        string $className,
        array $methods,
        ?int $parameters,
    ): void {
        $class       = new DiscoveryClass($className);
        $constructor = $class->getConstructor();
        $names       = [];
        foreach ($class->getMethods() as $method) {
            $names[] = $method->getName();
        }

        $this->assertSame($methods, $names);
        $this->assertSame($parameters, $constructor?->getNumberOfParameters());
    }

    /**
     * @return array<string,array{string,list<string>,int|null}>
     */
    public static function providerMethods(): array {
        return [
            "with a constructor"    => [ Thing::class, [ "__construct", "isHidden" ], 1 ],
            "without a constructor" => [ BaseThing::class, [], null ],
        ];
    }



    public function testANewInstanceRunsTheConstructor(): void {
        $class    = new DiscoveryClass(Thing::class);
        $instance = $class->newInstance("blue");

        $this->assertInstanceOf(Thing::class, $instance);
        $this->assertSame("blue", $instance->colour);
        $this->assertFalse($instance->isHidden());
    }

    public function testTheConstructorCanBeRunAfterwards(): void {
        $class    = new DiscoveryClass(Thing::class);
        $instance = $class->newInstanceWithoutConstructor();

        $this->assertInstanceOf(Thing::class, $instance);
        $this->assertSame("", $instance->colour);

        $class->invokeConstructor($instance, "green");
        $this->assertSame("green", $instance->colour);
    }

    public function testInvokingTheConstructorOfAClassWithoutOneDoesNothing(): void {
        $class    = new DiscoveryClass(BaseThing::class);
        $instance = $class->newInstance();

        $this->assertInstanceOf(BaseThing::class, $instance);
        $class->invokeConstructor($instance);
        $this->assertSame(0, $instance->id);
    }
}
