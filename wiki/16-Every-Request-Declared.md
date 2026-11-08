# Lesson 16 - What a page or a form cannot say

**In this lesson** Bookshelf gets three requests that are neither a page nor a form: a link that goes to a book chosen
at random, an RSS feed for feed readers, and a link that gives a loan 14 more days. Each is one line of `web.php`,
**`Route::action`**, answered by a capability declared **`webOnly: true`**. You test them, you see why nothing else may
reach them, and you end with a `web.php` that names no controller at all: every request of Bookshelf is declared.

**Before you start:** lesson 15 is done (tag `lesson-15`), `docker compose ps` shows the containers running, you can
sign in as `reader1`, and Invisible Cities is lent (lesson 13).

**Time:** 45 minutes.

---

## New concepts

**What a page or a form cannot say.** `Route::page` shows a view and never changes anything. `Route::form` answers a form
with a redirect. Some requests are neither:

| The request | Why it is not a page or a form |
|---|---|
| "Surprise me": a link that goes to a book at random | it decides where to go; it shows nothing |
| An RSS feed | it answers XML: not a view, not JSON, not a CSV file |
| A link in a message: "14 more days" | a link is a GET, so it cannot carry a form's CSRF token, and it changes data |
| Elsewhere: a payment page, a sign-in between two sites, a gate | the answer depends on what the session holds |

**`Route::action`.** `Route::action('/surprise', 'surprise_me')` answers `GET /surprise` with the capability
`surprise_me`, called as the person who sent the request, with the query string, the form fields and the route variables
as inputs. When two of them give the same name, the route variable wins: `/loans/5/renew?book_id=3` is book 5, so a query
string cannot point a link at another row. Its rules, its permissions and the audit log work as for any capability.

| Option | Default | What it does |
|---|---|---|
| `method` | `'GET'` | `'POST'`, `'PUT'`, `'DELETE'` or a list |
| `signedIn`, `level`, `comeBack`, `visitorsOnly` | none | as in `Route::page` (lesson 11) |
| `back`, `error`, `signIn` | as in `Route::form` | for a refusal the capability does not answer itself |

**`Caligrafy\Web`** is what such a capability returns to choose what the browser gets:

| It returns | The browser gets |
|---|---|
| `Web::redirect('/books/3')`, or a full address | a redirect (302, or the status you give) |
| `Web::view('Bookshelf/notfound', ['message' => '...'], 404)` | a page, with `$authorized` and `$user` as in `Route::page` |
| `Web::text($xml)` | that text |
| `Web::json($data, 201)` | JSON |
| any of them `->header('Content-Type', '...')` | one more response header |
| plain data | JSON |

**A refusal it does not answer itself** is treated as in `Route::form`: invalid inputs or a conflict go back with
`errors()`, `old()` and `flash('error')`; a visitor goes to sign in; forbidden is 403; anything else 404.

**Why `webOnly: true`.** A capability normally may not read the session, and is never called by opening a link, because
every experience must be able to use it the same way. A web-only capability is the exception: it may read `session()`
and the request, sign people in with `AuthRoutes::signIn($user)`, and change data on a GET. So nothing but `Route::action`
reaches it:

| Experience | What it gets for a web-only capability |
|---|---|
| REST, `POST /capabilities/surprise_me` | `There is no capability 'surprise_me'` (404) |
| MCP, the AI agents | not in `tools/list`; `Unknown tool: surprise_me` |
| the command line, `call surprise_me` | `There is no capability 'surprise_me'` |
| the JS clients | no function for it |
| `Route::page`, `Route::form`, `$this->call()` | refused the same way |

What several experiences share stays in plain capabilities. A web-only capability calls them with
**`$this->capability('list_books', $input, $context)`**: the same person, the same rules, no copy of the reading.

**A GET that changes data** can be opened without anyone meaning to: by a mail scanner, a link preview, or an image tag
on another site. Use it only when an accidental visit does no harm: `renew_loan` sets the due date to 14 days from
today, however often it is opened. For anything else, use `'method' => 'POST'` with a form, whose CSRF token is checked.

**A JSON call to `Route::action`** (`Accept: application/json`) needs the page's `X-CSRF-Token` header, or a token with
the scope `*`: a token with narrower scopes is refused, as on a controller route. Without one it answers 401,
`[ERROR 1004]`, signed in or not. In a page, `apiHeaders()` of `public/js/services/app.js` sends the header.

**How a session signed in.** A permission level says what a person may do. `AuthRoutes::signedInBy()` says how sure you
are that this is that person, because it answers how the session was opened:

| The session was opened by | `AuthRoutes::signedInBy()` |
|---|---|
| the sign-in page of `Route::auth`, with a password | `password` |
| a passkey, through `Route::auth` | `passkey` |
| your own web-only capability, with `AuthRoutes::signIn($user, null, 'guest-card')` | `guest-card`, the word you gave |
| a page that signed in with `Auth::authorize()`, then called `AuthRoutes::rememberSignIn('guest-card')` | that word |
| the `signsIn` of `Route::form` (the join form of lesson 11) | `password`, or what `signsInWith` says |
| `AuthRoutes::signIn($user)`, without saying how | `null`: nobody recorded how |
| nobody is signed in, or the person signed out | `null` |

It matters for whatever changes how an account signs in. A sign-in that proves less (a shared card, a face that a good
photo can pass) must not be able to change the password or add a passkey, or it could keep the account for good. Read
it in a web-only capability (a plain capability has no session), and treat `null` as *not allowed*. The exercise builds it. See
[How a session signed in](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#signedinby).

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - Three capabilities

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/SurpriseMe --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Bookshelf/BooksFeed --scope books:read --read-only
docker compose exec dev-box php caligrafer.php service Loans/RenewLoan --scope loans:write
```
Each prints ` Created application/services/<Folder>/<Name>.php: capability '<name>' (scope ...)`. Replace their content:

**File `application/services/Bookshelf/SurpriseMe.php`** (decides where to go):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

// surprise_me: a request that decides where to go: the page of a book chosen at random
#[Capability(
    name: 'surprise_me',
    description: 'Sends the visitor to the page of a book chosen at random.',
    scope: 'books:read',
    readOnly: true,
    public: true,
    webOnly: true   // only Route::action reaches it: its answer is a redirect, which means nothing to a program or an agent
)]
class SurpriseMe extends Service
{
    public function handle(array $input, Context $context)
    {
        // the catalog's own capability, called as the same person: its rules stay in one place
        $page = $this->capability('list_books', array(), $context);
        if (!$page['data']) {
            return Web::view('Bookshelf/notfound', array('message' => 'The shelf is empty.'), 404);
        }
        $book = $page['data'][array_rand($page['data'])];
        return Web::redirect('/books/' . $book['id']);
    }
}
```

**File `application/services/Bookshelf/BooksFeed.php`** (answers XML):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

// books_feed: the first page of the catalog as an RSS feed: XML, which is neither a page, a form, JSON nor a CSV file
#[Capability(
    name: 'books_feed',
    description: 'Answers the first page of the catalog as an RSS feed, for feed readers.',
    scope: 'books:read',
    readOnly: true,
    public: true,
    webOnly: true
)]
class BooksFeed extends Service
{
    public function handle(array $input, Context $context)
    {
        $page = $this->capability('list_books', array('page' => 1), $context);
        $home = session('home');
        $items = '';
        foreach ($page['data'] as $book) {
            $items .= '<item><title>' . htmlspecialchars($book['title'], ENT_XML1) . '</title>'
                . '<link>' . $home . 'books/' . $book['id'] . '</link>'
                . '<description>' . htmlspecialchars($book['author']['name'] . ', ' . $book['published_year'], ENT_XML1) . '</description></item>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel><title>Bookshelf</title><link>' . $home . 'books</link>'
            . '<description>The books we love</description>' . $items . '</channel></rss>';
        return Web::text($xml)->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
```

**File `application/services/Loans/RenewLoan.php`** (a link that changes data):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Flash;
use Caligrafy\Service;
use Caligrafy\Web;

// renew_loan: a link that changes data: 14 more days for a lent book. Opening it twice gives the same date
#[Capability(
    name: 'renew_loan',
    description: 'Gives a lent book 14 more days, from today, by the id of the book.',
    scope: 'loans:write',
    webOnly: true   // only Route::action may change data on a GET
)]
class RenewLoan extends Service
{
    public $rules = array('book_id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if (!$open) {
            return Web::view('Bookshelf/notfound', array('message' => "Book #{$input['book_id']} is not lent"), 404);
        }
        $due = date('Y-m-d', strtotime('+14 days'));
        Loan::where('id', $open->id)->update(array('due_on' => $due));
        Flash::message('success', "{$open->borrower} can keep it until $due");
        return Web::redirect('/loans');
    }
}
```

What to notice:
- `webOnly: true` is the only new setting of `#[Capability]`.
- `surprise_me` and `books_feed` read through `$this->capability('list_books', ...)`: the 20 books of its first page.
- `renew_loan` answers the case it knows itself, a book that is not lent, with a page and a 404. It says what happened
  with `Flash::message`: the loans page shows it with `flash('success')`.
- `surprise_me` and `books_feed` are `public`, like the catalog. `renew_loan` is not: it needs someone signed in.

Check that the list of capabilities does not show them, and that the clients do not get them:
```bash
docker compose exec dev-box php caligrafer.php capabilities | grep -c "surprise_me\|books_feed\|renew_loan"
docker compose exec dev-box php caligrafer.php client
```
Expected: `0`, then ` public/js/capabilities.js written: 25 capabilities, ...`: the same 25 as in lesson 15.

### Step 2 - The routes, and the last lines that name a controller

In `application/config/routing/web.php`:
1. Add the `// WHAT A PAGE OR A FORM CANNOT SAY` block above `// MUST NOT BE REMOVED`.
2. Delete what Bookshelf never used: the `/helloworld` closure, and the template's blocks that name controllers:
   `// User Administration Routes`, `// GPT AI controller`, `// BOT PAGE ROUTES`, `// BOT DATA ROUTES` and the `/* ML Routes */`
   block with its route.

The file is then:

**File `application/config/routing/web.php`** (three `Route::action` lines, and no controller):

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

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

```bash
grep -c Controller application/config/routing/web.php
```
Expected: `0`. Every request of Bookshelf is now declared: pages, forms, sign-in, exports, redirects, REST, agents and
these three. (An app with a Watson bot writes `Route::bots();`, one line for the bot's data routes.)

- `/surprise` and `/feed` are open to anyone, as their capabilities are.
- `/loans/{book_id}/renew` has `signedIn` and `comeBack`: someone who opens the link signed out signs in first and
  comes back to it. `{book_id}` is the input of `renew_loan`.

### Step 3 - The loans page

Add the **14 more days** link to each row of the loans, before the **Returned** form. The file is then:

**File `application/views/Loans/index.pug`** (one link in `.cq-row-actions`):

```pug
extends ../default/master/site

//- Loans: Route::page gives it $loans (list_loans: the books that are out) and $books (list_books: a page of books).
//- The lend form posts to Route::form('/loans', 'lend_book'), each Returned button to Route::form (return_book).
//- Administrators get the reminders: on this device (notify.js), and now (send_reminders, in the queue)

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
                                a.btn.btn-secondary(href="{$home}loans/{$loan['book_id']}/renew") 14 more days
                                form(method='POST', action="{$home}loans/{$loan['book_id']}/return")
                                    =csrf()
                                    button.btn.btn-secondary(type='submit') Returned
            p.cq-auth-note
                a(href="{$home}books") All books

    //- remind_loans and send_reminders have level 1: only administrators see their buttons
    if $user['permissions'] >= 1
        section.cq-band.cq-band--tight
            .cq-inner.cq-inner--narrow
                h2.cq-heading Reminders
                p Every morning at 8, the administrators hear about the books due today and the late ones.
                p.cq-auth-note
                    button#reminders-on.btn.btn-secondary(type='button') Turn on reminders on this device
                p#reminders-status.cq-auth-note(role='status')
                form(method='POST', action="{$home}loans/remind")
                    =csrf()
                    button.btn.btn-secondary(type='submit') Send the reminders now

block additional_scripts
    if $user['permissions'] >= 1
        script(src="{$home}public/js/notify.js")
        script.
            // web push on this device: the browser asks its permission, only after a click
            (function () {
                var button = document.getElementById('reminders-on'), status = document.getElementById('reminders-status');
                var say = { on: 'Reminders are on, on this device.', denied: 'Notifications are blocked in the settings of this browser.',
                    'install-needed': 'On iPhone and iPad, add Bookshelf to the Home Screen first.', unsupported: 'This browser has no notifications.' };
                Notify.status().then(function (now) { if (now === 'on') { button.hidden = true; status.textContent = say.on; } });
                button.addEventListener('click', function () {
                    Notify.enable().then(function (now) {
                        button.hidden = now === 'on';
                        status.textContent = say[now] || '';
                    }).catch(function (e) { status.textContent = e.message; });
                });
            })();
```

### Step 4 - Try them

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost:8080/surprise
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost:8080/surprise
curl -s -i http://localhost:8080/feed | grep -i "HTTP/\|content-type"
curl -s http://localhost:8080/feed | head -c 200
```
Expected: two `302` to the page of a book, usually two different ones; `HTTP/1.1 200 OK`,
`Content-Type: application/rss+xml; charset=utf-8`; and the start of the feed:
```text
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Bookshelf</title><link>http://localhost:8080/books</link><description>The books we love</description><item><title>A Wizard of
```

Now the link, signed out:
```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost:8080/loans/5/renew
```
Expected: `302 http://localhost:8080/login?return=%2Floans%2F5%2Frenew`. In your browser:
1. Sign out, then open http://localhost:8080/loans/5/renew and sign in as reader1. You come back to the link, it runs,
   and you land on the loans with `Sam can keep it until <today plus 14 days>` (5 is Invisible Cities).
2. On the loans page, click **14 more days** on a row: the same message.
3. Open http://localhost:8080/loans/99/renew: a full-screen **Not found**, `Book #99 is not lent`, status 404.
4. Open http://localhost:8080/loans/5/renew?book_id=3: again `Sam can keep it until ...`. The route variable won: the
   query string cannot turn this link into the renewal of Kindred.

Nothing else reaches them. Through REST, the command line and MCP (`$DESK` is the loans desk token of lesson 13):
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/surprise_me
docker compose exec dev-box php caligrafer.php call surprise_me
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $DESK" -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"surprise_me","arguments":{}}}' http://localhost:8080/mcp
```
Expected:
```text
{"error":{"code":"not_found","message":"There is no capability 'surprise_me'"}} [404]
{
    "error": {
        "code": "not_found",
        "message": "There is no capability 'surprise_me'"
    }
}
{"jsonrpc":"2.0","id":2,"error":{"code":-32602,"message":"Unknown tool: surprise_me"}}
```

A JSON call to the address itself, without a token, then with the loans desk's:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/surprise
curl -s -H "Accept: application/json" -H "Authorization: Bearer $DESK" -w " [%{http_code}]\n" http://localhost:8080/surprise
```
Expected:
```text
{"error":{"code":1004,"message":"Access Denied: send the page's CSRF token in an X-CSRF-Token header (pages, with the session cookie) or Authorization: Bearer <personal access token> (programs and agents)"}} [401]
{"error":{"code":1004,"message":"Access Denied: this personal access token is unknown, expired or revoked, or does not grant everything (*) as this route needs: call capabilities with scoped tokens"}} [401]
```
The second says what to do: a program calls capabilities, not these addresses.

The audit log (your times differ):
```bash
docker compose exec dev-box php caligrafer.php audit --capability renew_loan --limit 2
```
Expected:
```text
 2026-11-08 11:36:44  web   user   1            renew_loan                   ok
 2026-11-08 11:36:44  web   user   1            renew_loan                   ok
```
Reads that work are not logged; `renew_loan` changes data, so each visit is.

### Step 5 - Test them

**File `tests/ActionsTest.php`** (four tests: `action()` calls them as `Route::action` does):

```php
<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Web;

// The web-only capabilities: action() calls them as Route::action does; call() is refused, as every other channel is
final class ActionsTest extends TestCase
{
    private function book(): int
    {
        $author = Database::table('authors')->insert(['name' => 'Test Author']);
        return $this->call('create_book', ['title' => 'A test book', 'published_year' => 2000, 'author_id' => $author], $this->user(1))['id'];
    }

    public function testSurpriseGoesToTheCatalog(): void
    {
        $this->book();
        $answer = $this->action('surprise_me', [], 'guest');
        $this->assertInstanceOf(Web::class, $answer);
        $this->assertSame('redirect', $answer->describe()['type']);
        $this->assertMatchesRegularExpression('#^/books/\d+$#', $answer->describe()['target']);
    }

    public function testNoOtherChannelReachesAWebOnlyCapability(): void
    {
        $this->assertCapabilityFails('not_found', 'surprise_me', [], $this->user(1, 1));
    }

    public function testAVisitorCannotRenewALoan(): void
    {
        $this->expectException(\Caligrafy\ServiceError::class);
        $this->action('renew_loan', ['book_id' => 1], 'guest');
    }

    public function testRenewingAndWhatIsNotLent(): void
    {
        $book = $this->book();
        $missing = $this->action('renew_loan', ['book_id' => $book], $this->user(1))->describe();
        $this->assertSame(['view', 404], [$missing['type'], $missing['status']]);

        $this->call('lend_book', ['book_id' => $book, 'borrower' => 'Sam', 'days' => 1], $this->user(1));
        $answer = $this->action('renew_loan', ['book_id' => $book], $this->user(1))->describe();
        $this->assertSame(['redirect', '/loans'], [$answer['type'], $answer['target']]);
        $this->assertSame(date('Y-m-d', strtotime('+14 days')), Database::table('loans')->where('book_id', $book)->value('due_on'));
    }
}
```

**File `qa/bookshelf/smoke.sh`** (three more checks, after the join page):

```bash
#!/bin/bash
# Bookshelf smoke test: calls the application the way each experience does and checks the HTTP status codes.
# Run from the project folder: bash qa/bookshelf/smoke.sh   (or: bash qa/bookshelf/smoke.sh http://localhost:8081)
# With a read-only token, it also checks that it cannot write: READ_TOKEN=cq_... bash qa/bookshelf/smoke.sh
BASE="${1:-http://localhost:8080}"
JSON=(-H "Accept: application/json" -H "Content-Type: application/json")
FAILED=0

check() {   # check <expected status> <description> <curl arguments...>
    local expected="$1" description="$2"
    shift 2
    local status
    status="$(curl -s -o /dev/null -w '%{http_code}' "$@")"
    if [ "$status" = "$expected" ]; then
        echo "OK   $status $description"
    else
        echo "FAIL $status $description (expected $expected)"
        FAILED=1
    fi
}

# web pages
check 200 "the catalog page"             "$BASE/books"
check 200 "the page of book 1"           "$BASE/books/1"
check 404 "a book that does not exist"   "$BASE/books/999999"
check 403 ".env is never served"         "$BASE/.env"
check 403 "a form without its token"     -X POST -d '_method=PUT&title=x' "$BASE/books/1"
check 200 "the authors as a CSV file"    "$BASE/authors.csv"
check 302 "people: visitors to sign in"  "$BASE/people"
check 200 "join: the form for visitors"  "$BASE/join"
check 302 "surprise: to a book"          "$BASE/surprise"
check 200 "the feed"                    "$BASE/feed"
check 401 "a JSON call without its token" -H "Accept: application/json" "$BASE/surprise"
# REST
check 200 "REST: list the books"         "${JSON[@]}" "$BASE/api/books"
check 422 "REST: a search too short"     -X POST "${JSON[@]}" -d '{"query":"a"}' "$BASE/capabilities/search_books"
check 401 "REST: a visitor cannot write" -X POST "${JSON[@]}" -d '{"title":"x"}' "$BASE/api/books"
# AI agents
check 401 "MCP: no token, no tools"      -X POST "${JSON[@]}" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' "$BASE/mcp"
if [ -n "$READ_TOKEN" ]; then
    check 403 "REST: a read-only token cannot write" -X POST "${JSON[@]}" -H "Authorization: Bearer $READ_TOKEN" \
          -d '{"title":"x","published_year":2000,"author_id":1}' "$BASE/api/books"
fi

if [ "$FAILED" = "1" ]; then echo "Some checks failed."; exit 1; fi
echo "All checks passed."
```

`$this->action('surprise_me', [], 'guest')` returns the `Web`; `->describe()` says what it answers: `type`, `target`,
`data`, `status` and `headers`. `testNoOtherChannelReachesAWebOnlyCapability` checks that `$this->call()`, like REST and
MCP, is told there is no such capability.

```bash
docker compose exec dev-box php caligrafer.php test
bash qa/bookshelf/smoke.sh
```
Expected: `OK (11 tests, 24 assertions)`, the checks with these three among them, and `All checks passed.`:
```text
OK   302 surprise: to a book
OK   200 the feed
OK   401 a JSON call without its token
```

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Route::action: 'surprise_me' must be declared webOnly: true` | The setting is missing | Add `webOnly: true` to `#[Capability(...)]` |
> | `Route::action: no capability named ...` | A typo in the route, or the file is not in `application/services/` | Compare with steps 1 and 2 |
> | `curl -i /feed` says `Content-Type: text/html` | `->header('Content-Type', ...)` is missing | Compare with step 1 |
> | `Book #2 is not lent` for a book that is out | The address takes the id of the **book**, not of the loan | Use the book's id: `/loans/<book id>/renew` |
> | A page's fetch gets `[ERROR 1004]` | The call has no `X-CSRF-Token` | Use `apiHeaders()` of `public/js/services/app.js` |
> | `There is no capability 'surprise_me'` in a test | The test calls `$this->call()` | Use `$this->action()` |

---

## Check

- [ ] The three capabilities, `web.php`, `Loans/index.pug`, `ActionsTest.php` and `smoke.sh` are identical to the File
      blocks.
- [ ] `grep -c Controller application/config/routing/web.php` prints `0`.
- [ ] `/surprise` redirects to a book; `/feed` is `application/rss+xml`.
- [ ] `/loans/5/renew` sends a visitor to sign in and comes back; it says `Sam can keep it until ...`; a book that is not
      lent shows the Not found page; `?book_id=3` does not change which book it renews.
- [ ] REST, `call` and MCP answer that the capability does not exist; `capabilities` and the JS client do not list it.
- [ ] A JSON call without a token answers 401; the tests say `OK (11 tests, 24 assertions)`.

## With Claude

```text
/caligrafy-quill:new-capability surprise_me books:read read-only
Web-only: public: true, readOnly: true, webOnly: true. It calls $this->capability('list_books', [], $context), and
returns Web::redirect to /books/<id> of one book at random, or Web::view('Bookshelf/notfound', ['message' => 'The shelf
is empty.'], 404). In web.php: Route::action('/surprise', 'surprise_me').
```
then:
```text
Two more web-only capabilities, with no controller. books_feed (books:read, public, read-only): the first page of
list_books through $this->capability(), as RSS 2.0, every text escaped with htmlspecialchars(..., ENT_XML1), returned
with Web::text($xml)->header('Content-Type', 'application/rss+xml; charset=utf-8'); Route::action('/feed', 'books_feed').
renew_loan (loans:write): sets due_on of the open loan of book_id to 14 days from today, Flash::message('success',
"<borrower> can keep it until <date>"), Web::redirect('/loans'); a book that is not lent answers
Web::view('Bookshelf/notfound', ['message' => 'Book #<id> is not lent'], 404); Route::action('/loans/{book_id}/renew',
'renew_loan', ['signedIn' => true, 'comeBack' => true]). In Loans/index.pug add a "14 more days" link to each row.
Then remove from web.php the /helloworld closure and every template line that names a controller, and add
tests/ActionsTest.php with $this->action() and ->describe(). Run the tests.
```

**Check** before you accept:
- [ ] Each capability says `webOnly: true`, and only these three do.
- [ ] They read through `$this->capability()`, not with a copy of the query of `list_books`.
- [ ] `web.php` has three `Route::action` lines and names no controller; `application/controllers/` has no new file.
- [ ] Step 4 gives the same answers; the audit log shows `renew_loan`.

If Claude makes `surprise_me` a plain capability and adds a `Route::get` with a closure, ask for `Route::action`: a
closure has no permission rules and no audit. If it turns a capability that REST or an agent should use into a web-only
one, say no: `webOnly` is only for what makes no sense anywhere else.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 16: what a page or a form cannot say, every request declared"
git tag lesson-16
git push origin main --tags
```
Reference: [`checkpoints/lesson-16`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-16).
Learn more: [Declarative routes, Route::action](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action).

## Exercise

On club evenings, everyone shares one account, `guest@example.com`, opened by a button with no password. Whoever uses that
button must not be able to change the password of the guest account, or the next person is locked out. Add a page
`/account` that says how the session signed in and has a form to change the password, and refuse that form to a session
that did not sign in with a password.

With Claude, ask: *"Add a guest card: a button on /join that signs in as guest@example.com with
AuthRoutes::signIn($user, null, 'guest-card'), and a page /account, with web-only capabilities and Route::action, whose
form changes the password only when AuthRoutes::signedInBy() is password or passkey. Refuse null too."*

<details><summary>Show the solution</summary>

The account, once:
```bash
docker compose exec dev-box php caligrafer.php call register_user username=guest@example.com passcode=guest-evening-2026
```

`application/services/Bookshelf/GuestCard.php`:
```php
<?php

use Caligrafy\AuthRoutes;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Web;

// guest_card: the shared account of a club evening, opened with no password. It records how this session signed in
#[Capability(
    name: 'guest_card',
    description: 'Signs the visitor in as the shared guest account of a club evening.',
    scope: 'users:write',
    public: true,
    webOnly: true
)]
class GuestCard extends Service
{
    public function handle(array $input, Context $context)
    {
        $guest = User::where('username', 'guest@example.com')->first();
        if (!$guest) {
            throw ServiceError::notFound('There is no guest account');
        }
        AuthRoutes::signIn($guest, null, 'guest-card');   // a new session id, a new CSRF token, and how it signed in
        return Web::redirect('/account');
    }
}
```
`application/services/Bookshelf/MyAccount.php`:
```php
<?php

use Caligrafy\AuthRoutes;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

// my_account: the page that says how this session signed in
#[Capability(
    name: 'my_account',
    description: 'Shows how this session signed in, and the form to change the password.',
    scope: 'users:read',
    readOnly: true,
    webOnly: true
)]
class MyAccount extends Service
{
    public function handle(array $input, Context $context)
    {
        return Web::view('Bookshelf/account', array('how' => AuthRoutes::signedInBy()));
    }
}
```
`application/services/Bookshelf/ChangePasscode.php`:
```php
<?php

use Caligrafy\Auth;
use Caligrafy\AuthRoutes;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Flash;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Web;

// change_passcode: only a session that proved who it is may change how the account signs in
#[Capability(
    name: 'change_passcode',
    description: 'Changes the password of the person signed in.',
    scope: 'users:write',
    webOnly: true
)]
class ChangePasscode extends Service
{
    public $rules = array('passcode' => 'required|min_len,8|max_len,72');

    public function handle(array $input, Context $context)
    {
        // null means nobody recorded how: it is refused too
        if (!in_array(AuthRoutes::signedInBy(), array('password', 'passkey'), true)) {
            throw ServiceError::invalid(array('passcode' => 'Sign in with your password to change it'));
        }
        User::where('id', Auth::user()->id)->update(array('passcode' => User::hashPassword($input['passcode'])));
        Flash::message('success', 'Your password is changed: sign in with it');   // a change to a user's row ends the session
        return Web::redirect('/login');
    }
}
```
`application/views/Bookshelf/account.pug`:
```pug
extends ../default/master/site

//- Your account: Route::action('/account', 'my_account') gives it $how, what AuthRoutes::signedInBy() answered

block title
    | Your account · Bookshelf

block content
    main.cq-auth
        h1 Your account
        p.cq-auth-note How this session signed in: #{$how ?: 'not recorded'}

        form(method='POST', action="{$home}account", novalidate)
            =csrf()
            .cq-field
                label(for='passcode') A new password
                input#passcode.form-control(type='password', name='passcode', maxlength='72', autocomplete='new-password')
                if errors('passcode')
                    p.cq-field-error= errors('passcode')
            button.btn.btn-primary(type='submit') Change my password
```
In `application/views/Bookshelf/join.pug`, at the end of `main.cq-auth`:
```pug

        form(method='POST', action="{$home}guest-card")
            =csrf()
            button.btn.btn-secondary(type='submit') Use the guest card
```
In `web.php`, above `// MUST NOT BE REMOVED`:
```php
    // EXERCISE: how a session signed in
    Route::action('/guest-card', 'guest_card', ['method' => 'POST']);
    Route::action('/account', 'my_account', ['signedIn' => true, 'comeBack' => true]);
    Route::action('/account', 'change_passcode', ['method' => 'POST', 'signedIn' => true]);
```
Then try each way in:

| You come in by | `/account` says | **Change my password** |
|---|---|---|
| **Use the guest card** on `/join` (signed out) | `How this session signed in: guest-card` | refused: `Sign in with your password to change it` |
| the join form, with a new address | `How this session signed in: password` | changed: you are on `/login` with `Your password is changed: sign in with it` |
| `/login`, with your password | `How this session signed in: password` | changed: you are on `/login` with `Your password is changed: sign in with it` |

A password that is too short is refused by `$rules` first. After the change the person signs in again, because a change
to a user's row ends the session (lesson 11).

A door with no password must never go online, so this is not in the checkpoint. Undo it:
`git checkout application && git clean -fd application`, then remove the account with
`docker compose exec dev-box php caligrafer.php call delete_user id=<its id>`.
</details>

## Quiz

1. Why can `create_book` not be web-only?
2. What does a web-only capability use to read the catalog, and why not `Book::with(...)` again?
3. `renew_loan` is opened twice in a minute. What happens, and why does that matter for a link?
4. A program with a `books:read` token asks for `/surprise` with `Accept: application/json`. What does it get?

<details><summary>Answers</summary>

1. Nothing but `Route::action` could reach it: the REST API, the agents, the JS clients and the forms that use it would
   lose it. `webOnly` is for what has no meaning anywhere else.
2. `$this->capability('list_books', $input, $context)`: it calls the capability as the same person, so the rules and the
   rights stay in one place.
3. The due date is 14 days from today both times: nothing else changes. A link can be opened by accident or by a program,
   so a GET that changes data must be harmless when repeated.
4. A 401, `[ERROR 1004]`: a JSON call to `Route::action` needs the page's `X-CSRF-Token` or a token with `*`. A program
   calls capabilities instead.
</details>

---
Previous: [Lesson 15 - Reminders](15-Reminders) · Next: [Appendix A - Routes and controllers](A-Routes-and-Controllers)
