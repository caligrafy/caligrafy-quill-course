# Lesson 06 - Web pages

**In this lesson** Bookshelf gets its first experience for people: a catalog page (the books, page by page, filtered by
author, and a form to add one) and a page per book (edit it, delete it), both rendered on the server. Each is one line
of `web.php`: **`Route::page`** shows a view with the data of a capability, **`Route::form`** sends a form to a
capability, **`Route::download`** offers its result as a file. You write the views, and no controller.

**Before you start:** lesson 05 is done (tag `lesson-05`): `capabilities` lists 19 capabilities, and
`docker compose ps` shows the 3 containers running.

**Time:** 60 to 75 minutes.

---

## New concepts

**View and Pug.** A **view** is the HTML of a page. Caligrafy writes views in **Pug**, files ending in `.pug` in
`application/views/`. Pug is a short way to write HTML: no closing tags, and the **indentation** says what is inside
what. In Caligrafy the values in a view are **PHP**.

| Pug | HTML |
|---|---|
| `h1.cq-display Bookshelf` | `<h1 class="cq-display">Bookshelf</h1>` |
| `.cq-field` | `<div class="cq-field"></div>` (a `.` without a tag name is a `div`) |
| `input#title.form-control(type='text', name='title')` | `<input id="title" class="form-control" type="text" name="title">` |
| `h1= $book['title']` | the value, **escaped** (a `<` is shown as text, never run) |
| `p by #{$book['author']['name']}` | text with a value inside, escaped |
| `a(href="{$home}books/{$b['id']}")` | an attribute built from values (a PHP string in double quotes) |
| `= $book['title'] . ' · Bookshelf'` | PHP joins strings with `.`, never `+` |
| `each $b in $books['data']` / `if errors('title')` | repeats the indented lines / shows them only when true |
| `- $filter = ...;` | a line of PHP, nothing shown |

Indent with **spaces only**, 4 per level: a tab or one space too many breaks the page.

**Master.** Every page starts with `extends ../default/master/site`: the master of Caligrafy's own pages. It holds the
head, the stylesheets, the light **top bar** (the Caligrafy mark, *Sign in* and *Create account*; under a **Menu**
button on phones) and the footer. The page fills **blocks**: `title` (the name in the browser tab) and `content`. The
master gives each view `$home`, the address of the site (`http://localhost:8080/`): build every link from it.

**Caligrafy's look.** Classes of `public/css/layout.css` and `look.css` that every page uses, with no CSS to write
([page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents)):

| Markup | What it is |
|---|---|
| `section.cq-hero.cq-hero--page > h1.cq-display + p.cq-lead` | the **title band** of a page |
| `section.cq-band.cq-band--tight > .cq-inner.cq-inner--narrow` | a **band**: a full-width section, its content in a centered column |
| `.cq-form-grid.cq-form-grid--2 > .cq-field > label + input.form-control` | labeled fields, two columns from tablets up, one on phones |
| `button.btn.btn-primary` / `.btn-secondary` | the black button of the main action / a grey one |
| `p.cq-field-error` / `p.cq-alert` | a message of a refusal / a confirmation |
| `ul.cq-rows > li.cq-row > .cq-row-main (p.cq-row-title + p.cq-row-meta)` | **rows**: a list without cards |
| `p.cq-empty-state`, `p.cq-auth-note` | what to do when a list is empty; a small centered note |
| `section.cq-hero > h1.cq-display + p.cq-lead + .cq-cta` | a full-screen message (the Not found page) |

**`Route::page` with data.** `Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']])` shows
the view `application/views/Bookshelf/book.pug` and gives it `$book`: what `get_book` returned. The capability is
called **as the person who opens the page**, with the route variables (`{id}`) and the query string (`?page=2`) as
inputs: the same rules and checks as REST and agents. Only read-only capabilities can feed a page: opening a page never
changes data. When the capability refuses, the page does not render: a missing book answers **404** with the view
named in `notFound` (it gets `$message`, the capability's message). **Pages never read the models**: the data of a page
comes from capabilities.

**`Route::form`.** `Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added'])`
receives the form's POST and calls `create_book` as the visitor, with the fields of the form (and the route
variables) as inputs, then:

| The capability | The browser goes to | The next page has |
|---|---|---|
| succeeds | `success`, its `{id}` filled from the result | `flash('success')`: `Book added` |
| refuses the inputs | back to the page of the form | `errors('title')`: the message of each field; `old('title')`: what was typed; `flash('error')`: the message of the refusal |
| finds no such book | the error page, 404 | |

These three helpers last **one request**: the page right after the redirect.

**CSRF, PUT and DELETE.** Caligrafy refuses a browser POST that does not carry the CSRF token of the visitor's session
(`[ERROR 3000] Security Error - Cross Site Forgery`, 403): it proves the form came from your site. `=csrf()` writes that
token as a hidden field. HTML forms only send GET and POST: `=methodField('PUT')` adds a hidden field `_method`, and
Caligrafy treats the POST as a PUT, which the `Route::form` with `'method' => 'PUT'` answers.

**A session only when a page needs one.** The CSRF token lives in the visitor's **session**, which a cookie
(`PHPSESSID`) finds again at the next request. A page that prints a form with `=csrf()` starts a session, and so does
every POST, PUT or DELETE; a visit that only reads gets no cookie.

**`Route::download`.** `Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv'])` turns a
read-only capability into a file: a CSV (comma-separated values, which every spreadsheet opens), one line per row, with
a first line of column names.

**`Route::redirect`.** `Route::redirect('/', '/books')` sends the visitors of `/` to the catalog.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The routes

In `application/config/routing/web.php`, replace the first route, `Route::page('/', 'default/index');` and its
comment, with the redirect, and add the `// BOOKSHELF PAGES` block above `// MUST NOT BE REMOVED`. The file is then:

**File `application/config/routing/web.php`** (the redirect of `/` and the six lines of the Bookshelf pages):

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
    Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books', 'authors' => 'list_authors']]);
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

- `/books` gets two pieces of data: `$books` (a page of `list_books`: it reads `?author_id=` and `?page=` from the
  address) and `$authors` (`list_authors`, for the form's list of authors).
- `POST /books` is the add form: on success, the new book's page.
- `/books/{id}` gets `$book` from `get_book` (with the `{id}` of the address) and `$authors`; a missing book shows
  `Bookshelf/notfound`, status 404.
- `PUT` and `DELETE /books/{id}` are the edit and delete forms of that page: the same address, other methods.
- `/authors.csv` is the download.

### Step 2 - The catalog page

Create the folder `application/views/Bookshelf` and:

**File `application/views/Bookshelf/books.pug`** (the form, the rows of the page, and the links):

```pug
extends ../default/master/site

//- The catalog: Route::page gives it $books (list_books: a page of books) and $authors (list_authors).
//- The form posts to Route::form('/books', 'create_book'): a refusal comes back here with errors(), old() and flash('error')

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

- The form posts to `{$home}books` with `=csrf()`. Each field shows `old(...)` (empty on a normal visit) and, under it,
  `errors(...)` when `Route::form` sent the form back.
- `$books['data']` holds the books of the page, each with its `author`; `$books['total']`, `['page']` and `['pages']`
  are the rest of the page of lesson 04. The name of the author is a link to `?author_id=<id>`: the same page, filtered.
- `Previous` and `Next` show only when there is another page; they keep the author of the address.

### Step 3 - Try the catalog

Open **http://localhost:8080/** : the browser goes to `/books`. You see the title **Bookshelf**, the form (Title, Author
with the 3 authors, Year, ISBN, Summary, **Add book**), then **Books**: the 5 books sorted by title, each with
"author, year", and `Page 1 of 1, 5 books`.

1. Type the year `2099`, leave the title empty, and click **Add book**. The page comes back with
   `The Title field is required` under Title, `The Published Year field needs to be a numeric value, equal to, or
   lower than 2026` under Year, `2099` still in Year, and `Some of the inputs are invalid` under the form. These are
   the messages of lesson 05: the same rules, now on a page. Reload: the messages are gone (they last one request).
2. Add **The Dispossessed**, author Ursula K. Le Guin, year 1974, ISBN `978-0-06-051275-0`. The browser goes to its
   page, `/books/7` (7 if you followed every step: ids are never reused), with `Book added` above the summary.
3. Back on the catalog, click **Octavia E. Butler** under Kindred: the address is `/books?author_id=2`, and only Kindred
   and Parable of the Sower are listed. **All books** brings the whole list back.

The same from the terminal: the first command shows where `/` sends visitors, the second the titles of the filtered
page.
```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost:8080/
curl -s "http://localhost:8080/books?author_id=2" | grep -oE 'books/[0-9]+">[^<]*'
```
Expected:
```text
302 http://localhost:8080/books
books/3">Kindred
books/4">Parable of the Sower
```

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | An error page that names `books.pug` and a line | An indentation error (a tab, or one space too many) | Indent with 4 spaces per level, as in the File block |
> | `[ERROR 3000] Security Error - Cross Site Forgery` when you add a book | `=csrf()` is missing from the form | Add it under `form(...)`, as in the File block |
> | `Route::page('Bookshelf/books'): no capability named ...` | A capability name in the `Route::page` line is wrong | Compare with step 1: `'books' => 'list_books'` |
> | `http://localhost:8080/` still shows the Caligrafy welcome page | The first route still shows `default/index` | Step 1: `Route::redirect('/', '/books');` |
> | The messages never show under the fields | The view reads a variable `$errors` instead of the helper `errors('title')` | Compare with the File block |

### Step 4 - The book page

**File `application/views/Bookshelf/book.pug`** (the book, its edit form and its delete form):

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

            //- each field shows what was typed after a refusal (old), else what is saved
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

            form(method='POST', action="{$home}books/{$book['id']}", onsubmit="return confirm('Delete this book? This cannot be undone.')")
                =csrf()
                =methodField('DELETE')
                p.cq-auth-note
                    button.btn.btn-secondary(type='submit') Delete this book
                    |  
                    a(href="{$home}books") All books
```

- `$book` is what `get_book` returned: the book with its `author`. The title band shows what is saved; each field
  shows `old('title', $book['title'])`: what was typed when `Route::form` sent the form back, else the saved value.
- The edit form carries `=csrf()` and `=methodField('PUT')`; the delete form `=csrf()` and `=methodField('DELETE')`,
  and asks the browser to confirm first (`onsubmit`). Both post to the address of the page.
- `$book['summary'] ?: 'No summary yet.'`: the summary, or this text when it is empty.

### Step 5 - The Not found page

**File `application/views/Bookshelf/notfound.pug`** (the message of the capability that refused):

```pug
extends ../default/master/site

//- The notFound view of Route::page: $message is the message of the capability that refused (get_book)

block title
    | Not found · Bookshelf

block content
    section.cq-hero
        h1.cq-display Not found
        p.cq-lead= $message
        .cq-cta
            a.btn.btn-primary(href="{$home}books") All books
```

### Step 6 - Try the book page

Open the page of The Dispossessed (`/books/7`): the title, `by Ursula K. Le Guin, 1974`, `No summary yet.`,
`ISBN 978-0-06-051275-0`, and the form filled with the book.

1. Empty the Title and click **Save changes**. The page comes back: the title band still shows **The Dispossessed**
   (what is saved), the Title field is empty (what you sent), `The Title field is required` is under it and
   `Some of the inputs are invalid` under the form.
2. Type the title back, empty the ISBN, add the summary `An anarchist physicist crosses to the twin world.` and save.
   You are back on `/books/7` with `Saved`, the summary, and no ISBN line: `update_book` stored the empty ISBN as none.
3. On the catalog, add a book `A book to delete` (Italo Calvino, 2000). On its page (`/books/8`), click
   **Delete this book**, then **OK**. You are on the catalog, with `Book deleted` and `Page 1 of 1, 6 books`.
4. Open http://localhost:8080/books/99: a full-screen **Not found** with `There is no book #99` and an **All books**
   button.

Check the statuses, then the session cookie, from the terminal:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/books/1
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/books/99
curl -s -D - -o /dev/null http://localhost:8080/books/1 | grep -i set-cookie
```
Expected: `200`, `404`, then `Set-Cookie: PHPSESSID=...; path=/` (your id differs): the page prints a form with
`=csrf()`, so it starts a session.

Last, the phone check. In the browser's developer tools (F12), turn on the device toolbar and choose a width of
375 px: both pages show one column, the links of the top bar open under **Menu**, and nothing scrolls sideways.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | Save shows `[ERROR 1000] Invalid Http Request Method: this address answers GET, PUT, DELETE` (405) | `=methodField('PUT')` is missing, so the form sends a POST | Add it under `=csrf()` |
> | **Delete this book** shows `Saved` and the book stays | The delete form has `methodField('PUT')`, so it calls `update_book` with no changes | `=methodField('DELETE')` in the second form |
> | Save shows `[ERROR 1005] Page Not Found` (404) | The book was deleted in the meantime | Open the catalog again |
> | `/books/99` shows `[ERROR 1005] Page Not Found` instead of the Not found page | `'notFound' => 'Bookshelf/notfound'` is missing | Compare with step 1 |
> | `Route::form: '/books/{book_id}' needs {book_id}, which neither the result nor the route has` | The `success` path names a value that the capability does not return | `'success' => '/books/{id}'` |

### Step 7 - The download

Click **The authors as a spreadsheet (CSV)** under the list: the browser saves `authors.csv`. From the terminal:
```bash
curl -s -i http://localhost:8080/authors.csv
```
Expected, among the headers, `Content-Disposition: attachment; filename="authors.csv"` and
`Content-Type: text/csv; charset=utf-8`, then the file (your dates differ):
```text
id,name,bio,created_at,modified_at
3,Italo Calvino,"Italian writer of playful, inventive novels and short stories.",2026-11-07 23:24:20,2026-11-07 23:24:20
2,Octavia E. Butler,"American science fiction author, the first to receive a MacArthur fellowship.",2026-11-07 23:24:20,2026-11-07 23:24:20
1,Ursula K. Le Guin,"American author of science fiction and fantasy, known for the Earthsea books.",2026-11-07 23:24:20,2026-11-07 23:24:20
```
A bio with a comma is put in quotes, so the spreadsheet keeps it in one cell. The rows are those of `list_authors`,
in its order: the same capability as the form's list of authors, as one more experience.

---

## Check

- [ ] `web.php` and the three views are identical to the File blocks; `application/controllers/` has no Bookshelf file.
- [ ] `/` redirects to `/books`; the catalog lists the books with `Page 1 of 1`; `?author_id=2` filters it.
- [ ] A refused form comes back with the messages under the fields and what you typed; a reload clears them.
- [ ] Adding goes to the new book's page with `Book added`; saving shows `Saved`; deleting goes to the catalog with
      `Book deleted`.
- [ ] `/books/99` answers 404 with the Not found page; `/authors.csv` downloads the 3 authors.
- [ ] Both pages work at 375 px.

## With Claude

```text
Add the Bookshelf web pages on the existing capabilities, rendered on the server, with no controller and no
JavaScript of our own. In application/config/routing/web.php replace Route::page('/', 'default/index') with
Route::redirect('/', '/books') and add a "// BOOKSHELF PAGES" block above Route::run():
Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books', 'authors' => 'list_authors']]);
Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);
Then the views in application/views/Bookshelf/ (extends ../default/master/site, Caligrafy's look, 4-space indents):
books.pug (flash('success'), the add form with =csrf(), each field with old() and errors(), flash('error'); the rows of
$books['data'] with the author's name linking to ?author_id=; "Page x of y, n books" with Previous/Next; links to all
books and authors.csv), book.pug (the book, an edit form with =csrf() and =methodField('PUT'), a delete form with
=methodField('DELETE') and a confirm), notfound.pug ($message and an All books button).
```

**Check** before you accept:
- [ ] No file in `application/controllers/`; no rule repeated in a view; the views read `errors()`, `old()` and
      `flash()`, never a `$errors` variable.
- [ ] Every form has `=csrf()`; the edit and delete forms have `methodField('PUT')` and `methodField('DELETE')`.
- [ ] String joins in the views use `.`, not `+`.
- [ ] The checks of steps 3, 6 and 7 give what the lesson describes, at desktop width and at 375 px.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 06: the catalog page, the page of a book, the authors as CSV"
git tag lesson-06
```
Reference: [`checkpoints/lesson-06`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-06).

## Quiz

1. Where is the rule "a title is required" written, and which places show its message?
2. After a refused save, the title band shows the saved title but the Title field is empty. Where does each come from?
3. You save with an empty title, then open the catalog and come back. Is the message still there? Why?
4. Why can `list_books` feed a page and a download, but `create_book` cannot?

<details><summary>Show the solution</summary>

1. Once, in the `$rules` of `create_book` (and for an update, in `handle()` of `update_book`). Its message appears under
   the field of both pages, for `curl` (lesson 05) and for AI agents.
2. The title band shows `$book`, which `get_book` reads again on every visit: what is saved. The field shows
   `old('title', ...)`: what the visitor typed, which `Route::form` kept for the next page.
3. No. `errors()`, `old()` and `flash()` last one request: the page right after the redirect of `Route::form`.
4. Opening a page or a link must never change data: `Route::page` and `Route::download` accept only `readOnly`
   capabilities. A change goes through a form, `Route::form`, with its CSRF token.
</details>

---
Previous: [Lesson 05 - Rules and changes](05-Rules-and-Changes) · Next: [Lesson 07 - The REST API](07-The-REST-API)
