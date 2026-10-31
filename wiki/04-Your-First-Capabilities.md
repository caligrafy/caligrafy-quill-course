# Lesson 04 - Your first capabilities

**In this lesson** you write the first three features of Bookshelf as **capabilities**: list the books, read one
book, list the authors. You call them with `curl`, and the AI agent you connected in lesson 02 can use them at once,
without one more line of code.

**Before you start:** lesson 03 is done (tag `lesson-03`): the `books` and `authors` tables have their 5 books and
3 authors, and `docker compose ps` shows the 3 containers running. Keep the token of lesson 02 at hand.

**Time:** 45 minutes.

---

## New concepts

**Capability.** One feature of your application, written once, in one small PHP class: "list the books", "add a
book". Every way of reaching Bookshelf (a web page, a REST API, an AI agent, a Vue app) calls the same capability.
Each of these ways is a **channel** (or an **experience**, seen from the person using it). This is **build once,
experience everywhere**: you write the rule once, every channel gets it.

**Service.** The PHP class that holds a capability. It lives in `application/services/` and extends
`Caligrafy\Service`. A line that starts with `#[Capability(...)]` above the class (a PHP **attribute**) turns the
service into a capability and describes it:

| Argument | What it means | In this lesson |
|---|---|---|
| `name` | How every channel calls it, in snake_case | `list_books`, `get_book`, `list_authors` |
| `description` | One or two sentences: what it does and what it returns. AI agents read it to choose the tool | written for an agent |
| `scope` | The permission a program needs, written `resource:action` | `books:read` |
| `readOnly` | `true` when it changes nothing: agents may call it freely | `true` |
| `public` | `true` when anyone may call it, without signing in or a token | `true`: anyone may read the catalog |

**Scope.** A label such as `books:read` or `books:write`. A program or an agent gets a **personal access token**
(lesson 02) with a list of scopes, and may only call the capabilities whose scope is in that list. A `public`
capability ignores scopes: everyone may call it. Lesson 11 uses scopes for real.

**Inside a service:**

| Part | What it is |
|---|---|
| `$rules` | The inputs the capability accepts, and the rules each one must follow (`required`, `integer`...). **Only the inputs listed here reach `handle()`**: anything else a caller sends is dropped |
| `$filters` | Cleaning applied before the rules, such as `trim` (lesson 05) |
| `$descriptions` | One sentence per input. Agents and the JS client read them |
| `handle(array $input, Context $context)` | The work. It receives the checked inputs and returns data: an array, a model, a list. `$context` says who calls (lesson 11) |

**ServiceError.** How a capability says "no". Instead of returning data, `handle()` throws an error, for example
`throw ServiceError::notFound("There is no book #99");`. Every channel turns it into the right answer: a 404 for
REST, a tool error for an agent, a message on a page.

**A capability never draws anything.** No `echo`, no `view()`, no `redirect()` in `handle()`: it only returns data.
Each channel decides how to show it (an HTML page, JSON, a sentence from an agent). That is what lets one capability
serve them all.

**JSON.** The text format that programs exchange: `{"title": "Kindred", "published_year": 1979}` is an object with
two fields, `[ ... ]` is a list. Every channel except web pages answers in JSON.

---

## Manual track

Run every command from the `bookshelf` folder.

### Step 1 - Create the service list_books

Caligrafer writes the starting file for you:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
```
Expected:
```
 Created application/services/Bookshelf/ListBooks.php: capability 'list_books' (scope books:read)
 Fill in $rules and handle(), then call it from a controller ($this->call('list_books')), REST (Route::capability) or MCP (Route::mcp)
```
- `Bookshelf/ListBooks` is the folder and the class name. The capability name, `list_books`, comes from the class name.
- `--read-only` writes `readOnly: true`, because listing books changes nothing.

Open the file. It has the attribute and empty parts, each with a comment that says what goes there (shortened here):
```php
#[Capability(
    name: 'list_books',
    description: 'Describe in one or two sentences what this does and what it returns: AI agents read it to choose the tool.',
    scope: 'books:read',
    readOnly: true
)]
class ListBooks extends Service
{
    // Inputs: GUMP rules (...). Only these inputs reach handle().
    public $rules = array(
        // 'title' => 'required|max_len,255',
    );
    // Filters applied before validation
    public $filters = array( ... );
    // What each input is, for the JSON Schema that clients and agents see
    public $descriptions = array( ... );

    public function handle(array $input, Context $context)
    {
        // Do the work and return data (an array, a model, a list). Never render, redirect or print here.
        ...
        return array();
    }
}
```
The service has no `namespace` line: services are global classes, like the models `Book` and `Author`.

Replace the whole content of the file:

**File `application/services/Bookshelf/ListBooks.php`** (the catalog, sorted by title, with each author's name):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title, with the name of each author. Anyone may read it
#[Capability(
    name: 'list_books',
    description: 'Lists the books of the Bookshelf catalog, sorted by title: id, title, year, author id and author name. Give author_id to list only the books of one author.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer');
    public $descriptions = array('author_id' => 'Optional: only the books of this author');

    public function handle(array $input, Context $context)
    {
        // the name of every author, by id
        $names = array();
        foreach ((new Author('authors'))->all() as $author) {
            $names[$author->id] = $author->name;
        }

        $query = array('order' => 'ORDER BY title');
        if (!empty($input['author_id'])) {
            $query['condition'] = 'WHERE author_id = ?';   // ? is replaced by the value of bindings, safely
            $query['bindings'] = array($input['author_id']);
        }

        $books = array();
        foreach ((new Book('books'))->all($query) as $book) {
            $books[] = array(
                'id' => $book->id,
                'title' => $book->title,
                'published_year' => $book->published_year,
                'author_id' => $book->author_id,
                'author' => $names[$book->author_id] ?? ''
            );
        }
        return $books;
    }
}
```
- `public: true`: anyone may read the catalog.
- `$rules` has one optional input, `author_id`: when a caller gives it, only the books of that author are listed.
  It is not `required`, so a call without it is valid.
- `all(array('order' => ..., 'condition' => ..., 'bindings' => ...))` runs one `SELECT` on the `books` table. The
  `?` is replaced by the value of `bindings`, safely: never paste an input into SQL yourself.
- The names of the authors are read once into `$names`, then each book gets its author's name. The result is a
  plain list of arrays with exactly the fields an agent or a page needs.

### Step 2 - Create get_book and list_authors

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/GetBook --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/ListAuthors --scope books:read --read-only
```
Expected: ` Created application/services/Bookshelf/GetBook.php: capability 'get_book' (scope books:read)`, then the
same for `ListAuthors` and `list_authors`.

**File `application/services/Bookshelf/GetBook.php`** (one book and its author, or "not found"):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// get_book: one book and its author, or "not found"
#[Capability(
    name: 'get_book',
    description: 'Returns one book of the catalog (id, title, year, summary) and its author (id, name, bio), by the id of the book.',
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
        $book = (new Book('books'))->find($input['id']);
        if (!$book) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return array('book' => $book, 'author' => $book->author());
    }
}
```
- `'id' => 'required|integer'`: a call without an id, or with `"abc"`, is refused before `handle()` runs.
- `find($input['id'])` returns the book, or nothing. Nothing becomes a `ServiceError::notFound(...)`: a 404 everywhere.
- `$book->author()` is the relationship you wrote in lesson 03.

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
        return (new Author('authors'))->all(array('order' => 'ORDER BY name'));
    }
}
```

### Step 3 - List what Bookshelf offers

```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected (name, who may call it, what kind, description):
```
 get_book                     public         read      Returns one book of the catalog (id, title, year, summary) and its author (id, name, bio), by the id of the book.
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title: id, title, year, author id and author name. Give author_id to list only the books of one author.
```
You wrote no route. The command found the three capabilities in `application/services/`.

### Step 4 - Call them with curl

Quickstart added a generic endpoint to `web.php` in lesson 01: `Route::capabilities('/capabilities')`. It lets you
call any capability by its name with `POST /capabilities/<name>` and a JSON body of inputs. Lesson 06 uses it from
the browser; here you use `curl`.

```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{}' http://localhost:8080/capabilities/list_books
```
Expected (on one line, cut here): `{"data":[{"id":1,"title":"A Wizard of Earthsea","published_year":1968,"author_id":1,"author":"Ursula K. Le Guin"},{"id":5,"title":"Invisible Cities",...` with the 5 books, sorted by title.

Every successful answer is `{"data": ...}`. Now only the books of author 2:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"author_id": 2}' http://localhost:8080/capabilities/list_books
```
Expected: Kindred (1979) and Parable of the Sower (1993), by Octavia E. Butler.

One book:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"id": 3}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/get_book
```
Expected: `{"data":{"book":{"id":3,"title":"Kindred","published_year":1979,"summary":"A young woman is pulled back in time to a plantation in Maryland.","author_id":2,"created_at":"...","modified_at":"..."},"author":{"id":2,"name":"Octavia E. Butler","bio":"American science fiction author, the first to receive a MacArthur fellowship.",...}}} [200]`.
`-w ' [%{http_code}]\n'` prints the **HTTP status** after the answer: a number that says how the request went
(200 = OK, 404 = not found, 422 = invalid input).

Now three refusals. Change only the `-d` part of the last command:

| `-d` | Expected answer | Status | Who refused |
|---|---|---|---|
| `'{"id": 99}'` | `{"error":{"code":"not_found","message":"There is no book #99"}}` | 404 | your `ServiceError::notFound` |
| `'{"id": "abc"}'` | `{"error":{"code":"invalid",...,"fields":{"id":"The Id field must be a number without a decimal"}}}` | 422 | the rule `integer` |
| `'{}'` | `{"error":{"code":"invalid",...,"fields":{"id":"The Id field is required"}}}` | 422 | the rule `required` |

Every refusal is `{"error": {"code", "message", "fields"}}`: a `code` a program can test, a `message` for people,
and `fields`, one message per wrong input.

To see what the endpoint offers (with the inputs of each capability):
```bash
curl -s -H "Accept: application/json" http://localhost:8080/capabilities
```
Expected: a JSON list of the capabilities you may call, each with its input schema (built from `$rules` and
`$descriptions`).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | A change to a capability seems ignored, or `There is no capability` right after you saved | The server checks PHP files for changes every 2 seconds | Wait 2 seconds and run the command again |
> | ` application/services/Bookshelf/ListBooks.php already exists` | You ran the `service` command twice | Nothing to do: open the file and replace its content |
> | ` Usage: php caligrafer.php service <Name> ...` | The name is not in PascalCase (`listBooks`, `list_books`) | Use `Bookshelf/ListBooks` |
> | `capabilities` does not list one of them | The file has a PHP error, or the `#[Capability(` block is incomplete | Compare with the File block above, character by character |
> | `{"error":{"code":"not_found",...}}` for `list_books` itself (404) | The name in the URL is wrong (`listbooks`, `list-books`) | Use the `name` of the attribute: `list_books` |
> | An error that names the class `Book` or `Author` | The model file of lesson 03 is missing or misnamed | `ls application/models/Bookshelf`: `Book.php` and `Author.php` must be there |
> | An HTML page instead of JSON | The `Accept: application/json` header is missing | Copy the whole command |
> | `curl: (7) Failed to connect to localhost port 8080` | The server is stopped | `docker compose up -d dev-box` |
> | The list is empty (`{"data":[]}`) | The seed of lesson 03 was not run | `docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/seed.sql` |
> | `Permission denied` or `EACCES` when you save or create a file (Linux, or WSL) | The container writes the files it creates as root | In the `bookshelf` folder run `sudo chown -R "$USER": .`, then retry (again after a `caligrafer` command that creates files) |

### Step 5 - The AI agent already has them

You wrote no MCP code, but `Route::mcp('/mcp')` (added by quickstart) turns every capability into a tool for AI
agents. Ask the server which tools your token of lesson 02 sees. Put the token in a variable first, without
showing it on screen (paste it, then press Enter):
```bash
read -rs TOKEN
echo ${#TOKEN}
```
`echo ${#TOKEN}` prints the length of the token, not the token: it must not be `0`. Then:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected: `"name":"get_book"`, `"name":"list_authors"`, `"name":"list_books"`: the three capabilities, as tools.

Now in **Claude Code** (connected to `bookshelf` in lesson 02), start a new session in the `bookshelf` folder (the
`claude mcp add` of lesson 02 registers the server for that folder only) and ask:
```
Which books by Octavia E. Butler do we have?
```
Claude calls `list_authors` to find her id, then `list_books` with `author_id` (it asks your permission the first
time it uses each tool), and answers with Kindred (1979) and Parable of the Sower (1993). It knew what each tool does
from the `description` and `$descriptions` you wrote. That is build once: three small classes, and both `curl` and an
AI agent use them.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Unauthorized: send Authorization: Bearer <personal access token>` | `$TOKEN` is empty (a new terminal window) or mistyped | `read -rs TOKEN` again and paste the whole `cq_...` value |
> | `[ERROR 3000] Security Error - Cross Site Forgery` | The `Accept: application/json` header is missing | Copy the whole command |
> | Claude does not see the new tools | Claude Code was already open with the old list | Type `/mcp` and reconnect `bookshelf`, or start a new session |
> | `claude mcp list` shows `bookshelf ... Failed to connect` | The server is stopped, or the token was revoked | `docker compose up -d dev-box`; if you lost the token, create a new one and `claude mcp remove bookshelf`, then `claude mcp add` again (lesson 02) |

---

## With Claude

Start Claude Code in the `bookshelf` folder (`claude`). The plugin command `/caligrafy-quill:new-capability`
scaffolds the service with `caligrafer.php service`, fills it in the Caligrafy way and checks every channel. Run it
once per capability, with a precise description:

```
/caligrafy-quill:new-capability list_books books:read read-only
Put it in application/services/Bookshelf/ListBooks.php (caligrafer service Bookshelf/ListBooks). public: true.
It lists every book of the books table sorted by title, as a list of arrays with id, title, published_year,
author_id and author (the author's name, read from the authors table once, not one query per book). One optional
input author_id (rule 'integer', description 'Optional: only the books of this author'): when given, only the books
of that author (bound query with ?). Description: 'Lists the books of the Bookshelf catalog, sorted by title: id,
title, year, author id and author name. Give author_id to list only the books of one author.' Do not add routes.
```
```
/caligrafy-quill:new-capability get_book books:read read-only
In application/services/Bookshelf/GetBook.php, public: true. One input id (required|integer, 'The id of the
book'). Returns array('book' => the book, 'author' => $book->author()). When the book does not exist, throw
ServiceError::notFound("There is no book #<id>"). Do not add routes.
```
```
/caligrafy-quill:new-capability list_authors books:read read-only
In application/services/Bookshelf/ListAuthors.php, public: true, no inputs. Returns every author sorted by name.
Do not add routes.
```

**What Claude should do:** run the `service` command three times, write the three files of steps 1 and 2, then run
`capabilities` and the `curl` calls of step 4.

**Check** before you accept:
- [ ] The names are exactly `list_books`, `get_book`, `list_authors`, all with `scope: 'books:read'`, `readOnly: true`, `public: true`.
- [ ] The files are in `application/services/Bookshelf/` and have no `namespace` line.
- [ ] `handle()` only returns data: no `echo`, `view()`, `redirect()` or `api()`.
- [ ] Every input that `handle()` reads is in `$rules` (`author_id` for list_books, `id` for get_book).
- [ ] The SQL of list_books uses `?` and `bindings`, never the input pasted in the string.
- [ ] No new line in `web.php` (the channels already exist).
- [ ] The `curl` calls of step 4 give the expected answers, and the agent question of step 5 works.

**If Claude's result differs:** Claude may return the models of `all()` directly from list_books (each book without
the author's name), or build the names with one query per book. Both work, but the pages of lesson 06 show
`book.author`: ask for the `author` field and a single query on `authors`. If Claude adds `Route::capability` lines
to `web.php`, remove them: the REST routes come in lesson 07. If Claude proposes to create a token, say no: you
already have the one of lesson 02. Descriptions in other words are fine: the `capabilities` output will show yours instead of the one in the Manual
track.

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
4. What would change if `list_books` had no `public: true`?

<details><summary>Show the solution</summary>

1. Only `array('author_id' => 2)`: only the inputs listed in `$rules` reach `handle()`. `admin` is dropped.
2. A capability never draws anything: every channel turns the `ServiceError` into its own answer (a 404 with
   `{"error": ...}` for REST, a tool error for an agent, a Not found page in lesson 06). `echo` would only work for
   one channel, and badly.
3. From the capability itself: `$rules` and `$descriptions` become the input schema of the tool, and `description`
   tells the agent what the tool does. Changing a description changes what every agent reads.
4. Callers would need to sign in, or send a token whose scopes include `books:read`. Visitors would get a 401.
   Lesson 11 does this for the capabilities that change data.
</details>

---
Previous: [Lesson 03 - Your data: tables and models](03-Your-Data) · Next: [Lesson 05 - Rules: adding, changing and deleting books](05-Rules-and-Changes)
