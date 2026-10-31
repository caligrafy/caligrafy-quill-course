# Lesson 12 - Ship it: tests, review, doctor

**In this lesson** you make Bookshelf safe to put online: unit tests that check the rules of your capabilities
without a database, a smoke test that calls the running application the way every experience does, the `doctor`
health check, the end of the legacy API key, a code review by the `caligrafy-reviewer` agent, an `upgrade` preview,
and a go-live checklist.

**Before you start:** lesson 11 is done (tag `lesson-11`), and the containers run: `docker compose ps` shows
`bookshelf-dev-box-1`, `bookshelf-mysql-1` and `bookshelf-phpmyadmin-1`.

**Time:** 60 minutes.

---

## New concepts

**Automated tests** are small programs that check your program, so you can run them after every change instead of
clicking through every page. You write two kinds:

| Kind | What it checks | Needs | Here |
|---|---|---|---|
| **Unit test** | one piece of code alone: the rules of a capability | PHP only: no web server, no database | `qa/bookshelf/BookRulesTest.php` |
| **Smoke test** | the running application answers each experience with the expected HTTP status | the containers running | `qa/bookshelf/smoke.sh` |

**PHPUnit** is the test tool of PHP. It is already in `vendor/` (quickstart installed it). A test class extends
`PHPUnit\Framework\TestCase`; each public method whose name starts with `test` is one test; an **assertion** such as
`$this->assertSame($expected, $actual)` fails the test when the two values differ.

**Why test the rules, and why without a database?** The `$rules` of a capability are the same for the pages, REST,
the Vue apps and AI agents: test them once and you have tested them for every experience. Every service has a
`validate($input)` method (from `Caligrafy\Service`) that applies `$filters` and `$rules` and returns what `handle()`
would receive, or throws a `ServiceError` of kind `invalid` with one message per field (`$e->fields`). It does not
run `handle()`, so it needs no database: the tests run in a fraction of a second.

**Where tests live.** Not in `application/`: Caligrafy loads every PHP file of `application/` on every request, so a
test file there would run inside your website. We put them in `qa/bookshelf/`. The `.htaccess` of Caligrafy
refuses to serve the `qa` folder.

**A smoke test checks the refusals too.** A site that is safe to put online must say **no** correctly: 401 when a
visitor writes, 403 when a read-only token writes, 403 for `.env`. The smoke test checks both the yes and the no.

**`doctor`** (`php caligrafer.php doctor`) is a read-only health check of the project: PHP, `vendor/`, `.env` and the
keys (never printed), the legacy API key setting, the database and the framework tables, and every optional service
configured in `.env`. Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured).

**The legacy API key.** Before tokens existed, programs used the `API_KEY` of `.env`. Every key made from one
`APP_KEY` is the same key: full access, and no way to revoke one alone. Since lesson 11 every program and agent has
its own **personal access token** and the browser apps use the session, so the old key is no longer needed:
`LEGACY_API_KEY=false` in `.env` turns it off.

**`upgrade`.** `quickstart` copied the framework's template files into `application/`. When a later release of
Caligrafy Quill comes out, `php caligrafer.php upgrade` compares your copies with the new templates and updates the
files you never changed. Without `--apply` it is a **dry run**: it only shows the plan.

---

## Manual track

### Step 1 - The test settings

Create the folder `qa/bookshelf` and two files in it.

**File `qa/bookshelf/phpunit.xml`** (the settings of PHPUnit for these tests):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- The Bookshelf unit tests. Run: docker compose exec dev-box vendor/bin/phpunit -c qa/bookshelf/phpunit.xml -->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="../../vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="bootstrap.php"
         colors="true"
         cacheDirectory="../../.phpunit.cache">
    <testsuites>
        <testsuite name="bookshelf">
            <directory>.</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**File `qa/bookshelf/bootstrap.php`** (runs before the tests: loads the Caligrafy classes a service needs, then every
Bookshelf service):

```php
<?php
// Loads what the Bookshelf unit tests need, without starting the framework: no web server, no database, no .env
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
foreach (array('Validator', 'Context', 'ServiceError', 'Capability', 'Service') as $class) {
    require_once $root . "/framework/core/$class.php";
}
foreach (glob($root . '/application/services/Bookshelf/*.php') as $service) {
    require_once $service;
}
```

- `dirname(__DIR__, 2)` is the project folder (two levels above `qa/bookshelf`).
- `vendor/autoload.php` loads PHPUnit and the libraries, including GUMP, the validation library behind `$rules`.
- The five framework classes are the ones a service uses: the validator, the context, the errors, the
  `#[Capability]` attribute and the `Service` base class. Nothing starts the framework, reads `.env` or connects
  to MySQL.

### Step 2 - The unit tests

**File `qa/bookshelf/BookRulesTest.php`** (five tests of the rules of `create_book`, `update_book` and `search_books`):

```php
<?php

use Caligrafy\ServiceError;
use PHPUnit\Framework\TestCase;

// The rules of the Bookshelf capabilities: the same for the pages, the REST API, the Vue apps and AI agents.
// validate() checks the inputs against $rules, without the database: what handle() would receive, or a refusal
class BookRulesTest extends TestCase
{
    private function refusal($service, array $input)
    {
        try {
            $service->validate($input);
        } catch (ServiceError $e) {
            return $e;
        }
        $this->fail('The input was accepted');
    }

    public function testABookNeedsATitleAYearAndAnAuthor()
    {
        $e = $this->refusal(new CreateBook(), array());
        $this->assertSame('invalid', $e->kind);
        $this->assertSame(array('title', 'published_year', 'author_id'), array_keys($e->fields));
    }

    public function testTheYearMustBeAWholeNumber()
    {
        $e = $this->refusal(new CreateBook(), array('title' => 'Kindred', 'published_year' => 'soon', 'author_id' => 2));
        $this->assertArrayHasKey('published_year', $e->fields);
    }

    public function testAValidBookIsTrimmedAndKeepsOnlyItsInputs()
    {
        $input = (new CreateBook())->validate(array('title' => '  Kindred ', 'published_year' => 1979, 'author_id' => 2, 'price' => 9));
        $this->assertSame('Kindred', $input['title']);
        $this->assertArrayNotHasKey('price', $input);   // only the inputs of $rules reach handle()
    }

    public function testUpdatingNeedsTheIdOfTheBook()
    {
        $e = $this->refusal(new UpdateBook(), array('title' => 'Kindred'));
        $this->assertArrayHasKey('id', $e->fields);
    }

    public function testASearchNeedsAtLeastTwoCharacters()
    {
        $e = $this->refusal(new SearchBooks(), array('query' => 'a'));
        $this->assertArrayHasKey('query', $e->fields);
    }
}
```

- `refusal()` is a helper (its name does not start with `test`, so it is not a test): it calls `validate()`, returns
  the `ServiceError`, and fails the test if the input was accepted.
- The first test checks that an empty book is refused for its three required inputs, in the order of `$rules`.
- The third test checks two promises of lesson 05: the `trim` filter removed the spaces, and an input that is not in
  `$rules` (`price`) never reaches `handle()`.

Run the tests from the `bookshelf` folder:
```bash
docker compose exec dev-box vendor/bin/phpunit -c qa/bookshelf/phpunit.xml
```
Expected (among lines that show the PHPUnit and PHP versions and the time):
```
.....                                                               5 / 5 (100%)

OK (5 tests, 7 assertions)
```
Each `.` is a test that passed.

Now see a test catch a bug. In `application/services/Bookshelf/CreateBook.php`, change
`'title' => 'required|max_len,200'` into `'title' => 'max_len,200'`, save, wait 2 seconds and run the tests again.
The line of dots now starts with an `F` (a failure):
```
F....                                                               5 / 5 (100%)

There was 1 failure:

1) BookRulesTest::testABookNeedsATitleAYearAndAnAuthor
Failed asserting that two arrays are identical.
--- Expected
+++ Actual
@@ @@
 Array &0 [
-    0 => 'title',
-    1 => 'published_year',
-    2 => 'author_id',
+    0 => 'published_year',
+    1 => 'author_id',
 ]

/var/www/html/qa/bookshelf/BookRulesTest.php:24

FAILURES!
Tests: 5, Assertions: 7, Failures: 1.
```
The test expected three refused fields and got two: `title` is no longer required, on every experience at once.
Put `required|` back and check that you get `OK (5 tests, 7 assertions)` again.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Cannot open file "qa/bookshelf/phpunit.xml"` | The command was not run from the `bookshelf` folder | `cd` to the `bookshelf` folder |
> | `vendor/bin/phpunit: No such file or directory` | The PHP libraries are not installed | Run `.bin/caligrafer quickstart` again: it installs what is missing |
> | `Class "CreateBook" not found` | `bootstrap.php` was not loaded (a typo in `bootstrap="bootstrap.php"`) or the services folder is not `application/services/Bookshelf` | Compare both files with step 1 |
> | `Failed opening required '.../framework/core/...'` | A class name in the list of `bootstrap.php` is misspelled | Compare with step 1 |
> | A test fails although you changed nothing | A rule in a service differs from the checkpoint | Compare the service with [`checkpoints/lesson-12`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-12) |
> | Your change does not show | PHP caches compiled files and checks for changes every 2 seconds | Wait 2 seconds and run again |

### Step 3 - The smoke test

**File `qa/bookshelf/smoke.sh`** (one line per experience; each `check` calls a URL with `curl` and compares the
status code):

```bash
#!/bin/bash
# Bookshelf smoke test: calls the application the way each experience does and checks the HTTP status codes.
# Run from the project folder: bash qa/bookshelf/smoke.sh   (or: bash qa/bookshelf/smoke.sh http://localhost:8081)
# With a read-only token, it also checks that it cannot write: READ_TOKEN=cq_... bash qa/bookshelf/smoke.sh
BASE="${1:-http://localhost:8080}"
JSON=(-H "Accept: application/json" -H "Content-Type: application/json")
FAILED=0

check() {   # check <expected status> <description> <curl arguments...>
    local expected="$1" description="$2"
    shift 2
    local status
    status="$(curl -s -o /dev/null -w '%{http_code}' "$@")"
    if [ "$status" = "$expected" ]; then
        echo "OK   $status $description"
    else
        echo "FAIL $status $description (expected $expected)"
        FAILED=1
    fi
}

# web pages
check 200 "the catalog page"             "$BASE/books"
check 200 "the page of book 1"           "$BASE/books/1"
check 404 "a book that does not exist"   "$BASE/books/999999"
check 403 ".env is never served"         "$BASE/.env"
# REST
check 200 "REST: list the books"         "${JSON[@]}" "$BASE/api/books"
check 422 "REST: a search too short"     -X POST "${JSON[@]}" -d '{"query":"a"}' "$BASE/capabilities/search_books"
check 401 "REST: a visitor cannot write" -X POST "${JSON[@]}" -d '{"title":"x"}' "$BASE/api/books"
# AI agents
check 401 "MCP: no token, no tools"      -X POST "${JSON[@]}" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' "$BASE/mcp"
if [ -n "$READ_TOKEN" ]; then
    check 403 "REST: a read-only token cannot write" -X POST "${JSON[@]}" -H "Authorization: Bearer $READ_TOKEN" \
          -d '{"title":"x","published_year":2000,"author_id":1}' "$BASE/api/books"
fi

if [ "$FAILED" = "1" ]; then echo "Some checks failed."; exit 1; fi
echo "All checks passed."
```

- `check 200 "the catalog page" "$BASE/books"` means: request `/books`, expect `200`. Everything after the
  description is passed to `curl`.
- `-o /dev/null -w '%{http_code}'` makes `curl` throw the body away and print only the status code.
- The last check needs a token that may read but not write. It is only run when the variable `READ_TOKEN` is set.

Run it from the `bookshelf` folder (it runs on your computer, not in the container):
```bash
bash qa/bookshelf/smoke.sh
```
Expected: 8 checks.
```
OK   200 the catalog page
OK   200 the page of book 1
OK   404 a book that does not exist
OK   403 .env is never served
OK   200 REST: list the books
OK   422 REST: a search too short
OK   401 REST: a visitor cannot write
OK   401 MCP: no token, no tools
All checks passed.
```

Now with a read-only token. This one command creates a token that expires after one day and passes it to the test:
```bash
READ_TOKEN=$(docker compose exec -T dev-box php caligrafer.php token create "smoke test" --scopes books:read --expires 1 | grep -oE 'cq_[0-9]+_[A-Za-z0-9]+') bash qa/bookshelf/smoke.sh
```
- `$( ... )` runs the command inside and uses what it prints.
- `-T` tells Docker not to open a terminal: needed when the output goes to another command instead of your screen.
- `grep -oE 'cq_[0-9]+_[A-Za-z0-9]+'` keeps only the token from what `token create` prints.

Expected: the same 8 lines, then `OK   403 REST: a read-only token cannot write`, then `All checks passed.`
Each run creates a new "smoke test" token: `docker compose exec dev-box php caligrafer.php token list` shows them;
they stop working after one day, and `token revoke <id>` stops one at once.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | Every line `FAIL 000` | The site is not reachable at that address | `docker compose up -d dev-box`, or pass your address: `bash qa/bookshelf/smoke.sh http://localhost:8081` |
> | `FAIL 422 REST: a visitor cannot write (expected 401)` | `create_book` is still `public: true`: the visitor got through to the rules | Remove `public: true` from `CreateBook.php` (lesson 11) |
> | `FAIL 404 the page of book 1` | Book 1 was deleted | Run the seed again: `docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/seed.sql` |
> | `FAIL 401 REST: a read-only token cannot write (expected 403)` | `READ_TOKEN` is empty: the `token create` command failed, or `-T` is missing | Run the `token create` command alone and read its output |
> | `bash: qa/bookshelf/smoke.sh: No such file or directory` | Wrong folder | Run it from the `bookshelf` folder |

### Step 4 - Turn off the legacy API key

Run the health check:
```bash
docker compose exec dev-box php caligrafer.php doctor
```
It ends with `13 OK, 0 WARN, 0 FAIL, 6 SKIP` and contains this line:
```
OK    Legacy API key: allowed (every generated key is the same key: full access, no revocation); set LEGACY_API_KEY=false once clients use tokens
```
Open `.env` in your editor. Find `LEGACY_API_KEY=true` (quickstart leaves it `true`) and change it to:
```
LEGACY_API_KEY=false
```
Save, and run `doctor` again. The line is now:
```
OK    Legacy API key: off: API clients use personal access tokens
```
and it still ends with `13 OK, 0 WARN, 0 FAIL, 6 SKIP`. The `SKIP` lines are the optional services you did not
configure (OpenAI, Groq, Stripe, Coinbase, Watson, mail): normal. Run the smoke test again: all checks pass, because
no experience of Bookshelf uses the old key. `.env` is not committed, so remember to set it on every server.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | A database failure when you run `php caligrafer.php doctor` on your computer | Outside Docker the host `mysql` does not exist | Always run it in the container: `docker compose exec dev-box php caligrafer.php doctor` |
> | The line still says `allowed` | The change was not saved, or you edited another file (`.env.example`) | Edit `.env` in the `bookshelf` folder |

### Step 5 - Preview an upgrade

```bash
docker compose exec dev-box php caligrafer.php upgrade
```
Expected (among other lines):
```
  routes      application/config/routing/web.php: your routes: never replaced
  identical  35 file(s)
 Nothing was changed.
```
Your project was created with the current release, so every template is `identical`, and `web.php` is yours: it is
never replaced. `upgrade` also reports the client apps of `public/` that differ from their template, without ever
changing them:
```
 Client apps (report only, never modified; compare them with framework/plugins/<type> by hand):
  public/finder (app): 2 file(s) differ from framework/plugins/app: index.php, scripts/main.js
```
That is expected: you rewrote both files in lesson 09. The Vite app `public/bookshelf-spa` is not listed: `upgrade`
compares only the apps made from `framework/plugins` (`app`, `bot`, `facedetect`, `ml`).

When a later release comes out, you update the `framework/` folder, run `upgrade` to read the plan, then
`docker compose exec dev-box php caligrafer.php upgrade --apply`: files you never changed are updated (with a backup
in `backups/`), files you changed get the new version next to them as `<file>.caligrafy-new` for you to merge. Run
the unit tests, the smoke test and `doctor` afterwards.

### Step 6 - Before going live

Go through this list before you put Bookshelf on a server. Lesson 11 and this lesson already did the first ones.

- [ ] Nothing that writes is `public: true` (lesson 11): `docker compose exec dev-box php caligrafer.php capabilities` shows `public` only on the read capabilities.
- [ ] `LEGACY_API_KEY=false` in the `.env` of the server (step 4).
- [ ] Tokens: `token list`, then `token revoke <id>` for every token you no longer use. Each program or agent has its own token with the smallest scopes, and `--expires <days>` when it is temporary.
- [ ] HTTPS on the server (it is also what lets phones install the app, lesson 10).
- [ ] `APP_ENV=production` and `APP_DEBUG=false` in the `.env` of the server (see [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading) and [Getting started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)).
- [ ] `MCP_ALLOWED_ORIGINS` only if a browser-based MCP client must reach `/mcp`; leave it empty otherwise.
- [ ] `doctor` on the server, then the smoke test against the server's address: `bash qa/bookshelf/smoke.sh https://<your-host>` (it checks that `.env` is not served).
- [ ] Read `docker compose exec dev-box php caligrafer.php audit` regularly: every refusal is there, with who and through which experience.
- [ ] Never commit `.env` or a token.

---

## With Claude

Start Claude Code in the `bookshelf` folder (`claude`).

### Review your code

```
Use the caligrafy-reviewer agent to review application/ and public/js
```

**What Claude should do:** start the `caligrafy-reviewer` agent of the plugin. It only reads: it never edits your
files. It knows the framework's real behavior and its pitfalls, and reports findings for framework-specific bugs and
security issues: SQL built from user input, missing CSRF tokens in forms, API header mistakes, routes in the wrong
order, unsafe uploads, exposed secrets... Each finding names a file and a line, the problem and a fix.

**How to review the review:** for each finding, open the file at that line and decide: is it a real bug in **your**
code, a limit you accept, or a mistake of the reviewer? Ask Claude "show me the request that would fail" when a
finding is not clear. Fix the real bugs, run the unit tests and the smoke test, then commit. Do not apply every
suggestion blindly.

### Add a test

```
Add a test to qa/bookshelf/BookRulesTest.php that checks that create_book refuses a published_year above 2100.
Use the refusal() helper. Do not change the services. Then run
docker compose exec dev-box vendor/bin/phpunit -c qa/bookshelf/phpunit.xml and show me the result.
```

**Check** before you accept:
- [ ] The new method is public and its name starts with `test`.
- [ ] It calls `refusal(new CreateBook(), ...)` with a complete book whose year is above 2100 (for example 2200) and checks `published_year` is in `$e->fields`.
- [ ] Claude ran PHPUnit and the result is `OK (6 tests, ...)`.
- [ ] No file in `application/services/` changed: a test must not change the rules to pass.

### Doctor, upgrade, legacy key

```
Run php caligrafer.php doctor in the dev-box container and explain each WARN or FAIL. Then run
php caligrafer.php upgrade as a dry run and explain the plan. Do not run upgrade --apply. Do not print .env.
```

**Check:**
- [ ] Claude ran both commands with `docker compose exec dev-box ...`.
- [ ] It did not print `.env` or any key (`doctor` masks them).
- [ ] It did not run `upgrade --apply` without your OK.
- [ ] If you asked it to turn off the legacy key, `.env` has `LEGACY_API_KEY=false` and `doctor` shows `Legacy API key: off`.

**If Claude's result differs:** if Claude runs `doctor` on your computer instead of the container and reports a
database failure, ask it to use `docker compose exec dev-box`. If the reviewer reports a "bug" you cannot reproduce,
ask for the request that shows it; keep the code if there is none.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 12: unit tests and smoke test"
git tag lesson-12
```
`.env` is not committed (`.gitignore`), so `LEGACY_API_KEY=false` stays on your computer. The PHPUnit cache
(`.phpunit.cache/`) is ignored too.
Reference: [`checkpoints/lesson-12`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-12).

## Quiz

1. The unit tests check the rules of `create_book` without a database. Which experiences does that protect?
2. Why does the smoke test expect `401` for `POST /api/books`, and what would `422` mean there?
3. After `LEGACY_API_KEY=false`, does the Vue finder app still work? Why?
4. A "smoke test" token was printed in a shared terminal recording. What do you do?

<details><summary>Show the solution</summary>

1. All of them: the pages, REST, the Vue apps and AI agents all call the same capability, so its `$rules` apply to
   every experience. One test of the rules is a test for every experience.
2. A visitor (no session, no token) must not write: `401` means "sign in or send a token". The caller is checked
   before the inputs, so `422` (the inputs `{"title":"x"}` are incomplete) would mean that the visitor got past the
   check: `create_book` is open to anyone again (`public: true` came back). A security bug, caught before it goes
   online.
3. Yes. The finder and the other browser apps use the session through the JS client, and programs and agents use
   personal access tokens: nothing uses the legacy key any more.
4. Revoke it at once: `docker compose exec dev-box php caligrafer.php token list` to find its id, then
   `token revoke <id>`. Only that token stops working; the others are not affected. (It would also expire after one
   day, but do not wait.)
</details>

---
Previous: [Lesson 11 - People and permissions](11-People-and-Permissions) · Next: [Lesson 13 - Capstone: one more feature, everywhere](13-Capstone-Loans)
