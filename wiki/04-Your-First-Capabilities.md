# Lesson 04 - Your first capabilities

**In this lesson** you write the first three features of Bookshelf as **capabilities**: list the books (20 per page,
each with its author), read one book, list the authors. They read the tables with the **query builder**. You call them
from the command line and with `curl`, and the AI agent of lesson 02 can use them at once, without one more line of
code.

**Before you start:** lesson 03 is done (tag `lesson-03`): the `books` and `authors` tables hold 5 books and 3
authors, and `docker compose ps` shows the 3 containers running. Keep the token of lesson 02 at hand.

**Time:** 45 minutes.

---

## New concepts

**Service and capability.** A capability is one feature, written once in one small PHP class, a **service**, in
`application/services/`. The class extends `Caligrafy\Service`, and a `#[Capability(...)]` line above it (a PHP
**attribute**) describes it:

| Argument | What it means | In this lesson |
|---|---|---|
| `name` | How every experience calls it, in snake_case | `list_books`, `get_book`, `list_authors` |
| `description` | One or two sentences: what it does and what it returns. AI agents read it to choose the tool | written for an agent |
| `scope` | The permission a token needs, written `resource:action` (lesson 07) | `books:read` |
| `readOnly` | `true` when it changes nothing: pages and downloads may only use these, agents may call them freely | `true` |
| `public` | `true` when anyone may call it, without signing in or a token | `true`: anyone may read the catalog |

**Inside a service:**

| Part | What it is |
|---|---|
| `$rules` | The inputs the capability accepts, and the rules each one must follow (`required`, `integer`...). **Only the inputs listed here reach `handle()`**: anything else a caller sends is dropped |
| `$filters` | Cleaning applied before the rules, such as `trim` (lesson 05) |
| `$descriptions` | One sentence per input. Agents and the JS client read them |
| `handle(array $input, Context $context)` | The work. It receives the checked inputs and returns data. `$context` says who calls (lesson 11) |

**ServiceError.** How a capability says "no": `throw ServiceError::notFound("There is no book #99");`. Every experience
turns it into its own answer: a 404 for REST, a tool error for an agent, a Not found page (lesson 06). A capability
never draws anything: no `echo`, no view, no redirect in `handle()`.

**The query builder.** Reads and writes rows with PHP calls instead of SQL strings. Every value is **bound**: it travels
apart from the SQL, so what a caller sends can never change the query.

| Call | What it does |
|---|---|
| `Author::query()->orderBy('name')->get()` | every author, sorted by name, as `Author` models |
| `Book::find(3)` | book 3, or `null` |
| `Book::where('author_id', 2)` | adds a condition |
| `Book::with('author')` | adds each book's author, found by `author_id`, with **one** query for all of them |
| `->paginate(20, $page)` | one page of 20 rows, and the count: `data`, `total`, `page`, `per_page`, `pages` |

Reading the author of each book in a loop costs one query per book: 21 queries for 20 books, the **N+1 problem**.
`with('author')` avoids it. While you develop, Caligrafy writes "Probable N+1 query" in the PHP error log when the same
query runs 10 times in one request.

**JSON.** The text format programs exchange: `{"title": "Kindred", "published_year": 1979}` is an object with two
fields, `[ ... ]` is a list. Every experience except web pages answers in JSON.

---

## Steps

### Step 1 - Create the service list_books

Caligrafer writes the starting file:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
```
Expected:
```text
 Created application/services/Bookshelf/ListBooks.php: capability 'list_books' (scope books:read)
 Fill in $rules and handle(), then serve it: a page (Route::page data), a form (Route::form), REST (Route::capability or Route::capabilities), MCP (Route::mcp)
```
- `Bookshelf/ListBooks` is the folder and the class name. The capability name, `list_books`, comes from the class name.
- `--read-only` writes `readOnly: true`, because listing books changes nothing.

The new file has the attribute and empty `$rules`, `$filters`, `$descriptions` and `handle()`, each with a comment
that says what goes there. Replace its whole content:

**File `application/services/Bookshelf/ListBooks.php`** (the catalog, sorted by title, 20 books per page, each with its author):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title, 20 books per page, each with its author. Anyone may read it
#[Capability(
    name: 'list_books',
    description: 'Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer', 'page' => 'integer');
    public $descriptions = array(
        'author_id' => 'Optional: only the books of this author',
        'page' => 'Optional: the page, 1 by default (20 books per page)'
    );

    public function handle(array $input, Context $context)
    {
        // two queries for the page (the count, then the books) and one for all their authors
        $query = Book::with('author')->orderBy('title');
        if (!empty($input['author_id'])) {
            $query->where('author_id', $input['author_id']);   // the value is bound, never pasted into the SQL
        }
        return $query->paginate(20, $input['page'] ?? 1);
    }
}
```

- `public: true`: anyone may read the catalog.
- `$rules` has two optional inputs. `author_id`: only the books of that author. `page`: the page to return. Neither is
  `required`, so a call without them is valid; `integer` refuses `"abc"`.
- `Book::with('author')->orderBy('title')` starts a query on `books`, sorted by title, that adds each book's author.
- `where('author_id', $input['author_id'])` adds the condition only when a caller gives an author.
- `paginate(20, $input['page'] ?? 1)` returns the page. The capability passes the page of its input: a REST call, an
  agent or the command line has no address bar to read `?page=2` from.

### Step 2 - Create get_book and list_authors

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/GetBook --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/ListAuthors --scope books:read --read-only
```
Expected: ` Created application/services/Bookshelf/GetBook.php: capability 'get_book' (scope books:read)`, then the
same for `ListAuthors` and `list_authors`, each followed by the `Fill in $rules...` line. Replace their content:

**File `application/services/Bookshelf/GetBook.php`** (one book with its author, or "not found"):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// get_book: one book with its author, or "not found"
#[Capability(
    name: 'get_book',
    description: 'Returns one book of the catalog with its author (id, name, bio), by the id of the book.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class GetBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book');

    public function handle(array $input, Context $context)
    {
        $book = Book::with('author')->find($input['id']);
        if (!$book) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return $book;
    }
}
```

- `'id' => 'required|integer'`: a call without an id, or with `"abc"`, is refused before `handle()` runs.
- `Book::with('author')->find($input['id'])` returns the book with its author, or `null`. `null` becomes
  `ServiceError::notFound(...)`: a 404 everywhere.

**File `application/services/Bookshelf/ListAuthors.php`** (no inputs, so no `$rules`):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_authors: every author, sorted by name. Anyone may read it
#[Capability(
    name: 'list_authors',
    description: 'Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListAuthors extends Service
{
    public function handle(array $input, Context $context)
    {
        return Author::query()->orderBy('name')->get();
    }
}
```

### Step 3 - List and call them from the command line

```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected: 16 lines (name, who may call it, what kind, description), sorted by name: the 13 capabilities of the
framework and, among them, your three:
```text
 get_book                     public         read      Returns one book of the catalog with its author (id, name, bio), by the id of the book.
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.
```
You wrote no route: the command found the three capabilities in `application/services/Bookshelf`.

`call` runs a capability and prints its answer in JSON, the way every experience gets it:
```bash
docker compose exec dev-box php caligrafer.php call list_books author_id=2
```
Expected (your dates differ):
```json
{
    "data": {
        "data": [
            {
                "id": 3,
                "title": "Kindred",
                "published_year": 1979,
                "summary": "A young woman is pulled back in time to a plantation in Maryland.",
                "author_id": 2,
                "created_at": "2026-11-07 23:17:51",
                "modified_at": "2026-11-07 23:17:51",
                "author": {
                    "id": 2,
                    "name": "Octavia E. Butler",
                    "bio": "American science fiction author, the first to receive a MacArthur fellowship.",
                    "created_at": "2026-11-07 23:17:51",
                    "modified_at": "2026-11-07 23:17:51"
                }
            },
            {
                "id": 4,
                "title": "Parable of the Sower",
                "published_year": 1993,
                "summary": "In a collapsing California, a teenager starts a new belief system.",
                "author_id": 2,
                "created_at": "2026-11-07 23:17:51",
                "modified_at": "2026-11-07 23:17:51",
                "author": {
                    "id": 2,
                    "name": "Octavia E. Butler",
                    "bio": "American science fiction author, the first to receive a MacArthur fellowship.",
                    "created_at": "2026-11-07 23:17:51",
                    "modified_at": "2026-11-07 23:17:51"
                }
            }
        ],
        "total": 2,
        "page": 1,
        "per_page": 20,
        "pages": 1
    }
}
```
The outer `data` is the answer of every capability; inside it is the **page** of `paginate()`: its own `data` (the two
books, each with its `author`), `total` (2 books in all), `page`, `per_page` and `pages`. Every list of Bookshelf has
this shape.

A book that does not exist:
```bash
docker compose exec dev-box php caligrafer.php call get_book id=99
```
Expected:
```json
{
    "error": {
        "code": "not_found",
        "message": "There is no book #99"
    }
}
```
Inputs are written `key=value`, or as one JSON object in single quotes: `call get_book '{"id": 3}'`. `call` runs with
full access, like any command you type on your own server.

### Step 4 - Call them over HTTP

Quickstart added `Route::capabilities('/capabilities')` to `web.php` in lesson 01. It serves every capability at
`POST /capabilities/<name>`, with a JSON body of inputs: the generic API that the JS client of lesson 09 uses, and that
`curl` uses here.

```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"author_id": 2}' http://localhost:8080/capabilities/list_books
```
Expected: the same page as step 3, on one line, `{"data":{"data":[{"id":3,"title":"Kindred",...}],"total":2,"page":1,"per_page":20,"pages":1}}`.
A page past the last one has no books, and is not an error: with `-d '{"page": 2}'` the answer is
`{"data":{"data":[],"total":5,"page":2,"per_page":20,"pages":1}}`.

One book, with the status printed after the answer:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"id": 3}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/get_book
```
Expected:
```text
{"data":{"id":3,"title":"Kindred","published_year":1979,"summary":"A young woman is pulled back in time to a plantation in Maryland.","author_id":2,"created_at":"2026-11-07 23:17:51","modified_at":"2026-11-07 23:17:51","author":{"id":2,"name":"Octavia E. Butler","bio":"American science fiction author, the first to receive a MacArthur fellowship.","created_at":"2026-11-07 23:17:51","modified_at":"2026-11-07 23:17:51"}}} [200]
```

Now three refusals. Change only the `-d` part of the last command:

| `-d` | Answer | Who refused |
|---|---|---|
| `'{"id": 99}'` | `{"error":{"code":"not_found","message":"There is no book #99"}} [404]` | your `ServiceError::notFound` |
| `'{"id": "abc"}'` | `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"id":"The Id field must be a number without a decimal"}}} [422]` | the rule `integer` |
| `'{}'` | `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"id":"The Id field is required"}}} [422]` | the rule `required` |

Every refusal is `{"error": {"code", "message", "fields"}}`: a `code` a program can test, a `message` for people, and
`fields`, one message per wrong input.

What a visitor may call, with the input schema of each (built from `$rules` and `$descriptions`):
```bash
curl -s -H "Accept: application/json" http://localhost:8080/capabilities
```
Expected: `{"data":[...],"csrf":"..."}`, where `data` lists the five public capabilities, `get_book`, `list_authors`,
`list_books`, `notify_email_confirm` and `register_user`; the entry of `list_books` ends with
`"input":{"type":"object","properties":{"author_id":{"type":"integer","description":"Optional: only the books of this author"},"page":{"type":"integer","description":"Optional: the page, 1 by default (20 books per page)"}},"additionalProperties":false}`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | A change to a capability seems ignored right after you saved | The server checks PHP files for changes every 2 seconds | Wait 2 seconds and run the command again |
> | ` application/services/Bookshelf/ListBooks.php already exists` | You ran the `service` command twice | Nothing to do: open the file and replace its content |
> | `capabilities` does not list one of them | The file has a PHP error, or the `#[Capability(` block is incomplete | Compare with the File block, character by character |
> | `{"error":{"code":"not_found",...}}` for `list_books` itself (404) | The name in the URL is wrong (`listbooks`, `list-books`) | Use the `name` of the attribute: `list_books` |
> | An error that names the class `Book` or `Author` | The model file of lesson 03 is missing or misnamed | `ls application/models/Bookshelf`: `Book.php` and `Author.php` |
> | An HTML page instead of JSON | The `Accept: application/json` header is missing | Copy the whole command |
> | The list is empty (`"total":0`) | The sample books of lesson 03 are missing | Run the `sql` command of lesson 03, step 2 |

### Step 5 - The AI agent already has them

You wrote no MCP code, but `Route::mcp('/mcp')` (added by quickstart) turns every capability into a tool. In a terminal
where `$TOKEN` holds your token of lesson 02 (`read -rs TOKEN` again in a new window):
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected: 8 tools, your three next to the user tools of the framework:
```text
"name":"delete_user"
"name":"get_book"
"name":"get_user"
"name":"list_authors"
"name":"list_books"
"name":"list_users"
"name":"register_user"
"name":"update_user"
```
In **Claude Code** (connected to `bookshelf` in lesson 02), start a new session in the `bookshelf` folder and ask
`Which books by Octavia E. Butler do we have?`. Claude calls `list_authors` to find her id, then `list_books` with
`author_id`, and answers with Kindred (1979) and Parable of the Sower (1993). It knew what each tool does from the
`description` and `$descriptions` you wrote.

---

## Check

- [ ] The three files are identical to the File blocks, in `application/services/Bookshelf/`, with no `namespace` line.
- [ ] `capabilities` lists 16 capabilities, your three `public` and `read`.
- [ ] `call list_books author_id=2` returns a page with Kindred and Parable of the Sower, each with its `author`.
- [ ] The `curl` calls of step 4 answer 200, 404 and 422 as the table shows.
- [ ] `tools/list` over MCP shows `get_book`, `list_authors` and `list_books`.

## With Claude

The plugin command `/caligrafy-quill:new-capability` scaffolds the service with `caligrafer.php service` and fills it.
Run it once per capability, with a precise description:
```text
/caligrafy-quill:new-capability list_books books:read read-only
In application/services/Bookshelf/ListBooks.php, public: true. It lists the books sorted by title, 20 per page, each
with its author, with the query builder: Book::with('author')->orderBy('title'), ->where('author_id', ...) when
author_id is given, and ->paginate(20, $input['page'] ?? 1). Two optional integer inputs, author_id and page, each with a
description. No SQL string, no loop over the authors, no routes, no tests, no reviewer.
```
```text
/caligrafy-quill:new-capability get_book books:read read-only
In application/services/Bookshelf/GetBook.php, public: true. Input id (required|integer). Returns
Book::with('author')->find($input['id']), or throws ServiceError::notFound("There is no book #<id>"). No routes, no
tests, no reviewer.
```
```text
/caligrafy-quill:new-capability list_authors books:read read-only
In application/services/Bookshelf/ListAuthors.php, public: true, no inputs: Author::query()->orderBy('name')->get().
No routes, no tests, no reviewer.
```
Without "no tests, no reviewer", the skill adds a test file and runs the reviewer for each capability. That is lesson 12's
work, and its test rows would move the ids this course shows.

**Check** before you accept:
- [ ] The names, `scope: 'books:read'`, `readOnly: true` and `public: true` are as in the File blocks.
- [ ] `handle()` only returns data or throws a `ServiceError`: no `echo`, view, redirect or SQL string.
- [ ] Every input `handle()` reads is in `$rules`.
- [ ] `list_books` uses `with('author')` and `paginate(20, ...)`: the pages of lesson 06 read `$books['data']` and
      `$book['author']['name']`.
- [ ] `web.php` has no new line. Descriptions in other words are fine: `capabilities` then shows yours.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 04: list_books, get_book and list_authors"
git tag lesson-04
```
Reference: [`checkpoints/lesson-04`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-04).

## Quiz

1. A caller sends `{"author_id": 2, "admin": true}` to `list_books`. What does `handle()` receive?
2. Why does `get_book` throw `ServiceError::notFound(...)` instead of returning `null` or calling `echo "Not found"`?
3. You wrote no MCP code. How did Claude know that `list_books` accepts an `author_id`?
4. A page of `list_books` shows 20 books with their authors. How many queries does it run, and how many would a loop
   that reads the author of each book run?

<details><summary>Show the solution</summary>

1. Only `array('author_id' => 2)`: only the inputs listed in `$rules` reach `handle()`. `admin` is dropped.
2. A capability never draws anything: every experience turns the `ServiceError` into its own answer (a 404 with
   `{"error": ...}` for REST, a tool error for an agent, a Not found page in lesson 06).
3. From the capability itself: `$rules` and `$descriptions` become the input schema of the tool, and `description`
   tells the agent what the tool does.
4. Three: the count of all the books (`paginate()` needs it for `total`), the 20 books of the page, and all their
   authors at once (`with('author')`). With a loop, one query per book: 22. That is the N+1 problem.
</details>

---
Previous: [Lesson 03 - Your data: tables and models](03-Your-Data) · Next: [Lesson 05 - Rules and changes](05-Rules-and-Changes)
