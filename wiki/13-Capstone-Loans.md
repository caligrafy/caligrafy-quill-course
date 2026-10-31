# Lesson 13 - Capstone: one more feature, everywhere

**In this lesson** you add **Loans** to Bookshelf (who borrowed which book, until when) in one go: a table, three
capabilities, and at once a web page, REST routes, the JS client of the Vue apps and tools for AI agents, with their
own scopes and a token for a "loans desk" agent. It is everything the course taught, on a new feature.

**Before you start:** lesson 12 is done (tag `lesson-12`), the containers run (`docker compose ps`). You need the
"Claude Code" token of lesson 11 (scopes `books:read,books:write`) and the account `reader1`.

**Time:** 60 to 90 minutes with the Manual track, 30 minutes with Claude.

---

## New concepts

There is almost nothing new: that is the point. A new feature is the same five pieces every time.

| Piece | Loans | Lesson where you learned it |
|---|---|---|
| A table and a model | `loans`, `Loan` | 03 |
| Capabilities with rules and errors | `lend_book`, `return_book`, `list_loans` | 04, 05 |
| Experiences | the `/loans` page, REST routes, the JS client, MCP tools | 06 to 10 |
| Who may call | scopes `loans:read` and `loans:write` | 11 |
| Checks | `capabilities`, curl, the agent, `audit` | 12 |

**A new resource, a new scope.** Loans get their own scopes, `loans:read` and `loans:write`, instead of reusing
`books:*`. A token for the catalog does not see loans, and a "loans desk" token can lend books without being able to
change the catalog. The capabilities are not `public` (a visitor sees no loans) and have no `level` (any signed-in
user may lend).

**409 Conflict.** `ServiceError::conflict('...')` refuses a call that clashes with the current state: lending a book
that is already lent. It answers `409`, the code for "this cannot be done right now", different from `422` (your
inputs are wrong) and `404` (that thing does not exist).

**A computed value.** The caller gives a number of `days` (1 to 60, 14 when empty); the capability computes the due
date, `due_on`. The rule lives in one place, so a page, a script and an agent all get the same due date.

**What experiences you get for free.** You write no new controller and no new API code: `Route::capability()` gives
REST, `Route::mcp('/mcp')` (there since lesson 01) gives agents the new tools at once, and regenerating the client
gives the Vue apps three new functions.

---

## Manual track

### Step 1 - The table

**File `application/models/Loans/create_db.sql`** (one row per loan; `returned_at` stays empty while the book is
out):

```sql
/* Loans: who borrowed which book. Safe to run more than once: it never drops anything.
 * Run it with:
 * docker compose exec dev-box php caligrafer.php sql application/models/Loans/create_db.sql
 */

CREATE TABLE IF NOT EXISTS loans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    borrower VARCHAR(100) NOT NULL,
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

- `book_id` is a foreign key to `books` (lesson 03). `ON DELETE CASCADE`: deleting a book deletes its loans.
- `due_on` is a `DATE` (no time); `returned_at` is a `DATETIME` that is `NULL` until the book comes back.

Run it:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Loans/create_db.sql
```
Expected: ` application/models/Loans/create_db.sql run on caligrafy`. Check:
`docker compose exec mysql mysql -uroot -proot caligrafy -e "DESCRIBE loans"` lists the 7 columns (the password
warning is harmless).

### Step 2 - The model

**File `application/models/Loans/Loan.php`**:

```php
<?php

use Caligrafy\Model;

// One Loan object = one row of the loans table: a book lent to someone, returned or not
class Loan extends Model
{
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

Scaffold them (lesson 04):
```bash
docker compose exec dev-box php caligrafer.php service Loans/LendBook --scope loans:write
docker compose exec dev-box php caligrafer.php service Loans/ReturnBook --scope loans:write
docker compose exec dev-box php caligrafer.php service Loans/ListLoans --scope loans:read --read-only
```
Each prints ` Created application/services/Loans/<Name>.php: capability '<name>' (scope ...)`. Replace their
content with the files below.

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
    description: 'Lends a book of the catalog to someone for a number of days (14 by default) and returns the loan. Refused when the book is already lent.',
    scope: 'loans:write'
)]
class LendBook extends Service
{
    public $rules = array('book_id' => 'required|integer', 'borrower' => 'required|max_len,100', 'days' => 'integer|min_numeric,1|max_numeric,60');
    public $filters = array('borrower' => 'trim');
    public $descriptions = array(
        'book_id' => 'The id of the book (list_books, search_books)',
        'borrower' => 'The name of the person who borrows it',
        'days' => 'For how many days: 1 to 60, 14 by default'
    );

    public function handle(array $input, Context $context)
    {
        if (!(new Book('books'))->find($input['book_id'])) {
            throw ServiceError::notFound("There is no book #{$input['book_id']}");
        }
        $open = (new Loan('loans'))->all(array('condition' => 'WHERE book_id = ? AND returned_at IS NULL', 'bindings' => array($input['book_id'])));
        if ($open) {
            throw ServiceError::conflict("This book is already lent to {$open[0]->borrower}");
        }
        $days = (int) ($input['days'] ?? 14);
        return (new Loan('loans'))->create(array(
            'book_id' => $input['book_id'],
            'borrower' => $input['borrower'],
            'due_on' => date('Y-m-d', strtotime("+$days days"))
        ));
    }
}
```

- No `public` and no `level`: a visitor is refused (401), a signed-in user or a token with `loans:write` may lend.
- `days` has no `required`: when it is missing, `$input['days'] ?? 14` gives 14 days.
- The checks in `handle()` need the database, so they come after the rules: the book must exist (404), and must not
  have an open loan (409, with the name of the borrower). The query uses `?` and `bindings`: never put an input
  inside SQL text.

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
    description: 'Records that a lent book came back, by the id of the book. Returns the closed loan.',
    scope: 'loans:write'
)]
class ReturnBook extends Service
{
    public $rules = array('book_id' => 'required|integer');
    public $descriptions = array('book_id' => 'The id of the book that came back');

    public function handle(array $input, Context $context)
    {
        $loans = new Loan('loans');
        $open = $loans->all(array('condition' => 'WHERE book_id = ? AND returned_at IS NULL', 'bindings' => array($input['book_id'])));
        if (!$open) {
            throw ServiceError::notFound("Book #{$input['book_id']} is not lent");
        }
        return $loans->update(array('returned_at' => date('Y-m-d H:i:s')), $open[0]->id);
    }
}
```

- It takes the id of the **book** (what a librarian has in hand), finds its open loan, and sets `returned_at`.
- The REST route of step 4 is `/api/books/{book_id}/return`: the route variable is `{book_id}`, the name of the
  input, so it reaches `handle()`.

**File `application/services/Loans/ListLoans.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_loans: the books that are out, with who has them and whether they are late
#[Capability(
    name: 'list_loans',
    description: 'Lists the books that are lent and not returned yet: book id and title, borrower, due date, and overdue (true when the due date is past).',
    scope: 'loans:read',
    readOnly: true
)]
class ListLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        $loans = array();
        foreach ((new Loan('loans'))->all(array('condition' => 'WHERE returned_at IS NULL', 'order' => 'ORDER BY due_on')) as $loan) {
            $book = (new Book('books'))->find($loan->book_id);
            $loans[] = array(
                'book_id' => $loan->book_id,
                'title' => $book ? $book->title : '',
                'borrower' => $loan->borrower,
                'due_on' => $loan->due_on,
                'overdue' => $loan->due_on < date('Y-m-d')
            );
        }
        return $loans;
    }
}
```

- `readOnly: true`: agents may call it freely, and a successful call is not written to the audit log.
- `overdue` compares two `Y-m-d` dates as text, which works because the format sorts like the dates.

Check:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected, among the Bookshelf capabilities:
```
 lend_book      loans:write   writes    Lends a book of the catalog to someone ...
 list_loans     loans:read    read      Lists the books that are lent and not returned yet ...
 return_book    loans:write   writes    Records that a lent book came back ...
```

### Step 4 - The routes

In `application/config/routing/web.php`, add these lines above `// MUST NOT BE REMOVED` and `Route::run();` (the
full file is in the checkpoint):

```php
    // LOANS: one more feature, every experience: the page, REST, the JS client and AI agents
    Route::get('/loans', function () { return view('Loans/index', array('authorized' => authorized())); });
    Route::capability('GET', '/api/loans', 'list_loans');
    Route::capability('POST', '/api/loans', 'lend_book');
    Route::capability('POST', '/api/books/{book_id}/return', 'return_book');
```

The page route is a closure: the page has no server-side data, `public/js/loans.js` does the work in the browser.
`authorized` tells the layout whether to show "Sign out".

### Step 5 - Check REST

Keep two tokens in variables for this terminal (never in a file of the project). The "Claude Code" token of
lesson 11 can read and write books, but has no loans scope:
```bash
BOOKS_TOKEN=$WRITER   # in the same terminal as lesson 11; otherwise paste the "Claude Code" token of lesson 11
docker compose exec dev-box php caligrafer.php token create "loans desk" --scopes "books:read,loans:*"
```
The second command prints the new token once: copy it, store it as a secret, then `LOANS_TOKEN=cq_...`.
(`books:read` lets the agent find books; `loans:*` means every action on loans.)

Then, from the `bookshelf` folder:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/loans
```
Expected: `401` with `"code":"unauthenticated"`: a visitor sees no loans.
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $BOOKS_TOKEN" -w " [%{http_code}]\n" http://localhost:8080/api/loans
```
Expected: `{"error":{"code":"forbidden","message":"This needs the scope loans:read"}} [403]`.

Lend Kindred (book 3) to Sam for 7 days:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $LOANS_TOKEN" -d '{"book_id": 3, "borrower": "Sam", "days": 7}' -w " [%{http_code}]\n" http://localhost:8080/api/loans
```
Expected: `{"data":{"id":1,"book_id":3,"borrower":"Sam","due_on":"2026-11-07","returned_at":null,...}} [201]`.
`due_on` is today plus 7 days: the test run was on 2026-10-31, yours shows your date.

Run the same command with `"borrower": "Alex"`: `{"error":{"code":"conflict","message":"This book is already lent to Sam"}} [409]`.

Wrong inputs: `-d '{"book_id": 1, "borrower": "", "days": 90}'` answers `422` with
`"fields":{"borrower":"The Borrower field is required","days":"The Days field needs to be a numeric value, equal to, or lower than 60"}`.

List, return, return again:
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $LOANS_TOKEN" http://localhost:8080/api/loans
curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer $LOANS_TOKEN" -w " [%{http_code}]\n" http://localhost:8080/api/books/3/return
curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer $LOANS_TOKEN" -w " [%{http_code}]\n" http://localhost:8080/api/books/3/return
```
Expected: `{"data":[{"book_id":3,"title":"Kindred","borrower":"Sam","due_on":"2026-11-07","overdue":false}]}`, then
the loan with a `returned_at` date `[200]`, then `{"error":{"code":"not_found","message":"Book #3 is not lent"}} [404]`.

### Step 6 - The page

**File `application/views/Loans/index.pug`** (the page components of lesson 06: one card, a form, a list, an empty
state):

```pug
extends ../default/master/layoutblocks

//- Loans: lend a book, see who has what, record returns (public/js/loans.js, the loan capabilities)

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title Loans
                p.cq-note Who borrowed which book. AI agents with a loans token see the same list.

            .cq-section#sign-in(hidden)
                a.btn.btn-secondary(href="{$home}login") Sign in to manage loans

            form#lend.cq-form(novalidate, hidden)
                .cq-fields.cq-fields--3
                    div
                        label(for='loan-book') * Book
                        select#loan-book.form-select(name='book_id')
                    div
                        label(for='loan-borrower') * Borrower
                        input#loan-borrower.form-control(type='text', name='borrower', maxlength='100', autocomplete='off')
                    div
                        label(for='loan-days') Days
                        input#loan-days.form-control(type='number', name='days', placeholder='14')
                button.btn.btn-primary.cq-submit(type='submit') Lend

            p#error.cq-error(role='alert')
            ul#loans.cq-list
            p#empty.cq-empty(hidden) Every book is on the shelf.

block additional_scripts
    script(type='module', src="{$home}public/js/loans.js")
```

**File `public/js/loans.js`** (the same capabilities through the JS client):

```js
// The loans page: lend_book, return_book and list_loans, the same capabilities as REST and AI agents
import { capabilities, listBooks, listLoans, lendBook, returnBook, CapabilityError } from './capabilities.js'

const form = document.getElementById('lend')
const list = document.getElementById('loans')
const empty = document.getElementById('empty')
const errorBox = document.getElementById('error')

function showError (e) {
  if (e instanceof CapabilityError) {
    errorBox.textContent = Object.keys(e.fields).length ? Object.values(e.fields).join(' ') : e.message
  } else {
    errorBox.textContent = 'Something went wrong. Try again.'
    console.error(e)
  }
}

function row (loan) {
  const li = document.createElement('li')
  li.className = 'cq-item'
  li.innerHTML = '<div class="cq-item-body"><p class="cq-item-title"></p><p class="cq-item-meta"></p></div><div class="cq-actions"></div>'
  li.querySelector('.cq-item-title').textContent = loan.title
  li.querySelector('.cq-item-meta').textContent = loan.borrower + ', due ' + loan.due_on + (loan.overdue ? ' (late)' : '')

  const back = document.createElement('button')
  back.type = 'button'
  back.className = 'btn btn-secondary'
  back.textContent = 'Returned'
  back.addEventListener('click', async () => {
    try {
      await returnBook({ book_id: loan.book_id })
      await showLoans()
    } catch (e) {
      showError(e)
    }
  })
  li.querySelector('.cq-actions').append(back)
  return li
}

async function showLoans () {
  const loans = await listLoans()
  list.replaceChildren(...loans.map(row))
  empty.hidden = loans.length > 0
}

form.addEventListener('submit', async (event) => {
  event.preventDefault()
  errorBox.textContent = ''
  const input = Object.fromEntries(new FormData(form))
  if (input.days === '') delete input.days   // empty: 14 days
  try {
    await lendBook(input)
    form.reset()
    await showLoans()
  } catch (e) {
    showError(e)   // for example "This book is already lent to Sam"
  }
})

// Visitors who may not see the loans get the sign-in button instead
const allowed = new Set((await capabilities()).map((c) => c.name))
if (allowed.has('list_loans')) {
  form.hidden = !allowed.has('lend_book')
  const books = await listBooks()
  document.getElementById('loan-book').replaceChildren(...books.map((b) => new Option(b.title, b.id)))
  await showLoans()
} else {
  document.getElementById('sign-in').hidden = false
}
```

- `capabilities()` asks the server what this visitor may call (lesson 11). A visitor gets the sign-in button; a
  signed-in user gets the form and the list.
- `showError()` shows the field messages, or the message of a refusal such as the 409.

Regenerate both clients (rule of lesson 06: after every change to a capability):
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```
Expected for the first: ` public/js/capabilities.js written: 10 capabilities, calls /capabilities (...)`. The client
now has `lendBook`, `listLoans` and `returnBook`.

Open http://localhost:8080/loans:
- signed out: the card "Loans" and the button "Sign in to manage loans";
- signed in as `reader1`: a form with Book (a list of titles), Borrower, Days (placeholder `14`) and Lend. Lend
  Invisible Cities to Sam for 7 days: the list shows "Invisible Cities" and "Sam, due <date>", with a Returned button;
- lend Invisible Cities again to someone else: "This book is already lent to Sam" appears under the form.

Leave Invisible Cities lent: step 7 uses it.

### Step 7 - Let an agent run the loans desk

Connect Claude Code with the loans desk token (lesson 11 showed how to switch tokens):
```bash
claude mcp remove bookshelf
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer $LOANS_TOKEN"
```
Start `claude` in the `bookshelf` folder (the `claude mcp add` of lesson 02 registers the server for that folder
only; a new session sees the new tools) and ask:
```
Which books are lent right now? Then lend Kindred to Ana for 3 days.
```
Claude calls `list_loans`, finds the id of Kindred (`list_books` or `search_books`) and calls `lend_book`. In the test
run it answered: "Before this loan, only Invisible Cities was out: Sam has it, due 2026-11-07, not overdue. I lent
Kindred to Ana for 3 days, and it's due back on 2026-11-03." Reload http://localhost:8080/loans: Kindred is there.

Check the audit log:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 5
```
Expected, among the lines: `mcp   token  6   lend_book   ok` (6 is the id of the loans desk token in the test run;
yours may differ). The successful `list_loans` call is not there: read-only successes are not audited.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `capabilities` does not list `lend_book`, or `/api/loans` answers like an unknown address | The service file is not in `application/services/Loans/`, the class name differs from the file name, or the routes are below `Route::run();` | Compare with steps 3 and 4 |
> | `Table 'caligrafy.loans' doesn't exist` | Step 1 was skipped | Run the `sql` command of step 1 |
> | `403 This needs the scope loans:write` with the loans desk token | The token was created with other scopes (for example without quotes around `books:read,loans:*`) | `token list` shows its scopes; create it again with the quotes, revoke the old one |
> | `zsh: no matches found: books:read,loans:*` (no token created) | zsh reads the `*` of unquoted scopes as a file pattern | Quote the scopes: `--scopes "books:read,loans:*"` |
> | The card shows no form and no sign-in button, and the browser Console says `does not provide an export named 'listLoans'` | The client was not regenerated, so `listLoans` is missing | Run both `client` commands of step 6 and reload |
> | `due_on` is a day off | The due date uses the PHP time zone (`SERVER_TIMEZONE` in `.env`) | Normal near midnight; set `SERVER_TIMEZONE` to yours |
> | `POST /api/books/3/return` answers `422` with `book_id` in `fields` | The route variable is not named `{book_id}` (for example `{id}`), so the input `book_id` is missing | Compare the route with step 4 |
> | `Book #3 is not lent` the first time you return it | The book was already returned (or never lent) | `GET /api/loans` shows what is out |
> | The agent does not see the loan tools | Claude Code still uses the old token, or the session started before the change | `claude mcp list`, then a new `claude` session (or `/mcp` to reconnect) |

---

## With Claude

This is the highlight of the course: the whole feature from one precise request. Do it instead of the Manual track,
or afterwards on a branch started from lesson 12 to compare: `git switch -c loans-with-claude lesson-12`. (If you did
the Manual track, the `loans` table and a loans desk token already exist: the SQL is safe to run again, and you can
keep that token. The database also keeps the loans: first return the open ones, with
`curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer $LOANS_TOKEN" http://localhost:8080/api/books/3/return`
and the same command for `/api/books/5/return`. The tag `lesson-13` already exists, so skip the Checkpoint on that
branch.) Start Claude Code in the `bookshelf` folder and run:

```
/caligrafy-quill:new-capability lend_book loans:write
```
Then give Claude the whole feature:
```
Build a Loans feature for Bookshelf, as capabilities, in every experience. Ask me before anything that is not here.

Data: application/models/Loans/create_db.sql creates a table loans (CREATE TABLE IF NOT EXISTS): id, book_id
(INT UNSIGNED, foreign key to books(id), ON DELETE CASCADE), borrower VARCHAR(100) NOT NULL, due_on DATE NOT NULL,
returned_at DATETIME NULL, created_at, modified_at. Run it with php caligrafer.php sql in the dev-box container.
Model: application/models/Loans/Loan.php, class Loan, no namespace, one public property per column.

Capabilities in application/services/Loans/, scaffolded with php caligrafer.php service, no namespace,
not public, no level:
- lend_book (LendBook, scope loans:write): inputs book_id required integer, borrower required max 100 characters
  (trim), days integer 1 to 60, optional, 14 by default. The book must exist (ServiceError::notFound "There is no
  book #<id>"); a book with an open loan (returned_at IS NULL) is refused with ServiceError::conflict "This book is
  already lent to <borrower>". It creates the loan with due_on = today + days (Y-m-d) and returns it.
- return_book (ReturnBook, scope loans:write): input book_id required integer. Sets returned_at on the open loan of
  that book and returns the loan; no open loan: ServiceError::notFound "Book #<id> is not lent".
- list_loans (ListLoans, scope loans:read, readOnly): the open loans sorted by due_on, each with book_id, title,
  borrower, due_on and overdue (true when due_on is before today).
Write descriptions for AI agents (say where book_id comes from: list_books, search_books). Bound queries only.

Experiences:
- REST in web.php, in a "// LOANS" block above "// MUST NOT BE REMOVED": GET /api/loans list_loans,
  POST /api/loans lend_book, POST /api/books/{book_id}/return return_book.
- A page /loans (closure route, view Loans/index with 'authorized' => authorized()) built with the page components:
  one card "Loans", a form (Book select of titles from list_books, Borrower, Days with placeholder 14, Lend button),
  the list of open loans ("<borrower>, due <date>", "(late)" when overdue) with a Returned button, an empty state
  "Every book is on the shelf.", errors in .cq-error. Its script public/js/loans.js uses the JS client; visitors
  who may not call list_loans see a "Sign in to manage loans" button instead.
- Regenerate public/js/capabilities.js and public/bookshelf-spa/src/capabilities.js.
Then verify every experience with curl and show me the results.
```

**What Claude should do:** scaffold the three services with `service`, write the table, the model, the services, the
routes, the page and its script, run the SQL, regenerate both clients, then run checks like the ones of step 5 and
show you the answers. It asks you before anything you did not specify.

Create the loans desk token yourself, in your terminal, with the `token create "loans desk" ...` command of step 5,
then `LOANS_TOKEN=cq_...`. Never let a secret go into the chat.

**Check** before you accept:
- [ ] `capabilities` shows `lend_book loans:write writes`, `list_loans loans:read read`, `return_book loans:write writes`, and **none** of them `public`.
- [ ] `list_loans` has `readOnly: true`; `lend_book` and `return_book` have no `destructive` (they change, they do not delete) and no `level`.
- [ ] `handle()` has no `echo`, `view()`, `redirect()` or `$_POST`/`$_SESSION`: it returns data or throws `ServiceError`.
- [ ] The queries use `?` and `bindings`, never an input inside the SQL text.
- [ ] The conflict is a `ServiceError::conflict` (409), not `invalid` (422).
- [ ] The routes are in web.php above `// MUST NOT BE REMOVED`, and the return route uses `{book_id}`.
- [ ] Both clients were regenerated (`public/js/capabilities.js` has `lendBook`, `listLoans`, `returnBook`).
- [ ] The token is not in any file of the project (`git status` shows no new file with `cq_` in it).

**Then verify it yourself, with the same checks as the Manual track:**
1. The page: step 6 (signed out, signed in, lend, the 409 message).
2. REST: every curl command of step 5 (401 for a visitor, 403 for the books token, 201, 409, 422, the list, 200 and 404 on return).
3. The agent: step 7 with the loans desk token you created.
4. The audit: `audit --limit 5` shows `mcp token <id> lend_book ok`.
5. The tests of lesson 12: the unit tests and the smoke test still pass.

**If Claude's result differs:** names, scopes and messages are what other experiences rely on: ask Claude to fix
any that differ from your request. If it puts the rules in a controller or writes a separate API controller, ask
for capabilities only (REST comes from `Route::capability`). If it makes the capabilities `public` or gives them a
`level`, ask it to remove that. If it wants to write the token in `.env` or a file, refuse. A different look of the
page or other wording of the descriptions is fine. You can also compare its files with the checkpoint:
`diff -ru <course clone>/checkpoints/lesson-13/application/services/Loans application/services/Loans`, where
`<course clone>` is a clone of the course repository.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 13: Loans, a new feature everywhere"
git tag lesson-13
```
Reference: [`checkpoints/lesson-13`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-13).

You have finished the course: Bookshelf has ten capabilities, five experiences (pages, REST, AI agents, the Vue
finder, the phone and tablet app), people with permissions, tokens with scopes, tests and an audit log. The
[Caligrafy Quill wiki](https://github.com/caligrafy/caligrafy-quill/wiki) documents the modules the course did not
cover (file uploads, payments, social cards, the AI and machine learning modules). The appendix shows what is under
the hood: routes, controllers and forms written by hand.

## Exercise

Add a read-only capability `overdue_loans` (scope `loans:read`) that lists only the loans whose due date is past,
with book id, title, borrower and due date. Make it reachable at `GET /api/loans/overdue`, regenerate the clients,
and ask the loans desk agent "Which books are late?".

<details><summary>Show the solution</summary>

```bash
docker compose exec dev-box php caligrafer.php service Loans/OverdueLoans --scope loans:read --read-only
```

`application/services/Loans/OverdueLoans.php`:
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// overdue_loans: the lent books that should be back already
#[Capability(
    name: 'overdue_loans',
    description: 'Lists the lent books whose due date is past: book id and title, borrower and due date. Use return_book when one comes back.',
    scope: 'loans:read',
    readOnly: true
)]
class OverdueLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        $late = array();
        $loans = (new Loan('loans'))->all(array(
            'condition' => 'WHERE returned_at IS NULL AND due_on < ?',
            'bindings' => array(date('Y-m-d')),
            'order' => 'ORDER BY due_on'
        ));
        foreach ($loans as $loan) {
            $book = (new Book('books'))->find($loan->book_id);
            $late[] = array(
                'book_id' => $loan->book_id,
                'title' => $book ? $book->title : '',
                'borrower' => $loan->borrower,
                'due_on' => $loan->due_on
            );
        }
        return $late;
    }
}
```

In `web.php`, in the `// LOANS` block:
```php
    Route::capability('GET', '/api/loans/overdue', 'overdue_loans');
```
Then regenerate both clients (11 capabilities). No loan is late yet, so the answer is `{"data":[]}`. To see one, make
a loan late in MySQL:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "UPDATE loans SET due_on = '2026-10-01' WHERE book_id = 5 AND returned_at IS NULL"
```
`GET /api/loans/overdue` with the loans desk token now lists Invisible Cities, and the agent (which sees the new tool
at once, or after `/mcp` reconnects) answers that Sam is late with it. The token needs no change: `loans:*` already
covers `loans:read`.
</details>

---
Previous: [Lesson 12 - Ship it: tests, review, doctor](12-Ship-It) · Next: [Appendix A - Routes and controllers by hand](A-Routes-and-Controllers) · [Back to the course home](Home)
