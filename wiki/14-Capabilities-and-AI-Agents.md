# Lesson 14 - Capabilities and AI agents

**In this lesson** you write three Bookshelf features once, as **capabilities**, and use them through a REST API
and from an **AI agent** (Claude Code) over **MCP**, with a **personal access token** that can only do what you allow.

**Before you start:** lesson 13 is done (tag `lesson-13`), the containers run, and your project runs Caligrafy Quill
**2.1.0** or newer: `docker compose exec dev-box php caligrafer.php version`. If it prints `2.0.0`, see
"Update a 2.0.0 project" at the end of the Manual track first.

**Time:** 45 to 60 minutes.

---

## New concepts

**A capability is a feature written once.** Until now each channel had its own code: `BookController` for the pages,
`BookApiController` for the API. They share the rules of `BookForm`, but each one repeats the checks (signed in?
valid? the author exists?). A **capability** is one small class, a **service**, that does it all once: it lists the
inputs it accepts, checks who is asking, does the work and returns data. It never renders a page or redirects:
every channel (a page, the REST API, the Vue apps, an AI agent) calls it and shows the result its own way.

| Word | What it is |
|---|---|
| service | a class in `application/services/` that extends `Caligrafy\Service`: `$rules` (the inputs), `handle()` (the work) |
| `#[Capability]` | the line above the class that gives it a name (`create_book`), a description, and the **scope** it needs |
| scope | a right, written `resource:action`: `books:read`, `books:write` |
| personal access token | a secret like `cq_3_9f2c...` that a program or an agent sends; it has a name and scopes, and you can revoke it |
| MCP | the Model Context Protocol: how AI agents such as Claude use the tools of an application |

**Why a token, not the API key.** The `API_KEY` of lessons 09 to 12 is given to every page of the Vue apps, so anyone
can read it, and every key made from your `APP_KEY` is the same key: you cannot revoke one without the others.
A token is given to one program, can only do what its scopes allow, and is revoked on its own.

**Who checks what.** For every call, whatever the channel, the framework checks in this order: the capability exists
(404), the caller is signed in or sends a token (401), the token has the scope (403), the inputs follow `$rules`
(422, with a message per field). Then `handle()` runs, and the call is written to the **audit log**.

---

## Manual track

### Step 1 - Create the framework tables

Tokens and the audit log are stored in two tables of the framework:
```bash
docker compose exec dev-box php caligrafer.php platform install
```
Expected: `Framework tables ready: caligrafy_tokens, caligrafy_audit`. Check:
```bash
docker compose exec dev-box php caligrafer.php doctor --only database
```
Expected: `OK    Framework tables: caligrafy_tokens and caligrafy_audit installed`.

### Step 2 - Write three capabilities

Caligrafer creates the files with their boilerplate:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/GetBook --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
```
Expected, for each: `Created application/services/Bookshelf/<Name>.php: capability '<name>' (scope ...)`. Replace
their content with the following.

**File `application/services/Bookshelf/ListBooks.php`** (read-only: it changes nothing):

```php
<?php

namespace Bookshelf;

use Book;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title. Read-only: it changes nothing
#[Capability(
    name: 'list_books',
    description: 'Lists every book of the Bookshelf catalog, sorted by title, with its id, title, year, summary and author id.',
    scope: 'books:read',
    readOnly: true
)]
class ListBooks extends Service
{
    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->all(array('order' => 'ORDER BY title'));
    }
}
```

**File `application/services/Bookshelf/GetBook.php`** (`$rules` lists the only input it accepts):

```php
<?php

namespace Bookshelf;

use Book;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// get_book: one book and its author, or "not found"
#[Capability(
    name: 'get_book',
    description: 'Returns one book of the catalog and its author, by the id of the book.',
    scope: 'books:read',
    readOnly: true
)]
class GetBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book');

    public function handle(array $input, Context $context)
    {
        $book = (new Book('books'))->find($input['id']);
        if (!$book) {
            throw ServiceError::notFound('Book not found');
        }
        return array('book' => $book, 'author' => $book->author());
    }
}
```

**File `application/services/Bookshelf/CreateBook.php`** (the rules of `BookForm`, from lesson 07, reused as is):

```php
<?php

namespace Bookshelf;

use Author;
use Book;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// create_book: the same rules as the web form and the API (BookForm), written once
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the catalog and returns it with its id. The author must already exist (see get_book for author ids).',
    scope: 'books:write'
)]
class CreateBook extends Service
{
    public $rules = BookForm::RULES;
    public $filters = BookForm::FILTERS;
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published',
        'summary' => 'A short summary (optional)',
        'author_id' => 'The id of its author'
    );

    public function handle(array $input, Context $context)
    {
        if (!(new Author('authors'))->find($input['author_id'])) {
            throw ServiceError::invalid(array('author_id' => 'There is no author with this id'));
        }
        return (new Book('books'))->create(BookForm::columns($input + array('summary' => '')));
    }
}
```

Three things to notice:
- `description` is written for an AI agent: it reads it to decide which tool to use.
- `handle()` returns data (a model, an array) and refuses with `ServiceError`: never `view()`, `redirect()` or `echo`.
- Only the inputs listed in `$rules` reach `handle()`: anything else the caller sends is dropped.

List them:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected (three lines):
```
 create_book                  books:write    writes    Adds a book to the catalog and returns it with its id. ...
 get_book                     books:read     read      Returns one book of the catalog and its author, by the id of the book.
 list_books                   books:read     read      Lists every book of the Bookshelf catalog, sorted by title, ...
```

### Step 3 - Expose them

Add five routes, above `Route::run()`.

**File `application/config/routing/web.php`**:

```php
<?php
/**
 * web.php - every URL of the Bookshelf application is declared in this file
 */

use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {

    // API ACCESS: lets programs that send the API key call the controller routes (lesson 09)
    Auth::activateAPI();

    // AUTHENTICATION: users are rows of the users table, read with the User model (lesson 08)
    Auth::authentication('User', 'users');

    // ROUTES. The first route must stay '/': a URL that matches no route shows the first route.
    Route::get('/', 'Bookshelf\\BookController');
    Route::get('/helloworld', function() { echo "Hello World"; });

    // Lesson 02: our first routes
    Route::get('/about', function() {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function() {
        $name = request()->fetch('name');
        echo 'Hello ' . sanitize($name) . '!';
    });

    // Books and authors
    Route::get('/books', 'Bookshelf\\BookController');            // no @action: index()
    Route::get('/books/new', 'Bookshelf\\BookController@createForm');   // before /books/{id}
    Route::get('/books/{id}', 'Bookshelf\\BookController@read');
    Route::get('/books/{id}/edit', 'Bookshelf\\BookController@editForm');
    Route::post('/books', 'Bookshelf\\BookController@create');
    Route::put('/books/{id}', 'Bookshelf\\BookController@update');
    Route::delete('/books/{id}', 'Bookshelf\\BookController@remove');
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@read');

    // Sign in, sign out, register (controllers shipped with the framework)
    Route::get('/home', 'Bookshelf\\BookController');          // where the login and logout pages go
    Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
    Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');
    Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');

    // REST API (lesson 09): JSON only, called with the API key
    Route::get('/api/books', 'Bookshelf\\Api\\BookApiController');
    Route::get('/api/authors', 'Bookshelf\\Api\\BookApiController@authors');
    Route::get('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@read');
    Route::post('/api/books', 'Bookshelf\\Api\\BookApiController@create');
    Route::put('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@update');
    Route::delete('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@remove');

    // The Vue catalog (lesson 10): public/catalog, shown by a controller that passes signedIn to the app
    Route::get('/catalog', 'Bookshelf\\CatalogController');

    // Client apps (lesson 10): public/<appName> is shown at /apps/<appName>
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');

    // BUILD ONCE (lesson 14): the capabilities of application/services, for REST, the JS client and AI agents
    Route::capabilities('/capabilities');
    Route::mcp('/mcp');
    Route::capability('GET', '/v2/books', 'list_books');
    Route::capability('GET', '/v2/books/{id}', 'get_book');
    Route::capability('POST', '/v2/books', 'create_book');

    // MUST STAY LAST: finds the route that matches the request and runs it
    Route::run();
}
catch (Exception $e) {

    // Errors get their HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

- `Route::capability()` maps one REST route to one capability; `{id}` becomes the `id` input.
- `Route::capabilities('/capabilities')` lists what the caller may use and calls any capability by name (the Vue
  apps can use it through a generated client: `php caligrafer.php client`).
- `Route::mcp('/mcp')` is the door for AI agents.

### Step 4 - Create a token

```bash
docker compose exec dev-box php caligrafer.php token create "Bookshelf agent" --scopes books:read,books:write
```
Expected:
```
 Token #1 "Bookshelf agent" created with books:read books:write

 cq_1_3f9a...  (40 hex characters after the second _)

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
Copy it into a variable of your terminal (it stays only in this terminal window):
```bash
TOKEN=cq_1_paste-yours-here
```

### Step 5 - Call the capabilities over REST

```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" http://localhost:8080/v2/books/1
```
Expected: `{"data":{"book":{"id":1,"title":...},"author":{"id":...,"name":...}}}`.

A valid book:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"title": "The Dispossessed", "published_year": 1974, "author_id": 1}' http://localhost:8080/v2/books
```
Expected: `{"data":{"id":...,"title":"The Dispossessed",...}}`, status 201.

An invalid one gets the same messages as the web form:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"published_year": 3000, "author_id": 999}' http://localhost:8080/v2/books
```
Expected: status 422 and `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":...,"published_year":...}}}`.

Without a token: `curl -s -i -H "Accept: application/json" http://localhost:8080/v2/books` answers `401` with
`WWW-Authenticate: Bearer`.

### Step 6 - Let an AI agent use Bookshelf

Add Bookshelf to Claude Code as an MCP server (one line):
```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer $TOKEN"
```
Then start `claude` in any folder and ask:
```
Using the bookshelf tools, list the books, then add "Bloodchild" (1995) by the author of "Kindred" if that author is in the catalog.
```
**What to expect:** Claude calls `list_books`, then `get_book` to find the author id, then `create_book`. Claude Code
asks before each tool call unless you allow it. Open http://localhost:8080/books: the new book is there.

Without Claude Code, you can talk MCP with `curl`:
```bash
curl -s -X POST -H "Accept: application/json, text/event-stream" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp
```
Expected: `{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"create_book",...},{"name":"get_book",...},{"name":"list_books",...}]}}`.

### Step 7 - Limit and revoke

A token that can only read:
```bash
docker compose exec dev-box php caligrafer.php token create "Read only" --scopes books:read
```
With it, `POST /v2/books` answers `403` ("This needs the scope books:write") and `tools/list` shows only `get_book` and
`list_books`. List and revoke tokens:
```bash
docker compose exec dev-box php caligrafer.php token list
docker compose exec dev-box php caligrafer.php token revoke 2
```
A revoked token gets `401` at once, on REST and MCP.

See what happened:
```bash
docker compose exec dev-box php caligrafer.php audit
```
Each line shows when, the channel (`rest`, `mcp`), who (`token` and its number), the capability and the outcome
(`ok`, `invalid`, `forbidden`).

> **If it doesn't work**
>
> - `The token table does not exist yet`: run step 1.
> - `401` with a token you just created: check `echo $TOKEN` (no spaces, the whole `cq_...` value), and that you
>   copied it from this project (each project has its own tokens).
> - `404` on `/v2/books`: the routes are not in `web.php`, or they are below `Route::run()`.
> - `capabilities` lists nothing: the files are not in `application/services/`, or a `#[Capability(...)]` line is
>   missing; run `docker compose exec dev-box php -l application/services/Bookshelf/CreateBook.php`.
> - Claude Code does not see the tools: `claude mcp list` must show `bookshelf ... Connected`; remove and add it
>   again with the right token (`claude mcp remove bookshelf`).

### Update a 2.0.0 project

If `php caligrafer.php version` printed `2.0.0`, your project was cloned before the capabilities existed. Copy the
framework of 2.1.1 over it (your `application/` and `public/` apps stay yours), then let `upgrade` update the
templates you never changed:
```bash
git clone --branch v2.1.1 --depth 1 https://github.com/caligrafy/caligrafy-quill.git ../quill-2.1.1
cp -R ../quill-2.1.1/framework ../quill-2.1.1/caligrafer.php ../quill-2.1.1/claude-plugin .
cp ../quill-2.1.1/public/css/tokens.css ../quill-2.1.1/public/css/tokens.json public/css/
docker compose exec dev-box php caligrafer.php version
docker compose exec dev-box php caligrafer.php upgrade --apply
```
Expected: `Caligrafy Quill 2.1.1`. Commit, then start at step 1.

---

## With Claude

In Claude Code, in the `bookshelf` folder:
```
/caligrafy-quill:new-capability create_book books:write
Use the rules of Bookshelf\BookForm, check that the author exists, and also add list_books and get_book (books:read,
read-only). Expose them with Route::capability under /v2/books, add Route::capabilities and Route::mcp, create a
token for an agent and show me the curl checks.
```
**Check:**
- [ ] `php caligrafer.php capabilities` lists the three capabilities with the right scopes, `read` for the two reads.
- [ ] `CreateBook` uses `BookForm::RULES` and `BookForm::FILTERS`, and `handle()` has no `view()`, `redirect()` or `echo`.
- [ ] Every input `handle()` reads is a key of `$rules`.
- [ ] The routes are above `Route::run()`.
- [ ] The token was shown to you once and is not written in any file of the project.
- [ ] The invalid `curl` of step 5 answers `422` with the same field names as the web form.

**If Claude's result differs:** a capability without a scope works with any token: ask for the scope. A
`description` like "Creates a book" works, but an agent chooses tools better with what it returns and what it needs
(the author must exist).

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 14: capabilities, tokens and MCP"
git tag lesson-14
```
Reference: [`checkpoints/lesson-14`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-14).
Never commit a token: it is only in your terminal and in Claude Code's settings.

You finished the course. The guide [11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
covers the rest: calling a capability from a Pug page (`$this->call()`), the JS client for the Vue apps, MCP over
stdio, levels and the audit log.

## Exercise

1. Add a `delete_book` capability that only users with `permissions >= 1` may use, and tell agents that it deletes data.
2. Why does the read-only token of step 7 not even see `create_book` in `tools/list`?

<details><summary>Show the solution</summary>

1. `docker compose exec dev-box php caligrafer.php service Bookshelf/DeleteBook --scope books:write`, then:
   ```php
   #[Capability(name: 'delete_book', description: 'Deletes a book by its id.', scope: 'books:write', level: 1, destructive: true)]
   class DeleteBook extends Service
   {
       public $rules = array('id' => 'required|integer');

       public function handle(array $input, Context $context)
       {
           $books = new Book('books');
           if (!$books->find($input['id'])) {
               throw ServiceError::notFound('Book not found');
           }
           $books->delete($input['id']);
           return array('deleted' => (int) $input['id']);
       }
   }
   ```
   `level: 1` applies to signed-in users and to tokens created with `--user <id>`; `destructive: true` makes agents
   ask before calling it. Route it with `Route::capability('DELETE', '/v2/books/{id}', 'delete_book');`.
2. An agent sees only the tools its token allows: hiding the others means it cannot even try them, and cannot learn
   what the application can do beyond its rights.
</details>

---
Previous: [Lesson 13 - Review, tests, doctor and upgrade](13-Review-Tests-Doctor-Upgrade) · [Back to the course home](Home)
