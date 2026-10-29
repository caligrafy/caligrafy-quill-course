# Lesson 08 - Authentication

**In this lesson** Bookshelf gets accounts: visitors can register and sign in, and only signed-in users can add,
edit or delete books. Everyone can still read the catalog. You use the authentication module that ships with
Caligrafy instead of writing your own.

**Before you start:** lesson 07 is done (tag `lesson-07`): you can add, edit and delete books.

---

## New concepts

**Authentication** answers "who are you?" (sign in with a username and a password). **Authorization** answers
"are you allowed to do this?" (only signed-in users change books).

**Session.** HTTP forgets everything between two requests. PHP keeps a **session** per browser: a small storage on
the server, found again thanks to a cookie (`PHPSESSID`) that the browser sends with every request. When you sign
in, Caligrafy stores your user (encrypted with the `APP_KEY` of `.env`) in your session. The CSRF token of lesson
07 lives in the session too.

**The Auth module of Caligrafy.** `initialize` (lesson 01) copied it into your project:

| File | What it is |
|---|---|
| `application/models/Auth/create_db.sql` | the `users` table: `username` (unique), `passcode` (a password **hash**), `permissions` |
| `application/models/Auth/User.php` | the `User` model, with `findByUsername()`, `User::hashPassword()` and `verifyPassword()` |
| `application/controllers/core/Auth/AuthController.php` | `Caligrafy\Controllers\AuthController`: the actions `index` (login form), `login`, `logout`, `registerForm`, `register`, `home`, `notAuthorized` |
| `application/views/Auth/login.pug`, `register.pug` | the sign in and sign up pages, in the Caligrafy look |

`Auth::authentication('User', 'users');` in `web.php` switches the module on: it says which model and which table
hold the users. It must come before any route that uses it.

**Password hash.** The database never stores your password: it stores `password_hash($password)`, a one-way
fingerprint (it starts with `$2y$12$`). At sign in, `password_verify()` checks the typed password against it. Even
someone who reads the database cannot get the passwords back. Passwords must be 8 to 72 characters; usernames
are letters and digits only.

**The helpers you use in your code**

| Helper | Returns / does |
|---|---|
| `authorized()` | `true` when someone is signed in in this browser |
| `user()` | the signed-in `User` object (`user()->username`) |
| `redirect('/login'); exit;` | sends the visitor to the login page |
| `guard('permissions', 1)` | redirects to `/login` (not signed in) or `/notAuthorized` (permissions below 1): used for administrators |

**`$authorized` in the views.** The default layout shows the signed-in header (with a **Sign out** link) only when
the view receives `authorized` = true. So every page passes `'authorized' => authorized()`; a small private method
`page()` in the controllers does it for every `view()`.

**Where sign in goes.** `AuthController` sends the visitor to `/home` after signing in and after signing out. We
point `/home` at our list of books.

---

## Manual track

### Step 1 - Create the users table

```bash
docker compose exec -T mysql mysql -uroot -proot bookshelf < application/models/Auth/create_db.sql
docker compose exec mysql mysql -uroot -proot bookshelf -e "DESCRIBE users"
```
Expected (after the password warning): a table with the fields `id`, `username`, `passcode`, `permissions`,
`created_at`, `modified_at`. The script has `CREATE TABLE IF NOT EXISTS` and no `DROP`: it is safe to run again.

### Step 2 - Switch the module on and add its routes

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

New lines: `Auth::authentication('User', 'users');` at the top, and the seven routes of the Auth module. The
controller names are written in full (`'Caligrafy\\Controllers\\AuthController'`): it is the namespace of the
example controllers of the framework.

### Step 3 - Protect the book actions and tell the views who is signed in

**File `application/controllers/Bookshelf/BookController.php`** - replace its whole content with:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // GET /books (and GET /)
    public function index()
    {
        $this->associate('Book', 'books');
        $books = $this->all(array('order' => 'ORDER BY title'));
        return $this->page('Bookshelf/books', array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return $this->page('Bookshelf/book', array('book' => $book, 'author' => $book->author()));
    }

    // GET /books/new : the empty form
    public function createForm()
    {
        $this->requireLogin();
        return $this->showForm('Add a book', 'books', 'POST', array());
    }

    // POST /books : the form of createForm() is sent here
    public function create()
    {
        $this->requireLogin();
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return $this->showForm('Add a book', 'books', 'POST', $data, $errors);
        }
        $this->associate('Book', 'books');
        $book = $this->save(BookForm::columns($data));   // no {id} in this route: save() inserts a new row
        redirect('/books/' . $book->id);
        exit;
    }

    // GET /books/{id}/edit : the form filled with the book
    public function editForm()
    {
        $this->requireLogin();
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return $this->showForm('Edit the book', 'books/' . $book->id, 'PUT', (array) $book);
    }

    // PUT /books/{id} : the form of editForm() is sent here
    public function update()
    {
        $this->requireLogin();
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return $this->showForm('Edit the book', 'books/' . $book->id, 'PUT', $data, $errors);
        }
        $this->associate('Book', 'books');
        $this->save(BookForm::columns($data));   // {id} is in this route: save() updates that row
        redirect('/books/' . $book->id);
        exit;
    }

    // DELETE /books/{id}
    public function remove()
    {
        $this->requireLogin();
        $this->associate('Book', 'books');
        if ($this->find()) {
            $this->delete();
        }
        redirect('/books');
        exit;
    }

    // Sends visitors who are not signed in to the login page
    private function requireLogin()
    {
        if (!authorized()) {
            redirect('/login');
            exit;
        }
    }

    // Shows a view; 'authorized' tells the layout and the views whether someone is signed in
    private function page($viewName, $viewData)
    {
        $viewData['authorized'] = authorized();
        return view($viewName, $viewData);
    }

    // Filters and validates the posted fields, and checks that the author exists
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

    // Shows the book form; $values fills the fields, $errors holds one message per invalid field
    private function showForm($heading, $action, $method, $values, $errors = array())
    {
        $this->associate('Author', 'authors');
        $authors = $this->all(array('order' => 'ORDER BY name'));
        return $this->page('Bookshelf/bookform', array(
            'heading' => $heading,
            'action' => $action,
            'method' => $method,
            'values' => $values,
            'errors' => $errors,
            'authors' => $authors
        ));
    }
}
```

What changed:
- the five actions that show or receive a form start with `$this->requireLogin();`, which sends visitors who are
  not signed in to `/login`. Protecting the **actions** matters, not only hiding the buttons: a visitor can type
  `/books/new` or send a POST with `curl`.
- every `view(...)` became `$this->page(...)`, which adds `'authorized' => authorized()`.

**File `application/controllers/Bookshelf/AuthorController.php`** - replace its whole content with:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

class AuthorController extends Controller {

    // GET /authors/{id}
    public function read()
    {
        $this->associate('Author', 'authors');
        $author = $this->find();
        if (!$author) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no author with this number.'));
        }
        return $this->page('Bookshelf/author', array('author' => $author, 'books' => $author->books()));
    }

    // Shows a view; 'authorized' tells the layout and the views whether someone is signed in
    private function page($viewName, $viewData)
    {
        $viewData['authorized'] = authorized();
        return view($viewName, $viewData);
    }
}
```

### Step 4 - Show the buttons only to signed-in users

**File `application/views/Bookshelf/books.pug`** - replace its whole content with:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            h2.medium-text.semibold.horizontal-center Bookshelf
            p.horizontal-center A small catalog of the books we love
            .horizontal-center
                if $authorized
                    a.btn.btn-primary(href="{$home}books/new") Add a book
                else
                    a.btn.btn-secondary(href="{$home}login") Sign in to add books
            .spacer-2
            each $book in $books
                a(href="{$home}books/{$book->id}")
                    .card.color0.dropshadow.equally-spaced-1
                        h3.medium-text.semibold.textcolor5.reset-margin= $book->title
                        p.default-text.textcolor5= $book->published_year
            .spacer-2
```

**File `application/views/Bookshelf/book.pug`** - replace its whole content with:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            .card.color0.dropshadow.equally-spaced-1
                h2.medium-text.semibold.reset-margin= $book->title
                .spacer-1
                p.default-text.textcolor5
                    | by 
                    a(href="{$home}authors/{$author->id}")= $author->name
                    | , #{$book->published_year}
                p= $book->summary
                if $authorized
                    .spacer-1
                    a.btn.btn-primary(href="{$home}books/{$book->id}/edit") Edit
                    .spacer-1
                    form(method='POST', action="{$home}books/{$book->id}")
                        =csrf()
                        =methodField('DELETE')
                        input.btn.btn-secondary(type='submit', value='Delete')
            .spacer-2
            .horizontal-center
                a.btn.btn-secondary(href="{$home}books") All books
            .spacer-2
```

### Step 5 - Register, sign in, sign out

1. Open http://localhost:8080/. The header shows only **Caligrafy**; under the title there is a white button
   **Sign in to add books**, and no **Edit** or **Delete** on the book pages.
2. Open http://localhost:8080/books/new: you are sent to the sign in page (`/login`).
3. Click **Sign up**. Type the username `reader1` and the passcode `bookshelf-2026` twice, click **Sign up**. You
   are sent to the sign in page.
4. Sign in with `reader1` / `bookshelf-2026`. You land on `/home`, the list of books, and the header now has a
   **Sign out** link (on a narrow window, in the menu icon). **Add a book**, **Edit** and **Delete** are back.
5. Click **Sign out**: you are a visitor again.

Look at the stored password: it is a hash, not `bookshelf-2026`.
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "SELECT id, username, LEFT(passcode, 7) AS hash_start, permissions FROM users"
```
Expected: one row `1 | reader1 | $2y$12$ | 0` (`$2y$` is the bcrypt algorithm, `12` its cost).

![Signed in](https://raw.githubusercontent.com/caligrafy/caligrafy-quill-course/main/docs/images/lesson-08-signed-in.jpg)

### Step 6 - Check the protection from the terminal

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost:8080/books/new
```
Expected: `302 http://localhost:8080/login` (302 = "go to this other URL").

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'bookshelf.users' doesn't exist` | Step 1 was skipped | Run the users script of step 1 |
> | `Call to a member function find() on null` (500) | `Auth::authentication('User', 'users');` is missing or below `Route::run()` | Put it at the top of the `try`, as in step 2 |
> | After signing in you see the Caligrafy welcome page | `/home` still points to `AuthController@home` | `Route::get('/home', 'Bookshelf\\BookController');` |
> | Signed in, but no **Sign out** and no buttons | The view did not receive `authorized` | Use `$this->page(...)` instead of `view(...)` in the controllers |
> | `Invalid credentials` with the right password | The username is case-sensitive, or the account was not created (the sign up page showed an error) | Check the users table with the SELECT of step 5 |
> | Sign up says the inputs are invalid | Username with a space or a dash, or a passcode shorter than 8 characters | Letters and digits only, 8 to 72 characters |
> | Signed out after restarting the containers | Sessions are stored in the `dev-box` container | Sign in again |

---

## With Claude

```
Turn on the Caligrafy authentication module in Bookshelf: run application/models/Auth/create_db.sql with
/caligrafy-quill:db, add Auth::authentication('User', 'users') at the top of web.php and the routes of
Caligrafy\Controllers\AuthController (GET/POST /login, GET /logout, GET/POST /register, GET /notAuthorized),
with GET /home pointing to Bookshelf\BookController. In Bookshelf\BookController, the actions createForm,
create, editForm, update and remove must redirect to /login (then exit) when authorized() is false. Every
view of BookController and AuthorController must receive 'authorized' => authorized(), and books.pug /
book.pug show Add, Edit and Delete only when $authorized (otherwise a "Sign in to add books" button).
```

**What Claude should do:** run the users script, then change `web.php`, the two controllers and two views as in
steps 2 to 4.

**Check** before you accept:
- [ ] `Auth::authentication('User', 'users');` is inside the `try`, before the routes.
- [ ] The **actions** are protected (not only the buttons hidden): `/books/new` answers 302 to `/login` (step 6).
- [ ] Every `redirect()` is followed by `exit;`.
- [ ] No password or hash is printed in the chat; nothing in `.env` was changed.
- [ ] Step 5 works from register to sign out.

**If Claude's result differs:** Claude may protect the actions with `guard('permissions', 0)` instead of
`authorized()`: `guard()` is meant for permission levels (administrators), and level 0 lets every signed-in user
through, which is the same here but reads as a mistake: prefer `authorized()`. If Claude edits
`application/controllers/core/Auth/AuthController.php` to change where sign in goes, ask it to undo that and use
the `/home` route instead: keeping the framework's example files untouched lets `php caligrafer.php upgrade`
update them later (lesson 13).

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 08: authentication"
git tag lesson-08
```
Reference: [`checkpoints/lesson-08`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-08).

## Exercise

Show "Signed in as reader1" (the real username) at the top of the list of books for signed-in users.

<details><summary>Show the solution</summary>

In `books.pug`, under `p.horizontal-center A small catalog of the books we love`, at the same indentation:
```pug
            if $authorized
                p.small-text.textcolor5.horizontal-center Signed in as #{user()->username}
```
`user()` is a global helper, so a view can call it. Undo the change before lesson 09 to match the checkpoint.
</details>

---
Previous: [Lesson 07 - Forms, CSRF and validation](07-Forms-CSRF-and-Validation) · Next: [Lesson 09 - The REST API](09-The-REST-API)
