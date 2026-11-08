# Lesson 11 - People and permissions

**In this lesson** Bookshelf gets accounts and rules: everyone can still read the catalog, signed-in people can add and
edit books, only administrators can delete them or see who has an account, and each program or agent gets a token
with just the rights it needs. One line of `web.php` (`Route::auth`) gives the sign-in and sign-up pages; you close the
doors once, in three capabilities, and every experience obeys at once: the pages, REST, the agents, the finder and the
SPA. The pages show only what the visitor may use.

**Before you start:** lesson 10 is done (tag `lesson-10`), `docker compose ps` shows the 3 containers running, and you
have token #2 "reading partner" of lesson 07.

**Time:** 60 to 75 minutes.

---

## New concepts

**Authentication and authorization.** Authentication answers "who are you?": a person signs in with a username and a
password, a program sends a token. Authorization answers "may you do this?": only administrators delete books.

**Session.** HTTP forgets everything between two requests. PHP keeps a **session** per browser, found again thanks to a
cookie the browser sends with every request. When a person signs in, Caligrafy keeps the user in the session, under a
**new** session id (an id someone saw before the sign-in is worthless after it), and a new CSRF token (lesson 06).

**`Route::auth`.** One line declares the addresses of the authentication module:

| Address | What it does |
|---|---|
| `GET /login`, `POST /login` | the sign-in page and its form; after 5 wrong passwords for one username in 15 minutes, that sign-in is refused until the 15 minutes end |
| `GET /logout`, `POST /logout` | signs out, then goes to `/` |
| `GET /register`, `POST /register` | the sign-up page; the form calls the framework capability `register_user` (permissions 0) |
| `GET /notAuthorized` | the page (403) of a signed-in person whose level is too low |

Options: `home` (where a person lands after signing in, default `/`), `signInAfterRegister` (`true`: a new account is
signed in at once and goes to `home`; default `false`: it goes to the sign-in page), and others listed in
[Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes). It needs
`Auth::authentication('User', 'users')` above it: the model and the table of the people.

**The users table and the permission level.** The table `users` has `id`, `username`, `passcode` (a one-way hash,
never the password), `permissions` (0 for every new account), `created_at`, `modified_at`. The number in `permissions`
is the person's **permission level**: 0 for a reader, 1 for an administrator.

**Who may call a capability.** Three settings of `#[Capability]`:

| Setting | Meaning |
|---|---|
| `public: true` | anyone, even a visitor nobody knows; scope and level are not checked |
| `scope: 'books:write'` | a **token** needs this scope (`books:write`, `books:*` or `*`) |
| `level: 1` | a **person** needs `permissions` of 1 or more (default 0: any signed-in person) |

And who is calling:

| Caller | Recognized by | Checked against |
|---|---|---|
| visitor | nothing | only `public` capabilities, else 401 `unauthenticated` |
| person signed in | the session cookie | the `level` (people have every scope) |
| **application token** (`token create ... --scopes ...`) | `Authorization: Bearer cq_...` | its **scopes only**: no person behind it, so no level |
| **token for a person** (`token create ... --user <id>`) | `Authorization: Bearer cq_...` | its scopes **and** the level of that person |

Read the last two lines twice. A token with `books:write` and no `--user` can delete books; a token for reader1 can
delete only once reader1 is an administrator. Give write scopes with care.

**Pages for some people only.** `Route::page` takes `signedIn` and `level`:
`['signedIn' => true]` sends visitors to `/login`, `['level' => 1]` sends visitors to `/login` and people below level 1
to `/notAuthorized`. Every page of `Route::page` also gets `$authorized` (true when someone is signed in) and `$user`
(`id`, `username`, `permissions`, or null): the views use them to show only what the visitor may use. Hiding a button
is for comfort; the protection is in the capability, which refuses on every channel.

**The audit log.** Every write and every refusal is a line: when, through which channel (`web`, `rest`, `mcp`, `cli`),
who (`guest`, `user 1`, `token 3`), which capability, and the outcome.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The users table

The script is the authentication module's own `create_db.sql`, which initialize copied into your project:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Auth/create_db.sql
```
Expected: ` application/models/Auth/create_db.sql run on caligrafy`. It creates the table only if it is missing:
running it again never deletes anyone.

### Step 2 - Sign-in, sign-up, and a page for administrators

Three changes in `application/config/routing/web.php`:
1. Uncomment `Auth::authentication('User', 'users');` (remove the `//`).
2. Replace the commented block `// Auth Routes - Uncomment only if AUTHENTICATION activated above` and its 7 lines with
   the `// SIGN IN, SIGN OUT, SIGN UP` comment and `Route::auth(...)`.
3. In `// BOOKSHELF PAGES`, after the `Route::download` line, add the page `/people` with `'level' => 1`.

The file is then:

**File `application/config/routing/web.php`** (`Auth::authentication`, `Route::auth` and the `/people` page):

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

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

`/people` gets its data from `list_users`, a capability of the framework (`users:read`, level 1, never a password).
Write its view:

**File `application/views/Bookshelf/people.pug`**:

```pug
extends ../default/master/site

//- The people who can sign in: Route::page('/people', ..., ['level' => 1]) opens it to administrators only, and gives
//- it $people (list_users, a framework capability: id, username, permissions; never a password)

block title
    | People · Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display People
        p.cq-lead Who can sign in to Bookshelf. Permissions 1 or more: an administrator.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            ul.cq-rows
                each $p in $people
                    li.cq-row
                        .cq-row-main
                            p.cq-row-title= $p['username']
                            p.cq-row-meta
                                | #{$p['permissions'] >= 1 ? 'Administrator' : 'Reader'} · since #{$p['created_at']}
            p.cq-auth-note
                a(href="{$home}books") All books
```

Try it as a visitor:
```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost:8080/people
```
Expected: `302 http://localhost:8080/login`. The **Sign in** and **Create account** links of the top bar now lead to
real pages.

### Step 3 - Close the doors, once

`create_book` and `update_book` lose `public: true`; `delete_book` gets `level: 1` in its place. The four reads stay
public. The three files are then:

**File `application/services/Bookshelf/CreateBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.',
    scope: 'books:write'   // signed-in people, and tokens with books:write
)]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'isbn' => 'alpha_numeric_dash|max_len,20|unique,books.isbn',
        'author_id' => 'required|integer|exists,authors.id'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim', 'isbn' => 'trim');
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published, for example 1969 (not after this year)',
        'summary' => 'Optional: one or two sentences about the book',
        'isbn' => 'Optional: its ISBN, digits and dashes, for example 978-0-441-47812-5; two books never share one',
        'author_id' => 'The id of its author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        // an empty ISBN is no ISBN: NULL, which the unique index allows on many books
        $input['isbn'] = ($input['isbn'] ?? '') === '' ? null : $input['isbn'];
        $id = Book::insert($input);   // one INSERT, every value bound; returns the id of the new book
        return Book::with('author')->find($id);
    }
}
```

**File `application/services/Bookshelf/UpdateBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// update_book: changes only the inputs that are given
#[Capability(
    name: 'update_book',
    description: 'Changes the title, year, summary, ISBN or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book with its author.',
    scope: 'books:write'   // signed-in people, and tokens with books:write
)]
class UpdateBook extends Service
{
    public $rules = array(
        'id' => 'required|integer',
        'title' => 'max_len,200',
        'published_year' => 'integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'isbn' => 'alpha_numeric_dash|max_len,20|unique,books.isbn,id,id',   // unique, except on this book itself
        'author_id' => 'integer|exists,authors.id'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim', 'isbn' => 'trim');
    public $descriptions = array(
        'id' => 'The id of the book to change',
        'title' => 'A new title',
        'published_year' => 'A new year of publication',
        'summary' => 'A new summary',
        'isbn' => 'A new ISBN (empty removes it)',
        'author_id' => 'The id of a new author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        if (!Book::find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        // keep only the inputs that were given: a missing input never erases a column
        $changes = array();
        foreach (array('title', 'published_year', 'summary', 'isbn', 'author_id') as $field) {
            if (isset($input[$field])) {
                $changes[$field] = $input[$field];
            }
        }
        // a book always has a title, a year and an author: given empty, they are refused like a missing required input
        $errors = array();
        foreach (array('title' => 'Title', 'published_year' => 'Published Year', 'author_id' => 'Author Id') as $field => $label) {
            if (($changes[$field] ?? null) === '') {
                $errors[$field] = "The $label field is required";
            }
        }
        if ($errors) {
            throw ServiceError::invalid($errors);
        }
        if (($changes['isbn'] ?? null) === '') {
            $changes['isbn'] = null;
        }
        if ($changes) {
            Book::where('id', $input['id'])->update($changes);   // only this book: an update needs a where
        }
        return Book::with('author')->find($input['id']);
    }
}
```

**File `application/services/Bookshelf/DeleteBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// delete_book: destructive, so AI agents ask before calling it
#[Capability(
    name: 'delete_book',
    description: 'Deletes a book of the catalog, by its id. This cannot be undone.',
    scope: 'books:write',
    destructive: true,
    level: 1   // people whose permissions are 1 or more (administrators), and tokens with books:write
)]
class DeleteBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book to delete');

    public function handle(array $input, Context $context)
    {
        // delete() returns how many rows it deleted: 0 when there is no such book
        if (!Book::where('id', $input['id'])->delete()) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return array('deleted' => (int) $input['id']);
    }
}
```

Check:
```bash
docker compose exec dev-box php caligrafer.php capabilities | grep "_book\|_author"
```
Expected (descriptions cut here):
```text
 create_book                  books:write    writes    Adds a book to the Bookshelf catalog ...
 delete_book                  books:write    destroys  Deletes a book of the catalog, by its id. This cannot be undone.
 get_book                     public         read      Returns one book of the catalog with its author ...
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title, ...
 search_books                 public         read      Finds the books of the catalog whose title or summary contains ...
 update_book                  books:write    writes    Changes the title, year, summary, ISBN or author of a book ...
```
That is the whole security change. Everything below tells people and programs what they may do.

### Step 4 - The pages show what the visitor may do

The forms need no change to be safe: `Route::form` sends a visitor who submits to `/login`, and a reader who deletes
gets the 403 page. The views only hide what the visitor may not use.

**File `application/views/Bookshelf/books.pug`** (the form for people signed in, else a Sign in button):

```pug
extends ../default/master/site

//- The catalog: Route::page gives it $books (list_books: a page of books) and $authors (list_authors).
//- The form posts to Route::form('/books', 'create_book'): a refusal comes back here with errors(), old() and flash('error').
//- $authorized and $user (id, username, permissions) come from Route::page: who is signed in

block title
    | Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display Bookshelf
        p.cq-lead The books we love. The same catalog that the REST API and AI agents use.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')

            //- the form only for people signed in: create_book refuses visitors on every channel anyway
            if $authorized
                form(method='POST', action="{$home}books", novalidate)
                    =csrf()
                    .cq-form-grid.cq-form-grid--2
                        .cq-field
                            label(for='title') * Title
                            input#title.form-control(type='text', name='title', value=old('title'), maxlength='200')
                            if errors('title')
                                p.cq-field-error= errors('title')
                        .cq-field
                            label(for='author_id') * Author
                            select#author_id.form-select(name='author_id')
                                each $a in $authors
                                    option(value=$a['id'], selected=$a['id'] == old('author_id'))= $a['name']
                            if errors('author_id')
                                p.cq-field-error= errors('author_id')
                        .cq-field
                            label(for='published_year') * Year
                            input#published_year.form-control(type='number', name='published_year', value=old('published_year'), placeholder='1969')
                            if errors('published_year')
                                p.cq-field-error= errors('published_year')
                        .cq-field
                            label(for='isbn') ISBN
                            input#isbn.form-control(type='text', name='isbn', value=old('isbn'), placeholder='Optional', maxlength='20')
                            if errors('isbn')
                                p.cq-field-error= errors('isbn')
                    .cq-field
                        label(for='summary') Summary
                        input#summary.form-control(type='text', name='summary', value=old('summary'), placeholder='Optional', maxlength='2000')
                    button.btn.btn-primary(type='submit') Add book
                if flash('error')
                    p.cq-field-error(role='alert')= flash('error')
            else
                p.cq-auth-note
                    a.btn.btn-primary(href="{$home}login") Sign in to add books

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            h2.cq-heading Books
            if $books['total'] == 0
                p.cq-empty-state No books here yet: add the first one above.
            else
                ul.cq-rows
                    each $b in $books['data']
                        li.cq-row
                            .cq-row-main
                                p.cq-row-title
                                    a(href="{$home}books/{$b['id']}")= $b['title']
                                p.cq-row-meta
                                    a(href="{$home}books?author_id={$b['author_id']}")= $b['author']['name']
                                    | , #{$b['published_year']}
                //- the next and previous pages keep the author of the address (?author_id=2)
                - $filter = isset($_GET['author_id']) ? '&author_id=' . (int) $_GET['author_id'] : '';
                p.cq-auth-note
                    | Page #{$books['page']} of #{$books['pages']}, #{$books['total']} books
                    if $books['page'] > 1
                        |  · 
                        a(href="{$home}books?page=" . ($books['page'] - 1) . $filter) Previous
                    if $books['page'] < $books['pages']
                        |  · 
                        a(href="{$home}books?page=" . ($books['page'] + 1) . $filter) Next
            p.cq-auth-note
                a(href="{$home}books") All books
                |  · 
                a(href="{$home}authors.csv") The authors as a spreadsheet (CSV)
```

**File `application/views/Bookshelf/book.pug`** (the edit form for people signed in, Delete for administrators):

```pug
extends ../default/master/site

//- One book: Route::page gives it $book (get_book: the book with its author) and $authors (list_authors).
//- The edit form posts to Route::form (PUT, update_book), the delete form to Route::form (DELETE, delete_book)

block title
    = $book['title'] . ' · Bookshelf'

block content
    section.cq-hero.cq-hero--page
        h1.cq-display= $book['title']
        p.cq-lead
            | by #{$book['author']['name']}, #{$book['published_year']}

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')
            p= $book['summary'] ?: 'No summary yet.'
            if $book['isbn']
                p.cq-row-meta ISBN #{$book['isbn']}

            //- the edit form for people signed in, Delete for administrators (permissions 1 or more):
            //- the capabilities refuse the others on every channel, the page only hides what they may not use
            if $authorized
                form(method='POST', action="{$home}books/{$book['id']}", novalidate)
                    =csrf()
                    =methodField('PUT')
                    .cq-form-grid.cq-form-grid--2
                        .cq-field
                            label(for='title') * Title
                            input#title.form-control(type='text', name='title', value=old('title', $book['title']), maxlength='200')
                            if errors('title')
                                p.cq-field-error= errors('title')
                        .cq-field
                            label(for='author_id') * Author
                            select#author_id.form-select(name='author_id')
                                each $a in $authors
                                    option(value=$a['id'], selected=$a['id'] == old('author_id', $book['author_id']))= $a['name']
                            if errors('author_id')
                                p.cq-field-error= errors('author_id')
                        .cq-field
                            label(for='published_year') * Year
                            input#published_year.form-control(type='number', name='published_year', value=old('published_year', $book['published_year']))
                            if errors('published_year')
                                p.cq-field-error= errors('published_year')
                        .cq-field
                            label(for='isbn') ISBN
                            input#isbn.form-control(type='text', name='isbn', value=old('isbn', $book['isbn']), maxlength='20')
                            if errors('isbn')
                                p.cq-field-error= errors('isbn')
                    .cq-field
                        label(for='summary') Summary
                        input#summary.form-control(type='text', name='summary', value=old('summary', $book['summary']), maxlength='2000')
                    button.btn.btn-primary(type='submit') Save changes
                if flash('error')
                    p.cq-field-error(role='alert')= flash('error')
                if $user['permissions'] >= 1
                    form(method='POST', action="{$home}books/{$book['id']}", onsubmit="return confirm('Delete this book? This cannot be undone.')")
                        =csrf()
                        =methodField('DELETE')
                        p.cq-auth-note
                            button.btn.btn-secondary(type='submit') Delete this book
            p.cq-auth-note
                if !$authorized
                    a.btn.btn-primary(href="{$home}login") Sign in to edit this book
                    |  
                a(href="{$home}books") All books
```

`$user['permissions'] >= 1` mirrors the `level: 1` of `delete_book`.

### Step 5 - Regenerate both clients

```bash
docker compose exec dev-box php caligrafer.php client
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```
Expected: `... written: 20 capabilities ...` twice. In their comments, `create_book (writes, public)` became
`create_book (writes, books:write)`. Rebuild the SPA: `cd public/bookshelf-spa && npm run build && cd ../..`.

### Step 6 - As a visitor, then as a reader

1. Open http://localhost:8080/books: the list and a **Sign in to add books** button, no form. Open a book: **Sign in
   to edit this book**, no form, no Delete.
2. Click **Create account** (http://localhost:8080/register). Username `reader1`, password `bookshelf-2026`, twice.
   Usernames are letters and digits; passwords 8 to 72 characters. `signInAfterRegister` signs you in at once: you land
   on `/books` with **Sign out** in the top bar and the form.
3. Add *The Word for World Is Forest*, Ursula K. Le Guin, 1972: it works (`"id":12` if you followed every lesson).
   Open it: the edit form, and no **Delete**: reader1 has permissions 0.
4. Open http://localhost:8080/people: the **notAuthorized** page. reader1 is signed in, but below level 1.

The REST API obeys the same rules. As a visitor:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "x", "published_year": 2000, "author_id": 1}' -w " [%{http_code}]\n" http://localhost:8080/api/books
curl -s -o /dev/null -H "Accept: application/json" -w "[%{http_code}]\n" http://localhost:8080/api/books
```
Expected:
```text
{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}} [401]
[200]
```
From the command line, `call --guest` calls as a visitor (without it, `call` has full access, like any command you run
on your own computer):
```bash
docker compose exec dev-box php caligrafer.php call create_book title=Ghost published_year=2001 author_id=1 --guest
```
Expected:
```json
{
    "error": {
        "code": "unauthenticated",
        "message": "Sign in or send a personal access token"
    }
}
```

### Step 7 - Tokens with just the rights they need

Token #2 (`books:read`, `read -rs PARTNER` if your terminal lost it) now sees only the reads:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $PARTNER" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $PARTNER" -d '{"title": "x", "published_year": 2000, "author_id": 1}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected:
```text
"name":"get_book"
"name":"list_authors"
"name":"list_books"
"name":"register_user"
"name":"search_books"
{"error":{"code":"forbidden","message":"This needs the scope books:write"}} [403]
```
The public tools (`register_user` is the sign-up, which only answers web pages), and a 403 on the write: lesson 07's
promise kept.

Token #1 (`*`, lesson 02) can do everything: revoke it.
```bash
docker compose exec dev-box php caligrafer.php token revoke 1
```
Expected: ` Token #1 revoked`. The `tools/list` of lesson 08 with `$TOKEN` now answers 401 `Unauthorized`.

An **application token** for Claude Code, which reads and writes:
```bash
docker compose exec dev-box php caligrafer.php token create "Claude Code" --scopes books:read,books:write
```
Expected: ` Token #3 "Claude Code" created with books:read books:write`, then the token. `read -rs WRITER`, paste it.

A **token for reader1** (`--user 1`: reader1 is the first account), with every books scope:
```bash
docker compose exec dev-box php caligrafer.php token create "librarian agent" --scopes "books:*" --user 1
```
Expected: ` Token #4 "librarian agent" created with books:*`. `read -rs LIBRARIAN`, paste it.

Add a book with the application token, then try to delete it with each token (use the id the first command prints):
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $WRITER" -d '{"title": "Token test", "published_year": 2026, "author_id": 3}' -w " [%{http_code}]\n" http://localhost:8080/api/books
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $LIBRARIAN" -w " [%{http_code}]\n" http://localhost:8080/api/books/13
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $WRITER" -w " [%{http_code}]\n" http://localhost:8080/api/books/13
```
Expected:
```text
{"data":{"id":13,"title":"Token test",...}} [201]
{"error":{"code":"forbidden","message":"This needs the scope books:write and permission level 1"}} [403]
{"data":{"deleted":13}} [200]
```
`books:*` includes `books:write`, but the librarian acts for reader1, who has permissions 0. The application token has
the scope and no person, so no level applies. The librarian's `tools/list` has no `delete_book`.

Connect Claude Code with its own token in place of the revoked one:
```bash
claude mcp remove bookshelf
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer $WRITER"
```

### Step 8 - The first administrator

The first administrator is set in the database, once:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "UPDATE users SET permissions = 1 WHERE username = 'reader1'"
```
(The password warning is harmless.) Reload the catalog: you are signed out. A change to a user's row ends their
session: **sign in again** as reader1. Now every book has **Delete this book**, and http://localhost:8080/people lists
`reader1`, `Administrator`. The librarian's `tools/list` now has `delete_book`: it acts for reader1.

The next administrators need no SQL: `update_user`, a framework capability for administrators, changes `permissions`
(an agent with a token of reader1 that has `users:write`, or the framework's admin pages). Nobody can give a level
above their own, and a token without `--user` cannot manage people at all.

### Step 9 - Who did what

```bash
docker compose exec dev-box php caligrafer.php audit --limit 8
```
Expected (your times differ):
```text
 2026-11-07 23:49:23  rest  token  3            delete_book                  ok
 2026-11-07 23:49:23  rest  token  4            delete_book                  forbidden  This needs the scope books:write and permission level 1
 2026-11-07 23:49:23  rest  token  3            create_book                  ok
 2026-11-07 23:49:06  rest  token  2            delete_book                  forbidden  This needs the scope books:write and permission level 1
 2026-11-07 23:49:06  rest  token  2            create_book                  forbidden  This needs the scope books:write
 2026-11-07 23:48:56  web   user   1            delete_book                  forbidden  This needs the scope books:write and permission level 1
 2026-11-07 23:48:56  web   user   1            create_book                  ok
 2026-11-07 23:48:48  web   guest  -            register_user                ok
```
(The line `web user 1 delete_book forbidden` is there if you tried to delete as reader1 before step 8.) And the
tokens:
```bash
docker compose exec dev-box php caligrafer.php token list
```
Expected:
```text
 #4    active   librarian agent                books:*  user 1  last used 2026-11-07 23:49:33
 #3    active   Claude Code                    books:read books:write  last used 2026-11-07 23:49:23
 #2    active   reading partner                books:read  last used 2026-11-07 23:49:06
 #1    revoked  my agent                       *  last used 2026-11-07 23:41:55
```
The other experiences: the finder still works for visitors (it only reads). The SPA answers **Add book** from a
visitor with `Sign in or send a personal access token`; sign in on http://localhost:8080, and **Add book** works on
http://localhost:5173 too (the cookie belongs to `localhost`, and the proxy forwards it).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | An error that ends with `Table 'caligrafy.users' doesn't exist` | Step 1 was skipped | Run the users script |
> | `Invalid credentials` on the sign-in page | A wrong username or password | Usernames are case sensitive; after 5 failures wait 15 minutes |
> | Signed in, but no **Sign out** and no form | The view reads another variable than `$authorized` | Compare the views with step 4 |
> | The form still shows to visitors | `if $authorized` is missing, or its indentation is wrong (Pug nests by indentation) | Compare with step 4 |
> | No **Delete** for the administrator | You did not sign in again after the `UPDATE` | Sign out, sign in |
> | Sign up says the inputs are invalid | A space or a dash in the username, or a password under 8 characters | Letters and digits; 8 to 72 characters |
> | 401 with a token you just created | The variable is empty or cut | `echo ${#WRITER}` prints 45; paste the whole `cq_...` value again |
> | Claude Code answers `Unauthorized` | It uses the revoked token | Remove and add the server again (end of step 7) |

---

## Check

- [ ] `web.php`, `people.pug`, the three services and the two views are identical to the File blocks.
- [ ] `capabilities`: the 4 reads `public`; `create_book`, `update_book`, `delete_book` show `books:write`; `DeleteBook.php`
      has `level: 1` and `destructive: true`.
- [ ] As a visitor: no form, `/people` sends to `/login`, `POST /api/books` answers 401.
- [ ] As reader1 (level 0): the form, no Delete, `/people` is notAuthorized; as administrator: Delete and `/people`.
- [ ] Token #2 sees 5 tools and gets 403 on writes; the librarian (#4) gets 403 on delete until reader1 is an
      administrator; the application token (#3) deletes.
- [ ] No controller in `application/controllers/Bookshelf/`; no password, hash or token in a file.

## With Claude

In `claude`, in the `bookshelf` folder:
```text
/caligrafy-quill:db application/models/Auth/create_db.sql
```
then:
```text
Turn on people and permissions in Bookshelf. In web.php: uncomment Auth::authentication('User', 'users'); replace the
commented "Auth Routes" block with one line, Route::auth(['home' => '/books', 'signInAfterRegister' => true]) (no
AuthController routes); add Route::page('/people', 'Bookshelf/people', ['level' => 1, 'data' => ['people' =>
'list_users']]) after the Route::download line, and its view (title band "People", one cq-row per person: username,
"Administrator" when permissions >= 1 else "Reader", and created_at). Capabilities: remove public: true from
create_book and update_book; delete_book gets level: 1 in its place; the reads stay public. Views: books.pug shows its
form only if $authorized, else a "Sign in to add books" button to /login; book.pug shows the edit form only if
$authorized and the delete form only if $user['permissions'] >= 1, else a "Sign in to edit this book" button.
Regenerate public/js/capabilities.js and public/bookshelf-spa/src/capabilities.js. Do not create tokens or users.
```

**Check** before you accept:
- [ ] The protection is in the capabilities, not only in the views: the visitor curl of step 6 answers 401.
- [ ] `web.php` has `Route::auth([...])` and no `AuthController` route; no new controller.
- [ ] The views use `$authorized` and `$user`, and no PHP block that reads the session.
- [ ] No password, hash or token in the chat or a file.
- [ ] Steps 6 to 8 give the same results: do them yourself, they are the proof.

If Claude writes a controller that checks `guard()` and leaves `public: true` in the capabilities, the page is safe but
REST, the agents and the SPA are not: ask it to close the capabilities instead. If it gives the librarian token no
`--user`, the level does not apply to it.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 11: sign-in, scopes, levels and tokens"
git tag lesson-11
```
Reference: [`checkpoints/lesson-11`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-11).
After copying a checkpoint, run step 1 again (the users table) and create your account. Learn more:
[9.1 Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication) and the tokens section of
[11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Exercise

1. Add a page `/mine` that only signed-in people may open, with the title "Welcome, <username>". Which option of
   `Route::page` do you use, and where does the username come from?
2. A token created with `--scopes books:read,books:write` and no `--user` deletes a book. reader1, signed in with
   permissions 0, cannot. Why?

<details><summary>Show the solution</summary>

1. `signedIn`, and the `$user` that every `Route::page` view gets. In `web.php`, in `// BOOKSHELF PAGES`:
   ```php
       Route::page('/mine', 'Bookshelf/mine', ['signedIn' => true]);
   ```
   and `application/views/Bookshelf/mine.pug`:
   ```pug
   extends ../default/master/site

   block content
       section.cq-hero.cq-hero--page
           h1.cq-display Welcome, #{$user['username']}
   ```
   A visitor who opens http://localhost:8080/mine goes to `/login`; reader1 sees `Welcome, reader1`. The page needs no
   `data`: `$user` is there on every page. (Not in the checkpoint: delete both before you commit.)
2. The level applies to people, and to tokens created with `--user`. A token without `--user` is an application token:
   only its scopes count, and it has `books:write`. reader1 is a person with permissions 0, below the `level: 1` of
   `delete_book`.
</details>

---
Previous: [Lesson 10 - Phones and tablets](10-Phones-and-Tablets) · Next: [Lesson 12 - Ship it: tests, review, doctor](12-Ship-It)
