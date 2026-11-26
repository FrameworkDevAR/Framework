<?php
namespace Tests\Tools;

use Framework\Tools\Docs;
use Framework\Discovery\Package;
use Framework\File\Storage;
use Framework\Utils\Strings;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionMethod;

/**
 * The Documentation commands
 *
 * The checks docsCheck is made of are run here one at a time, so a page that
 * stops resolving is caught by the suite rather than only by whoever remembers
 * to run the command before merging. Each returns the number of problems it
 * found, and prints what they were.
 *
 * Nothing here writes: docsIndex and docsSchema rebuild files that are
 * committed, and a test run has no business changing them.
 */
class DocsTest extends TestCase {

    /** @var array<string,string>|null */
    private static ?array $pageContents = null;


    /**
     * Returns the path of the Documentation
     * @return string
     */
    private function docsPath(): string {
        return Package::getBasePath(Package::DocsDir);
    }

    /**
     * Calls one of the private methods the commands are built from
     * @param string $method
     * @param mixed  ...$args
     * @return mixed
     */
    private function call(string $method, mixed ...$args): mixed {
        $reflection = new ReflectionMethod(Docs::class, $method);
        return $reflection->invoke(null, ...$args);
    }

    /**
     * Returns every page, read once for the whole class
     * @return array<string,string>
     */
    private function contents(): array {
        if (self::$pageContents === null) {
            $docsPath = $this->docsPath();
            $result   = [];
            foreach ($this->pages() as $page) {
                $result[$page] = Storage::readFile($docsPath, $page);
            }
            self::$pageContents = $result;
        }
        return self::$pageContents;
    }

    /**
     * Returns the path of every page
     * @return list<string>
     */
    private function pages(): array {
        /** @var list<string> */
        return $this->call("getPages", $this->docsPath());
    }

    /**
     * Runs one of the checks over the given pages, and returns what it printed
     * @param string               $method
     * @param array<string,string> $contents
     * @return array{int,string}
     */
    private function runCheck(string $method, array $contents): array {
        // Three of them read a definition of their own out of the docs directory,
        // and the two that compare a built file against it are not given pages
        $args = match ($method) {
            "checkVersion", "checkNavLinks", "checkPageLinks" => [ $this->docsPath(), $contents ],
            "checkSearchIndex", "checkSchema"                 => [ $this->docsPath() ],
            default                                           => [ $contents ],
        };

        ob_start();
        /** @var int */
        $broken = $this->call($method, ...$args);
        $output = Strings::toString(ob_get_clean());

        return [ $broken, $output ];
    }



    #[DataProvider("providerPages")]
    public function testThePagesAreFound(string $page): void {
        $this->assertStringEndsWith(".html", $page);
        $this->assertStringNotContainsString($this->docsPath(), $page);
        $this->assertFileExists($this->docsPath() . "/$page");
    }

    /**
     * Every page of the Documentation, one per case
     * @return array<string,array{string}>
     */
    public static function providerPages(): array {
        $docs   = Package::getBasePath(Package::DocsDir);
        $method = new ReflectionMethod(Docs::class, "getPages");
        /** @var list<string> */
        $pages  = $method->invoke(null, $docs);
        $result = [];

        foreach ($pages as $page) {
            $result[$page] = [ $page ];
        }
        return $result;
    }

    public function testThereIsDocumentationToCheck(): void {
        $pages = $this->pages();

        $this->assertDirectoryExists($this->docsPath());
        $this->assertGreaterThan(10, count($pages));
        $this->assertContains("index.html", $pages);
        $this->assertContains("404.html", $pages);
    }



    /**
     * Every check docsCheck is made of, against the pages as they are
     * @param string $method
     * @param string $hint
     * @return void
     */
    #[DataProvider("providerChecks")]
    public function testTheDocumentationHoldsUp(string $method, string $hint): void {
        [ $broken, $output ] = $this->runCheck($method, $this->contents());

        $this->assertSame(0, $broken, "$hint\n$output");
    }

    /**
     * The seven checks, with what to do when one of them fails
     * @return array<string,array{string,string}>
     */
    public static function providerChecks(): array {
        return [
            "source links"  => [ "checkSourceLinks", "A source link points at a file, or a method, that is not there" ],
            "version"       => [ "checkVersion", "Run ./framework setVersion to write it everywhere it is shown" ],
            "menu"          => [ "checkNavLinks", "A page is missing from docs/assets/nav.js, or the menu points at one that is gone" ],
            "page links"    => [ "checkPageLinks", "A link between two pages, or an #anchor, does not resolve" ],
            "code examples" => [ "checkCodeExamples", "An example uses a class or calls a method that no longer exists" ],
            "search index"  => [ "checkSearchIndex", "Run ./framework docsIndex to rebuild docs/assets/search.json" ],
            "schema"        => [ "checkSchema", "Run ./framework docsSchema to rebuild docs/assets/schema.json" ],
        ];
    }




    // Above says the checks pass against the pages as they are, which one that
    // always answered zero would do as well. These hand them a page with the
    // fault in it and read what they say about it.

    /**
     * A page with something wrong in it, and what the check makes of it
     * @param string $method
     * @param string $body
     * @param int    $broken
     * @param string $message
     * @return void
     */
    #[DataProvider("providerFaults")]
    public function testTheChecksCatchWhatTheyAreFor(
        string $method,
        string $body,
        int $broken,
        string $message,
    ): void {
        $contents = [ "made-up.html" => $body ];

        // The menu is checked in both directions, so the real pages have to be
        // there or every one of its entries is reported as missing as well
        if ($method === "checkNavLinks") {
            $contents = $this->contents();
            $contents["made-up.html"] = $body;
        }

        [ $found, $output ] = $this->runCheck($method, $contents);

        $this->assertSame($broken, $found, $output);
        if ($message !== "") {
            $this->assertStringContainsString($message, $output);
        }
    }

    /**
     * A page for each way the Documentation goes wrong, and the ones that are fine
     * @return array<string,array{string,string,int,string}>
     */
    public static function providerFaults(): array {
        $url      = "https://github.com/FrameworkDevAR/Framework/blob/";
        $branch   = Docs::SourceBranch;
        $onBranch = fn(string $on, string $path, string $label) =>
            "<a href=\"$url$on/$path\"><code>$label</code></a>";
        $link     = fn(string $path, string $label) => $onBranch($branch, $path, $label);
        $php      = fn(string $code) => "<pre><code class=\"language-php\">$code</code></pre>";
        $buildNav = '<div id="docsNav"></div><script src="assets/nav.js"></script>';

        return [
            // The deploy stamps the branch into the copy it publishes, so one
            // written by hand means the stamping quietly stopped covering it
            "a source link on another branch"       => [
                "checkSourceLinks", $onBranch("dev", "src/Discovery/Package.php", "Package"),
                1, "points at dev, not $branch",
            ],
            "a source link on a tag"                => [
                "checkSourceLinks", $onBranch("v0.16.0", "src/Discovery/Package.php", "Package"),
                1, "points at v0.16.0, not $branch",
            ],
            "a file that is gone on another branch" => [
                // The branch is reported rather than the file, since the file
                // was never looked for on this one
                "checkSourceLinks", $onBranch("dev", "src/Discovery/Gone.php", "Gone"),
                1, "points at dev, not $branch",
            ],

            "a source file that is gone"            => [
                "checkSourceLinks", $link("src/Discovery/Gone.php", "Gone"),
                1, "src/Discovery/Gone.php does not exist",
            ],
            "a source method that is gone"          => [
                "checkSourceLinks", $link("src/Discovery/Package.php", "Package::notThere()"),
                1, "has no notThere()",
            ],
            "a source method that is there"         => [
                "checkSourceLinks", $link("src/Discovery/Package.php", "Package::getVersion()"),
                0, "",
            ],
            "a label without parentheses"           => [
                // An enum case or a constant, which is not asked to be a method
                "checkSourceLinks", $link("src/Discovery/Package.php", "Package::SchemaDir"),
                0, "",
            ],

            "an old version in a snippet"           => [
                "checkVersion", '"frameworkdevar/framework": "dev-main#v0.0.1"',
                1, "requires v0.0.1",
            ],
            "a snippet at the right version"        => [
                "checkVersion", '"frameworkdevar/framework": "dev-main#v' . Package::getVersion() . '"',
                0, "",
            ],
            "two old versions"                      => [
                "checkVersion", "dev-main#v0.0.1 and dev-main#v0.0.2",
                2, "requires v0.0.2",
            ],

            "a page in no menu"                     => [
                "checkNavLinks", $buildNav,
                1, "made-up.html is not in the menu",
            ],
            "a page that renders no menu"           => [
                // Two problems: it is in no menu, and it builds none
                "checkNavLinks", "<p>nothing</p>",
                2, "does not build the menu",
            ],

            "a link to a page that is gone"         => [
                "checkPageLinks", '<a href="nowhere.html">gone</a>',
                1, "nowhere.html does not exist",
            ],
            "an anchor the page has not"            => [
                "checkPageLinks", '<h2 id="here">Here</h2><a href="#elsewhere">go</a>',
                1, "#elsewhere is not an anchor",
            ],
            "an anchor the page has"                => [
                "checkPageLinks", '<h2 id="here">Here</h2><a href="#here">go</a>',
                0, "",
            ],
            "an external link"                      => [
                "checkPageLinks", '<a href="https://example.com/x.html">out</a>',
                0, "",
            ],
            "a mail link"                           => [
                "checkPageLinks", '<a href="mailto:a@b.c">mail</a>',
                0, "",
            ],
            "a link to an asset"                    => [
                "checkPageLinks", '<a href="assets/nav.js">the menu</a>',
                0, "",
            ],

            "an import that is gone"                => [
                "checkCodeExamples", $php('use Framework\Utils\NotAClass;'),
                1, "Framework\\Utils\\NotAClass does not exist",
            ],
            "a call that is gone"                   => [
                "checkCodeExamples", $php("use Framework\Utils\Strings;\nStrings::notAMethod(\"x\");"),
                1, "has no notAMethod()",
            ],
            "an example that holds up"              => [
                "checkCodeExamples", $php("use Framework\Utils\Strings;\nStrings::toString(\"x\");"),
                0, "",
            ],
            "a call on something not imported"      => [
                // Only what the example imports is looked up
                "checkCodeExamples", $php('Whatever::atAll("x");'),
                0, "",
            ],
            "a generated class"                     => [
                // Everything under System is written by the build of each app
                "checkCodeExamples", $php('use Framework\System\Config;'),
                0, "",
            ],
            "a page with no examples"               => [
                "checkCodeExamples", "<p>prose only</p>",
                0, "",
            ],
        ];
    }



    /**
     * The two things assets/version.js has to say, read from a made up copy of it
     *
     * They cannot go through the page provider, because this file is read off
     * the disk rather than handed in with the pages.
     * @param string $definition
     * @param int    $broken
     * @param string $message
     * @return void
     */
    #[DataProvider("providerVersionFile")]
    public function testTheVersionFileIsChecked(string $definition, int $broken, string $message): void {
        $docsPath = sys_get_temp_dir() . "/docsTest" . getmypid();
        Storage::createDir("$docsPath/assets");
        Storage::writeFile("$docsPath/assets/version.js", $definition);

        ob_start();
        /** @var int */
        $found  = $this->call("checkVersion", $docsPath, []);
        $output = Strings::toString(ob_get_clean());
        Storage::deleteDir($docsPath);

        $this->assertSame($broken, $found, $output);
        if ($message !== "") {
            $this->assertStringContainsString($message, $output);
        }
    }

    /**
     * The version is what the sidebar shows, and the branch is the line the
     * deploy stamps to tell the two published copies apart
     * @return array<string,array{string,int,string}>
     */
    public static function providerVersionFile(): array {
        $version = Package::getVersion();
        $branch  = Docs::SourceBranch;
        $good    = "window.DOCS_VERSION = \"$version\";\nwindow.DOCS_BRANCH = \"$branch\";";

        return [
            "both right"       => [ $good, 0, "" ],
            "an old version"   => [ "window.DOCS_VERSION = \"0.0.1\";\nwindow.DOCS_BRANCH = \"$branch\";", 1, "says 0.0.1" ],
            "no version"       => [ "window.DOCS_BRANCH = \"$branch\";", 1, "composer.json says $version" ],
            "the other branch" => [ "window.DOCS_VERSION = \"$version\";\nwindow.DOCS_BRANCH = \"dev\";", 1, "the branch is 'dev'" ],
            "the line renamed" => [ "window.DOCS_VERSION = \"$version\";\nwindow.DOCS_BRANCHES = \"$branch\";", 1, "the branch is ''" ],
            "no branch at all" => [ "window.DOCS_VERSION = \"$version\";", 1, "the branch is ''" ],
            "neither of them"  => [ "", 2, "the branch is ''" ],
        ];
    }

    public function testTheIndexDescribesEveryPageButThe404(): void {
        /** @var list<array<string,string>> */
        $index = $this->call("buildIndex", $this->docsPath());

        $this->assertNotEmpty($index);
        foreach ($index as $entry) {
            // The keys are single letters, since every reader downloads them:
            // the title, the section, the url and the description
            $this->assertSame([ "t", "s", "u", "d" ], array_keys($entry));
            $this->assertStringNotContainsString("404.html", $entry["u"]);
        }
    }

    public function testTheIndexHasAnEntryPerLine(): void {
        /** @var list<array<string,string>> */
        $index   = $this->call("buildIndex", $this->docsPath());
        $encoded = $this->call("encodeIndex", $index);
        $this->assertIsString($encoded);

        // Two lines for the brackets, and one per entry
        $lines = explode("\n", trim($encoded));
        $this->assertCount(count($index) + 2, $lines);
        $this->assertSame($index, json_decode($encoded, true));
    }

    public function testEveryPageHasAnEntryOfItsOwn(): void {
        /** @var list<array<string,string>> */
        $index = $this->call("buildIndex", $this->docsPath());
        $urls  = [];
        foreach ($index as $entry) {
            $urls[Strings::substringBefore($entry["u"], "#")] = true;
        }

        foreach ($this->pages() as $page) {
            if ($page === "404.html") {
                continue;
            }
            $this->assertArrayHasKey($page, $urls, "$page is not in the search index");
        }
    }

    #[DataProvider("providerSchemaTables")]
    public function testTheSchemaIsBuiltFromTheFrameworkModels(string $table): void {
        /** @var array<string,array<string,mixed>> */
        $schema = $this->call("buildSchema");

        $this->assertArrayHasKey($table, $schema);
        $this->assertArrayHasKey("description", $schema[$table]);
        $this->assertArrayHasKey("fields", $schema[$table]);
    }

    /**
     * One table from each of the Framework's own modules
     * @return array<string,array{string}>
     */
    public static function providerSchemaTables(): array {
        return [
            "auth"         => [ "credential" ],
            "email"        => [ "email_queue" ],
            "log"          => [ "log_session" ],
            "notification" => [ "notification_queue" ],
            "core"         => [ "settings" ],
        ];
    }



    /**
     * A link written on a page, and what it points at
     * @param string $page
     * @param string $link
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerResolveLink")]
    public function testALinkResolvesAgainstItsPage(string $page, string $link, string $expected): void {
        $this->assertSame($expected, $this->call("resolveLink", $page, $link));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function providerResolveLink(): array {
        return [
            "beside it"        => [ "guides/routing.html", "signals.html", "guides/signals.html" ],
            "one up"           => [ "guides/routing.html", "../index.html", "index.html" ],
            "two up"           => [ "a/b/c.html", "../../e.html", "e.html" ],
            "one up and down"  => [ "a/b/c.html", "../d/e.html", "a/d/e.html" ],
            "a single dot"     => [ "a/b/c.html", "./d.html", "a/b/d.html" ],
            "from the root"    => [ "index.html", "guides/routing.html", "guides/routing.html" ],
            "into a directory" => [ "index.html", "a/b/c.html", "a/b/c.html" ],
            "an empty link"    => [ "guides/routing.html", "", "guides" ],
            "past the root"    => [ "index.html", "../../x.html", "x.html" ],
        ];
    }



    /**
     * A page, and the part of it the index is built from
     * @param string $contents
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerGetBody")]
    public function testTheBodyIsTheArticle(string $contents, string $expected): void {
        $this->assertSame($expected, $this->call("getBody", $contents));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerGetBody(): array {
        $open = '<article class="doc-main prose">';
        $shut = '<div class="page-nav">';

        return [
            "an article"          => [ "HEAD{$open}INSIDE{$shut}NAV", "INSIDE" ],
            "over several lines"  => [ "{$open}one\ntwo{$shut}", "one\ntwo" ],
            "no article"          => [ "just text", "just text" ],
            "an article unclosed" => [ "{$open}INSIDE", "{$open}INSIDE" ],
            "nothing at all"      => [ "", "" ],
        ];
    }

    /**
     * Some html, and the plain text the index describes it with
     * @param string $contents
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerToText")]
    public function testTheTextIsWhatAReaderWouldRead(string $contents, string $expected): void {
        $this->assertSame($expected, $this->call("toText", $contents));
    }

    /**
     * The code blocks are examples rather than prose, and would drown the results
     * @return array<string,array{string,string}>
     */
    public static function providerToText(): array {
        return [
            "tags taken out"    => [ "<p>Hello <b>there</b></p>", "Hello there" ],
            "entities decoded"  => [ "<p>&amp; more</p>", "& more" ],
            "spaces collapsed"  => [ "<p>a   b\n\nc</p>", "a b c" ],
            "code left out"     => [ "<p>a</p><pre>code</pre><p>b</p>", "a b" ],
            "code with a class" => [ '<pre class="x">code</pre><p>b</p>', "b" ],
            "trimmed"           => [ "  <p>a</p>  ", "a" ],
            "nothing at all"    => [ "", "" ],
        ];
    }

    /**
     * The snippet is cut by characters, since cutting bytes would split one
     * @param string $contents
     * @param int    $expected
     * @return void
     */
    #[DataProvider("providerGetSnippet")]
    public function testTheSnippetIsCutToLength(string $contents, int $expected): void {
        /** @var string */
        $text = $this->call("getSnippet", $contents);

        $this->assertSame($expected, mb_strlen($text));
        $this->assertSame($text, mb_convert_encoding($text, "UTF-8", "UTF-8"));
    }

    /**
     * @return array<string,array{string,int}>
     */
    public static function providerGetSnippet(): array {
        return [
            "shorter than the cut" => [ "<p>short</p>", 5 ],
            "longer than the cut"  => [ str_repeat("word ", 100), 180 ],
            "multi byte"           => [ str_repeat("á", 200), 180 ],
            "nothing at all"       => [ "", 0 ],
        ];
    }



    /**
     * A pattern over some text, and every match it makes with its groups
     * @param string                  $contents
     * @param string                  $pattern
     * @param list<array<int,string>> $expected
     * @return void
     */
    #[DataProvider("providerMatchSets")]
    public function testEveryMatchIsReturnedWithItsGroups(
        string $contents,
        string $pattern,
        array $expected,
    ): void {
        $this->assertSame($expected, $this->call("matchSets", $contents, $pattern));
    }

    /**
     * @return array<string,array{string,string,list<array<int,string>>}>
     */
    public static function providerMatchSets(): array {
        return [
            "two matches"    => [ "a=1 a=2", '~a=(\d)~', [ [ "a=1", "1" ], [ "a=2", "2" ] ] ],
            "one match"      => [ "a=1", '~a=(\d)~', [ [ "a=1", "1" ] ] ],
            "no match"       => [ "nope", '~a=(\d)~', [] ],
            "two groups"     => [ "a=1", '~(a)=(\d)~', [ [ "a=1", "a", "1" ] ] ],
            "no group"       => [ "a=1", '~a=\d~', [ [ "a=1" ] ] ],
            "nothing at all" => [ "", '~a=(\d)~', [] ],
        ];
    }

    /**
     * The first group of the first match, or the default when there was none
     * @param string $contents
     * @param string $expected
     * @return void
     */
    #[DataProvider("providerGetFirstMatch")]
    public function testTheFirstMatchFallsBackToTheDefault(string $contents, string $expected): void {
        $this->assertSame($expected, $this->call("getFirstMatch", $contents, '~a=(\d)~', "d"));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerGetFirstMatch(): array {
        return [
            "the first of two" => [ "a=1 a=2", "1" ],
            "the only one"     => [ "a=2", "2" ],
            "no match"         => [ "nope", "d" ],
            "nothing at all"   => [ "", "d" ],
        ];
    }

    /**
     * A group of a match, which is empty when it did not take part
     * @param array<int,string> $match
     * @param int               $group
     * @param string            $expected
     * @return void
     */
    #[DataProvider("providerGetGroup")]
    public function testAGroupThatDidNotTakePartIsEmpty(array $match, int $group, string $expected): void {
        $this->assertSame($expected, $this->call("getGroup", $match, $group));
    }

    /**
     * @return array<string,array{array<int,string>,int,string}>
     */
    public static function providerGetGroup(): array {
        return [
            "the whole match" => [ [ "full", "one" ], 0, "full" ],
            "the first group" => [ [ "full", "one" ], 1, "one" ],
            "past the end"    => [ [ "full" ], 3, "" ],
            "no match at all" => [ [], 0, "" ],
        ];
    }



    /**
     * A name a code example writes, and whether it is a type that exists
     * @param string $name
     * @param bool   $expected
     * @return void
     */
    #[DataProvider("providerTypeExists")]
    public function testEveryKindOfTypeIsFound(string $name, bool $expected): void {
        $this->assertSame($expected, $this->call("typeExists", $name));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function providerTypeExists(): array {
        return [
            "a class"      => [ Package::class, true ],
            "an interface" => [ "Framework\\Discovery\\Type\\DiscoveryBuilder", true ],
            "a trait"      => [ "Framework\\Enum\\IsEnum", true ],
            "an enum"      => [ "Framework\\Date\\Type\\PeriodType", true ],
            "none of them" => [ "Framework\\NotAClass", false ],
            "empty"        => [ "", false ],
        ];
    }
}
