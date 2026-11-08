# Lesson 12 - Ship it: tests, review, doctor

**In this lesson** you make Bookshelf safe to put online: tests that check the rules and the permissions of your
capabilities (`Caligrafy\Testing\TestCase`, `php caligrafer.php test`), a smoke test that calls the running
application the way every experience does, the `doctor` health check, the end of the legacy API key, an `upgrade`
preview, a review by the `caligrafy-reviewer` agent, and a go-live list.

**Before you start:** lesson 11 is done (tag `lesson-11`) and `docker compose ps` shows the 3 containers running.

**Time:** 60 minutes.

---

## New concepts

**Automated tests** are small programs that check your program, so you run them after every change instead of
clicking through every page. You write two kinds:

| Kind | What it checks | Needs | Here |
|---|---|---|---|
| **Capability test** | one capability, called as every experience calls it: who may call it, its rules, its answer | PHP and the database, no web server | `tests/BookRulesTest.php` |
| **Smoke test** | the running application answers each experience with the expected HTTP status | the containers running | `qa/bookshelf/smoke.sh` |

**PHPUnit** is the test tool of PHP, already in `vendor/`. A test class extends `Caligrafy\Testing\TestCase`; each
public method whose name starts with `test` is one test; an **assertion** such as `$this->assertSame($expected,
$actual)` fails the test when the two values differ.

**Test the capabilities, and you test every experience.** Their `$rules` and permissions are the same for the pages,
REST, the Vue apps and the agents. `TestCase` gives:

| Method | What it does |
|---|---|
| `$this->call('create_book', [...], $as)` | calls the capability with the checks of the caller, the filters and rules, then `handle()`; returns its answer |
| `$this->assertCapabilityFails('invalid', 'create_book', [...], $as)` | checks that it refuses with that kind (`invalid`, `unauthenticated`, `forbidden`, `not_found`, `conflict`) and returns the `ServiceError`: `$e->fields` has one message per refused input |
| `$this->user($id, $level)` | a person for `$as`: `$this->user(1)` is user 1 with permissions 0, `$this->user(1, 1)` an administrator |
| `$as` = `'guest'` | a visitor; `null`: the command line (full access) |

**The database is left as it was.** Each test runs in a transaction that is rolled back at its end: what a test adds
is gone afterwards, and tests do not see each other's rows. They use the database of `.env`; `DB_TEST_DATABASE=<name>`
in `.env` gives them their own (create it, then run `platform install` and your scripts on it).

**Where tests live.** In `tests/`, one class per file named `<Name>Test.php`: `php caligrafer.php test make <Name>`
writes one, `php caligrafer.php test` runs them all, `php caligrafer.php test <filter>` runs those whose name matches.
The framework never loads `tests/` on a page, the `.htaccess` refuses to serve it, and the production image leaves it
out. The smoke test is a shell script in `qa/bookshelf/`, which the `.htaccess` refuses to serve too.

**A smoke test checks the refusals too.** A site that is safe to put online says **no** correctly: 401 when a visitor
writes, 403 when a read-only token writes, 403 for `.env`.

**`doctor`** (`php caligrafer.php doctor`) is a read-only health check: PHP, `vendor/`, `.env` and the keys (masked:
only their last 4 characters), the legacy API key setting, the database, the framework tables and the migrations, and
each optional service configured in `.env`. Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured).

**The legacy API key.** Before tokens, programs used the `API_KEY` of `.env`. Every key made from one `APP_KEY` is the
same key: full access, and no way to revoke one alone. Since lesson 11 every program has its own token and the browser
apps use the session, so `LEGACY_API_KEY=false` in `.env` turns the old key off.

**`upgrade`.** Initialize copied the framework's template files into `application/`. `php caligrafer.php upgrade`
compares your copies with the templates and, with `--apply`, updates the files you never changed; without `--apply` it
is a **dry run** that only shows the plan. When a newer release is out, it plans the move to it like `update`
(lesson 14); `--local` compares with the templates of the release you have, and nothing else.

**The `caligrafy-reviewer`.** An agent of the Claude Code plugin that only reads your code and reports
framework-specific bugs and security issues: SQL built from user input, CSRF and API header mistakes, routes in the
wrong order, unsafe uploads, exposed secrets, controllers where a declarative route fits, N+1 loops.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The test file

```bash
docker compose exec dev-box php caligrafer.php test make BookRules
```
Expected: ` tests/BookRulesTest.php written: php caligrafer.php test BookRules`. The file has one example test. Replace
its whole content:

**File `tests/BookRulesTest.php`** (seven tests of the rules and the permissions):

```php
<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;

// The rules and permissions of the Bookshelf capabilities: the same for the pages, REST, the Vue apps and AI agents.
// call() goes through the capability as every experience does: who calls, the rules, then handle().
// Each test runs in a transaction that is rolled back at its end: what it adds is gone afterwards
final class BookRulesTest extends TestCase
{
    // an author of the test's own: the tests do not depend on the sample data
    private function author(): int
    {
        return Database::table('authors')->insert(['name' => 'Test Author']);   // the id of the new row
    }

    public function testABookNeedsATitleAYearAndAnAuthor(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'create_book', [], $this->user(1));
        $this->assertSame(['title', 'published_year', 'author_id'], array_keys($e->fields));
    }

    public function testTheYearIsAWholeNumberAndNotInTheFuture(): void
    {
        $author = $this->author();
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Soon', 'published_year' => 'soon', 'author_id' => $author], $this->user(1));
        $this->assertArrayHasKey('published_year', $e->fields);
        $next = (int) date('Y') + 1;
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Soon', 'published_year' => $next, 'author_id' => $author], $this->user(1));
        $this->assertArrayHasKey('published_year', $e->fields);
    }

    public function testAValidBookIsTrimmedAndKeepsOnlyItsInputs(): void
    {
        $book = $this->call('create_book', ['title' => '  Kindred ', 'published_year' => 1979, 'author_id' => $this->author(), 'price' => 9], $this->user(1));
        $this->assertSame('Kindred', $book['title']);
        $this->assertSame('Test Author', $book['author']['name']);
        $this->assertArrayNotHasKey('price', $book);   // only the inputs of $rules reach handle()
    }

    public function testTwoBooksNeverShareAnIsbn(): void
    {
        $author = $this->author();
        $this->call('create_book', ['title' => 'One', 'published_year' => 2000, 'author_id' => $author, 'isbn' => '978-0-00-000000-1'], $this->user(1));
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Two', 'published_year' => 2000, 'author_id' => $author, 'isbn' => '978-0-00-000000-1'], $this->user(1));
        $this->assertArrayHasKey('isbn', $e->fields);
    }

    public function testAVisitorCannotAddABook(): void
    {
        $this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Kindred', 'published_year' => 1979, 'author_id' => $this->author()], 'guest');
    }

    public function testOnlyAnAdministratorDeletesABook(): void
    {
        $book = $this->call('create_book', ['title' => 'Gone soon', 'published_year' => 2000, 'author_id' => $this->author()], $this->user(1));
        $this->assertCapabilityFails('forbidden', 'delete_book', ['id' => $book['id']], $this->user(1, 0));   // a reader
        $this->assertSame(['deleted' => $book['id']], $this->call('delete_book', ['id' => $book['id']], $this->user(1, 1)));
    }

    public function testASearchNeedsAtLeastTwoCharacters(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'search_books', ['query' => 'a'], 'guest');
        $this->assertArrayHasKey('query', $e->fields);
    }
}
```

What to notice:
- `author()` adds an author of the test's own with the query builder: the tests do not depend on the sample data, and
  the transaction removes it afterwards.
- `testOnlyAnAdministratorDeletesABook` checks `level: 1` both ways: a reader is `forbidden`, an administrator deletes.
- `testTwoBooksNeverShareAnIsbn` checks the rule `unique,books.isbn` of lesson 05.

### Step 2 - Run the tests

```bash
docker compose exec dev-box php caligrafer.php test
```
Expected (your times differ):
```text
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12

.......                                                             7 / 7 (100%)

Time: 00:00.052, Memory: 18.00 MB

OK (7 tests, 16 assertions)
```
Each `.` is a test that passed. `php caligrafer.php test Isbn` runs only `testTwoBooksNeverShareAnIsbn`:
`OK (1 test, 2 assertions)`.

Now see a test catch a bug. In `application/services/Bookshelf/CreateBook.php`, change
`'title' => 'required|max_len,200'` into `'title' => 'max_len,200'`, save, and run the tests again:
```text
F......                                                             7 / 7 (100%)

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

/var/www/html/tests/BookRulesTest.php:20

FAILURES!
Tests: 7, Assertions: 16, Failures: 1.
```
The title is no longer required, on every experience at once, and the test says so. Put `required|` back
(`git checkout application/services/Bookshelf/CreateBook.php`) and run the tests: `OK (7 tests, 16 assertions)`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `No tests yet: php caligrafer.php test make <Name> writes tests/<Name>Test.php` | The file is not in `tests/`, or its name does not end with `Test.php` | `ls tests`: `BookRulesTest.php` |
> | `PHPUnit is not installed: ...` | The development packages of Composer are missing | `docker compose exec dev-box composer install` |
> | `create_book failed with 'unauthenticated' (...), not 'invalid'` | A test calls it without `$this->user(1)`: as nobody | Compare with step 1 |
> | `create_book did not fail (expected 'unauthenticated')` | `create_book` is still `public: true` | Remove it (lesson 11) |
> | `delete_book did not fail (expected 'forbidden')` | `DeleteBook.php` has no `level: 1` | Compare with lesson 11 |
> | `There is no capability ...` | A typo in the name | Use the `name` of its attribute |

### Step 3 - The smoke test

**File `qa/bookshelf/smoke.sh`** (one line per check; each `check` calls an address with `curl` and compares the status):

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
check 403 "a form without its token"     -X POST -d '_method=PUT&title=x' "$BASE/books/1"
check 200 "the authors as a CSV file"    "$BASE/authors.csv"
check 302 "people: visitors to sign in"  "$BASE/people"
check 200 "join: the form for visitors"  "$BASE/join"
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

- `check 200 "the catalog page" "$BASE/books"` requests `/books` and expects `200`. Everything after the description
  goes to `curl`; `-o /dev/null -w '%{http_code}'` keeps only the status.
- `a form without its token` posts the edit form of book 1 the way another website would, without the CSRF token: 403.
- `people: visitors to sign in`: the `level` of `/people` sends a visitor to `/login?return=%2Fpeople` (302).
- `join: the form for visitors`: `/join` opens for someone who is not signed in.
- The last check needs a token that may read but not write; it runs only when `READ_TOKEN` is set.

Run it on your computer (not in the container):
```bash
bash qa/bookshelf/smoke.sh
```
Expected:
```text
OK   200 the catalog page
OK   200 the page of book 1
OK   404 a book that does not exist
OK   403 .env is never served
OK   403 a form without its token
OK   200 the authors as a CSV file
OK   302 people: visitors to sign in
OK   200 join: the form for visitors
OK   200 REST: list the books
OK   422 REST: a search too short
OK   401 REST: a visitor cannot write
OK   401 MCP: no token, no tools
All checks passed.
```
Now with a read-only token that expires after one day, created and passed in one command:
```bash
READ_TOKEN=$(docker compose exec -T dev-box php caligrafer.php token create "smoke test" --scopes books:read --expires 1 | grep -oE 'cq_[0-9]+_[A-Za-z0-9]+') bash qa/bookshelf/smoke.sh
```
`$( ... )` uses what the command prints; `-T` because the output goes to `grep`, not to your screen; `grep -oE` keeps
only the token. Expected: the same 12 lines, then `OK   403 REST: a read-only token cannot write` and
`All checks passed.` Each run creates a "smoke test" token (`token list` shows them): it stops working after a day, and
`token revoke <id>` stops it at once.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | Every line `FAIL 000` | The site is not reachable at that address | `docker compose up -d dev-box`, or give your address: `bash qa/bookshelf/smoke.sh http://localhost:8081` |
> | `FAIL 422 REST: a visitor cannot write (expected 401)` | `create_book` is public again: the visitor got through to the rules | Remove `public: true` (lesson 11) |
> | `FAIL 404 the page of book 1` | Book 1 was deleted | Run `create_db.sql` again: its `INSERT IGNORE` puts the sample books back |
> | `FAIL 401 REST: a read-only token cannot write (expected 403)` | `READ_TOKEN` is empty | Run the `token create` command alone and read its output |

### Step 4 - Turn off the legacy API key

```bash
docker compose exec dev-box php caligrafer.php doctor
```
Expected (the key endings, the plugin name and the versions are yours):
```text
Caligrafy doctor (read-only): Caligrafy Quill 2.21.4

 Project
  OK    PHP: 8.5.12
  OK    Extensions: curl, json, mbstring, openssl, pdo
  OK    vendor/: Composer dependencies installed
  OK    .env: present
  OK    APP_KEY: set …xxxx (44 chars)
  OK    API_KEY: set …xxxx (112 chars)
  OK    Legacy API key: allowed (every generated key is the same key: full access, no revocation); set LEGACY_API_KEY=false once clients use tokens
  OK    Claude Code plugin: caligrafy-quill@caligrafy-77be (not registered yet: php caligrafer.php plugin, or start claude here)
  OK    public/uploads: writable
  OK    public/manifest.webmanifest: 4 icon(s) found
  OK    Design style: Caligrafy's own look (DESIGN_STYLE is empty)
  OK    Templates: application/ is up to date with the framework templates

 Database
  OK    Database: connected to mysql://mysql:3306/caligrafy (server 8.4.13)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache and migrations installed
  OK    Migrations: 1 ran, none pending
```
then one `SKIP` per optional service you did not configure (OpenAI, Groq, Stripe, Coinbase, Watson, Mail: normal),
and `16 OK, 0 WARN, 0 FAIL, 6 SKIP`.

Open `.env` in your editor, find `LEGACY_API_KEY=true` and change it to:
```text
LEGACY_API_KEY=false
```
Run `doctor` again. The line is now `OK    Legacy API key: off: API clients use personal access tokens`, still
`16 OK, 0 WARN, 0 FAIL, 6 SKIP`. Run the smoke test again: `All checks passed.`, because no experience of Bookshelf
uses the old key. `.env` is never committed: set it on every server.

### Step 5 - Preview an upgrade

```bash
docker compose exec dev-box php caligrafer.php upgrade --local
```
Expected:
```text
 Caligrafy upgrade (dry run: nothing is changed, run 'php caligrafer.php upgrade --apply' to update)
 Templates: framework/settings/application (45 files) compared with application/ - manifest .caligrafy-manifest.json found

  routes      application/config/routing/web.php: your routes: never replaced
  identical  44 file(s)

 Client apps (report only, never modified; compare them with framework/plugins/<type> by hand):
  public/finder (app): 2 file(s) differ from framework/plugins/app: index.php, scripts/main.js

 Nothing was changed.
```
Every template is `identical`; `web.php` is yours and never replaced; the finder differs from its template because you
rewrote it in lesson 09 (the Vite SPA is not compared). When a later release comes out, `upgrade` without `--local`
shows the plan, and `upgrade --apply` applies it: untouched files are updated (backups in `backups/`), files you
changed get the new version beside them as `<file>.caligrafy-new`. Run the tests, the smoke test and `doctor`
afterwards.

### Step 6 - Before going live

Lesson 14 puts Bookshelf online. Go through this list first; on Caligrafy Cloud, HTTPS, `APP_ENV=production`,
`APP_DEBUG=false` and `LEGACY_API_KEY=false` are set for you.

- [ ] Nothing that writes is `public: true`: `capabilities` shows `public` only on the Bookshelf reads and on the
      framework's `register_user` (the join form) and `notify_email_confirm`.
- [ ] `LEGACY_API_KEY=false` in the `.env` of the server.
- [ ] Tokens: `token list`, then `token revoke <id>` for every token you no longer use; each program has its own token
      with the smallest scopes, and `--expires <days>` when it is temporary.
- [ ] HTTPS on the server (it also lets phones install the app, lesson 10).
- [ ] `APP_ENV=production` and `APP_DEBUG=false` in the `.env` of the server.
- [ ] `MCP_ALLOWED_ORIGINS` only if a browser-based MCP client must reach `/mcp`; empty otherwise.
- [ ] `doctor` on the server, then the smoke test against its address: `bash qa/bookshelf/smoke.sh https://<your-host>`.
- [ ] Read `audit` regularly; never commit `.env` or a token.

---

## Check

- [ ] `tests/BookRulesTest.php` and `qa/bookshelf/smoke.sh` are identical to the File blocks.
- [ ] `php caligrafer.php test` answers `OK (7 tests, 16 assertions)`; with `required|` removed it fails, and it passes
      again once it is back.
- [ ] The smoke test passes, with and without `READ_TOKEN`.
- [ ] `doctor` says `Legacy API key: off` and `0 WARN, 0 FAIL`.
- [ ] `upgrade --local` changed nothing.

## With Claude

Start Claude Code in the `bookshelf` folder (`claude`).

**Review your code:**
```text
Use the caligrafy-reviewer agent to review application/, public/finder and public/bookshelf-spa/src
```
The agent only reads. Each finding names a file and a line, the problem and a fix. For each one, open the file and
decide: a real bug in **your** code, a limit you accept, or a mistake of the reviewer. Ask "show me the request that
would fail" when a finding is not clear. Fix the real bugs, run the tests and the smoke test, then commit.

**Add a test:**
```text
Add a test to tests/BookRulesTest.php that checks that update_book refuses an empty title on an existing book, with
assertCapabilityFails('invalid', ...) as $this->user(1). Create the book in the test with $this->call('create_book',
...) and the author() helper. Do not change the services. Then run
docker compose exec dev-box php caligrafer.php test and show me the result.
```
**Check** before you accept:
- [ ] The method is public and its name starts with `test`; it checks that `title` is in `$e->fields`.
- [ ] The result is `OK (8 tests, ...)`.
- [ ] No file in `application/services/` changed: a test never changes the rules to pass.

**Doctor and upgrade:**
```text
Run php caligrafer.php doctor in the dev-box container and explain each WARN or FAIL. Then run
php caligrafer.php upgrade --local as a dry run and explain the plan. Do not run upgrade --apply. Do not print .env.
```
**Check:** Claude ran both with `docker compose exec dev-box ...` (outside Docker the host `mysql` does not exist and
the database check fails), printed no key, and did not run `upgrade --apply`.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 12: tests and smoke test"
git tag lesson-12
```
`.env` is not committed, so `LEGACY_API_KEY=false` stays on your computer.
Reference: [`checkpoints/lesson-12`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-12).
Learn more: [Testing](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App).

## Quiz

1. The tests call `create_book` with `$this->call()`, from PHP. Which experiences does that protect?
2. Why does the smoke test expect `401` for `POST /api/books` from a visitor, and what would `422` mean there?
3. After `LEGACY_API_KEY=false`, does the finder still work? Why?
4. A "smoke test" token was printed in a shared terminal recording. What do you do?

<details><summary>Answers</summary>

1. All of them: the pages, REST, the Vue apps and the agents call the same capability, so its rules and permissions
   apply everywhere. One test is a test for every experience.
2. A visitor must not write: `401` means "sign in or send a token". The caller is checked before the inputs, so `422`
   would mean that the visitor got past the check: `create_book` is public again. A security bug, caught before it
   goes online.
3. Yes. The finder and the SPA use the session through the JS client, and programs and agents use tokens: nothing uses
   the legacy key.
4. Revoke it at once: `token list` for its id, then `token revoke <id>`. Only that token stops working.
</details>

---
Previous: [Lesson 11 - People and permissions](11-People-and-Permissions) · Next: [Lesson 13 - Capstone: one more feature, everywhere](13-Capstone-Loans)
