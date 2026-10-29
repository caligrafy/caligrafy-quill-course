# Lesson 07 - Forms, CSRF and validation

**In this lesson** visitors can add, edit and delete books with HTML forms. Every form is protected against CSRF
attacks, every value is checked on the server, and an invalid form comes back with a red message under each wrong
field and the values the visitor typed.

**Before you start:** lesson 06 is done (tag `lesson-06`): http://localhost:8080/ shows the list of books.

---

## New concepts

**A form sends data with POST.** `<form method="POST" action="http://localhost:8080/books">` sends the fields
(`title=Kindred&published_year=1979...`) in the body of a `POST /books` request. In a controller,
`$this->request->parameters()` returns them as an array: `array('title' => 'Kindred', ...)`.

**PUT and DELETE from a form.** HTML forms can only send GET and POST. To edit (PUT) or delete (DELETE), the form
sends POST with a hidden field `_method` = `PUT` or `DELETE`; Caligrafy then treats the request as a PUT or a DELETE
and runs the `Route::put` / `Route::delete` route. In a Pug view, `=methodField('PUT')` writes that hidden field.

**CSRF (Cross-Site Request Forgery).** Without protection, any other website could contain a hidden form that posts
to *your* Bookshelf with the browser of a visitor who is signed in to it. Caligrafy blocks this with a **token**:
a random value stored in the visitor's session (on the server) and written as a hidden field `_token` in your
forms with `=csrf()`. Every POST, PUT and DELETE from a browser must send the right `_token`, otherwise Caligrafy
refuses it with `[ERROR 3000] Security Error - Cross Site Forgery` and status **403**. The other website cannot
read the token, so it cannot forge the form. **Every form you write needs `=csrf()`.**

**Filters and rules.** Never trust what a form sends: a visitor can send anything, not only what your form
allows. Caligrafy uses the GUMP library (`$this->validator` in a controller):
- **filters** clean the values first: `trim` removes spaces at both ends;
- **rules** say what is valid: `required`, `max_len,200`, `integer`, `min_numeric,1`, `max_numeric,2100`...
  Rules are separated by `|`. A rule other than `required` is skipped when the field is empty.
`$this->validate($data, $rules)` returns `true`, or an array with one readable message per invalid field:
`array('title' => 'The Title field is required')`. (`check()` exists too, but with rules **and** filters it
returns an array even when everything is valid, which is a classic bug: this course always calls `filter()`
then `validate()`.)

**`BookForm`: the rules in one place.** Lesson 09 checks books sent to the API with the same rules, so we put them
in a small class of their own, `Bookshelf\BookForm`, used by both. It has no database code, which also makes it
easy to test (lesson 13).

**422 and the redirect after POST.** When the form is invalid, the controller shows the form again with status
**422 Unprocessable Content** ("I understood, but the data is invalid"). When it is valid, the controller saves
and **redirects** (`redirect('/books/7')` + `exit;`) to the page of the book: the browser then loads that page
with GET, so pressing *Reload* does not send the form a second time. `redirect()` only sends a header: always
write `exit;` after it.

**`save()`.** In a controller, `$this->save($columns)` inserts a new row when the route has no `{id}`
(`POST /books`), and updates the row `{id}` when it has one (`PUT /books/{id}`). It returns the saved row. Only
the keys that are real columns are written; `null` values are skipped.

---

## Manual track

### Step 1 - The book rules

**File `application/controllers/Bookshelf/BookForm.php`**:

```php
<?php

namespace Bookshelf;

use Caligrafy\Validator;

// The rules of a valid book, in one place: the web forms (lesson 07) and the API (lesson 09) both use them
class BookForm {

    // filters clean the input before it is checked
    const FILTERS = array(
        'title' => 'trim',
        'published_year' => 'trim',
        'summary' => 'trim',
        'author_id' => 'trim'
    );

    // rules say what a valid value is
    const RULES = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1|max_numeric,2100',
        'summary' => 'max_len,2000',
        'author_id' => 'required|integer'
    );

    // Returns array($data, $errors): the cleaned data, and one message per invalid field (empty when all is valid)
    public static function check(Validator $validator, array $input)
    {
        $data = array();
        foreach (array_keys(self::RULES) as $field) {
            $data[$field] = $input[$field] ?? '';   // keep only the fields of a book
        }
        $data = $validator->filter($data, self::FILTERS);
        $valid = $validator->validate($data, self::RULES);
        $errors = $valid === true ? array() : $validator->get_errors_array();
        return array($data, $errors);
    }

    // The checked data as the columns of the books table, with numbers as integers
    public static function columns(array $data)
    {
        return array(
            'title' => $data['title'],
            'published_year' => (int) $data['published_year'],
            'summary' => $data['summary'],
            'author_id' => (int) $data['author_id']
        );
    }
}
```

- `check()` keeps only the four fields of a book (a visitor could add `id=1` to the form: it is ignored),
  trims them, validates them, and returns both the data and the errors.
- `columns()` turns the year and the author into integers for the database.
- The `RULES` do not check that the author exists: that needs the database, so the controller does it (step 2).

### Step 2 - The controller actions

`BookController` gets five new actions: `createForm` (show the empty form), `create` (receive it), `editForm`,
`update` and `remove`, and three private helpers.

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
        return view('Bookshelf/books', array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return view('Bookshelf/book', array('book' => $book, 'author' => $book->author()));
    }

    // GET /books/new : the empty form
    public function createForm()
    {
        return $this->showForm('Add a book', 'books', 'POST', array());
    }

    // POST /books : the form of createForm() is sent here
    public function create()
    {
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
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return $this->showForm('Edit the book', 'books/' . $book->id, 'PUT', (array) $book);
    }

    // PUT /books/{id} : the form of editForm() is sent here
    public function update()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
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
        $this->associate('Book', 'books');
        if ($this->find()) {
            $this->delete();
        }
        redirect('/books');
        exit;
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
        return view('Bookshelf/bookform', array(
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

Read it slowly:
- `create()` and `update()` call `checkBook()`, which uses `BookForm::check()` and then looks the author up
  (`find($data['author_id'])`). On errors they show the form again, with status 422, the typed values and the
  messages.
- On success they `associate('Book', 'books')` again (`checkBook()` switched to the authors table), save, and
  redirect to the book page.
- `editForm()` passes `(array) $book`: the object turned into an array, so that the form reads
  `$values['title']` in both cases (new book, existing book).
- `remove()` deletes the book if it exists and goes back to the list.
- The private methods are not actions: no route can call them.

### Step 3 - The form view

One view serves both forms. `$action` is the URL the form is sent to and `$method` is `POST` or `PUT`.

**File `application/views/Bookshelf/bookform.pug`**:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            .card.color0.dropshadow.equally-spaced-1
                h2.medium-text.semibold.reset-margin= $heading
                .spacer-1
                if $errors
                    p.error Some fields need your attention.
                form(method='POST', action="{$home}{$action}")
                    =csrf()
                    if $method == 'PUT'
                        =methodField('PUT')
                    .mb-3
                        label.default-text.textcolor5(for='title') * title
                        input.form-control#title(type='text', name='title', maxlength='200', value=$values['title'] ?? '')
                        if isset($errors['title'])
                            p.error= $errors['title']
                    .mb-3
                        label.default-text.textcolor5(for='author_id') * author
                        select.form-select#author_id(name='author_id')
                            option(value='') Choose an author
                            each $author in $authors
                                option(value=$author->id, selected=($author->id == ($values['author_id'] ?? '')))= $author->name
                        if isset($errors['author_id'])
                            p.error= $errors['author_id']
                    .mb-3
                        label.default-text.textcolor5(for='published_year') * year
                        input.form-control#published_year(type='number', name='published_year', value=$values['published_year'] ?? '')
                        if isset($errors['published_year'])
                            p.error= $errors['published_year']
                    .mb-3
                        label.default-text.textcolor5(for='summary') summary
                        textarea.form-control#summary(name='summary', rows='4')= $values['summary'] ?? ''
                        if isset($errors['summary'])
                            p.error= $errors['summary']
                    .spacer-2
                    input.btn.btn-primary(type='submit', value='Save')
            .spacer-2
            .horizontal-center
                a.btn.btn-secondary(href="{$home}books") Cancel
            .spacer-2
```

- `=csrf()` writes `<input type='hidden' name='_token' value='...'>`.
- `=methodField('PUT')` writes `<input type='hidden' name='_method' value='PUT'>`, only for the edit form.
- `value=$values['title'] ?? ''` is a PHP expression: the typed value, or empty.
- `selected=(...)` is true for the author of the book: Pug writes `selected` only when the value is true.
- `if isset($errors['title'])` shows the message of that field in the red `error` style.
- The fields have no HTML `required` attribute on purpose, so that you can see the server-side validation work.
  In a real application add it too: the browser then helps the visitor, and the server still checks.

### Step 4 - Buttons on the list and on the book page

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
                a.btn.btn-primary(href="{$home}books/new") Add a book
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

The **Delete** button is a small form: POST + `_method=DELETE` + `_token`. A link (`a href`) cannot delete,
because links send GET, and GET requests must never change data.

### Step 5 - The routes

Order matters: `/books/new` is declared **before** `/books/{id}` (otherwise `new` would be read as an id).

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

### Step 6 - Try every path

1. Open http://localhost:8080/ and click **Add a book**. The form opens at `/books/new`.
2. Click **Save** without filling anything. The form comes back with *Some fields need your attention.* and a red
   message under title, author and year:
   `The Title field is required`, `The Author Id field is required`, `The Published Year field is required`.
3. Type the title `The Dispossessed`, choose *Ursula K. Le Guin*, type the year `3000`, and save: the title and the
   author are kept, and the year says
   `The Published Year field needs to be a numeric value, equal to, or lower than 2100`.
   (The year field is `type="number"`, so the browser does not let you type letters in it. Lesson 09 sends letters
   through the API, and the server refuses them too.)
4. Type `1974` and save. You land on the page of the new book, `/books/7` (the id 6 was used up by the refused insert
   of lesson 04, step 6).
5. Click **Edit**, change the year to `1975`, **Save**: the book page shows 1975.
6. Click **Delete**: you are back on the list, without the book.

![The form with errors](https://raw.githubusercontent.com/caligrafy/caligrafy-quill-course/main/docs/images/lesson-07-form-errors.jpg)

### Step 7 - Check the protections from the terminal

A POST without the token is refused:
```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST -d "title=Hack&published_year=2000&author_id=1" http://localhost:8080/books
```
Expected: `403`. And the body says why:
```bash
curl -s -X POST -d "title=Hack" http://localhost:8080/books
```
Expected: `[ERROR 3000] Security Error - Cross Site Forgery`. No book was added (check the list).

An invalid form answers 422. Log the status of the request in the browser: open the developer tools
(<kbd>F12</kbd> or <kbd>Cmd</kbd>+<kbd>Option</kbd>+<kbd>I</kbd>), tab **Network**, submit the empty form, and
click the `books` request: *Status 422*.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 3000] Security Error - Cross Site Forgery` when you submit your own form | `=csrf()` is missing in the form, or your session expired (you restarted the containers while the form was open) | Add `=csrf()` right under `form(...)`; reload the form page and submit again |
> | Saving goes to the list page / the welcome page instead of the book | The form's `action` or the `_method` does not match a route (for example `Route::put` missing) | Compare the routes with step 5 |
> | `/books/new` shows "There is no book with this number." | `/books/new` is declared after `/books/{id}` | Move it before |
> | Saving the edit form shows the list of books and nothing changed | The edit form lacks `=methodField('PUT')`, so it was sent as `POST /books/7`, which matches no route: the first route answered (rule 3 of lesson 02) | Keep `if $method == 'PUT'` / `=methodField('PUT')` |
> | Your change does not show | PHP's file cache (2 seconds, lesson 02) | Wait 2 seconds and reload |
> | `SQLSTATE[23000]: Integrity constraint violation: 1452` (500) | A book was saved with an author id that does not exist | Keep the author check of `checkBook()` |
> | `SQLSTATE[HY000]: General error: 1366 Incorrect integer value` | An empty or non-numeric year reached the database | Keep `required|integer` in `RULES` and `(int)` in `columns()` |
> | The page after saving is blank | Code ran after `redirect()` and failed, or `exit;` is missing | Keep `redirect(...); exit;` |

---

## With Claude

```
Add create, edit and delete for books to Bookshelf with HTML forms:
- application/controllers/Bookshelf/BookForm.php: class Bookshelf\BookForm with FILTERS (trim) and RULES
  (title required max_len,200; published_year required integer between 1 and 2100; summary max_len,2000;
  author_id required integer), a static check(Validator $validator, array $input) that keeps only those four
  fields, filters, validates and returns array($data, $errors), and a static columns($data) with integers.
- Bookshelf\BookController: createForm, create, editForm, update, remove (not delete: it is a Controller
  method). Validate with BookForm::check then check the author exists. Invalid: HTTP 422 and the form again with
  the typed values and one message per field. Valid: save() and redirect('/books/<id>') then exit.
- application/views/Bookshelf/bookform.pug used for both forms, with =csrf(), =methodField('PUT') for edit,
  an author select, and p.error messages; an "Add a book" button on books.pug, Edit and a Delete form
  (POST + _method DELETE + csrf) on book.pug. Caligrafy classes only.
- routes: GET /books/new (before /books/{id}), POST /books, GET /books/{id}/edit, PUT /books/{id},
  DELETE /books/{id}.
```

**What Claude should create:** `BookForm.php`, a new `BookController`, `bookform.pug`, the two changed views and
five routes. The `caligrafy` skill knows the CSRF rule and the `check()` pitfall.

**Check** before you accept:
- [ ] Every `form` in the views contains `=csrf()`; the edit form has `=methodField('PUT')`, the delete form
      `=methodField('DELETE')`.
- [ ] The controller uses `filter()` then `validate()` (directly or through `BookForm`), and does **not** test
      `check($data, $rules, $filters) !== true` (that is always "invalid").
- [ ] `/books/new` is before `/books/{id}` in `web.php`.
- [ ] Every `redirect(...)` is followed by `exit;` (or `return`).
- [ ] No action is named `delete`, `save` or `find`.
- [ ] Step 6 and step 7 give the expected results.

**If Claude's result differs:** Claude may keep the rules as private properties of the controller instead of a
`BookForm` class: it works, but lesson 09 and 13 use `BookForm`, so ask for it. If Claude uses HTML5 `required`
attributes, the browser stops the empty form before the server sees it: remove them temporarily to test step 6.2.
If Claude makes the delete button a link (`a(href=".../delete")` with a GET route), refuse: a GET request must not
change data.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 07: forms to add, edit and delete books"
git tag lesson-07
```
Reference: [`checkpoints/lesson-07`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-07).

## Exercise

1. A visitor adds the hidden field `<input name="id" value="1">` to the add form with the browser tools. What
   happens, and which line of code protects you?
2. Make the summary required with at least 10 characters. What do you change?

<details><summary>Show the solution</summary>

1. Nothing special: a new book is created. `BookForm::check()` builds `$data` only from the keys of `RULES`
   (`foreach (array_keys(self::RULES) as $field)`), so `id` is dropped before `save()`.
2. In `BookForm::RULES`: `'summary' => 'required|min_len,10|max_len,2000'`, and put a `*` in its label in
   `bookform.pug`. Undo it before lesson 08 to match the checkpoint.
</details>

---
Previous: [Lesson 06 - Views and the layout](06-Views-and-the-Layout) · Next: [Lesson 08 - Authentication](08-Authentication)
