# Lesson 13 - Review, tests, doctor and upgrade

**In this lesson** you learn four habits that keep Bookshelf healthy after the course: let the
`caligrafy-reviewer` agent review your code, run **automated tests** (a PHPUnit unit test of `BookForm` and a smoke
test of the URLs), check the setup with `php caligrafer.php doctor`, and update the framework files of your project
with `php caligrafer.php upgrade`.

**Before you start:** lesson 12 is done (tag `lesson-12`), the containers run.

---

## New concepts

**Code review.** Reading code to find bugs before users do. The plugin's **`caligrafy-reviewer`** agent is a Claude
helper that only reads (it never edits files). It knows the framework's real behavior and its pitfalls (each one has
an id like `G6` in the plugin's `gotchas.md`): SQL built from input, a form without `=csrf()`, `check()` misused,
`redirect()` without `exit`, routes in the wrong order, API calls without the JSON header, secrets in code... It
reports findings with the file and line, the problem, and a fix.

**Automated tests** are programs that check your program, so you can run them after every change:
- a **unit test** checks one piece of code alone, without the web server or the database. **PHPUnit** is the
  test tool of PHP (it is installed in `vendor/` by `composer install`). A test class extends
  `PHPUnit\Framework\TestCase`; each method whose name starts with `test` is one test, and
  `$this->assertSame($expected, $actual)` fails the test when the two differ. `BookForm` has no database code, so it
  is easy to unit test.
- a **smoke test** calls the running application and checks that the main URLs answer the expected status code:
  quick, and it catches a broken route or a missing table.

**Where tests live.** Not in `application/` and not in `tests/`: Caligrafy loads **every** PHP file of these two
folders on every request, so a test file there would run inside your website. We put them in `qa/bookshelf/`. The
`qa/` folder holds the framework's own test suite, is never loaded by the framework, and `.htaccess` refuses to
serve it.

**`doctor`** (`php caligrafer.php doctor`) checks the project and the services configured in `.env`, and changes
nothing: PHP version and extensions, `vendor/`, `.env`, the keys (it never prints them in full), `public/uploads`,
whether your framework files are out of date, the database, and, when you configure them, OpenAI, Groq, Stripe,
Coinbase, Watson and mail. Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured), with a fix after `->`.

**`upgrade`.** `initialize` copied the framework's example files into `application/` (the Auth module, the
client app view, the example controllers...). When you update the framework later, those copies stay old.
`php caligrafer.php upgrade` compares them with the framework's current templates:

| Status | Meaning | What `--apply` does |
|---|---|---|
| `identical` | your copy is the current template | nothing |
| `outdated` | your copy is an older template you never changed | replaces it (with a backup in `backups/`) |
| `customized` | you changed the file | writes the new template next to it as `<file>.caligrafy-new`, for you to merge |
| `missing` | the template is new | copies it |
| `routes` | `web.php` | never replaced (it is yours) |

Without `--apply` it only shows the plan (a **dry run**). It never touches `.env`, uploads, or your own files.

---

## Manual track

### Step 1 - Run the framework's doctor

From the `bookshelf` folder:
```bash
docker compose exec dev-box php caligrafer.php doctor
```
Expected: the same report as in lesson 01, step 8, ending with `12 OK, 0 WARN, 0 FAIL, 7 SKIP`. The line
`OK    Templates: application/ is up to date with the framework templates` comes from the `upgrade` check of step 4. `SKIP` lines are services you did not configure: normal. (Run it with
`docker compose exec dev-box`: inside the container, `DB_HOST=mysql` resolves.) `--only database` limits it to one
check, `--json` prints JSON for programs.

### Step 2 - A unit test of `BookForm`

Create the folder `qa/bookshelf` and three files in it.

**File `qa/bookshelf/bootstrap.php`** (loads what the tests need, without starting the framework):

```php
<?php
// Loads what the Bookshelf unit tests need, without starting the framework: no web server, no database, no .env
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/framework/core/Validator.php';
require $root . '/application/controllers/Bookshelf/BookForm.php';
```

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

**File `qa/bookshelf/BookFormTest.php`**:

```php
<?php

use Bookshelf\BookForm;
use Caligrafy\Validator;
use PHPUnit\Framework\TestCase;

// Each public function whose name starts with "test" is one test
final class BookFormTest extends TestCase
{
    public function testAValidBookHasNoErrors(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array(
            'title' => '  Kindred ',
            'published_year' => '1979',
            'summary' => '',
            'author_id' => '2'
        ));
        $this->assertSame(array(), $errors);
        $this->assertSame('Kindred', $data['title']);   // the trim filter removed the spaces
    }

    public function testTheTitleIsRequired(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array('published_year' => '1979', 'author_id' => '2'));
        $this->assertArrayHasKey('title', $errors);
    }

    public function testTheYearMustBeANumber(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array('title' => 'Kindred', 'published_year' => 'soon', 'author_id' => '2'));
        $this->assertArrayHasKey('published_year', $errors);
    }

    public function testOtherFieldsAreIgnored(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array('title' => 'Kindred', 'published_year' => '1979', 'author_id' => '2', 'id' => '99'));
        $this->assertArrayNotHasKey('id', $data);
    }

    public function testColumnsAreIntegers(): void
    {
        $columns = BookForm::columns(array('title' => 'Kindred', 'published_year' => '1979', 'summary' => '', 'author_id' => '2'));
        $this->assertSame(1979, $columns['published_year']);
        $this->assertSame(2, $columns['author_id']);
    }
}
```

Run the tests:
```bash
docker compose exec dev-box vendor/bin/phpunit -c qa/bookshelf/phpunit.xml
```
Expected (the versions and the time vary):
```
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: /var/www/html/qa/bookshelf/phpunit.xml

.....                                                               5 / 5 (100%)

Time: 00:00.007, Memory: 16.00 MB

OK (5 tests, 7 assertions)
```
Each `.` is a test that passed. Now break `BookForm` on purpose: in `application/controllers/Bookshelf/BookForm.php`
change `'title' => 'required|max_len,200'` into `'title' => 'max_len,200'`, run the tests again:
```
.F...                                                               5 / 5 (100%)
...
There was 1 failure:

1) BookFormTest::testTheTitleIsRequired
Failed asserting that an array has the key 'title'.

/var/www/html/qa/bookshelf/BookFormTest.php:25

FAILURES!
Tests: 5, Assertions: 7, Failures: 1.
```
The test caught the bug. Put `required|` back and check that the tests pass again.

### Step 3 - A smoke test of the running site

**File `qa/bookshelf/smoke.sh`**:

```bash
#!/bin/bash
# Bookshelf smoke test: calls a few URLs and checks their HTTP status code.
# Run from the project folder: bash qa/bookshelf/smoke.sh   (or: bash qa/bookshelf/smoke.sh http://localhost:8081)
BASE="${1:-http://localhost:8080}"
API_KEY="$(grep '^API_KEY=' .env | cut -d= -f2-)"
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

check 200 "home page"                 "$BASE/"
check 200 "list of books"             "$BASE/books"
check 200 "book 1"                    "$BASE/books/1"
check 404 "book that does not exist"  "$BASE/books/999999"
check 302 "add form needs a sign in"  "$BASE/books/new"
check 403 ".env is never served"      "$BASE/.env"
check 401 "API without the key"       -H "Accept: application/json" "$BASE/api/books"
check 200 "API with the key"          -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" "$BASE/api/books"
check 401 "API write without sign in" -X POST -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
                                      -H "Content-Type: application/json" -d '{"title":"x"}' "$BASE/api/books"

if [ "$FAILED" = "1" ]; then echo "Some checks failed."; exit 1; fi
echo "All checks passed."
```

Run it from the `bookshelf` folder:
```bash
bash qa/bookshelf/smoke.sh
```
Expected:
```
OK   200 home page
OK   200 list of books
OK   200 book 1
OK   404 book that does not exist
OK   302 add form needs a sign in
OK   403 .env is never served
OK   401 API without the key
OK   200 API with the key
OK   401 API write without sign in
All checks passed.
```
Run both tests after every change, before you commit.

### Step 4 - Preview an upgrade

```bash
docker compose exec dev-box php caligrafer.php upgrade
```
Expected:
```
 Caligrafy upgrade (dry run: nothing is changed, run 'php caligrafer.php upgrade --apply' to update)
 Templates: framework/settings/application (33 files) compared with application/ - manifest .caligrafy-manifest.json found

  routes      application/config/routing/web.php: your routes: never replaced
  identical  32 file(s)

 Client apps (report only, never modified; compare them with framework/plugins/<type> by hand):
  public/catalog (app): 2 file(s) differ from framework/plugins/app: index.php, scripts/main.js

 Nothing was changed.
```
Your project was initialized from the current framework, so every template is `identical`, except `web.php` (your
routes, never replaced). The client app `public/catalog` is reported because you rewrote both its files: `upgrade`
never touches client apps, it only tells you which ones differ from the framework's template. Run it again after you update the framework (for example a new
Caligrafy Quill release copied over `framework/`): it then lists the `outdated` files, and
`php caligrafer.php upgrade --apply` updates them, with a backup of every replaced file in `backups/`. Run `doctor`
afterwards: its *Project* check warns while some templates are outdated.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `vendor/bin/phpunit: No such file or directory` | The development packages were not installed (`composer install --no-dev`) | Run the composer command of lesson 01 without `--no-dev` |
> | `Class "Bookshelf\BookForm" not found` | `bootstrap.php` points to another path, or `BookForm.php` moved | Compare with step 2 |
> | `Cannot open file "qa/bookshelf/phpunit.xml"` | The command was run from another folder | Run it from the `bookshelf` folder |
> | `bash: qa/bookshelf/smoke.sh: No such file or directory` | Wrong folder | Run it from the `bookshelf` folder |
> | Smoke test: every line `FAIL 000` | The site is not reachable on that address | Start the containers, or pass your address: `bash qa/bookshelf/smoke.sh http://localhost:8081` |
> | `doctor` shows `FAIL Database` on your computer but `OK` in the container | Outside Docker the host `mysql` does not exist | Always `docker compose exec dev-box php caligrafer.php doctor` |

---

## With Claude

### Review your code

In Claude Code, in the `bookshelf` folder:
```
Use the caligrafy-reviewer agent to review application/controllers/Bookshelf, application/views/Bookshelf,
application/config/routing/web.php, application/models/Bookshelf and public/catalog.
```
**What to expect:** a short verdict, then findings by severity, each with `file:line`, the problem, what fails,
the gotcha id and a fix. For the course code the reviewer should find no SQL injection, no missing CSRF token and
no `check()` misuse. It is likely to mention, as information or low severity:
- the API key is visible in the `/catalog` page (G27): expected, and handled because changes require a signed-in
  user;
- unknown URLs show the first route with status 200 (G4): a known behavior of the framework;
- every registered user can change every book (G14): a choice of the course, explained in lesson 08 with the
  `guard('permissions', 1)` fix;
- the SPA calls `/api/...` from the root of the site: right while `APP_ROOT` is empty (lesson 12).

(These are the findings of a review of the course's final code run with the reviewer's instructions while the
course was written; your own code may get others.)

**How to review the review:** for each finding, open the file at the line, and decide: is it a real bug in **your**
code, a known limit you accept, or a mistake of the reviewer? Ask Claude "show me the request that would fail" when a
finding is not clear. Fix real bugs, run the tests of steps 2 and 3, and commit.

### Write a test with Claude

```
Add a test to qa/bookshelf/BookFormTest.php that checks a year above 2100 is refused, run
docker compose exec dev-box vendor/bin/phpunit -c qa/bookshelf/phpunit.xml and show me the result.
```
**Check:** the new method name starts with `test`; Claude ran the tests and they pass; Claude did not change
`BookForm` to make a test pass unless you agreed the rule was wrong.

### Doctor and upgrade

```
Run php caligrafer.php doctor in the dev-box container and explain each WARN or FAIL. Then run
php caligrafer.php upgrade (dry run only) and explain the plan. Do not run upgrade --apply.
```
**Check:** Claude ran `doctor` inside the container (`docker compose exec dev-box ...`); it did not print the keys of
`.env` (doctor masks them, and Claude must not `cat .env`); it did not run `--apply` without your OK. The plugin's
`server` skill has a troubleshooting section for the most common setup problems (ports, TLS proxies) and starts with
`doctor`.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 13: unit and smoke tests"
git tag lesson-13
```
Reference: [`checkpoints/lesson-13`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-13).

Next: [Lesson 14 - Capabilities and AI agents](14-Capabilities-and-AI-Agents) writes Bookshelf features once and lets an AI agent use them. After it, the [Caligrafy Quill wiki](https://github.com/caligrafy/caligrafy-quill/wiki)
documents the modules this course did not cover (file uploads, payments, metadata and social cards, the AI and
machine learning modules).

## Exercise

1. Your `API_KEY` was pasted in a public chat. What do you do?
2. Add a smoke test line that checks `/api/books/1` answers 200 with the key.

<details><summary>Show the solution</summary>

1. Every API key is derived from the `APP_KEY`, so all keys made from one `APP_KEY` are equal and can only be
   revoked together: generate a new pair with `docker compose exec dev-box php caligrafer.php generatekeys`, put
   both values in `.env` (`APP_KEY` and `API_KEY`) and in `public/bookshelf-spa/.env` (`VITE_APP_KEY`,
   `VITE_API_KEY`), rebuild the SPA, and sign in again (the sessions were encrypted with the old key). Never commit
   `.env`.
2. Before the `if [ "$FAILED" ...` line of `smoke.sh`:
   ```bash
   check 200 "API book 1"                -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" "$BASE/api/books/1"
   ```
</details>

---
Previous: [Lesson 12 - Pinia, styling and the production build](12-Pinia-Styling-and-Build) · Next: [Lesson 14 - Capabilities and AI agents](14-Capabilities-and-AI-Agents) · [Back to the course home](Home)
