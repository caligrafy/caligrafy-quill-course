# Lesson 07 - The REST API

**In this lesson** Bookshelf gets a REST API for other programs: a mobile app, a script, another website. Every
capability is already served by one line quickstart wrote, `Route::capabilities`; you add six lines of
`Route::capability` that give the books and the authors their own addresses (`/api/books/3`). Then you give a partner
program its own **token** with a limited **scope**, and see who called what in the audit log. No controller, no rule,
no SQL is written again.

**Before you start:** lesson 06 is done (tag `lesson-06`): http://localhost:8080/books shows the catalog, and
`docker compose ps` shows the 3 containers running. Keep the token of lesson 02 at hand.

**Time:** 45 minutes.

---

## New concepts

**API and REST.** An **API** is a set of addresses made for programs instead of people: they answer JSON, not HTML. In
a **REST** API, the address names a thing (`/api/books/3`: book 3) and the **HTTP method** says what to do with it.

**Two ways to serve capabilities over HTTP.**

| Line in `web.php` | Addresses | Use it for |
|---|---|---|
| `Route::capabilities('/capabilities')` (quickstart wrote it) | `GET /capabilities`: what the caller may call, with the input schemas; `POST /capabilities/<name>`: call any capability by its name | the JS client of the Vue apps (lesson 09) and programs that call capabilities by name, as you did with `curl` in lessons 04 and 05 |
| `Route::capability('GET', '/api/books/{id}', 'get_book')` | one method and one address for one capability | a REST API whose addresses name things, with the usual statuses |

Bookshelf's REST API:

| Method + address | Capability | Action |
|---|---|---|
| `GET /api/books` | `list_books` | list the books, 20 per page (`?author_id=3` for one author, `?page=2` for the next page) |
| `GET /api/books/{id}` | `get_book` | read one book with its author |
| `POST /api/books` | `create_book` | add a book |
| `PUT /api/books/{id}` | `update_book` | change a book |
| `DELETE /api/books/{id}` | `delete_book` | delete a book |
| `GET /api/authors` | `list_authors` | list the authors |

**Inputs.** A capability's inputs come from three places, merged: the **body** (JSON with
`Content-Type: application/json`, or form fields), the **query string** (`?author_id=3`), and the **route variables**
(`{id}`). The route variables win over the two others: `/api/books/3?id=9` is book 3, so nothing in a query string can
point an address at another row. The rules, the checks of `handle()` and the 404s all come from the capability, exactly
as for the pages and the agent.

**The answers.** Success: `{"data": ...}`. Refusal: `{"error": {"code", "message", "fields"}}` (`fields` only when
inputs are wrong). A list is a **page**, always the same shape:
`{"data": {"data": [...], "total": 6, "page": 1, "per_page": 20, "pages": 1}}`. A program reads `pages` to know how many
times to ask, with `?page=2`, `?page=3`...

| Status | When, in Bookshelf |
|---|---|
| 200 | a read, an update, a delete that worked |
| 201 | `POST /api/books` added a book |
| 401 | the capability is not public and the caller sent no token and is not signed in |
| 403 | the token does not have the scope (or the permission level) |
| 404 | `ServiceError::notFound`: no such book; or no route for the address |
| 405 | the address exists, but not with this method |
| 422 | wrong inputs, with `fields` |

Unlike `POST /capabilities/<name>` (201 for every successful change), REST routes answer 201 only when a POST creates,
and 200 for PUT and DELETE.

**Tokens and scopes.** A program proves who it is with a **personal access token** in the header
`Authorization: Bearer <token>`. Each token has **scopes**: `books:read` lets it call the capabilities whose scope is
`books:read`, `*` lets it call everything. A token is shown once, stored only as a hash, and can be revoked. A `public`
capability does not look at scopes: everyone may call it. Until lesson 11 every Bookshelf capability is public, so the
API works without a token; lesson 11 closes the writes, and then the scopes decide.

**`Accept: application/json`.** Programs send it: it tells Caligrafy that this is an API call (JSON answers, errors in
JSON, no CSRF form token needed). That goes for the capability routes of this lesson. The addresses of `Route::action`
(lesson 16) are for pages: a JSON call to one needs the page's token as well, in the header `X-CSRF-Token`.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - Six routes

In `application/config/routing/web.php`, add the `// BOOKSHELF API` block below the `// BOOKSHELF PAGES` block, above
`// MUST NOT BE REMOVED`. The file is then:

**File `application/config/routing/web.php`** (the six lines of the REST API added):

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
    // Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::redirect('/', '/books');   // the home of Bookshelf is its catalog
    Route::redirect('/catalog', '/books', 301, ['keepQuery' => true]);   // the catalog's old address: the filter of its address goes along
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // Auth Routes - Uncomment only if AUTHENTICATION activated above
    // Route::get('/home', 'Caligrafy\\Controllers\\AuthController@home');
    // Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    // Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
	// Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');
    // Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    // Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    // Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');

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
    Route::page('/books', 'Bookshelf/books', ['query' => ['author_id'], 'data' => ['books' => 'list_books', 'authors' => 'list_authors']]);
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file

    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

That is the whole API. `/api/books/{id}` and `/books/{id}` never clash: their first parts differ (`api`, `books`). And
`GET /api/books/{id}` and `PUT /api/books/{id}` are the same address with two methods: the method chooses the
capability.

### Step 2 - Read, page by page

In every command, `-H "Accept: application/json"` asks for JSON and `-w " [%{http_code}]\n"` prints the status. Put an
address with a `?` in double quotes: zsh gives `?` a meaning of its own.

The books of Italo Calvino (author 3), through the query string:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" "http://localhost:8080/api/books?author_id=3"
```
Expected (your dates differ):
```text
{"data":{"data":[{"id":5,"title":"Invisible Cities","published_year":1972,"summary":"Marco Polo describes imaginary cities to the emperor Kublai Khan.","isbn":null,"author_id":3,"created_at":"2026-11-07 23:24:20","modified_at":"2026-11-07 23:24:20","author":{"id":3,"name":"Italo Calvino","bio":"Italian writer of playful, inventive novels and short stories.","created_at":"2026-11-07 23:24:20","modified_at":"2026-11-07 23:24:20"}}],"total":1,"page":1,"per_page":20,"pages":1}} [200]
```
The second page of the whole catalog, and a page that is not a number:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" "http://localhost:8080/api/books?page=2"
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" "http://localhost:8080/api/books?page=abc"
```
Expected:
```text
{"data":{"data":[],"total":6,"page":2,"per_page":20,"pages":1}} [200]
{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"page":"The Page field must be a number without a decimal"}}} [422]
```
6 books in all, 1 page of 20: page 2 is empty, not an error. `abc` is refused by the rule `integer` of `list_books`.

One book that does not exist, through the route variable `{id}`:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/books/42
```
Expected: `{"error":{"code":"not_found","message":"There is no book #42"}} [404]`: the `ServiceError::notFound` of
`get_book`, the same that the book page shows as its Not found page. And `http://localhost:8080/api/books/1` answers
the book with its author and `[200]`.

The route variable wins over the query string:
```bash
curl -s -H "Accept: application/json" "http://localhost:8080/api/books/3?id=5" | cut -c1-40
```
Expected: `{"data":{"id":3,"title":"Kindred","publi`: book 3, the one the address names.

`-i` prints the headers of the answer; `head -1` keeps the status line:
```bash
curl -s -i -H "Accept: application/json" http://localhost:8080/api/authors | head -1
```
Expected: `HTTP/1.1 200 OK`.

### Step 3 - Write

Add a book with JSON. The title has an apostrophe, so the JSON is written between double quotes, with `\"` for each
quote inside it:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"title\": \"If on a Winter's Night a Traveler\", \"published_year\": 1979, \"author_id\": 3}" -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected:
```text
{"data":{"id":9,"title":"If on a Winter's Night a Traveler","published_year":1979,"summary":null,"isbn":null,"author_id":3,"created_at":"2026-11-07 23:25:29","modified_at":"2026-11-07 23:25:29","author":{"id":3,"name":"Italo Calvino","bio":"Italian writer of playful, inventive novels and short stories.","created_at":"2026-11-07 23:24:20","modified_at":"2026-11-07 23:24:20"}}} [201]
```
The id is the next free number: 9 if you followed every lesson. Use **your** id below.

Programs can also send form fields, like an HTML form (no `Content-Type: application/json`):
```bash
curl -s -X POST -H "Accept: application/json" -d 'title=Bloodchild&published_year=1995&author_id=2' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: the new book, `{"data":{"id":10,"title":"Bloodchild",...}} [201]`.

Change only the year of book 9:
```bash
curl -s -X PUT -H "Accept: application/json" -H "Content-Type: application/json" -d '{"published_year": 1980}' -w " [%{http_code}]\n" http://localhost:8080/api/books/9
```
Expected: the book with `"published_year":1980` and `[200]`. The `id` came from the address, the year from the body.

A book without a year:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "No year", "author_id": 3}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected:
`{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"published_year":"The Published Year field is required"}}} [422]`.
Submit the catalog form with a title and no year: the page shows the same message.

Delete Bloodchild (id 10, or yours):
```bash
curl -s -X DELETE -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/books/10
```
Expected: `{"data":{"deleted":10}} [200]`.

Open http://localhost:8080/books: *If on a Winter's Night a Traveler* is there, 1980, by Italo Calvino; Bloodchild is
gone. You changed the catalog through REST; the page reads it through the same capabilities.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 3000] Security Error - Cross Site Forgery` (403) on POST, PUT or DELETE | `-H "Accept: application/json"` is missing, so Caligrafy treats the call as a browser form without its CSRF token | Add the header |
> | `{"error":{"code":1005,"message":"Page Not Found"}} [404]` | The address matches no route (a typo, or step 1 is missing) | Check the address and the lines of step 1 |
> | `{"error":{"code":1000,"message":"Invalid Http Request Method: this address answers GET, POST"}} [405]` | The address exists, but not with this method (a `PUT` on `/api/books`) | Check the method against the table above |
> | `zsh: no matches found: http://localhost:8080/api/books?author_id=3` | zsh reads the `?` | Put the address in double quotes |
> | Every field reported missing on a JSON POST | `-H "Content-Type: application/json"` is missing, so the JSON is not read | Add the header |
> | `There is no book #9` on PUT | Your book has another id | Use the id the POST returned |

### Step 4 - A token for a partner, with one scope

A partner site wants to show your catalog. Give it its own token, limited to reading books:
```bash
docker compose exec dev-box php caligrafer.php token create "reading partner" --scopes books:read
```
Expected:
```text
 Token #2 "reading partner" created with books:read

 cq_...

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
Put it in a variable without showing it (`read -rs PARTNER`, paste, Enter), then call the API with it:
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $PARTNER" -w " [%{http_code}]\n" http://localhost:8080/api/books/3
```
Expected: Kindred with its author, and `[200]`.

Now a capability that is **not** public: `list_users`, the framework's list of accounts (scope `users:read`, for
administrators). Without a token, then with the partner's token:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/list_users
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $PARTNER" -d '{}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/list_users
```
Expected:
```text
{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}} [401]
{"error":{"code":"forbidden","message":"This needs the scope users:read and permission level 1"}} [403]
```
401: nobody is calling. 403: someone is, but the token's scope does not allow it.

The Bookshelf capabilities are still public, so the scope does not limit them yet: the partner's token reaches
`delete_book` too (here on a book that does not exist):
```bash
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $PARTNER" -w " [%{http_code}]\n" http://localhost:8080/api/books/42
```
Expected: `{"error":{"code":"not_found","message":"There is no book #42"}} [404]`: not a 403. Lesson 11 removes
`public: true` from the capabilities that write; from then on this token gets 403 on them, and a visitor 401.

### Step 5 - Who called what

The tokens, without their secrets:
```bash
docker compose exec dev-box php caligrafer.php token list
```
Expected (your times differ):
```text
 #2    active   reading partner                books:read  last used 2026-11-07 23:25:30
 #1    active   my agent                       *  last used 2026-11-07 23:24:52
```
The audit log records each token by its number:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 3
```
Expected:
```text
 2026-11-07 23:25:30  rest  token  2            delete_book                  not_found  There is no book #42
 2026-11-07 23:25:30  rest  token  2            list_users                   forbidden  This needs the scope users:read and permission level 1
 2026-11-07 23:25:30  rest  guest  -            list_users                   unauthenticated  Sign in or send a personal access token
```
When a partner should stop, revoke its token: `docker compose exec dev-box php caligrafer.php token revoke 2` prints
` Token #2 revoked`. Keep it for now.

---

## Check

- [ ] `web.php` is identical to the File block: six `Route::capability` lines in a `// BOOKSHELF API` block.
- [ ] No new file in `application/controllers/` or `application/services/`; no capability was changed.
- [ ] Reads answer a page `{"data":{"data":[...],"total":...,"page":...,"per_page":20,"pages":...}}`; `?page=abc` is 422.
- [ ] `/api/books/3?id=5` answers book 3.
- [ ] The statuses: 200 for reads, PUT and DELETE; 201 for each POST that creates; 404 for book 42; 422 for the book
      without a year.
- [ ] `list_users` answers 401 without a token and 403 with the `books:read` token.
- [ ] `token list` shows `#2 ... books:read`, and no command printed or saved a token in the project.

## With Claude

```text
Add a REST API to Bookshelf on the existing capabilities, with no new controller or service. In
application/config/routing/web.php, add a "// BOOKSHELF API: the same capabilities, for other applications (JSON)"
block below the BOOKSHELF PAGES block, with Route::capability for: GET /api/books list_books, GET /api/books/{id}
get_book, POST /api/books create_book, PUT /api/books/{id} update_book, DELETE /api/books/{id} delete_book,
GET /api/authors list_authors. Then test each route with curl (-H "Accept: application/json" -w " [%{http_code}]\n")
and show me each command and its status. Do not create tokens.
```
Create the partner's token yourself (step 4): a token must never go through the chat.

**Check** before you accept:
- [ ] `web.php` has exactly six new `Route::capability` lines, with the methods, addresses and names of the table.
- [ ] No controller with `api()`: it would copy the rules and lose the capability's checks and audit.
- [ ] The statuses are those of steps 2 and 3.
- [ ] No token was written into a file of the project.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 07: the REST API"
git tag lesson-07
```
Reference: [`checkpoints/lesson-07`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-07).

## Exercise

A partner wants the books of one author at a nicer address: `GET /api/authors/{author_id}/books`. Add it, then try it
with author 2. How many lines of PHP did you write, and which rules apply?

<details><summary>Show the solution</summary>

One line, in the `// BOOKSHELF API` block:
```php
    Route::capability('GET', '/api/authors/{author_id}/books', 'list_books');
```
The route variable `{author_id}` becomes the input `author_id` of `list_books`:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/authors/2/books
```
answers the page with Kindred and Parable of the Sower, `"total":2`, and `[200]`. The rules are those of `list_books`:
`/api/authors/abc/books` is refused with 422 and
`"fields":{"author_id":"The Author Id field must be a number without a decimal"}`. The checkpoint does not have this
route: remove it before you commit, or keep it in your own project.
</details>

---
Previous: [Lesson 06 - Web pages](06-Web-Pages) · Next: [Lesson 08 - AI agents](08-AI-Agents)
