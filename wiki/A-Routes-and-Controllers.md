# Appendix A - Routes and controllers by hand

**In this appendix** you look under the hood of the routes of the lessons. You write routes that answer with a
function, a controller for HTTP that no declarative route gives (an RSS feed) which calls a capability with
`$this->call()`, and then, the classic way, a controller that reads the tables itself and a JSON route. Then you compare
them with capabilities, and see what the one-line routes (`Route::page`, `Route::form`, `Route::auth`,
`Route::download`) do for you.

> **This is another way, not the course's way.** Bookshelf's pages, forms, sign-in, exports, REST and agents are each
> one line of `web.php` on capabilities. A controller is right only for HTTP that none of them covers: a response that
> is neither a page, a redirect, JSON nor a file (an RSS feed, an image made on the fly), an action that calls several
> capabilities with conditions, an OAuth callback. Even then it holds no rule: it calls capabilities. The classic
> controller of step 3 is shown so that you can read older projects.

**Before you start:** this appendix is optional. Do it any time after lesson 11; its checkpoint is built on
`lesson-15`. The containers run (`docker compose ps`).

**Time:** 45 minutes.

---

## New concepts

**Route.** A route connects an HTTP method and a path to the code that answers. Every route is one line of
`application/config/routing/web.php`:
```php
Route::get('/about', function () { echo '...'; });
//     ▲      ▲       ▲
//     │      │       └─ what answers: a function (a "closure") or a controller action
//     │      └─ the path
//     └─ the HTTP method: get, post, put or delete
```

**Closure.** `function () { ... }` without a name, written where it is used. Fine for a tiny text answer.

**Route variable.** `{name}` in `/hello/{name}` matches any value in that position. A closure reads it with
`request()->fetch('name')`, a controller with `$this->request->fetch('name')`.

**The three rules of routing:**
1. `Route::run();` is the last line inside the `try { }` of `web.php`: the `Route::...` lines only declare routes, and
   a route declared after `Route::run()` is never used.
2. **The first route that matches wins.** A route matches when the method is the same and the path has the same number
   of segments (`/authors/2` has two). So `/authors/new` goes before `/authors/{id}` (Appendix B).
3. **No route answers 404** (`[ERROR 1005] Page Not Found`), or **405** when the address exists with another method
   (`[ERROR 1000] Invalid Http Request Method: this address answers GET`).

**Controller and action.** A controller is a class that extends `Caligrafy\Controller`; each public method that
answers a route is an **action**. The route names both: `'Bookshelf\\FeedController@books'` (namespace, class, `@`,
action; in a single-quoted PHP string the backslash is written twice). Caligrafy finds each class of `application/` by
its name, so `namespace Bookshelf;` keeps it apart from a class of the same name elsewhere. Never name an action like a
method of `Controller` (`find`, `save`, `delete`, `all`, `search`, `check`, `validate`, `filter`, `view`, `api`, `call`,
`associate`, `table`).

**`$this->call('list_books', $input)`** calls a capability as the visitor (the session's user, or nobody) through the
web channel: the same checks of the caller, rules and audit as the pages, REST and agents. Without `$input`, the inputs
are the route variables and the request's fields. A refusal is a `Caligrafy\ServiceError` (`$e->kind`, `$e->fields`).

**The classic helpers.** `$this->associate('Author', 'authors')` names the model and table of the next calls;
`$this->find()` returns the row whose id is `{id}`, `$this->all([...])` rows with SQL pieces (`condition`, `order`,
`bindings`). `view()` renders a Pug page; `api($data)` sends `$data` as JSON, without the `{"data"}` envelope.

**Controller routes and API calls.** A request with `Accept: application/json` is an **API call**. For a controller
route, Caligrafy then requires the page's CSRF token in `X-CSRF-Token` (a page of the app, with the session), or a
token with the scope `*`: scopes such as `books:read` mean nothing to a controller. A browser opening the address gets
the answer without a token.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - Two closure routes, a feed and the classic routes

In `application/config/routing/web.php`, add the `// APPENDIX A` block above `// MUST NOT BE REMOVED`. The file is
then:

**File `application/config/routing/web.php`** (the `// APPENDIX A` block: two closures and three controller routes):

```php
<?php
/**
 * web.php
 * Web Routes are defined in this file
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {
    
    // API ACCESS - add comments if you don't want to allow API access on routes
    Auth::activateAPI();
    
    
    // AUTHENTICATION - Remove comment if you have an authentication implemented
    Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::redirect('/', '/books');   // the home of Bookshelf is its catalog
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // SIGN IN, SIGN OUT, SIGN UP: /login, /logout, /register and /notAuthorized; after signing in or up, the catalog
    Route::auth(['home' => '/books', 'signInAfterRegister' => true]);

    // User Administration Routes - Uncomment only if AUTHENTICATION activated above
	// Route::get('/admin', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@editUserForm');
	// Route::put('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@updateUser');
	// Route::delete('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@deleteUser');

    // GPT AI controller
    // Route::get('/ai', 'Caligrafy\\Controllers\\AIController');
    // Route::post('/ai', 'Caligrafy\\Controllers\\AIController@query');

    // ROUTING TO JAVASCRIPT APPS
    // Make sure you create a JS app or a Vue app in Caligrafer first
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController'); // you can specify any path as long as it ends with appName

    
    // BOT PAGE ROUTES
    Route::get('/bot/{appName}', 'Caligrafy\\Controllers\\ClientController');  // A botSkillId needs to be provided in the URL. For Example: ?botSkillId=62196ce3-0b22-4aea-b2f2-d39730c03797
    
    // BOT DATA ROUTES
    // routes need to start with reserved path __bots__ 
    Route::post('/__bots__/{appName}/communicate', 'Caligrafy\\Controllers\\WatsonController@communicate');
    Route::post('/__bots__/{appName}/{appId}', 'Caligrafy\\Controllers\\WatsonController@connect');
    Route::delete('/__bots__/{appName}', 'Caligrafy\\Controllers\\WatsonController@end');   

    /* ML Routes
     *
     * If you don't specify an up, it will go to the face, body detector.
     * Specify an app as a URI Parameter ?app=<name of app>
     * 
     * There are 3 possible apps: featureextractor, neuralnetwork and posedetector
    */
    Route::get('/ml/{appName}', 'Caligrafy\\Controllers\\ClientController'); 
    

    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; each form calls one capability
    Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'with' => ['install' => true]]);   // install: offer to add it to the home screen
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file
    Route::page('/people', 'Bookshelf/people', ['level' => 1, 'data' => ['people' => 'list_users']]);   // administrators only

    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');

    // LOANS: one more feature, every experience: a page and its forms, REST, the JS clients and AI agents
    Route::page('/loans', 'Loans/index', ['signedIn' => true, 'data' => ['loans' => 'list_loans', 'books' => 'list_books']]);
    Route::form('/loans', 'lend_book', ['success' => '/loans', 'flash' => 'Lent to {borrower} until {due_on}']);
    Route::form('/loans/{book_id}/return', 'return_book', ['success' => '/loans', 'flash' => 'Returned by {borrower}']);
    Route::form('/loans/remind', 'send_reminders', ['success' => '/loans', 'flash' => 'The reminders are on their way (job {job})']);   // administrators
    Route::capability('GET', '/api/loans', 'list_loans');
    Route::capability('POST', '/api/loans', 'lend_book');
    Route::capability('POST', '/api/books/{book_id}/return', 'return_book');

    // APPENDIX A: routes and controllers written by hand
    Route::get('/about', function () {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function () {
        $name = request()->fetch('name');        // the {name} part of the address
        echo 'Hello ' . sanitize($name) . '!';   // sanitize: never print what a visitor typed as is
    });
    Route::get('/feed', 'Bookshelf\\FeedController@books');        // custom HTTP: a controller that calls a capability
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@show');   // under the hood: the classic way
    Route::get('/v1/authors', 'Bookshelf\\AuthorController@index');

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

Try the closures:
```bash
curl http://localhost:8080/about
curl http://localhost:8080/hello/Ursula
curl http://localhost:8080/hello/%3Cscript%3E
```
Expected:
```text
Bookshelf: a small catalog of the books we love.
Hello Ursula!
Hello &lt;script&gt;!
```
`sanitize()` turns `<`, `>`, `&` and quotes into HTML entities: an address with a `<script>` shows text instead of
running it. Always escape what comes from an address or a form before you print it (Pug does it for you).

### Step 2 - A controller that calls a capability

**File `application/controllers/Bookshelf/FeedController.php`** (an RSS feed from list_books):

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

// The right use of a controller: HTTP that no declarative route gives (an RSS feed is XML, not a page, JSON or CSV).
// It holds no rule and reads no model: it calls a capability, with the visitor's rights, and only shapes the answer
class FeedController extends Controller
{
    // GET /feed: the first page of the catalog, for feed readers
    public function books()
    {
        $page = $this->call('list_books', array('page' => 1));   // the same capability as the pages, REST and agents
        $home = session('home');
        $items = '';
        foreach ($page['data'] as $book) {
            $items .= '<item><title>' . htmlspecialchars($book['title'], ENT_XML1) . '</title>'
                . '<link>' . $home . 'books/' . $book['id'] . '</link>'
                . '<description>' . htmlspecialchars($book['author']['name'] . ', ' . $book['published_year'], ENT_XML1) . '</description></item>';
        }
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel><title>Bookshelf</title><link>' . $home . 'books</link>'
            . '<description>The books we love</description>' . $items . '</channel></rss>';
    }
}
```

```bash
curl -s -i http://localhost:8080/feed | grep -i "HTTP/\|content-type"
curl -s http://localhost:8080/feed | head -c 300
```
Expected:
```text
HTTP/1.1 200 OK
Content-Type: application/rss+xml; charset=utf-8
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Bookshelf</title><link>http://localhost:8080/books</link><description>The books we love</description><item><title>A Wizard of Earthsea</title><link>http://localhost:8080/books/1</link>
```
The feed has the books of `list_books`, sorted and paged by its rules; if `list_books` were closed to visitors, the
feed would be too. Add `http://localhost:8080/feed` to a feed reader: each book is an item with its link.

### Step 3 - Under the hood: the classic controller

Before capabilities, a controller read the tables itself. This one shows an author with their books, and a JSON list:

**File `application/controllers/Bookshelf/AuthorController.php`** (the classic way: no capability):

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

// Under the hood, the classic way: the controller reads the tables itself and renders a view. No capability is
// involved, so only this page has these features: REST clients, the JS clients and AI agents do not see them
class AuthorController extends Controller
{
    // GET /authors/{id}: an author and their books
    public function show()
    {
        $this->associate('Author', 'authors');   // the model and the table the next calls work on
        $author = $this->find();                 // the row whose id is {id} of the route, or nothing
        if (!$author) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no such author.'));
        }
        $this->associate('Book', 'books');
        $books = $this->all(array('condition' => 'WHERE author_id = ?', 'bindings' => array($author->id), 'order' => 'ORDER BY title'));
        return view('Bookshelf/author', array('author' => $author, 'books' => $books));
    }

    // GET /v1/authors: JSON for programs, the classic api() output (Accept: application/json and a token with *)
    public function index()
    {
        $this->associate('Author', 'authors');
        return api(array('authors' => $this->all(array('order' => 'ORDER BY name'))));
    }
}
```

**File `application/views/Bookshelf/author.pug`** (`$author` is a model and `$books` a list of models: their columns are read with `->`):

```pug
extends ../default/master/site

//- An author and their books, rendered by Bookshelf\AuthorController (the classic way)

block title
    = $author->name . ' · Bookshelf'

block content
    section.cq-hero.cq-hero--page
        h1.cq-display= $author->name
        p.cq-lead= $author->bio

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            ul.cq-rows
                each $book in $books
                    li.cq-row
                        .cq-row-main
                            p.cq-row-title
                                a(href="{$home}books/{$book->id}")= $book->title
                            p.cq-row-meta= $book->published_year
            p.cq-auth-note
                a(href="{$home}books") All books
```

Open http://localhost:8080/authors/2: "Octavia E. Butler", her bio, and her books (Bloodchild, Kindred, Parable of the
Sower) linked to their pages. Then:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/authors/99
```
Expected: `404`, and in a browser the Not found page with `There is no such author.`

The JSON route, as a program:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/v1/authors
curl -s -H "Accept: application/json" -H "Authorization: Bearer $WRITER" -w " [%{http_code}]\n" http://localhost:8080/v1/authors
```
Expected (`$WRITER` is the "Claude Code" token of lesson 11, `books:read books:write`):
```text
{"error":{"code":1004,"message":"Access Denied: send the page's CSRF token in an X-CSRF-Token header (pages, with the session cookie) or Authorization: Bearer <personal access token> (programs and agents)"}} [401]
{"error":{"code":1004,"message":"Access Denied: this personal access token is unknown, expired or revoked, or does not grant everything (*) as controller routes need: call capabilities with scoped tokens"}} [401]
```
A controller route accepts only a token with `*`. Create one, call, and revoke it at once (a `*` token may call
everything):
```bash
docker compose exec dev-box php caligrafer.php token create "old style client" --scopes "*"
```
`read -rs STAR`, paste it, then:
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $STAR" http://localhost:8080/v1/authors | head -c 120
docker compose exec dev-box php caligrafer.php token revoke 7
```
Expected: `{"authors":[{"table":"authors","id":3,"name":"Italo Calvino",...` (sorted by name), then
` Token #7 revoked` (use the number `token create` printed). Compare with `GET /api/authors` (lesson 07): no `data`
envelope, and each author has a `table` field: the classic `api()` output of a model.

### Step 4 - See the rules for yourself

- `curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/does/not/exist` prints `404`: no route matched.
- `curl -s -X DELETE -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/about` prints
  `{"error":{"code":1000,"message":"Invalid Http Request Method: this address answers GET"}} [405]`.
- Move `Route::run();` above the `// APPENDIX A` block, save and open http://localhost:8080/about:
  `[ERROR 1005] Page Not Found` (rule 1). Move it back above the closing `}` of the `try`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 1005] Page Not Found` instead of your page | A typo in the path, another number of segments, or the route is below `Route::run();` | Compare with step 1 |
> | `[ERROR 1001] Invalid Controller or Action` (404) | The route names a class or action that does not exist: `Bookshelf\\` missing, a typo, or no `namespace` line | Compare the route and the file |
> | `Cannot redeclare class Bookshelf\AuthorController` | A copy of the file is elsewhere in `application/` | Delete the copy |
> | Apache's own `404 Not Found` for `/hello/a%2Fb` | Apache refuses an encoded `/` in a path | Do not put `/` in a route variable |
> | The feed is an HTML error page | `list_books` refused (a rule), or a PHP error | Call `list_books` with `curl` (lesson 04) and read the answer |

---

## Check

- [ ] `web.php`, the two controllers and `author.pug` are identical to the File blocks.
- [ ] `/about`, `/hello/Ursula` and `/hello/%3Cscript%3E` answer as in step 1.
- [ ] `/feed` is `application/rss+xml` and lists the books of `list_books`.
- [ ] `/authors/99` answers 404; `/v1/authors` answers 401 to the `books:*` token and the JSON to a `*` token, which
      you revoked.

## With Claude

```text
In application/config/routing/web.php, in a block "// APPENDIX A: routes and controllers written by hand" above
"// MUST NOT BE REMOVED": GET /about (a closure echoing "Bookshelf: a small catalog of the books we love."), GET
/hello/{name} (request()->fetch('name') through sanitize()), GET /feed -> Bookshelf\FeedController@books. The feed
controller (namespace Bookshelf, extends Caligrafy\Controller) calls $this->call('list_books', ['page' => 1]) and
echoes an RSS 2.0 document (Content-Type application/rss+xml; charset=utf-8) with one item per book: title, link
<home>books/<id>, description "<author name>, <year>", every text escaped with htmlspecialchars(..., ENT_XML1). It
reads no model and holds no rule.
```

**Check** before you accept:
- [ ] `FeedController` calls `$this->call()` and never a model or `Database`.
- [ ] `Route::run();` is still the last line inside `try`; the name of `/hello/{name}` goes through `sanitize()`.
- [ ] The checks of steps 1 and 2 give the same answers.

If Claude writes the feed with `Book::...` in the controller, ask it to call `list_books`: the rules and the rights
stay in one place.

---

## Compare with capabilities

| | The classic `AuthorController` | A capability |
|---|---|---|
| The author page | yes | yes: one `Route::page` line with `data` (lesson 06), no controller |
| REST | only `/v1/authors`, with a `*` token, no `data` envelope | any route, scoped tokens, `{"data"}` / `{"error"}` |
| The JS clients | no function for it | yes, after `client` |
| AI agents (MCP) | no tool | a tool with its description and schema |
| Rules, permissions, audit | written again in each controller | written once; refusals and changes audited |

`FeedController` sits between the two: its HTTP is its own, its data and rights come from `list_books`.

## What the one-line routes do for you

| One line of `web.php` | What a controller would do by hand |
|---|---|
| `Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']])` | calls `get_book` as the visitor with `{id}`, renders the view with `$book`, `$authorized` and `$user`; a refusal renders the `notFound` view with 404, or sends a visitor to `/login`; `signedIn` and `level` guard the page |
| `Route::form('/books/{id}', 'update_book', ['method' => 'PUT', ...])` | checks the CSRF token, calls `update_book` with `{id}` and the fields; on success a flash message and a redirect; on a refusal the messages and the typed values (never a password) for `errors()` and `old()`, back to the form; a visitor to `/login`; JSON for a JSON caller |
| `Route::auth([...])` | the sign-in, sign-out and sign-up pages, a new session id at sign-in, 5 failed sign-ins per username in 15 minutes |
| `Route::download('/authors.csv', 'list_authors')` | calls a read-only capability with the query string, writes the CSV with its header row and quoting, and the headers that make the browser save it |

Appendix B writes a form by hand, to see what `Route::form` replaces.

---

## Checkpoint

```bash
git add -A
git commit -m "Appendix A: routes and controllers by hand"
git tag appendix-a
```
Reference: [`checkpoints/appendix-a`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/appendix-a).
Learn more: [2. Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing) and
[8. Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers).

## Quiz

1. You add `Route::get('/authors/top', ...)` below `/authors/{id}`. What does `/authors/top` show, and why?
2. Why does `/v1/authors` answer 401 to the "Claude Code" token, while `/api/authors` answers 200 to anyone?
3. When is a controller the right choice, and what does it call?

<details><summary>Answers</summary>

1. The Not found page of an author (404): `/authors/{id}` comes first and matches with `{id}` = `top`. Declare
   `/authors/top` above it.
2. `/v1/authors` is a controller route: an API call needs a token with `*`. `/api/authors` is the capability
   `list_authors`, which is public.
3. For HTTP that `Route::page`, `Route::form`, `Route::auth`, `Route::download`, `Route::webhook`, `Route::redirect`
   and `Route::capability` do not give, such as an RSS feed. It calls capabilities with `$this->call()` and holds no
   rule.
</details>

---
Previous: [Lesson 15 - Reminders](15-Reminders) · Next: [Appendix B - Classic forms](B-Classic-Forms)
