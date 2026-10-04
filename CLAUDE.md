# Framework

A PHP 8.1 framework built around attribute discovery and code generation.

## Committing

**Never commit unless you are asked to.** Leave the work in the working tree and say what is there. This holds even when a change is finished and every check passes, and it holds for `git add` too — staging sweeps up whatever else is in flight. When you are asked, stage the paths you touched by name rather than with `-A` or `.`, so anything being worked on elsewhere stays out of it.

- **Title** — aim for 50 characters, with an absolute hard cutoff at 72 characters.
- **Body** — wrap the text at 72 characters per line, so it reads cleanly in terminal windows and git logs without awkward wrapping.
- **Bug fixes** — start the title with `Fix ` followed by what is being fixed. The `BUG: ` prefix belongs to the Asana task names only, never to a commit:

  ```
  Fix the sort of the CRM Opportunity Table by a Custom Field
  ```

- **Trailer** — sign off as `Co-Authored-By: Claude <noreply@anthropic.com>`, without the model or its size.

## Branches and releases

`main` points at the last release. `dev` holds the development. Nothing else is long-lived.

A release is four steps, and half a release is worse than none, so one command does all of them:

```bash
./framework release              # the next minor, 0.17.0 to 0.18.0
./framework release --patch      # the next patch, 0.17.0 to 0.17.1
./framework release --push       # and offer to push it
```

The version is never given, only moved, so a release cannot land on a number nobody meant. It refuses unless you are on `dev` with nothing uncommitted, the version can move, and no tag claims where it lands. Then it builds, runs PHPStan, PHPCS, PHPUnit and `docsCheck`, and only if all of them pass does it rewrite the version, commit it as `Version 0.18.0`, tag that commit `v0.18.0`, and move `main` to `dev`. Nothing leaves the repository unless `--push` is given, and that asks first.

A failing check writes nothing at all, so a release is either whole or has not started.

Because `main` takes the documentation and the source together at a release, a page on `dev` linking to a file added on `dev` cannot dangle — both arrive at once.

**`main` does not move to publish a documentation change.** The Pages workflow deploys both branches, so `dev` is online under `/dev/` without touching `main`. It used to be the reason `main` drifted ahead of its tag.

## Checks

Run all three before considering work done. PHPStan is at level 10 with strict rules plus the custom ones in `src/Analysis`, so new code needs typed returns, `list<...>` shapes, named bool arguments and `Optional.` on optional params.

```bash
vendor/bin/phpstan analyse
vendor/bin/phpcs src            # 100 character lines
vendor/bin/phpunit
```

**A doc block is a summary line and its tags.** No paragraphs explaining the reasoning — only a genuinely hard function earns those. When something is worth saying, say it as a comment beside the code it is about, where whoever changes that line will read it.

## Tests

**Write cases in a data provider, not one test method per case.** A test method takes the inputs and the expected result as parameters, and a `#[DataProvider("providerX")]` beside it lists the cases, keyed by a short name that reads in the failure message. This is the pattern across `tests/`, and it is what makes adding the next case one line instead of one method.

```php
#[DataProvider("providerGetName")]
public function testTheNameIsShownWithItsAlias(string $name, string $alias, string $expected): void {
    $this->assertSame($expected, (new ConsoleCommand($name, $alias))->getName());
}

/**
 * @return array<string,array{string,string,string}>
 */
public static function providerGetName(): array {
    return [
        "no alias" => [ "build", "", "build" ],
        "an alias" => [ "migrate", "m", "migrate (m)" ],
    ];
}
```

Write a plain test method only when there is genuinely one case: a sequence of steps on one object, or a fact about the repository itself. If you find yourself writing a second method that differs from the first only in its values, that is the signal to turn both into a provider.

Two things a provider is worth reaching for beyond tidiness:

- **It gives each case its own test instance.** Some cases cannot share one — `PHPStan\Testing\RuleTestCase` keeps the rule it was first given, so a second `analyse()` in one method quietly runs the first rule again against a fixture it never meant to see. `tests/Analysis` builds its rule from the provider for exactly this reason.
- **A provider can be built rather than written out.** One case per file in `config/`, per page in `docs/`, per `loadDefault()` call in `src/` — the suite then grows with the repository. When you do that, fail loudly if it comes back empty, or the check silently stops being made.

Assert what a thing holds, not just that it is there. A test asking only whether the keys of a map exist passes just as well when every value was taken from the wrong place.

## The documentation site

`docs/` is the site published by GitHub Pages at `frameworkphp.com.ar`. The source is a workflow, not a branch: `.github/workflows/pages.yml` puts `main`'s `docs/` at the root and `dev`'s under `/dev/`, stamping the dev copy so its source links point at `dev`, its `DOCS_BRANCH` says `dev`, and its `404.html` knows it sits in a subfolder.

**The HTML is the source of truth.** The pages are hand-maintained — there is no generator, no templating step and no markdown behind them. Edit the HTML directly, and match the structure of a neighbouring page in the same folder.

Three private console commands back it, all only visible from inside this repo:

```bash
./framework docs          # serve it locally on port 2000
./framework docsCheck     # verify the links, and the code examples
./framework docsIndex     # rebuild docs/assets/search.json
./framework docsSchema    # rebuild docs/assets/schema.json
```

`docsCheck` covers seven things: that every source link names the branch the pages are written against and points at a file that exists and, when labelled `Class::method()`, at a method it declares; that every page link and `#anchor` resolves; that every `use Framework\...` in a PHP example is a real class whose called methods exist; that the version in `assets/version.js` and in the `dev-main#v` snippets matches `composer.json`, and that the `DOCS_BRANCH` line beside it is the one the deploy expects to stamp; that every menu link in `assets/nav.js` resolves and every page is reachable from it; that `search.json` matches the pages; and that `schema.json` matches the models. Classes under `Framework\System` are skipped, since the build of each app writes those.

Things that are easy to get wrong:

- **Run `docsIndex` after editing any page.** Nothing does it automatically, and the search would keep answering from a stale index — `docsCheck` fails when it is stale.
- **Run `docsSchema` after touching a model.** The published schema is generated from the `#[Model]` attributes, so a new field or description leaves it stale, and `docsCheck` fails on that too.
- **The sidebar lives in `assets/nav.js`, not in the pages.** A new page has to be listed there or nothing links to it, and `docsCheck` says so.
- **The source links are written against `main`, and the deploy stamps `dev` into the copy it publishes under `/dev/`.** So write `blob/main/...` in a page whatever branch you are on — `docsCheck` reports any link that names another one, because that is a link the stamping stopped covering.
- **`vendor/bin/phpcs` needs a path.** On its own it exits with an error and checks nothing, so run `vendor/bin/phpcs src`. The ruleset excludes `tests/`, so pointing it there checks nothing either.
- **Do not hand-edit the version.** `./framework incVersion` and `decVersion`, each of which takes `--patch`, rewrite `composer.json`, `README.md` and every docs page — the sidebar badge, the GitHub tag it links to, and the `dev-main#v` requires. A release does it for you.
- `docs/404.html` is served by Pages for any missing path at any depth, which is why it carries a `<base href>` that its siblings do not. It is excluded from the search index.
