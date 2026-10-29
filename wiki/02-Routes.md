# Lesson 02 - Routes

**In this lesson** you add your first pages to Bookshelf by declaring **routes**: `/about` shows a sentence, and
`/hello/{name}` greets whatever name is in the URL. You learn the three rules of Caligrafy routing that surprise
beginners.

**Before you start:** lesson 01 is done: `http://localhost:8080` shows the Caligrafy welcome page, and your project
has the tag `lesson-01`. Open a terminal in your `bookshelf` folder, and check the containers run:
```bash
docker compose ps
```
You should see `dev-box`, `mysql` and `phpmyadmin` with the status `Up`. If not: `docker compose up -d dev-box`.

---

## New concepts

**Route.** A route connects a **URL** and an **HTTP method** to the code that answers. In Caligrafy every route is
one line in `application/config/routing/web.php`:

```php
Route::get('/about', function() { echo 'Bookshelf...'; });
//     ▲      ▲       ▲
//     │      │       └─ what answers: a function (here), or a controller (lesson 03)
//     │      └─ the URL path
//     └─ the HTTP method: get, post, put or delete
```

**HTTP method.** Your browser uses `GET` when you type a URL or click a link, and usually `POST` when you send a
form. `PUT` (change something) and `DELETE` (remove something) are used by forms in lesson 07 and by the API in
lesson 09. A route only answers its own method: `Route::get('/about', ...)` does not answer a `POST /about`.

**Closure.** `function() { ... }` without a name is a *closure*: a function you write right where it is used.
Closures are fine for tiny pages. As soon as a page needs data, use a controller (lesson 03).

**Route parameter.** A part of the path written in braces, like `{name}` in `/hello/{name}`, matches any value in
that position. `/hello/Ada` and `/hello/Grace` both match, and your code reads the value with
`request()->fetch('name')`. `request()` is a Caligrafy helper function that returns the current request.

**Why one file?** All the URLs of the application are in one place, so you always know where to look when a page
does not answer, and the order of the lines (rule 2 below) is easy to see.

### The three rules of Caligrafy routing

1. **`Route::run();` must be the last line inside the `try { }`.** The `Route::get(...)` lines only *declare* routes;
   `Route::run()` then picks the route that matches the request and runs it. A route declared after `Route::run()`
   is never used.
2. **The first route that matches wins.** Routes are tested in the order of the file. This matters in lesson 07:
   `/books/new` must be declared before `/books/{id}`, otherwise `{id}` would catch the word `new`.
3. **A URL that matches no route shows the first route, with status 200.** Caligrafy has no automatic "404 page not
   found" for unknown URLs: it runs the first route of the file. That is why `Route::get('/', ...)` must stay the
   first route. A route matches only when the number of path segments is the same (`/hello/Ada` has two segments:
   `hello` and `Ada`) and the HTTP method is the same.

---

## Manual track

### Step 1 - Look at the routes file

Open `application/config/routing/web.php` in your editor. It was created by `initialize` in lesson 01 and contains:
- `use` lines that import the Caligrafy classes `Auth`, `Route` and `Errors`;
- a `try { ... } catch (Exception $e) { Errors::handle($e); }` block. Every route is inside the `try`. When something
  goes wrong (for example a controller that does not exist), `Errors::handle()` answers with the right HTTP status
  code (404, 401, 403, 500...) and the error text;
- `Auth::activateAPI();`, which allows API calls (lesson 09);
- the two routes `/` and `/helloworld`, and commented examples (lines starting with `//`) for modules we do not use;
- `Route::run();` near the end.

Open http://localhost:8080/helloworld: the page shows `Hello World`. That is the second route of the file.

### Step 2 - Replace the routes file

We remove the commented examples we do not need (the original file stays available in
`framework/settings/application/config/routing/web.php`) and add two routes.

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

Save the file. There is nothing to restart: the `dev-box` container reads your project folder directly, so every
saved change is live. One detail: PHP keeps compiled files in a cache and checks for changes **every 2 seconds**, so a
page loaded right after saving can still run the old code. If you see the old version, wait 2 seconds and reload.

What the new lines do:
- `/about` prints a sentence with `echo`.
- `/hello/{name}` reads the `{name}` part of the URL with `request()->fetch('name')` and prints it.
  `sanitize()` is a Caligrafy helper that turns `<`, `>`, `&` and quotes into HTML entities, so that a URL like
  `/hello/<script>...` shows the text instead of running a script. **Always escape what comes from the URL or a
  form before you print it.** (Pug views escape for you, lesson 06.)
- The `/apps/{appName}` route was already in the file; we keep it for lesson 10.

### Step 3 - Try the routes

Open each URL in your browser:

| URL | Expected page text | Why |
|---|---|---|
| http://localhost:8080/about | `Bookshelf: a small catalog of the books we love.` | route `/about` |
| http://localhost:8080/hello/Ada | `Hello Ada!` | `{name}` = `Ada` |
| http://localhost:8080/hello/%3Cb%3Ehi | `Hello <b>hi!` (as text, not bold) | `sanitize()` escaped the `<b>` tag |
| http://localhost:8080/does/not/exist | the Caligrafy welcome page | rule 3: no match, the first route answers |

### Step 4 - Check it from the terminal

`curl` is a command that requests a URL and prints the answer. It is installed on macOS, Linux and WSL.

```bash
curl http://localhost:8080/hello/Ada
```
Expected output: `Hello Ada!`

Add `-i` to see the **status code** and the headers of the response:
```bash
curl -i http://localhost:8080/about
```
The first line is `HTTP/1.1 200 OK`: 200 means "success". The last line is the sentence.

### Step 5 - See rule 2 for yourself (then undo it)

Move the line `Route::run();` so that it is **before** the `Route::get('/about', ...)` route, save, and open
http://localhost:8080/about: you get the welcome page (the `/about` route was never declared when `Route::run()`
ran, so rule 3 applied). Move `Route::run();` back to its place, just before the closing `}` of the `try`, save,
and check that `/about` works again.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 500] syntax error, unexpected token "}", expecting "," or ";"` (status 500) | A PHP syntax error in `web.php` (here a missing `;`) | Undo your last change, or paste the complete file of step 2 again. `docker compose exec dev-box php -l application/config/routing/web.php` names the line: `Parse error: ... on line 23` |
> | Your change does not show | PHP's file cache (2 seconds) | Wait 2 seconds and reload |
> | The welcome page instead of your page | Your route did not match: a typo in the path, a different number of segments, or the route is after `Route::run()` | Compare your file with step 2 |
> | `curl: (7) Failed to connect to localhost port 8080` | The containers are stopped | Start Docker Desktop, then `docker compose up -d dev-box` |
> | `403 Forbidden` (an Apache page) for `/hello/Grace%20Hopper` | Caligrafy's `.htaccess` passes the path to `index.php` in a query string, and Apache refuses a rewritten query string that contains a space | Do not put spaces in route parameters: use `-` (`/hello/Grace-Hopper`) |

---

## With Claude

Open Claude Code in your `bookshelf` folder (`claude` in the terminal). The `caligrafy` skill of the plugin knows the
routing rules. Ask:

```
In application/config/routing/web.php, add two closure routes: GET /about that echoes
"Bookshelf: a small catalog of the books we love." and GET /hello/{name} that reads the name with
request()->fetch('name') and echoes "Hello <name>!" escaped with sanitize(). Keep '/' as the first route
and Route::run() as the last line of the try block. Do not change anything else.
```

**What Claude should do:** edit only `web.php`, adding the two routes after `/helloworld` and before
`Route::run();`. It does not remove the commented example routes (you did that by hand in the Manual track); both
versions work.

**Check** before you accept:
- [ ] `Route::get('/', ...)` is still the first route, and `Route::run();` the last line inside `try`.
- [ ] The name is printed through `sanitize()` (or `htmlspecialchars()`), never raw.
- [ ] The closing `catch (Exception $e) { Errors::handle($e); }` is unchanged.
- [ ] The URLs of step 3 give the expected texts.

**If Claude's result differs:** Claude may read the value with `$_GET`, or with `Route::$currentBind['name']`.
`$_GET` does not contain route parameters: ask Claude to use `request()->fetch('name')`. `Route::$currentBind`
works but is not the usual API. If Claude creates a controller instead of a closure, that also works, but ask it to
keep closures for this lesson: controllers come in lesson 03.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 02: first routes"
git tag lesson-02
```
`git status` should then print `nothing to commit, working tree clean`. The reference code is in
[`checkpoints/lesson-02`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-02).

## Exercise

Add a route `GET /books/{id}/review/{stars}` that prints `Book 3 gets 5 stars` for `/books/3/review/5`.
Then answer: what does http://localhost:8080/books/3/review show, and why?

<details><summary>Show the solution</summary>

Inside the `try`, before `Route::run();`:
```php
    Route::get('/books/{id}/review/{stars}', function() {
        echo 'Book ' . sanitize(request()->fetch('id')) . ' gets ' . sanitize(request()->fetch('stars')) . ' stars';
    });
```
`/books/3/review` has three segments and the route has four, so it does not match: rule 3 applies and the welcome
page is shown. Remove the exercise route before lesson 03 (it would conflict with nothing, but keeps your file
identical to the checkpoint).
</details>

---
Previous: [Lesson 01 - Create your project](01-Create-Your-Project) · Next: [Lesson 03 - Controllers](03-Controllers)
