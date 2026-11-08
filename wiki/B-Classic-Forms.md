# Appendix B - Classic forms

**In this appendix** you write a form by hand, the way it was done before `Route::form`: a controller shows the empty
form, receives the posted fields, filters and validates them itself, shows the errors, writes the row and redirects.
It is the work that the one line `Route::form('/books', 'create_book', [...])` of lesson 06 does for every Bookshelf
form, with the rules in the capability. At the end, the same form is rewritten with `Route::form`.

> **This is the older way, not the course's way.** For a form, write a capability and one `Route::form` line (lesson 06).
> A hand-written form keeps its rules in one controller: REST, the JS clients and the agents never get them. Read this
> appendix to understand older projects, or what `Route::form` does for you.

**Before you start:** Appendix A is done (tag `appendix-a`), the containers run, and you can sign in as reader1.

**Time:** 40 minutes.

---

## New concepts

**A classic form** is a round trip of two routes:

| Route | Action | What it does |
|---|---|---|
| `GET /authors/new` | `createForm()` | shows the empty form |
| `POST /authors` | `create()` | reads the fields, filters and validates them; when they are wrong, shows the form again with the errors and what was typed (status 422); when they are right, saves the row and **redirects** to its page |

The redirect after a successful POST (the **Post/Redirect/Get** pattern) means that reloading the next page does not
send the form twice.

**What the controller does by hand**, and `Route::form` does for you:

| By hand, in `AuthorController` | With `Route::form` and a capability |
|---|---|
| `$this->request->parameters()`: the posted fields | the fields and the route variables become the inputs |
| `$this->validator->filter(...)` and `validate(...)` with the rules written in the action | the `$filters` and `$rules` of the capability, the same for every experience |
| render the form again with the values and the errors, status 422 | redirect back with `errors()`, `old()` and `flash('error')` for the view (never a password) |
| `$this->save($data)` | `handle()` of the capability, audited |
| `redirect('/authors/' . $author->id)` | `'success' => '/authors/{id}'`, filled from the answer, and a `flash` message |
| `if (!authorized()) redirect('/login')` | the capability refuses visitors, and `Route::form` sends them to `/login`; the page has `signedIn` and `comeBack` |
| compare two fields, check a box, in the action | `same` and `accepted` on the `Route::form` (lesson 11) |

**The CSRF token.** `=csrf()` in the form adds the hidden field `_token`; Caligrafy checks it on every POST, whoever
handles it. A form posted from another site has no token: 403.

**The route order.** `/authors/new` and `/authors/{id}` both have two segments: `/authors/new` is declared first, or
`{id}` would catch the word `new` (rule 2 of Appendix A).

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The controller actions

Add `createForm()`, `create()` and two private helpers to the controller of Appendix A. The file is then:

**File `application/controllers/Bookshelf/AuthorController.php`** (the classic form: two actions):

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

// Under the hood, the classic way: the controller reads and writes the tables itself and renders views. No capability
// is involved, so only these pages have these features: REST clients, the JS clients and AI agents do not see them
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

    // GET /authors/new: the empty form (people signed in only)
    public function createForm()
    {
        $this->signedInOnly();
        return $this->form(array('name' => '', 'bio' => ''), array());
    }

    // POST /authors: the form is sent here. The rules live in this controller only
    public function create()
    {
        $this->signedInOnly();
        $input = $this->request->parameters();   // the posted fields
        $data = $this->validator->filter(array(
            'name' => $input['name'] ?? '',
            'bio' => $input['bio'] ?? ''
        ), array('name' => 'trim', 'bio' => 'trim'));
        $valid = $this->validator->validate($data, array('name' => 'required|max_len,150', 'bio' => 'max_len,2000'));
        if ($valid !== true) {
            http_response_code(422);
            return $this->form($data, $this->validator->get_errors_array());
        }
        $this->associate('Author', 'authors');
        $author = $this->save($data);   // no {id} in this route: save() inserts a new row
        redirect('/authors/' . $author->id);
        exit;
    }

    private function form(array $values, array $errors)
    {
        return view('Bookshelf/authorform', array('values' => $values, 'errors' => $errors));
    }

    // sends visitors who are not signed in to the sign-in page
    private function signedInOnly()
    {
        if (!authorized()) {
            redirect('/login');
            exit;
        }
    }
}
```

- `signedInOnly()` is a private method: no route can call it.
- `get_errors_array()` gives one message per wrong field, for example `The Name field is required`.
- `save($data)` inserts a row (there is no `{id}` in the route) and returns the new author.

### Step 2 - The form

**File `application/views/Bookshelf/authorform.pug`** (a page that is only a form: the narrow column of the sign-in page):

```pug
extends ../default/master/site

//- Add an author: a classic form posted to Bookshelf\AuthorController@create

block title
    | Add an author · Bookshelf

block content
    main.cq-auth.cq-auth--wide
        h1 Add an author
        p.cq-lead A classic form: the page is sent to the server, which answers with a new page.

        form(method='POST', action="{$home}authors", novalidate)
            =csrf()
            .cq-field
                label(for='name') * Name
                input#name.form-control(type='text', name='name', value=$values['name'], maxlength='150')
            .cq-field
                label(for='bio') Bio
                textarea#bio.form-control(name='bio', rows='3', maxlength='2000')= $values['bio']
            button.btn.btn-primary(type='submit') Add author

        if $errors
            p.cq-field-error(role='alert')= implode(' ', $errors)

        p.cq-auth-note
            a(href="{$home}books") All books
```

### Step 3 - The routes

In the block of Appendix A, renamed `// APPENDIX: routes, controllers and forms written by hand`, add
`GET /authors/new` and `POST /authors` above `/authors/{id}`. The file is then:

**File `application/config/routing/web.php`** (`/authors/new` before `/authors/{id}`):

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
    Route::redirect('/catalog', '/books', 301, ['keepQuery' => true]);   // the catalog's old address: the filter of its address goes along

    // SIGN IN, SIGN OUT: /login, /logout and /notAuthorized; after signing in, /login?return=<page>, else the catalog
    Route::auth(['home' => '/books', 'register' => false, 'messages' => ['invalid' => 'Invalid email or password']]);   // no /register: people join with the form of /join

    // ROUTING TO JAVASCRIPT APPS
    Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);   // public/<appName>/index.php, or ?app=<file>

    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; each form calls one capability
    Route::page('/books', 'Bookshelf/books', ['query' => ['author_id'], 'data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'with' => ['install' => true]]);   // install: offer to add it to the home screen
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file
    Route::page('/people', 'Bookshelf/people', ['level' => 1, 'comeBack' => true, 'data' => ['people' => 'list_users']]);   // administrators only

    // JOIN THE CLUB: the only way to create an account; people already signed in go to the catalog
    Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
    Route::form('/join', 'register_user', [
        'visitorsOnly' => '/books',
        'same' => ['passcode_again' => ['passcode', 'The two passwords are not the same']],
        'accepted' => ['rules' => 'Accept the rules of the club to join'],
        'signsIn' => 'id',
        'success' => '/books',
        'flash' => 'Welcome to the club, {username}'
    ]);

    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');

    // LOANS: one more feature, every experience: a page and its forms, REST, the JS clients and AI agents
    Route::page('/loans', 'Loans/index', ['signedIn' => true, 'comeBack' => true, 'data' => ['loans' => 'list_loans', 'books' => 'list_books']]);
    Route::form('/loans', 'lend_book', ['success' => '/loans', 'flash' => 'Lent to {borrower} until {due_on}']);
    Route::form('/loans/{book_id}/return', 'return_book', ['success' => '/loans', 'flash' => 'Returned by {borrower}']);
    Route::form('/books/{book_id}/borrow', 'lend_book', ['inputs' => function () { return ['borrower' => Auth::user()->username ?? '', 'days' => 14]; }, 'success' => '/loans', 'flash' => 'It is yours until {due_on}']);   // the server says who borrows
    Route::form('/loans/remind', 'send_reminders', ['success' => '/loans', 'flash' => 'The reminders are on their way (job {job})']);   // administrators
    Route::capability('GET', '/api/loans', 'list_loans');
    Route::capability('POST', '/api/loans', 'lend_book');
    Route::capability('POST', '/api/books/{book_id}/return', 'return_book');

    // WHAT A PAGE OR A FORM CANNOT SAY: each answered by a capability declared webOnly: true
    Route::action('/surprise', 'surprise_me');                 // a request that decides where to go
    Route::action('/feed', 'books_feed');                      // an answer that is XML
    Route::action('/loans/{book_id}/renew', 'renew_loan', ['signedIn' => true, 'comeBack' => true]);   // a link that changes data

    // APPENDIX: routes, controllers and forms written by hand
    Route::get('/about', function () {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function () {
        $name = request()->fetch('name');        // the {name} part of the address
        echo 'Hello ' . sanitize($name) . '!';   // sanitize: never print what a visitor typed as is
    });
    Route::get('/classic/feed', 'Bookshelf\\FeedController@books');   // the older way of lesson 16's /feed: a controller that calls a capability
    Route::get('/authors/new', 'Bookshelf\\AuthorController@createForm');   // before /authors/{id}, or {id} would take "new"
    Route::post('/authors', 'Bookshelf\\AuthorController@create');
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

### Step 4 - Try it

1. As a visitor, open http://localhost:8080/authors/new: you land on the sign-in page.
2. Sign in as reader1 and open it again: **Add an author**, a Name and a Bio.
3. Leave the name empty, type `x` in Bio, **Add author**: `The Name field is required`, and `x` is still in Bio
   (status 422).
4. Name `  N. K. Jemisin ` (with spaces), Bio `American writer of the Broken Earth trilogy.`, **Add author**: you land
   on her page, `/authors/30` (your number differs: each test of lesson 12 used an id too), titled `N. K. Jemisin`
   without the spaces.
5. The catalog form of http://localhost:8080/books lists her in **Author**: `list_authors` reads the same table.

A post without the token, as another site would send it:
```bash
curl -s -o /dev/null -w "%{http_code}\n" -d "name=x" http://localhost:8080/authors
```
Expected: `403`.

The rules of this form exist only here: an agent cannot add an author, and `POST /capabilities/create_author` answers
`{"error":{"code":"not_found","message":"There is no capability 'create_author'"}}` (404).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `/authors/new` shows the Not found page of an author | `/authors/new` is declared below `/authors/{id}` | Move it above (step 3) |
> | `[ERROR 3000] Security Error - Cross Site Forgery` when you submit | `=csrf()` is missing from the form, or the page was opened before a sign-in | Compare with step 2; reload the form |
> | `[ERROR 1001] Invalid Controller or Action` | A typo in `createForm` or `create`, in the route or the class | Compare with steps 1 and 3 |
> | The errors show but the typed bio is gone | The view does not print `$values['bio']` | Compare with step 2 |

---

## Check

- [ ] The controller, `authorform.pug` and `web.php` are identical to the File blocks.
- [ ] A visitor goes to `/login`; an empty name gives `The Name field is required` and keeps the bio; a good one
      redirects to the author's page.
- [ ] A post without `_token` answers 403.

## With Claude

```text
The classic way, for Appendix B only: add createForm() and create() to application/controllers/Bookshelf/
AuthorController.php. createForm shows application/views/Bookshelf/authorform.pug (main.cq-auth.cq-auth--wide,
"Add an author", =csrf(), name and bio). create reads $this->request->parameters(), filters trim, validates
name required|max_len,150 and bio max_len,2000 with $this->validator, shows the form again with the values and the
errors (status 422), else associate('Author', 'authors'), save() and redirect to /authors/<id>. Both send visitors
to /login. Routes GET /authors/new (above /authors/{id}) and POST /authors.
```
The prompt adds to the `AuthorController` and the `/authors/{id}` page of the steps of Appendix A: do those first, or a good
save redirects to an address that does not exist.

**Check** before you accept:
- [ ] `/authors/new` is declared above `/authors/{id}`; the form has `=csrf()`.
- [ ] A refused form answers 422 with the typed values; a good one redirects.
- [ ] The checks of step 4 give the same answers.

---

## The same form, with Route::form

The course's way is a capability and two lines. `create_author` holds the rules:
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_author: adds an author, with the same rules whoever calls it
#[Capability(
    name: 'create_author',
    description: 'Adds an author to the Bookshelf catalog and returns it with its id.',
    scope: 'books:write'
)]
class CreateAuthor extends Service
{
    public $rules = array('name' => 'required|max_len,150', 'bio' => 'max_len,2000');
    public $filters = array('name' => 'trim', 'bio' => 'trim');
    public $descriptions = array('name' => 'The name of the author', 'bio' => 'Optional: a sentence about them');

    public function handle(array $input, Context $context)
    {
        return Author::find(Author::insert($input));
    }
}
```
In `web.php` the two controller routes become:
```php
    Route::page('/authors/new', 'Bookshelf/authorform', ['signedIn' => true, 'comeBack' => true]);
    Route::form('/authors', 'create_author', ['success' => '/authors/{id}', 'flash' => 'Author added']);
```
and the view reads `value=old('name')`, `= old('bio')` and `errors('name')` in place of `$values` and `$errors`.
`createForm()`, `create()` and the two helpers are deleted. Tried on the course's run: an empty name comes back with
`The Name field is required` and the typed bio, a good one redirects to the new author, a visitor goes to
`/login?return=%2Fauthors%2Fnew` and comes back, and the same capability now answers REST and agents:
`{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"name":"The Name field is required"}}}`
(422) for an empty name. That version is not in the checkpoint.

---

## Checkpoint

```bash
git add -A
git commit -m "Appendix B: classic forms"
git tag appendix-b
```
Reference: [`checkpoints/appendix-b`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/appendix-b).
Learn more: [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes) and
[9. Helpers, Forms and REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API).

## Exercise

Why does `create()` end with `redirect(...)` and `exit`, and what happens if you reload the author's page after
adding one? What would happen if `create()` rendered the author's page directly instead?

<details><summary>Show the solution</summary>

`redirect()` sends the browser to `/authors/<id>` with a GET; `exit` stops the action there. Reloading the author's
page repeats that GET: nothing is added twice. If `create()` rendered the page itself, the browser would still be on
the POST: a reload asks "send the form again?" and adds the author a second time. `Route::form` always redirects after
a success, for the same reason.
</details>

---
Previous: [Appendix A - Routes and controllers by hand](A-Routes-and-Controllers) · [Back to the course](Home)
