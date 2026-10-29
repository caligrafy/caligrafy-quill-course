# Lesson 09 - The REST API

**In this lesson** Bookshelf gets a JSON API under `/api/...`, for programs instead of browsers: the Vue apps of
part 2 will use it. You call it with `curl`, with the API key, and you see every HTTP status code it can answer:
200, 201, 401, 404 and 422.

**Before you start:** lesson 08 is done (tag `lesson-08`), and you have the account `reader1` /
`bookshelf-2026`.

---

## New concepts

**API, REST, JSON.** An API is a set of URLs made for programs. In a **REST** API, the URL names a thing
(`/api/books/3`) and the HTTP method says what to do with it:

| Method + URL | Action | Success answer |
|---|---|---|
| `GET /api/books` | list the books | 200 `{"books": [...]}` |
| `GET /api/books/3` | read book 3 | 200 `{"book": {...}, "author": {...}}` |
| `GET /api/authors` | list the authors | 200 `{"authors": [...]}` |
| `POST /api/books` | create a book (JSON body) | **201 Created** `{"book": {...}}` |
| `PUT /api/books/3` | change book 3 | 200 `{"book": {...}}` |
| `DELETE /api/books/3` | delete book 3 | 200 `{"deleted": true}` |

And the error answers: **401** (no valid API key, or not signed in for a change), **404** (no such book),
**422** `{"errors": {"title": "..."}}` (invalid data).

**How Caligrafy recognizes an API call.** A request is an API call when its header `Accept` contains
`application/json` (and not `text/html`). Then:
1. for a controller route, Caligrafy **requires the API key**: the header `Authorization: Bearer <API_KEY>`,
   where `<API_KEY>` is the `API_KEY` of `.env`. Without it: `401` and `{"error":{"code":1004,"message":"Access Denied"}}`.
   `Auth::activateAPI();` in `web.php` (there since lesson 02) is what allows API calls at all;
2. the CSRF check of lesson 07 is skipped (programs have no form token; the API key protects them instead);
3. `view()` answers JSON instead of HTML. Try it: `/books` returns the HTML page to a browser and the JSON of the
   same data to an API call (step 5).

**API key ≠ user.** The API key says "this program may call the API". It is not secret enough to protect changes:
lesson 10 puts it in a web page, where anyone can read it. So the actions that **change** books also require a
signed-in user (`authorized()`), exactly like the forms. A program signs in like a browser: it posts the login form
and keeps the session cookie (step 6).

**Why a separate API controller?** The forms of lesson 07 answer with pages and redirects; the API answers with
JSON and status codes. Two controllers keep each one simple. Both use `BookForm`, so a book is valid or invalid in
the same way everywhere. The API controller lives in the sub-namespace `Bookshelf\Api` and the folder
`controllers/Bookshelf/Api/`.

---

## Manual track

### Step 1 - The API controller

Create the folder `application/controllers/Bookshelf/Api` and:

**File `application/controllers/Bookshelf/Api/BookApiController.php`**:

```php
<?php

// A sub-namespace for the API: the file lives in controllers/Bookshelf/Api
namespace Bookshelf\Api;

use Caligrafy\Controller;
use Bookshelf\BookForm;

class BookApiController extends Controller {

    // GET /api/books -> 200 {"books": [...]}
    public function index()
    {
        $this->associate('Book', 'books');
        return api(array('books' => $this->all(array('order' => 'ORDER BY title'))));
    }

    // GET /api/books/{id} -> 200 {"book": {...}, "author": {...}} or 404
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            return $this->error(404, 'Book not found');
        }
        return api(array('book' => $book, 'author' => $book->author()));
    }

    // GET /api/authors -> 200 {"authors": [...]}
    public function authors()
    {
        $this->associate('Author', 'authors');
        return api(array('authors' => $this->all(array('order' => 'ORDER BY name'))));
    }

    // POST /api/books -> 201 {"book": {...}}, 401 when not signed in, 422 {"errors": {...}} when invalid
    public function create()
    {
        if (!authorized()) {
            return $this->error(401, 'Sign in first');
        }
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return api(array('errors' => $errors));
        }
        $this->associate('Book', 'books');
        $book = $this->save(BookForm::columns($data));
        http_response_code(201);
        return api(array('book' => $book));
    }

    // PUT /api/books/{id} -> 200 {"book": {...}}, 401, 404 or 422
    public function update()
    {
        if (!authorized()) {
            return $this->error(401, 'Sign in first');
        }
        $this->associate('Book', 'books');
        if (!$this->find()) {
            return $this->error(404, 'Book not found');
        }
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return api(array('errors' => $errors));
        }
        $this->associate('Book', 'books');
        return api(array('book' => $this->save(BookForm::columns($data))));
    }

    // DELETE /api/books/{id} -> 200 {"deleted": true}, 401 or 404
    public function remove()
    {
        if (!authorized()) {
            return $this->error(401, 'Sign in first');
        }
        $this->associate('Book', 'books');
        if (!$this->find()) {
            return $this->error(404, 'Book not found');
        }
        $this->delete();
        return api(array('deleted' => true));
    }

    // Same shape as the errors of the framework: {"error": {"code": ..., "message": "..."}}
    private function error($status, $message)
    {
        http_response_code($status);
        return api(array('error' => array('code' => $status, 'message' => $message)));
    }

    private function checkBook()
    {
        list($data, $errors) = BookForm::check($this->validator, $this->request->parameters());
        if (!isset($errors['author_id'])) {
            $this->associate('Author', 'authors');
            if (!$this->find($data['author_id'])) {
                $errors['author_id'] = 'Choose an author from the list.';
            }
        }
        return array($data, $errors);
    }
}
```

- `use Bookshelf\BookForm;`: this file is in `Bookshelf\Api`, so it must import `BookForm` from `Bookshelf`.
- `error()` sets the status code **before** `api()`, because `api()` ends the request.
- `create()` answers **201** with the new book; invalid data gives **422** with the messages of `BookForm`.

### Step 2 - The routes

**File `application/config/routing/web.php`** - replace its whole content with:

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

    // Client apps (lesson 10): public/<appName> is shown at /apps/<appName>
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');

    // MUST STAY LAST: finds the route that matches the request and runs it
    Route::run();
}
catch (Exception $e) {

    // Errors get their HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

`/api/books` and `/books/{id}` both have two segments, but their first segments differ (`api` / `books`), so they
never match each other.

### Step 3 - Put the API key in a shell variable

The key is in your `.env`. This command copies it into a variable of your terminal, **without printing it**:
```bash
API_KEY="$(grep '^API_KEY=' .env | cut -d= -f2-)"
```
Check it has a value (it prints the number of characters, not the key):
```bash
echo ${#API_KEY}
```
Expected: `112`. If it prints `0`, the line `API_KEY=` is missing from `.env` (lesson 01). The
variable lives only in this terminal window: run the command again in a new window.

### Step 4 - Read with the API

Without the key:
```bash
curl -i -H "Accept: application/json" http://localhost:8080/api/books
```
Expected: `HTTP/1.1 401 Unauthorized` and `{"error":{"code":1004,"message":"Access Denied"}}`.

With the key:
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" http://localhost:8080/api/books
```
Expected: `{"books":[{"table":"books","id":1,"title":"A Wizard of Earthsea",...}, ...]}` with the 5 books.

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" http://localhost:8080/api/books/999
```
Expected: `404`.

### Step 5 - The same URL, HTML or JSON

```bash
curl -s http://localhost:8080/books/3 | head -5
curl -s -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" http://localhost:8080/books/3
```
The first command prints the beginning of an HTML page (`<!DOCTYPE html>`...). The second prints the JSON of the
data the view would show: `{"book":{...},"author":{...},"authorized":false}`. That is `view()` answering an API call.

### Step 6 - Change data: sign in first

Without a session, a change is refused:
```bash
curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
     -H "Content-Type: application/json" -d '{"title":"The Dispossessed"}' \
     -w '\n%{http_code}\n' http://localhost:8080/api/books
```
Expected: `{"error":{"code":401,"message":"Sign in first"}}` then `401`.

Sign in like a browser. `-c cookies.txt` saves the session cookie in a file, `-b cookies.txt` sends it back. The
first command reads the CSRF token from the login page; the second posts the login form with it:
```bash
TOKEN="$(curl -s -c cookies.txt http://localhost:8080/login | grep -o "name='_token' value='[^']*'" | cut -d"'" -f4)"
curl -s -o /dev/null -w '%{http_code}\n' -b cookies.txt -c cookies.txt \
     --data-urlencode "_token=$TOKEN" --data-urlencode "username=reader1" --data-urlencode "passcode=bookshelf-2026" \
     http://localhost:8080/login
```
Expected: `302` (the login redirects to `/home`: you are signed in).

Now create a book with invalid data:
```bash
curl -s -X POST -b cookies.txt -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
     -H "Content-Type: application/json" -d '{"title":"The Dispossessed","author_id":1,"published_year":"soon"}' \
     -w '\n%{http_code}\n' http://localhost:8080/api/books
```
Expected: `{"errors":{"published_year":"The Published Year field must be a number without a decimal"}}` then `422`.

And with valid data:
```bash
curl -s -X POST -b cookies.txt -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
     -H "Content-Type: application/json" -d '{"title":"The Dispossessed","author_id":1,"published_year":1974,"summary":"Two worlds, one wall."}' \
     -w '\n%{http_code}\n' http://localhost:8080/api/books
```
Expected: `{"book":{"table":"books","id":8,"title":"The Dispossessed",...}}` then `201`. The id is the next number
MySQL has not used yet: 8 if you followed every step so far (6 was used up in lesson 04, 7 by the book of lesson 07).
Use the id **you** got in the next commands; here it is 8:
```bash
curl -s -X PUT -b cookies.txt -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
     -H "Content-Type: application/json" -d '{"title":"The Dispossessed","author_id":1,"published_year":1974,"summary":"An ambiguous utopia."}' \
     -w '\n%{http_code}\n' http://localhost:8080/api/books/8
curl -s -X DELETE -b cookies.txt -H "Accept: application/json" -H "Authorization: Bearer $API_KEY" \
     -w '\n%{http_code}\n' http://localhost:8080/api/books/8
```
Expected: the updated book and `200`, then `{"deleted":true}` and `200`. Finally delete the cookie file:
```bash
rm cookies.txt
```

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `401` `Access Denied` with the key | `$API_KEY` is empty (new terminal window), or `.env` was changed after the key was made | Step 3 again; `echo ${#API_KEY}` must not print 0 |
> | `403` `[ERROR 3000] Security Error` on POST | The `Accept: application/json` header is missing, so Caligrafy treats it as a browser form without token | Add `-H "Accept: application/json"` |
> | `401` `Sign in first` after the login | The login failed (`200` instead of `302`: wrong password, or the token was empty), or you forgot `-b cookies.txt` | Run the two login commands again; check `echo ${#TOKEN}` is not 0 |
> | An HTML page instead of JSON | The route did not match, or the `Accept` header is missing | Check the path and the headers |
> | `{"errors":{"author_id":"Choose an author from the list."}}` | The `author_id` does not exist | Use 1, 2 or 3 |
> | `{"error":{"code":404,"message":"Book not found"}}` on PUT or DELETE | The id does not exist (already deleted, or another number) | Use the id that the POST returned |

---

## With Claude

The plugin's `/caligrafy-quill:new-resource` command knows the API conventions (status codes, `api()`, the
`check()` pitfall), but it scaffolds a new table. For an API on existing tables, ask directly:

```
Add a JSON REST API to Bookshelf in a new controller Bookshelf\Api\BookApiController
(application/controllers/Bookshelf/Api/BookApiController.php): GET /api/books, GET /api/books/{id}
(book + author), GET /api/authors, POST /api/books (201), PUT /api/books/{id}, DELETE /api/books/{id}
({"deleted": true}). Validate with Bookshelf\BookForm::check and check that the author exists; invalid data
gives 422 {"errors": {...}}; missing book gives 404 and every error uses the shape
{"error": {"code": <status>, "message": "..."}}. POST, PUT and DELETE answer 401 when authorized() is false.
Set the status with http_response_code() before api(). Add the routes before Route::run() and keep
Auth::activateAPI(). Then show me curl commands to test it that read API_KEY from .env without printing it.
```

**What Claude should create:** the controller of step 1, the routes of step 2, and curl commands like those of
steps 3 to 6.

**Check** before you accept:
- [ ] Every status code is set **before** `api()`.
- [ ] POST, PUT and DELETE check `authorized()`; reads only need the API key.
- [ ] `Auth::activateAPI();` is still in `web.php`.
- [ ] Claude did not print the API key (no `cat .env`, no `echo $API_KEY`). If it did, treat the key as public:
      see lesson 13 for how to replace it.
- [ ] The curl commands of steps 4 and 6 give the expected codes.

**If Claude's result differs:** Claude may add the JSON answers to the existing `BookController` with
`if ($this->request->wantsJson())` instead of a new controller. That works too (the same URL then serves forms and
JSON), but lessons 10 to 12 call `/api/...`: ask for the separate controller. If Claude's `curl` commands send
JSON without `-H "Accept: application/json"`, they fail with 403: add the header.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 09: REST API"
git tag lesson-09
```
Make sure `cookies.txt` is not in the commit (`git show --stat HEAD`). Reference:
[`checkpoints/lesson-09`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-09).

## Quiz

1. You call `GET /api/books` with the API key but without `-H "Accept: application/json"`. What do you get?
2. Why do `POST /api/books` requests not need the CSRF token?
3. Why do changes need a signed-in user if the API key already protects the API?

<details><summary>Show the solution</summary>

1. The request is not an API call, so the API key is not checked and `api()` still answers JSON (api() always
   does): `200` with the books. The `Accept` header matters for the key check, for CSRF, and for `view()`.
2. Caligrafy skips the CSRF check for API calls (`Accept: application/json`); for controller routes they must carry
   the API key instead, which another website does not have.
3. The API key is shared by every client and ends up in web pages (lesson 10): anyone can read it there. A signed-in
   user is a person you know.
</details>

---
Previous: [Lesson 08 - Authentication](08-Authentication) · Next: [Lesson 10 - A Vue app inside Caligrafy](10-Vue-as-a-Library)
