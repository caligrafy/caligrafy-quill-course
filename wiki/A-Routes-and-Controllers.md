# Appendix A - Routes and controllers by hand

**In this appendix** you look under the hood: you write routes that answer with a function, a controller that reads
the models itself and renders a page (an author and their books), and a classic JSON route. Then you compare them
with capabilities, to see what the other experiences (REST, the JS client, AI agents) do **not** get.

**Before you start:** this appendix is optional. Do it any time after lesson 06 (the checkpoint `appendix-a` is built
on `lesson-13`, but nothing here needs loans). The containers run (`docker compose ps`).

**Time:** 45 minutes.

---

## New concepts

**Route.** A route connects an HTTP method and an address (a path) to the code that answers. Every route is one line
of `application/config/routing/web.php`:
```php
Route::get('/about', function () { echo '...'; });
//     ▲      ▲       ▲
//     │      │       └─ what answers: a function (a "closure"), a controller action, or a capability
//     │      └─ the path
//     └─ the HTTP method: get, post, put or delete
```
You already wrote three kinds of routes: `Route::get('/books/{id}', 'Bookshelf\\BookController@show')` (a controller,
lesson 06), `Route::capability(...)` (REST, lesson 07) and `Route::mcp('/mcp')` (agents). This appendix writes the
first kind by hand, without capabilities.

**Closure.** `function () { ... }` without a name: a function written right where it is used. Fine for a tiny page.

**Route variable.** A part of the path in braces, `{name}` in `/hello/{name}`, matches any value in that position.
The code reads it with `request()->fetch('name')`; inside a controller, `$this->request->fetch('name')`.

**The three rules of routing**:
1. `Route::run();` is the last line inside the `try { }` of `web.php`. The `Route::...` lines only declare routes;
   `Route::run()` picks the one that matches and runs it. A route declared after it is never used.
2. **The first route that matches wins.** A route matches when the method is the same and the path has the same
   number of segments (`/authors/2` has two: `authors` and `2`). So `/authors/new` must come before
   `/authors/{id}`, or `{id}` would catch the word `new` (Appendix B).
3. **An address that matches no route shows the first route** (`/`): Caligrafy has no automatic "404" for unknown
   addresses. That is why `/` stays the first route.

**Controller and action.** A controller is a class that extends `Caligrafy\Controller`; each public method that
answers a route is an **action**. The route names both: `'Bookshelf\\AuthorController@show'` (namespace, class,
`@`, action). `BookController` (lesson 06) is one: its actions call capabilities. `AuthorController` reads the models
itself, the classic way. Never name an action like a method of `Controller` (`find`, `save`, `delete`, `all`,
`search`, `check`, `validate`, `filter`, `view`, `api`, `associate`, `table`).

**Namespace.** Caligrafy loads every PHP file of `application/`, so two classes with the same name would crash every
page. `namespace Bookshelf;` makes the full name `Bookshelf\AuthorController`. In a single-quoted PHP string the
backslash is written twice: `'Bookshelf\\AuthorController'`.

**The controller's database helpers.** `$this->associate('Author', 'authors')` says which model and table the next
calls work on. Then `$this->find()` returns the row whose id is the `{id}` of the route (or nothing),
`$this->all(array('order' => 'ORDER BY name'))` returns every row. A model's relationships work as in lesson 03:
`$author->books()`.

**`view()` and `api()`.** `view('Bookshelf/author', $data)` renders a Pug page. `api($data)` sends `$data` as JSON:
the classic JSON output, without the `{"data": ...}` envelope of capabilities.

**Controller routes and API calls.** A request that asks for JSON (`Accept: application/json`) is an **API call**.
For a controller route, Caligrafy then requires a token with the scope `*` (or the legacy key, turned off in
lesson 12): scopes such as `books:read` mean nothing to a controller. A browser (no such header) gets the page or
the JSON without a token.

---

## Manual track

### Step 1 - Two closure routes

In `application/config/routing/web.php`, add these lines above `// MUST NOT BE REMOVED` and `Route::run();` (the
full file is in the checkpoint):

```php
    // APPENDIX A: routes and controllers written by hand
    Route::get('/about', function () {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function () {
        $name = request()->fetch('name');        // the {name} part of the address
        echo 'Hello ' . sanitize($name) . '!';   // sanitize: never print what a visitor typed as is
    });
```

`sanitize()` turns `<`, `>`, `&` and quotes into HTML entities, so an address that contains a `<script>` shows text
instead of running it. Always escape what comes from the address or a form before you print it (Pug views do it for
you).

Check, from the `bookshelf` folder:
```bash
curl http://localhost:8080/about
curl http://localhost:8080/hello/Ursula
```
Expected: `Bookshelf: a small catalog of the books we love.` and `Hello Ursula!`. Add `-i` to see the status:
the first line is `HTTP/1.1 200 OK`.

### Step 2 - A controller for authors

**File `application/controllers/Bookshelf/AuthorController.php`**:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

// The classic way: a controller reads the models itself and renders a view. No capability is involved,
// so only this page has these features: REST clients and AI agents do not see them
class AuthorController extends Controller
{
    // GET /authors/{id}: an author and their books
    public function show()
    {
        $this->associate('Author', 'authors');   // the model and the table this controller works on
        $author = $this->find();                 // finds the row whose id is {id} of the route
        if (!$author) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no such author.', 'authorized' => authorized()));
        }
        return view('Bookshelf/author', array('author' => $author, 'books' => $author->books(), 'authorized' => authorized()));
    }

    // GET /v1/authors: JSON for programs (Accept: application/json and a token with the scope *)
    public function index()
    {
        $this->associate('Author', 'authors');
        return api(array('authors' => $this->all(array('order' => 'ORDER BY name'))));
    }
}
```

- `namespace Bookshelf;` is the first statement after `<?php`; the folder `controllers/Bookshelf` matches it.
- `show()`: `associate()`, then `find()` reads `{id}` from the route. No author: status 404 (set **before** the
  view) and the "Not found" page of lesson 06. Otherwise the author page, with the books of the author.
- `index()`: every author, sorted by name, as JSON for programs.

**File `application/views/Bookshelf/author.pug`** (the page components of lesson 06; `$author` and `$books` are
models, so their columns are read with `->`):

```pug
extends ../default/master/layoutblocks

//- An author and their books, rendered by Bookshelf\AuthorController (the classic way)

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title= $author->name
                p.cq-note= $author->bio
            ul.cq-list
                each $book in $books
                    li.cq-item
                        .cq-item-body
                            p.cq-item-title
                                a(href="{$home}books/{$book->id}")= $book->title
                            p.cq-item-meta= $book->published_year
            .cq-foot
                a(href="{$home}books") All books
```

Add the two routes below the lines of step 1:
```php
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@show');
    Route::get('/v1/authors', 'Bookshelf\\AuthorController@index');
```

Open http://localhost:8080/authors/2: the card "Octavia E. Butler", her bio, and her books (Kindred, Parable of the
Sower, and the ones you added) with links to their pages, and "All books" at the bottom. Open
http://localhost:8080/authors/99: the "Not found" card with "There is no such author.". Check the status:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/authors/99
```
Expected: `404`.

### Step 3 - A classic JSON route

Open http://localhost:8080/v1/authors in your browser: the JSON of the authors (200). A browser does not ask for
JSON, so this is not an API call and no token is needed. Now as a program:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/v1/authors
```
Expected: `{"error":{"code":1004,"message":"Access Denied"}} [401]`. Try the "Claude Code" token of lesson 11
(`books:read,books:write`): put it in a variable, `BOOKS_TOKEN=$WRITER` in the same terminal as lesson 11 (otherwise
paste the "Claude Code" token of lesson 11, `BOOKS_TOKEN=cq_...`), then
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $BOOKS_TOKEN" -w " [%{http_code}]\n" http://localhost:8080/v1/authors
```
Expected: `401` again: controller routes accept only tokens with the scope `*`. Create one:
```bash
docker compose exec dev-box php caligrafer.php token create "old style client" --scopes "*"
```
Put the printed token in `STAR_TOKEN=cq_...` and call again with `-H "Authorization: Bearer $STAR_TOKEN"`. Expected:
`{"authors":[{"table":"authors","id":3,"name":"Italo Calvino",...` (sorted by name). Compare with
`GET /api/authors` (lesson 07): there is no `data` envelope, and each author has a `table` field. That is the
classic `api()` output of a model.

A `*` token may call **everything**, capabilities included. Revoke it now: `token list` to find its id, then
`docker compose exec dev-box php caligrafer.php token revoke <id>`.

> If you do this appendix before lesson 11: there is no "Claude Code" token yet, and the "my agent" token of
> lesson 02 has the scope `*`, so it already gets the JSON.

### Step 4 - See the rules for yourself

- Open http://localhost:8080/does/not/exist: the catalog. No route matched, so the first route (`/`) answered
  (rule 3).
- Move the line `Route::run();` above the `// APPENDIX A` block, save, wait 2 seconds and open
  http://localhost:8080/about: the catalog again, because `/about` was declared after `Route::run()` (rule 1). Move
  `Route::run();` back just above the closing `}` of the `try`, and check that `/about` works again.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | The catalog instead of your page | The route did not match: a typo in the path, another number of segments, or the route is below `Route::run();` | Compare with steps 1 and 2 |
> | `[ERROR 1001] Invalid Controller or Action` (404) | The route names a class or action that does not exist: missing `Bookshelf\\`, a typo, wrong case, or the `namespace` line is missing | Compare the route and the file with step 2 |
> | `Cannot redeclare class Bookshelf\AuthorController` | A copy of the file is somewhere in `application/` (every PHP file there is loaded) | Delete the copy or move it out of `application/` |
> | `[ERROR 500] syntax error, ...` | A PHP syntax error | `docker compose exec dev-box php -l application/controllers/Bookshelf/AuthorController.php` names the line |
> | `/v1/authors` with the `*` token still answers 401 | The token was not copied whole, or it was revoked | `token list`; create a new one |
> | Your change does not show | PHP caches compiled files and checks for changes every 2 seconds | Wait 2 seconds and reload |

---

## With Claude

Start Claude Code in the `bookshelf` folder (`claude`) and ask:
```
Without capabilities, the classic way: in application/config/routing/web.php, in a block
"// APPENDIX A: routes and controllers written by hand" above "// MUST NOT BE REMOVED", add two closure routes:
GET /about that echoes "Bookshelf: a small catalog of the books we love." and GET /hello/{name} that echoes
"Hello <name>!" with the name read by request()->fetch('name') and escaped with sanitize(). Then create
application/controllers/Bookshelf/AuthorController.php (namespace Bookshelf, extends Caligrafy\Controller) with
show(): associate('Author', 'authors'), find() the {id} of the route, 404 with the view Bookshelf/notfound and the
message "There is no such author." when missing, else the view Bookshelf/author with the author and $author->books();
and index(): api(['authors' => all authors ordered by name]). Write application/views/Bookshelf/author.pug with the
page components (one card: name, bio, the books as links to /books/<id> with their year, a link to all books). Pass
'authorized' => authorized() to the views. Routes: GET /authors/{id} show, GET /v1/authors index.
```

**What Claude should do:** add the four routes in one block, create the controller and the view. The plugin's
`caligrafy` skill knows the routing rules and the reserved action names.

**Check** before you accept:
- [ ] `Route::get('/', ...)` is still the first route and `Route::run();` the last line inside `try`.
- [ ] The name of `/hello/{name}` goes through `sanitize()`, never printed raw.
- [ ] The file starts with `namespace Bookshelf;` and the routes say `'Bookshelf\\AuthorController@...'`.
- [ ] No action is named `find`, `all`, `delete`, `save`, `view` or `api`.
- [ ] `http_response_code(404)` comes **before** the view of a missing author.
- [ ] The checks of steps 1 to 3 give the same answers (including the 401 on `/v1/authors` for a token without the scope `*`).

**If Claude's result differs:** if Claude offers to write a `get_author` capability instead, say no for this
appendix (that is the point of the comparison below). If it reads `{name}` with `$_GET`, ask for
`request()->fetch('name')`: `$_GET` does not contain route variables.

---

## Compare with capabilities

| | `AuthorController` (this appendix) | A capability (lessons 04 to 13) |
|---|---|---|
| The author page | yes | yes, through a controller that calls it (`$this->call()`) |
| REST | only `/v1/authors`, with a `*` token, no `data` envelope | any route, scoped tokens, `{"data"}` / `{"error"}` everywhere |
| The JS client (Vue apps, SPA) | no: there is no function for it | yes, after `client` |
| AI agents (MCP) | no: no tool | yes, a tool with its description and schema |
| Rules, permissions, audit | written again in each controller | written once; refusals and changes audited |

An agent connected to `/mcp` has no tool for the author page or `/v1/authors`: everything it knows about authors comes
from `list_authors`. Write an action by hand when it belongs to **one page** only; write a capability when anyone else
may need it.

---

## Checkpoint

```bash
git add -A
git commit -m "Appendix A: routes and controllers by hand"
git tag appendix-a
```
Reference: [`checkpoints/appendix-a`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/appendix-a).

## Quiz

1. You add `Route::get('/authors/top', ...)` below `/authors/{id}`. What does `/authors/top` show, and why?
2. Why does `/v1/authors` answer 401 to the "Claude Code" token, while `/api/authors` answers 200 to anyone?
3. `/hello/{name}` prints the name with `sanitize()`. What could happen without it?

<details><summary>Show the solution</summary>

1. The "Not found" page of an author (404): `/authors/{id}` comes first and matches (two segments), with `{id}` =
   `top`, and the first route that matches wins. Declare `/authors/top` above `/authors/{id}`.
2. `/v1/authors` is a controller route: an API call to it needs a token with the scope `*`. `/api/authors` is the
   capability `list_authors`, which is `public: true`, so anyone may call it.
3. A visitor could put HTML or a `<script>` in the address and send the link to someone: the page would run it in
   their browser (an XSS attack). `sanitize()` turns it into harmless text.
</details>

---
Previous: [Lesson 13 - Capstone: one more feature, everywhere](13-Capstone-Loans) · Next: [Appendix B - Classic forms and controller APIs](B-Classic-Forms)
