# Lesson 13 - Capstone: one more feature, everywhere

**In this lesson** you add **Loans** to Bookshelf (who borrowed which book, until when) in one go: a new table, three
capabilities written with the query builder, and at once a page with its forms, REST routes, the JS clients of the
Vue apps and tools for AI agents, with their own scopes and a token for a "loans desk" agent. It is everything the
course taught, on a new feature.

**Before you start:** lesson 12 is done (tag `lesson-12`), `docker compose ps` shows the 3 containers running, and
you have the account `reader1@example.com` and the "Claude Code" token (#3, `books:read books:write`) of lesson 11.

**Time:** 60 to 90 minutes.

---

## New concepts

There is almost nothing new: that is the point. A new feature is the same pieces every time. The one new option,
`inputs`, is explained below.

| Piece | Loans | Lesson |
|---|---|---|
| A table and a model | `application/models/Loans/create_db.sql`, `Loan` | 03 |
| Capabilities with rules and refusals | `lend_book`, `return_book`, `list_loans` | 04, 05 |
| A page and its forms | `Route::page('/loans', ...)`, three `Route::form` | 06 |
| REST and agents | `Route::capability`, the `/mcp` of lesson 01 | 07, 08 |
| The JS clients | `php caligrafer.php client` | 09, 10 |
| Who may call | scopes `loans:read` and `loans:write`, `signedIn` with `comeBack` | 11 |
| Checks | curl, `tools/call`, `audit`, the tests | 12 |

**A new table, a first version.** `loans` is new, so it gets its own `create_db.sql` with `CREATE TABLE IF NOT
EXISTS`, in its own folder `application/models/Loans/`. The deploy step runs every `create_db.sql` on the server
(lesson 14). Once `loans` holds data, a change to it is a migration (lesson 05), never an edit of this script.

**A new resource, new scopes.** Loans get `loans:read` and `loans:write` instead of reusing `books:*`: a token for the
catalog does not see loans, and a "loans desk" token can lend books without changing the catalog. The capabilities
are not `public` (a visitor sees no loans) and have no `level` (any signed-in person may lend).

**409 Conflict.** `ServiceError::conflict('...')` refuses a call that clashes with the current state: lending a book
that is already lent. It answers `409`, "not now", unlike `422` (the inputs are wrong) and `404` (no such thing).

**A computed value.** The caller gives a number of `days` (1 to 60, 14 when empty); the capability computes the due
date, `due_on`. The rule lives in one place: the page, a script and an agent get the same due date.

**`inputs`: values only the server sets.** The loans page lets a librarian type any borrower. A member who clicks **Borrow
this book** on a book must not be able to borrow in someone else's name, so the form sends no name at all:
`'inputs' => function () { return ['borrower' => Auth::user()->username ?? '', 'days' => 14]; }`. What `inputs` gives
**replaces** what the form sent, and only route variables win over it. It is an array, or a function that returns one
(it gets the form's fields). `lend_book` does not change: it still checks its rules and who calls it. A visitor gets
`''`, and is refused before those rules run: `lend_book` is not public.

`Auth::user()->username` is the member's email address (lesson 11), so `borrower` has room for 254 characters, and
everyone who is signed in sees that address on the loans page. That is fine for a book club. In an app where people
do not know each other, keep a display name in another column and show that.

**Models and arrays.** `Loan::where(...)->first()` gives a `Loan` model: its columns are properties
(`$open->borrower`). `with('book')` adds the related row as an array (`$loan['book']['title']` in a view).
`arrays()` keeps the rows as arrays, which a capability can add a value to (`overdue`).

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The table

**File `application/models/Loans/create_db.sql`** (one row per loan; `returned_at` stays empty while the book is out):

```sql
/* Loans: who borrowed which book, the first version of the table.
 * Run it with: docker compose exec dev-box php caligrafer.php sql application/models/Loans/create_db.sql
 * Safe to run again: CREATE TABLE IF NOT EXISTS skips a table that exists. The deploy step runs it on the server.
 * Never edit it once it ran on a database with data: change the table with a migration.
 */

CREATE TABLE IF NOT EXISTS loans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    borrower VARCHAR(254) NOT NULL,
    due_on DATE NOT NULL,
    returned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loans_book_id (book_id),
    CONSTRAINT fk_loans_book_id FOREIGN KEY (book_id) REFERENCES books (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

- `book_id` is a foreign key to `books`; `ON DELETE CASCADE`: deleting a book deletes its loans.
- `due_on` is a `DATE` (no time); `returned_at` is `NULL` until the book comes back.

Run it:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Loans/create_db.sql
docker compose exec mysql mysql -uroot -proot caligrafy -e "DESCRIBE loans"
```
Expected: ` application/models/Loans/create_db.sql run on caligrafy`, then the 7 columns (the password warning is
harmless):
```text
Field	Type	Null	Key	Default	Extra
id	int unsigned	NO	PRI	NULL	auto_increment
book_id	int unsigned	NO	MUL	NULL	
borrower	varchar(254)	NO		NULL	
due_on	date	NO		NULL	
returned_at	datetime	YES		NULL	
created_at	datetime	NO		CURRENT_TIMESTAMP	DEFAULT_GENERATED
modified_at	datetime	NO		CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP
```

### Step 2 - The model

**File `application/models/Loans/Loan.php`**:

```php
<?php

use Caligrafy\Model;

// One Loan = one row of the loans table: a book lent to someone, returned or not.
// Loan::with('book') adds the row of books whose id is book_id
class Loan extends Model {

    // one public property per column of the table
    public $id;
    public $book_id;
    public $borrower;
    public $due_on;
    public $returned_at;
    public $created_at;
    public $modified_at;
}
```

### Step 3 - The three capabilities

```bash
docker compose exec dev-box php caligrafer.php service Loans/LendBook --scope loans:write
docker compose exec dev-box php caligrafer.php service Loans/ReturnBook --scope loans:write
docker compose exec dev-box php caligrafer.php service Loans/ListLoans --scope loans:read --read-only
```
Each prints ` Created application/services/Loans/<Name>.php: capability '<name>' (scope ...)`. Replace their content:

**File `application/services/Loans/LendBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// lend_book: records that someone borrowed a book, for a number of days
#[Capability(
    name: 'lend_book',
    description: 'Lends a book of the catalog to someone for a number of days (14 by default) and returns the loan with its book. Refused when the book is already lent: use list_loans to see what is out.',
    scope: 'loans:write'
)]
class LendBook extends Service
{
    public $rules = array(
        'book_id' => 'required|integer|exists,books.id',
        'borrower' => 'required|max_len,254',
        'days' => 'integer|min_numeric,1|max_numeric,60'
    );
    public $filters = array('borrower' => 'trim');
    public $descriptions = array(
        'book_id' => 'The id of the book (list_books, search_books)',
        'borrower' => 'Who borrows it: a name, or the email address of a member',
        'days' => 'Optional: for how many days, 1 to 60, 14 by default'
    );

    public function handle(array $input, Context $context)
    {
        // one open loan per book: an open loan has no returned_at
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if ($open) {
            throw ServiceError::conflict("This book is already lent to {$open->borrower}");
        }
        $days = (int) ($input['days'] ?? 14);   // the rule lives here: every experience gets the same due date
        $id = Loan::insert(array(
            'book_id' => $input['book_id'],
            'borrower' => $input['borrower'],
            'due_on' => date('Y-m-d', strtotime("+$days days"))
        ));
        return Loan::with('book')->find($id);
    }
}
```

**File `application/services/Loans/ReturnBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// return_book: closes the open loan of a book
#[Capability(
    name: 'return_book',
    description: 'Records that a lent book came back, by the id of the book. Returns the closed loan with its book.',
    scope: 'loans:write'
)]
class ReturnBook extends Service
{
    public $rules = array('book_id' => 'required|integer');
    public $descriptions = array('book_id' => 'The id of the book that came back');

    public function handle(array $input, Context $context)
    {
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if (!$open) {
            throw ServiceError::notFound("Book #{$input['book_id']} is not lent");
        }
        Loan::where('id', $open->id)->update(array('returned_at' => date('Y-m-d H:i:s')));
        return Loan::with('book')->find($open->id);
    }
}
```

**File `application/services/Loans/ListLoans.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_loans: the books that are out, who has them, and whether they are late
#[Capability(
    name: 'list_loans',
    description: 'Lists the books that are lent and not returned yet, the soonest due first: id, book_id, borrower, due_on, overdue (true when the due date is past) and the book (id, title, published_year...).',
    scope: 'loans:read',
    readOnly: true
)]
class ListLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        // two queries in all: the open loans, then the books of all of them (with); arrays(): rows we can add to
        $loans = Loan::with('book')->whereNull('returned_at')->orderBy('due_on')->arrays()->get();
        $today = date('Y-m-d');
        foreach ($loans as $i => $loan) {
            $loans[$i]['overdue'] = $loan['due_on'] < $today;
        }
        return $loans;
    }
}
```

What to notice:
- `exists,books.id` refuses a book that does not exist before `handle()` runs (lesson 05).
- The open loan of a book is the one with no `returned_at`: `whereNull('returned_at')`. One open loan per book, or
  `conflict`.
- `update()` has a `where`: it changes only that loan.
- `list_loans` runs two queries whatever the number of loans: the loans, then their books (`with('book')`).

Try them from the command line (full access, like any command on your computer):
```bash
docker compose exec dev-box php caligrafer.php call lend_book book_id=42 borrower= days=90
```
Expected:
```json
{
    "error": {
        "code": "invalid",
        "message": "Some of the inputs are invalid",
        "fields": {
            "book_id": "There is no such book",
            "borrower": "The Borrower field is required",
            "days": "The Days field needs to be a numeric value, equal to, or lower than 60"
        }
    }
}
```

### Step 4 - The routes

In `application/config/routing/web.php`, add the `// LOANS` block above `// MUST NOT BE REMOVED`. The file is then:

**File `application/config/routing/web.php`** (the `// LOANS` block: a page, three forms, three REST routes):

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
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // SIGN IN, SIGN OUT: /login, /logout and /notAuthorized; after signing in, /login?return=<page>, else the catalog
    Route::auth(['home' => '/books', 'register' => false, 'messages' => ['invalid' => 'Invalid email or password']]);   // no /register: people join with the form of /join

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
    Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);   // public/<appName>/index.php, or ?app=<file>

    
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
    Route::capability('GET', '/api/loans', 'list_loans');
    Route::capability('POST', '/api/loans', 'lend_book');
    Route::capability('POST', '/api/books/{book_id}/return', 'return_book');

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

- `/loans` is `signedIn` and `comeBack`: a visitor goes to `/login?return=%2Floans` and comes back here. Its data comes
  from `list_loans` and `list_books` (for the list of books of the form).
- `Route::form('/loans', 'lend_book', ...)`: the flash message fills `{borrower}` and `{due_on}` from the answer.
- `/loans/{book_id}/return`: the route variable `{book_id}` is the input of `return_book`, on the page as on REST.
- `/books/{book_id}/borrow`: the route gives `book_id`, `inputs` gives the borrower and the days. The browser sends
  nothing but its token.

### Step 5 - The page

**File `application/views/Loans/index.pug`** (the parts of lesson 06: a title band, a form, rows, an empty state):

```pug
extends ../default/master/site

//- Loans: Route::page gives it $loans (list_loans: the books that are out) and $books (list_books: a page of books).
//- The lend form posts to Route::form('/loans', 'lend_book'), each Returned button to Route::form (return_book)

block title
    | Loans · Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display Loans
        p.cq-lead Who borrowed which book, and until when.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')

            form(method='POST', action="{$home}loans", novalidate)
                =csrf()
                .cq-form-grid.cq-form-grid--2
                    .cq-field
                        label(for='book_id') * Book
                        select#book_id.form-select(name='book_id')
                            each $b in $books['data']
                                option(value=$b['id'], selected=$b['id'] == old('book_id'))= $b['title']
                        if errors('book_id')
                            p.cq-field-error= errors('book_id')
                    .cq-field
                        label(for='borrower') * Borrower
                        input#borrower.form-control(type='text', name='borrower', value=old('borrower'), maxlength='100')
                        if errors('borrower')
                            p.cq-field-error= errors('borrower')
                    .cq-field
                        label(for='days') Days
                        input#days.form-control(type='number', name='days', value=old('days'), placeholder='14')
                        if errors('days')
                            p.cq-field-error= errors('days')
                button.btn.btn-primary(type='submit') Lend
            if flash('error')
                p.cq-field-error(role='alert')= flash('error')

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            h2.cq-heading Out now
            if count($loans) == 0
                p.cq-empty-state Every book is on the shelf.
            else
                ul.cq-rows
                    each $loan in $loans
                        li.cq-row
                            .cq-row-main
                                p.cq-row-title
                                    a(href="{$home}books/{$loan['book_id']}")= $loan['book']['title']
                                p.cq-row-meta
                                    | #{$loan['borrower']}, due #{$loan['due_on']}
                                    if $loan['overdue']
                                        strong  · late
                            .cq-row-actions
                                form(method='POST', action="{$home}loans/{$loan['book_id']}/return")
                                    =csrf()
                                    button.btn.btn-secondary(type='submit') Returned
            p.cq-auth-note
                a(href="{$home}books") All books
```

On the page of a book, add the **Borrow this book** form, under the edit form. The file is then:

**File `application/views/Bookshelf/book.pug`** (one more form, for people signed in):

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
                form(method='POST', action="{$home}books/{$book['id']}/borrow")
                    =csrf()
                    p.cq-auth-note
                        button.btn.btn-secondary(type='submit') Borrow this book
                if $user['permissions'] >= 1
                    form(method='POST', action="{$home}books/{$book['id']}", onsubmit="return confirm('Delete this book? This cannot be undone.')")
                        =csrf()
                        =methodField('DELETE')
                        p.cq-auth-note
                            button.btn.btn-secondary(type='submit') Delete this book
            p.cq-auth-note
                if !$authorized
                    a.btn.btn-primary(href="{$home}login?return=/books/{$book['id']}") Sign in to edit this book
                    |  
                a(href="{$home}books") All books
```

As a visitor:
```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost:8080/loans
```
Expected: `302 http://localhost:8080/login?return=%2Floans`. Open that address and sign in as reader1: you are on
http://localhost:8080/loans:
1. **Loans**, the form, and `Every book is on the shelf.`
2. Choose *Invisible Cities*, borrower `Sam`, days `7`, **Lend**: `Lent to Sam until 2026-11-14` (today plus 7 days:
   your date), and the row *Invisible Cities*, `Sam, due 2026-11-14`, with a **Returned** button.
3. Lend *Invisible Cities* again, to `Alex`: `This book is already lent to Sam` under the form.
4. Leave the borrower empty and type `90` days: `The Borrower field is required` and
   `The Days field needs to be a numeric value, equal to, or lower than 60` under the fields, and what you typed is
   still there.
5. Open *Invisible Cities* and click **Borrow this book**: `This book is already lent to Sam`, on that page.
6. Open *A Wizard of Earthsea* and click **Borrow this book**: you land on the loans with `It is yours until <today plus
   14 days>`, and the row says `reader1@example.com`. Click **Returned**: `Returned by reader1@example.com`.

### Step 6 - REST, with the right scopes

A token for the loans desk (`books:read` to find books, `loans:*` for every action on loans; the quotes keep zsh away
from the `*`):
```bash
docker compose exec dev-box php caligrafer.php token create "loans desk" --scopes "books:read,loans:*"
```
Expected: ` Token #6 "loans desk" created with books:read loans:*` (#6 if you ran the smoke test once with
`READ_TOKEN` in lesson 12), then the token: `read -rs DESK`, paste it. Keep `$WRITER` (the "Claude Code" token #3)
too.

Who may read the loans:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/loans
curl -s -H "Accept: application/json" -H "Authorization: Bearer $WRITER" -w " [%{http_code}]\n" http://localhost:8080/api/loans
```
Expected:
```text
{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}} [401]
{"error":{"code":"forbidden","message":"This needs the scope loans:read"}} [403]
```
Lend Kindred (book 3) to Ana for 3 days, then once more to Alex:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $DESK" -d '{"book_id": 3, "borrower": "Ana", "days": 3}' -w " [%{http_code}]\n" http://localhost:8080/api/loans
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $DESK" -d '{"book_id": 3, "borrower": "Alex"}' -w " [%{http_code}]\n" http://localhost:8080/api/loans
```
Expected (your dates differ):
```text
{"data":{"id":3,"book_id":3,"borrower":"Ana","due_on":"2026-11-10","returned_at":null,"created_at":"2026-11-07 23:58:18","modified_at":"2026-11-07 23:58:18","book":{"id":3,"title":"Kindred","published_year":1979,...}}} [201]
{"error":{"code":"conflict","message":"This book is already lent to Ana"}} [409]
```
The list, a return, and the same return again:
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $DESK" http://localhost:8080/api/loans
curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer $DESK" -w " [%{http_code}]\n" http://localhost:8080/api/books/3/return
curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer $DESK" -w " [%{http_code}]\n" http://localhost:8080/api/books/3/return
```
Expected: the two loans, the soonest due first (Kindred, due in 3 days, then Invisible Cities), each with
`"overdue":false` and its `book`; then the loan of Kindred
with a `returned_at` and `[201]` (a POST that changes data); then
`{"error":{"code":"not_found","message":"Book #3 is not lent"}} [404]`. The loans desk cannot change the catalog:
`POST /api/books` with `$DESK` answers `This needs the scope books:write` (403).

### Step 7 - The agent at the loans desk

The tools of the loans desk token:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $DESK" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected:
```text
"name":"get_book"
"name":"lend_book"
"name":"list_authors"
"name":"list_books"
"name":"list_loans"
"name":"register_user"
"name":"return_book"
"name":"search_books"
```
The three loan tools appeared without one line for MCP. Lend Kindred to Ana again, as an agent would:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $DESK" -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"lend_book","arguments":{"book_id":3,"borrower":"Ana","days":3}}}' http://localhost:8080/mcp
```
Expected: `"isError":false` and the loan, due in 3 days. Reload http://localhost:8080/loans: Kindred and Invisible
Cities are out.

The audit log:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 6
```
Expected (your times differ):
```text
 2026-11-07 23:58:19  mcp   token  6            lend_book                    ok
 2026-11-07 23:58:19  rest  token  6            create_book                  forbidden  This needs the scope books:write
 2026-11-07 23:58:19  rest  token  6            return_book                  not_found  Book #3 is not lent
 2026-11-07 23:58:19  rest  token  6            return_book                  ok
 2026-11-07 23:58:18  rest  token  6            lend_book                    conflict  This book is already lent to Ana
 2026-11-07 23:58:18  rest  token  6            lend_book                    ok
```
Every change and refusal of the loans desk, through REST and MCP, under one number. The successful `list_loans` calls
are not there: reads are logged only when refused.
To let Claude Code run the desk, connect it with this token (lesson 11 showed how to switch), start a new `claude`
session in `bookshelf` and ask `Which books are lent right now, and when are they due?`

### Step 8 - The clients, the tests

```bash
docker compose exec dev-box php caligrafer.php client
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
docker compose exec dev-box php caligrafer.php test
bash qa/bookshelf/smoke.sh
```
Expected: `... written: 23 capabilities ...` twice (`lendBook`, `listLoans`, `returnBook` are new), then
`OK (7 tests, 16 assertions)` and `All checks passed.` Rebuild the SPA so that its `dist/` has the new client:
`cd public/bookshelf-spa && npm run build && cd ../..`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `capabilities` does not list `lend_book` | The file is not in `application/services/Loans/`, or the class name differs from the file name | Compare with step 3 |
> | `Table 'caligrafy.loans' doesn't exist` | Step 1 was skipped | Run the `sql` command of step 1 |
> | `Cannot use object of type Loan as array` | `$open['borrower']` on a model | A model's columns are properties: `$open->borrower`; or add `arrays()` to the query |
> | `zsh: no matches found: books:read,loans:*` | zsh reads the `*` | Quote the scopes |
> | `403 This needs the scope loans:write` with the loans desk token | The token has other scopes | `token list` shows them; create it again, revoke the old one |
> | `POST /api/books/3/return` answers 422 with `book_id` | The route variable is not `{book_id}` | Compare with step 4 |
> | `due_on` is a day off | The due date uses the PHP time zone (`SERVER_TIMEZONE` in `.env`) | Normal near midnight; set `SERVER_TIMEZONE` to yours |
> | The page shows `Every book is on the shelf.` after a lend over REST | The page was not reloaded | Reload it |

---

## Check

- [ ] The two model files, the three capabilities, `web.php`, `Loans/index.pug` and `Bookshelf/book.pug` are identical
      to the File blocks.
- [ ] No controller, no SQL string in a capability, no `public: true` on a loan capability.
- [ ] `/loans` sends visitors to `/login?return=%2Floans`; reader1 lends, gets the conflict and the field messages on
      the page.
- [ ] **Borrow this book** lends the book to reader1, for 14 days, and says `already lent` on a book that is out.
- [ ] REST: 401 for a visitor, 403 for the "Claude Code" token, 201, 409, 404 for the loans desk token.
- [ ] `tools/list` of the loans desk token has `lend_book`, `list_loans`, `return_book`; the audit log shows
      `mcp token 6 lend_book ok`.
- [ ] Both clients have 23 capabilities; the tests and the smoke test pass.

## With Claude

In `claude`, in the `bookshelf` folder:
```text
Add Loans to Bookshelf, capabilities first, no controller.
1. application/models/Loans/create_db.sql: CREATE TABLE IF NOT EXISTS loans (id, book_id INT UNSIGNED FK to books
   ON DELETE CASCADE, borrower VARCHAR(254), due_on DATE, returned_at DATETIME NULL, created_at, modified_at); run it
   with php caligrafer.php sql. Model Loan (no namespace) in application/models/Loans/Loan.php.
2. With php caligrafer.php service, in application/services/Loans/: lend_book (loans:write; book_id
   required|integer|exists,books.id, borrower required|max_len,254 trimmed, days integer 1-60, 14 by default; conflict
   "This book is already lent to <borrower>" when the book has a loan with no returned_at; returns
   Loan::with('book')->find($id)), return_book (loans:write; book_id; not_found "Book #<id> is not lent"; sets
   returned_at), list_loans (loans:read, readOnly; the open loans with with('book'), soonest due first, each with
   overdue = due_on < today). Not public, no level. Query builder only.
3. web.php, a // LOANS block: Route::page('/loans', 'Loans/index', signedIn, comeBack, data loans => list_loans and
   books => list_books); Route::form POST /loans lend_book (success /loans, flash "Lent to {borrower} until {due_on}");
   Route::form POST /loans/{book_id}/return return_book (flash "Returned by {borrower}"); Route::form POST
   /books/{book_id}/borrow lend_book with inputs, a function returning borrower = Auth::user()->username ?? '' and
   days = 14 (success /loans, flash "It is yours until {due_on}"); REST GET /api/loans, POST /api/loans,
   POST /api/books/{book_id}/return.
4. Loans/index.pug like the catalog: a title band, the lend form with errors() and old(), the rows of the loans out
   ("<borrower>, due <date>", "late" when overdue) each with a Returned form, an empty state. In book.pug, for people
   signed in, a "Borrow this book" form that posts to books/<id>/borrow with =csrf() and no other field.
5. Regenerate both clients, run the tests and the smoke test. Do not create tokens.
```

**Check** before you accept:
- [ ] Steps 5 to 8 give the same results with Claude's files.
- [ ] The scopes are `loans:read` and `loans:write`; nothing is `public`.
- [ ] The Borrow form has no borrower field: the name comes from `inputs` in `web.php`.
- [ ] No `Database::execute()` with SQL strings, no query inside a `foreach`.
- [ ] `create_db.sql` is a new file in `application/models/Loans/`; `Bookshelf/create_db.sql` did not change.
- [ ] Claude created no token and printed none.

Then create the loans desk token yourself (step 6).

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 13: loans, everywhere"
git tag lesson-13
```
Reference: [`checkpoints/lesson-13`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-13).
After copying a checkpoint, run the `sql` command of step 1. Learn more:
[Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) and
[Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes).

## Exercise

1. Add a read-only capability `popular_books` (scope `loans:read`): the 5 books lent most often, returned or not, with
how many times. Use `withCount()` of the query builder, and call it from the command line.
2. A `Route::form` can send people to another site afterwards, as a payment page does. Add a form on
`/books/{book_id}/lookup` that lends the book to `Open Library` for 1 day, then goes to
`https://openlibrary.org/search?q=<the title>`. Which options do you use?

<details><summary>Show the solution</summary>

```bash
docker compose exec dev-box php caligrafer.php service Loans/PopularBooks --scope loans:read --read-only
```
`application/services/Loans/PopularBooks.php`:
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// popular_books: the books lent most often, with how many times
#[Capability(
    name: 'popular_books',
    description: 'Lists the 5 books lent most often, returned or not: id, title and loans_count (how many times).',
    scope: 'loans:read',
    readOnly: true
)]
class PopularBooks extends Service
{
    public function handle(array $input, Context $context)
    {
        // withCount('loans'): one more query counts the loans of every book (loans.book_id), never one per book
        $books = Book::withCount('loans')->select('id', 'title')->orderBy('title')->arrays()->get();
        usort($books, function ($a, $b) { return $b['loans_count'] <=> $a['loans_count']; });   // the counts come after the SQL
        return array_slice($books, 0, 5);
    }
}
```
```bash
docker compose exec dev-box php caligrafer.php call popular_books
```
answers Kindred with `"loans_count": 2`, then A Wizard of Earthsea and Invisible Cities with `1`, then two books with
`0`, each group in title order. The count is not a column of the SQL, so `orderBy('loans_count')` fails (`Unknown column 'loans_count'`): sort after
`get()`. `withCount('loans')` finds the table `loans` and its column `book_id` by convention. Not in the checkpoint:
delete the file before you commit.

For the second exercise, `inputs` for the two values and a function for `success`, which gets the answer of `lend_book`,
its book included:
```php
    Route::form('/books/{book_id}/lookup', 'lend_book', ['inputs' => ['borrower' => 'Open Library', 'days' => 1], 'success' => function ($loan) { return 'https://openlibrary.org/search?q=' . rawurlencode($loan['book']['title']); }]);
```
The form answers `302 https://openlibrary.org/search?q=The%20Left%20Hand%20of%20Darkness` for book 2. A real payment page
comes from your provider in the capability's answer: `'success' => function ($answer) { return $answer['url']; }`, a
function because a `{placeholder}` in `success` is encoded for a path. Not in the checkpoint either.
</details>

---
Previous: [Lesson 12 - Ship it: tests, review, doctor](12-Ship-It) · Next: [Lesson 14 - Put it online](14-Put-It-Online)
