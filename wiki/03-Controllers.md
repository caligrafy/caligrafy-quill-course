# Lesson 03 - Controllers

**In this lesson** you write your first controller, `Bookshelf\BookController`. It answers two URLs:
`/books` (all the books) and `/books/{id}` (one book, or a "404 Not Found" when the number does not exist).
The books are still written in the PHP file; lesson 05 reads them from MySQL.

**Before you start:** lesson 02 is done (tag `lesson-02`), the containers run (`docker compose ps`).

---

## New concepts

**Controller.** A PHP class that groups the code of related pages. Each public method of the class that answers a
URL is called an **action**. `BookController` will have the actions `index()` (all books) and `read()` (one book).
A route names the controller and the action, separated by `@`:

```php
Route::get('/books/{id}', 'Bookshelf\\BookController@read');
//                         └ namespace ┘└ class ──┘ └ action
```
When the route has no `@action`, Caligrafy calls `index()`.

**Why controllers instead of closures?** A closure in `web.php` is fine for one line. A controller keeps
`web.php` short (one line per URL), groups the code of one subject in one file, and gives you the tools of the
framework through `$this`: `$this->request` (the request), `$this->validator` (lesson 07), and the database
helpers `associate()`, `all()`, `find()`, `save()`, `delete()` (lesson 05).

**`extends Controller`.** Your controller *inherits* from the framework class `Caligrafy\Controller`, which provides
all of the above. Because of that, **never name one of your actions** like a method of `Controller`:
`find`, `save`, `delete`, `all`, `search`, `check`, `validate`, `filter`, `view`, `api`, `associate`, `table`.
That is why the action that deletes a book (lesson 07) is called `remove()`, not `delete()`.

**Namespace.** Caligrafy loads every PHP file of `application/`, so two classes with the same name anywhere in it
(two `BookController`, or your own `AuthController` next to the framework's) would crash every page with
`Cannot redeclare class`. A **namespace** is a family name for classes: `namespace Bookshelf;` at the top of the file
makes the full name of the class `Bookshelf\BookController`, which cannot collide with a `BookController` of
another family. The example controllers of the framework use the namespace `Caligrafy\Controllers` (you saw
`'Caligrafy\\Controllers\\ClientController'` in `web.php`). We put the file in a folder with the same name,
`controllers/Bookshelf/`, so the name tells you where the file is. In a PHP string written with single quotes, the
backslash of a namespace is written twice: `'Bookshelf\\BookController'`.

**`$this->request->fetch('id')`.** Inside a controller, `$this->request` is the current request, and `fetch('id')`
returns the `{id}` part of the URL, as a string. It is the same as `request()->fetch('id')` in lesson 02.

**`api($data)`.** Turns a PHP array into **JSON** (the text format of data on the web, for example
`{"book": {"id": 1, "title": "Kindred"}}`) and sends it. It also **ends the request**: nothing after `return api(...)`
runs. In lesson 06 the controller sends HTML pages with `view()` instead.

**HTTP status code.** A number in the first line of every response that says how it went: `200 OK`,
`404 Not Found` (this thing does not exist), `401` (you need to identify yourself), `422` (your data is invalid),
`500` (the server failed). Programs read it to know what happened. In PHP you set it with
`http_response_code(404);` **before** `api()` or `view()`.

---

## Manual track

### Step 1 - Create the controller

Create the folder `application/controllers/Bookshelf` and, in it, the file `BookController.php`.

**File `application/controllers/Bookshelf/BookController.php`**:

```php
<?php

// The namespace groups our classes under the name Bookshelf.
// The folder (controllers/Bookshelf) matches it, so the class is easy to find.
namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // Temporary data: lesson 05 replaces it with the database
    private $books = array(
        1 => array('id' => 1, 'title' => 'A Wizard of Earthsea', 'published_year' => 1968),
        2 => array('id' => 2, 'title' => 'Kindred', 'published_year' => 1979),
        3 => array('id' => 3, 'title' => 'Invisible Cities', 'published_year' => 1972)
    );

    // GET /books
    public function index()
    {
        return api(array('books' => array_values($this->books)));
    }

    // GET /books/{id}
    public function read()
    {
        $id = (int) $this->request->fetch('id');
        if (!isset($this->books[$id])) {
            http_response_code(404);
            return api(array('error' => array('code' => 404, 'message' => 'Book not found')));
        }
        return api(array('book' => $this->books[$id]));
    }
}
```

Line by line:
- `namespace Bookshelf;` must be the first statement after `<?php`.
- `use Caligrafy\Controller;` lets you write `Controller` instead of `\Caligrafy\Controller`.
- `$books` is a private property: an array of books, indexed by their id.
- `index()` returns all the books. `array_values()` turns `[1 => ..., 2 => ...]` into a list `[..., ...]`, which
  becomes a JSON array.
- `read()` reads `{id}`, converts it to a number with `(int)`, and answers 404 with an error message when there is
  no such book. The shape `{"error": {"code": ..., "message": ...}}` is the one the framework itself uses for its
  errors, so a program reads every error the same way.

### Step 2 - Add the routes

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

    // ROUTES. The first route must stay '/': a URL that matches no route shows the first route.
    Route::get('/', function() { return view('default/index'); });
    Route::get('/helloworld', function() { echo "Hello World"; });

    // Lesson 02: our first routes
    Route::get('/about', function() {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function() {
        $name = request()->fetch('name');
        echo 'Hello ' . sanitize($name) . '!';
    });

    // Lesson 03: books
    Route::get('/books', 'Bookshelf\\BookController');            // no @action: index()
    Route::get('/books/{id}', 'Bookshelf\\BookController@read');

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

### Step 3 - Try it

Open http://localhost:8080/books. The browser shows the JSON as one line of text:
```json
{"books":[{"id":1,"title":"A Wizard of Earthsea","published_year":1968},{"id":2,"title":"Kindred","published_year":1979},{"id":3,"title":"Invisible Cities","published_year":1972}]}
```
Open http://localhost:8080/books/2:
```json
{"book":{"id":2,"title":"Kindred","published_year":1979}}
```

### Step 4 - Check the status codes

```bash
curl -i http://localhost:8080/books/2
```
First line: `HTTP/1.1 200 OK`, then headers, then the JSON above. (One of the headers is
`Content-Type: text/html; charset=UTF-8`: `api()` does not send the JSON content type, which is why browsers show the
JSON as plain text instead of formatting it. Programs read it as JSON anyway.)

```bash
curl -i http://localhost:8080/books/42
```
First line: `HTTP/1.1 404 Not Found`. Last line: `{"error":{"code":404,"message":"Book not found"}}`.

To print only the status code, use `-o /dev/null -w '%{http_code}\n'` (it throws the body away and writes the code):
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/books/42
```
Expected: `404`.

### Step 5 - See a framework error

Change the read route to a method that does not exist, `'Bookshelf\\BookController@show'`, save, and run:
```bash
curl -i http://localhost:8080/books/2
```
Expected: `HTTP/1.1 404 Not Found` and the text `[ERROR 1001] Invalid Controller or Action`. Caligrafy found no
`show()` method in `Bookshelf\BookController`. The same error appears when the namespace or the class name in the
route has a typo (they are case-sensitive). Put `@read` back and save.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 1001] Invalid Controller or Action` | The route names a class or method that does not exist: missing `Bookshelf\\`, a typo, wrong case (`bookController`), or the `namespace` line is missing in the file | Compare the route and the file with steps 1 and 2 |
> | `[ERROR 500] Cannot redeclare class Bookshelf\BookController (previously declared in .../BookController.copy.php:9)` | Two files declare the same class: for example you copied `BookController.php` to a backup file inside `application/` | Delete the copy, or move it outside `application/` (Caligrafy loads every PHP file of `application/`) |
> | Your change does not show | PHP's file cache (2 seconds, lesson 02) | Wait 2 seconds and reload |
> | The welcome page instead of JSON | The route did not match (rule 3 of lesson 02): check the path and that the route is before `Route::run();` | |
> | `[ERROR 500]` with `syntax error, unexpected ...` | A PHP syntax error | `docker compose exec dev-box php -l application/controllers/Bookshelf/BookController.php` names the line |

---

## With Claude

```
Create a namespaced controller Bookshelf\BookController in
application/controllers/Bookshelf/BookController.php (namespace Bookshelf, extends Caligrafy\Controller) with
a private array of three books (id, title, published_year) and two actions: index() returns
api(['books' => list]) and read() returns api(['book' => ...]) for {id}, or HTTP 404 with
api(['error' => ['code' => 404, 'message' => 'Book not found']]). Add the routes GET /books (index) and
GET /books/{id} (read) to web.php before Route::run(), with the fully qualified controller name.
```

**What Claude should create:** the controller file and two routes, as in steps 1 and 2. The `caligrafy` skill tells
Claude to use `http_response_code()` before `api()` and to avoid the reserved method names.

**Check** before you accept:
- [ ] The first statement of the file is `namespace Bookshelf;`, and the routes say `'Bookshelf\\BookController'`
      (two backslashes in single quotes) - without the namespace in the route you get `[ERROR 1001]`.
- [ ] No action is named `find`, `all`, `delete`, `save`, `view` or `api`.
- [ ] `http_response_code(404)` is called **before** `return api(...)`.
- [ ] `curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/books/42` prints `404`.

**If Claude's result differs:** Claude may name the action `show()` instead of `read()`: fine, as long as the route
says the same name. If it returns the error as `{"error": "Book not found"}` (a plain string), ask it to use the
`{"error": {"code", "message"}}` shape: the Vue apps of part 2 read `error.message`.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 03: BookController"
git tag lesson-03
```
Reference: [`checkpoints/lesson-03`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-03).

## Exercise

Add an action `count()` to `BookController` and a route `GET /books/count` that answers `{"count":3}`. Where must
the route be in `web.php`, and why?

<details><summary>Show the solution</summary>

In the class:
```php
    // GET /books/count
    public function count()
    {
        return api(array('count' => count($this->books)));
    }
```
In `web.php`, **before** `Route::get('/books/{id}', ...)`:
```php
    Route::get('/books/count', 'Bookshelf\\BookController@count');
```
Both routes have two segments, and the first route that matches wins (rule 2 of lesson 02). After `/books/{id}`,
the word `count` would be taken as an id, and you would get a 404 "Book not found". Remove the exercise code before
lesson 04 if you want your project to match the checkpoint.
</details>

---
Previous: [Lesson 02 - Routes](02-Routes) · Next: [Lesson 04 - The database](04-The-Database)
