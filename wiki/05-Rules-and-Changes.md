# Lesson 05 - Rules and changes

**In this lesson** Bookshelf changes. First its table: books get an optional, unique **ISBN**, added with a
**migration**, because `books` already holds data. Then its catalog: you write the three capabilities that change it,
`create_book`, `update_book` and `delete_book`. Their rules (a title is required, a year is a number and not in the
future, the author must exist, two books never share an ISBN) are written once, and `curl` and an AI agent get the same
refusals with the same messages.

**Before you start:** lesson 04 is done (tag `lesson-04`): `docker compose exec dev-box php caligrafer.php capabilities`
lists `get_book`, `list_authors` and `list_books`, and `docker compose ps` shows the 3 containers running.

**Time:** 60 minutes.

---

## New concepts

### Migrations

`create_db.sql` gave the tables their **first version**. A change to a table that already holds data goes in a
**migration**: a file `application/migrations/<number>_<name>.sql` that runs **once** on each database, in number
order. Three commands:

| Command | What it does |
|---|---|
| `php caligrafer.php migrate make <name>` | writes an empty migration; its number is the date and time (`20261107232442`) |
| `php caligrafer.php migrate` | runs the migrations that never ran on this database, and records each one in the table `caligrafy_migrations` |
| `php caligrafer.php migrate status` | `ran`, `pending` or `changed` for each file |

Three habits: never edit a migration that ran (write a new one); to undo a change, write a migration that undoes it;
and keep one change per file, because MySQL applies each `ALTER TABLE` at once. On the server, the deploy step runs
the pending migrations after `create_db.sql` (lesson 14): nothing to do by hand.

The course gives its migrations fixed numbers, so that your files have the same names as the checkpoints: after
`migrate make`, you rename the file to the course's number.

### $rules: what a valid input is

Each input gets a list of rules separated by `|`. Caligrafy checks them before `handle()` runs
([Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)):

| Rule | Meaning | Message when it fails |
|---|---|---|
| `required` | present and not empty | `The Title field is required` |
| `integer` | a whole number | `The Published Year field must be a number without a decimal` |
| `max_len,200` | at most 200 characters | |
| `min_numeric,1450` / `max_numeric,current_year` | a number between these values; `current_year` is this year, when the input is checked | `The Published Year field needs to be a numeric value, equal to, or lower than 2026` |
| `alpha_numeric_dash` | letters, digits, `-` and `_` only | `The Isbn field may only contain letters, numbers, dashes and underscores` |
| `exists,authors.id` | the value is in that column of that table | `There is no such author` |
| `unique,books.isbn` | the value is not in that column yet | `This isbn is already used` |
| `unique,books.isbn,id,id` | the same, except on the row whose `id` is the input `id` (the book being changed) | |

A rule other than `required` is **skipped when the input is empty**: `'isbn' => 'alpha_numeric_dash|...'` accepts a
book without an ISBN. `exists` and `unique` ask the database with a bound query, so a check that needs the database is
still one rule.

**$filters: clean first.** Filters change the inputs **before** the rules: `'title' => 'trim'` removes the spaces at
both ends, so `"  "` becomes `""` and fails `required`.

### Checks in handle()

What a rule cannot say goes in `handle()`, which refuses with a `ServiceError` of the same shape as a rule's message:
`throw ServiceError::invalid(array('title' => 'The Title field is required'));`. `update_book` needs this: its title is
optional (a caller may change only the summary), but a title given empty must be refused.

| Error | Status | When |
|---|---|---|
| `ServiceError::invalid(array('field' => 'message'))` | 422 | an input is wrong |
| `ServiceError::notFound('There is no book #60')` | 404 | the thing does not exist |
| `ServiceError::forbidden()` | 403 | this caller may not do it |
| `ServiceError::conflict('...')` | 409 | it clashes with the current state |
| `ServiceError::unauthenticated()` | 401 | nobody is signed in |

**The order of every call**, whatever the experience: find the capability (`not_found`, 404); check who calls,
skipped when `public` (401, 403); filter, then check `$rules` (`invalid`, 422, one message per field); run `handle()`;
write the **audit log** (every refusal, and every success that changes data).

**destructive.** `destructive: true` marks a capability that deletes data. AI agents see it and ask the person before
calling it. Reads are `readOnly`, deletes are `destructive`, create and update are neither.

**public, for now.** The three new capabilities are `public: true`, so you can try them without signing in. Lesson 11
closes them; each file says so in a comment.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The migration: an ISBN for every book

```bash
docker compose exec dev-box php caligrafer.php migrate make add_books_isbn
```
Expected (your number is the date and time of your computer):
```text
 application/migrations/20261107232442_add_books_isbn.sql written: add its SQL, then php caligrafer.php migrate
```
Rename it to the course's number:
```bash
mv application/migrations/*_add_books_isbn.sql application/migrations/20261105000100_add_books_isbn.sql
```
Then replace its content:

**File `application/migrations/20261105000100_add_books_isbn.sql`** (a new column and its unique index, in one statement):

```sql
-- add_books_isbn
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.
-- One statement: the column and its unique index are added together, or not at all.

ALTER TABLE books
    ADD COLUMN isbn VARCHAR(20) NULL AFTER summary,
    ADD UNIQUE KEY uq_books_isbn (isbn);
```

- `ADD COLUMN isbn VARCHAR(20) NULL AFTER summary`: optional (`NULL`), so the five books keep working without one.
- `ADD UNIQUE KEY uq_books_isbn (isbn)`: MySQL refuses two books with the same ISBN. Many books may have **no** ISBN
  (`NULL`): a unique index allows any number of `NULL`.
- One `ALTER TABLE` for both: either both changes are made, or neither.

Look, then run it:
```bash
docker compose exec dev-box php caligrafer.php migrate status
docker compose exec dev-box php caligrafer.php migrate
docker compose exec dev-box php caligrafer.php migrate status
```
Expected:
```text
 pending  20261105000100_add_books_isbn.sql

 OK   20261105000100_add_books_isbn.sql

 1 migration(s) run on caligrafy

 ran      20261105000100_add_books_isbn.sql  (2026-11-07 23:24:42)
```
Run `migrate` once more: ` Nothing to migrate: caligrafy is up to date`. A migration runs once.

### Step 2 - The model gets the column

The new column reaches the answers only through a property of the model. Replace the content of `Book.php`:

**File `application/models/Bookshelf/Book.php`** (the property `$isbn` added after `$summary`):

```php
<?php

use Caligrafy\Model;

// One Book = one row of the books table. Book::with('author') adds the row of authors whose id is author_id
class Book extends Model {

    // one public property per column of the table
    public $id;
    public $title;
    public $published_year;
    public $summary;
    public $isbn;
    public $author_id;
    public $created_at;
    public $modified_at;
}
```

Check:
```bash
docker compose exec dev-box php caligrafer.php call get_book id=1
```
Expected: the book now has `"isbn": null`, between `"summary"` and `"author_id"`. Without the property, `isbn` would
be missing from every answer even though the column exists.

### Step 3 - create_book

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
```
Expected: ` Created application/services/Bookshelf/CreateBook.php: capability 'create_book' (scope books:write)` and
the `Fill in $rules...` line. No `--read-only` this time: it changes data. Replace the content of the file:

**File `application/services/Bookshelf/CreateBook.php`** (the rules of a valid book, in one place):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
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

- `summary` and `isbn` have no `required`: they are optional.
- `exists,authors.id` and `unique,books.isbn` ask the database: no code in `handle()` for them.
- An empty ISBN becomes `NULL`: two books without an ISBN must not clash on the unique index.
- `Book::insert($input)` writes one `INSERT` with every value bound, and returns the id of the new row. `$input` holds
  only the inputs of `$rules`, so a caller cannot fill another column. `Book::with('author')->find($id)` reads the new
  book back, with its author and the dates MySQL filled in.

### Step 4 - update_book and delete_book

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/UpdateBook --scope books:write
docker compose exec dev-box php caligrafer.php service Bookshelf/DeleteBook --scope books:write
```

**File `application/services/Bookshelf/UpdateBook.php`** (changes only the inputs that are given):

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
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
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

- Only `id` is `required`: a caller may change just the summary.
- `unique,books.isbn,id,id`: the ISBN must not be used by **another** book; the book keeps its own.
- `$changes` keeps only the inputs that were sent, so a missing input never erases a column. A title, year or author
  sent empty is refused with the message of `required`. (A web form sends every field, empty or not: lesson 06.)
- `Book::where('id', $input['id'])->update($changes)` changes that one book. A builder's `update()` (and `delete()`)
  refuses to run without a `where`: a forgotten condition never changes the whole table.

**File `application/services/Bookshelf/DeleteBook.php`** (destructive, so AI agents ask before calling it):

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
    public: true   // for now: lesson 11 closes it
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

- `delete()` returns how many rows it deleted: `0` means there was no such book, so one query both checks and deletes.

Check:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected: 19 lines; among them your six (the third column is the kind: `read`, `writes` or `destroys`):
```text
 create_book                  public         writes    Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.
 delete_book                  public         destroys  Deletes a book of the catalog, by its id. This cannot be undone.
 get_book                     public         read      Returns one book of the catalog with its author (id, name, bio), by the id of the book.
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.
 update_book                  public         writes    Changes the title, year, summary, ISBN or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book with its author.
```

### Step 5 - Add a book, then break the rules

Every call below is `POST /capabilities/<name>` with a JSON body, as in lesson 04.

A valid book:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "The Dispossessed", "published_year": 1974, "isbn": "978-0-06-051275-0", "author_id": 1}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/create_book
```
Expected:
```text
{"data":{"id":6,"title":"The Dispossessed","published_year":1974,"summary":null,"isbn":"978-0-06-051275-0","author_id":1,"created_at":"2026-11-07 23:24:52","modified_at":"2026-11-07 23:24:52","author":{"id":1,"name":"Ursula K. Le Guin","bio":"American author of science fiction and fantasy, known for the Earthsea books.","created_at":"2026-11-07 23:24:20","modified_at":"2026-11-07 23:24:20"}}} [201]
```
**201** means "created". This generic endpoint answers 201 for every successful call of a capability that changes
data, because the call is a POST; the REST routes of lesson 07 give each action its own status.

Now change only the `-d` part of the same command:

| `-d` | Answer | Status |
|---|---|---|
| `'{"title": "", "published_year": "soon", "author_id": 9}'` | `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required","published_year":"The Published Year field must be a number without a decimal","author_id":"There is no such author"}}}` | 422 |
| `'{"title": "Tomorrow", "published_year": 2099, "author_id": 1}'` | `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"published_year":"The Published Year field needs to be a numeric value, equal to, or lower than 2026"}}}` | 422 |
| `'{"title": "A copy", "published_year": 1974, "isbn": "978-0-06-051275-0", "author_id": 1}'` | `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"isbn":"This isbn is already used"}}}` | 422 |

Every rule that fails gets its message, in one answer, so a caller can fix every field at once.

### Step 6 - Change and delete

Use the id that `create_book` returned (6 if you followed every step):
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"id": 6, "summary": "An anarchist physicist crosses to the twin world."}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/update_book
```
Expected: the book with its new summary, the title, year and ISBN unchanged:
`{"data":{"id":6,"title":"The Dispossessed","published_year":1974,"summary":"An anarchist physicist crosses to the twin world.","isbn":"978-0-06-051275-0",...}} [201]`.

Now change only the `-d` part:

| `-d` | Answer | Status |
|---|---|---|
| `'{"id": 6, "isbn": "978-0-06-051275-0"}'` | the book, unchanged: its own ISBN is allowed | 201 |
| `'{"id": 1, "isbn": "978-0-06-051275-0"}'` | `"fields":{"isbn":"This isbn is already used"}` | 422 |
| `'{"id": 6, "title": ""}'` | `"fields":{"title":"The Title field is required"}` | 422 |
| `'{"id": 60, "title": "x"}'` | `{"error":{"code":"not_found","message":"There is no book #60"}}` | 404 |

Delete the book:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"id": 6}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/delete_book
```
Expected: `{"data":{"deleted":6}} [201]`. Run the same command again:
`{"error":{"code":"not_found","message":"There is no book #6"}} [404]`. Lesson 06 adds The Dispossessed back, from a
web page.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | Every input is reported as missing, or curl waits for more input (`>`) | The JSON of `-d '...'` is broken: a quote is missing, or copying changed `'` into `’` | Press Ctrl+C, then copy the whole command again |
> | `The Published Year field is required` although you sent it | The input name is wrong (`year`, `publishedYear`) | Use the names of `$rules`: `published_year`, `author_id` |
> | `The rule exists needs a table and a column: exists,authors.id (not 'exists,authors')` (500) | The rule is written `exists,authors` | Write `exists,authors.id` |
> | `"isbn"` is missing from the answers | The property `$isbn` is missing from `Book.php` | Step 2 |
> | A database error that names `isbn` | The migration did not run | `migrate status`, then `migrate` |
> | `capabilities` shows `writes` for delete_book | `destructive: true` is missing | Add it to the `#[Capability(` block |

### Step 7 - The same rules for an AI agent

Send the agent's version of the first wrong book over MCP (the token is in `$TOKEN`; in a new terminal window, run
`read -rs TOKEN` again):
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"create_book","arguments":{"title": "", "published_year": "soon", "author_id": 9}}}' http://localhost:8080/mcp
```
Expected: a tool error with the same three messages:
```text
{"jsonrpc":"2.0","id":2,"result":{"content":[{"type":"text","text":"{\"error\":{\"code\":\"invalid\",\"message\":\"Some of the inputs are invalid\",\"fields\":{\"title\":\"The Title field is required\",\"published_year\":\"The Published Year field must be a number without a decimal\",\"author_id\":\"There is no such author\"}}}"}],"isError":true}}
```
An agent that gets this error reads the field messages and corrects its call by itself. In Claude Code, ask
`Delete the book Kindred.`: because `delete_book` is destructive, Claude asks you to confirm first. Say **no**: Kindred
is used in the next lessons.

Then the audit log:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 5
```
Expected (your times differ):
```text
 2026-11-07 23:24:52  mcp   token  1            create_book                  invalid  Some of the inputs are invalid
 2026-11-07 23:24:52  rest  guest  -            delete_book                  not_found  There is no book #6
 2026-11-07 23:24:52  rest  guest  -            delete_book                  ok
 2026-11-07 23:24:52  rest  guest  -            update_book                  not_found  There is no book #60
 2026-11-07 23:24:52  rest  guest  -            update_book                  invalid  Some of the inputs are invalid
```
`POST /capabilities/<name>` is the `rest` experience. The reads of lesson 04 are not there: reads are logged only when
they are refused.

---

## Check

- [ ] `migrate status` shows `ran` for `20261105000100_add_books_isbn.sql`, and `get_book` answers with `"isbn"`.
- [ ] The four files of steps 2 to 4 are identical to the File blocks.
- [ ] `capabilities` lists 19 capabilities: `create_book` and `update_book` are `writes`, `delete_book` is `destroys`.
- [ ] The calls of steps 5 and 6 give the answers of the tables (use your ids).
- [ ] The agent's wrong book gets the same three messages as `curl`.

## With Claude

The plugin command `/caligrafy-quill:db` runs migrations, and `/caligrafy-quill:new-capability` writes capabilities:
```text
Add an optional ISBN to books with a migration: php caligrafer.php migrate make add_books_isbn, rename the file to
application/migrations/20261105000100_add_books_isbn.sql, one ALTER TABLE that adds isbn VARCHAR(20) NULL after summary
and the unique key uq_books_isbn; run it with /caligrafy-quill:db; add public $isbn to the Book model.
```
```text
/caligrafy-quill:new-capability create_book books:write
In application/services/Bookshelf/CreateBook.php, public: true with the comment "// for now: lesson 11 closes it".
Rules: title required|max_len,200; published_year required|integer|min_numeric,1450|max_numeric,current_year; summary
max_len,2000; isbn alpha_numeric_dash|max_len,20|unique,books.isbn; author_id required|integer|exists,authors.id.
Trim title, summary and isbn. An empty isbn is stored as NULL. Insert with Book::insert($input) and return
Book::with('author')->find($id). No routes.
```
Then the same for `update_book` (only `id` required, `unique,books.isbn,id,id`, empty title, year or author refused,
only the given inputs changed with `Book::where('id', ...)->update(...)`) and `delete_book` (`destructive: true`,
`Book::where('id', ...)->delete()`, 404 when it deleted nothing).

**Check** before you accept:
- [ ] The migration file has the course's name and was not edited after it ran; `create_db.sql` is unchanged.
- [ ] The rules are in `$rules` (`exists`, `unique`, `current_year`), not hand-written queries in `handle()`.
- [ ] In `update_book` only `id` is `required`; a call that only changes the summary works.
- [ ] Only `delete_book` has `destructive: true`; none of the three has `readOnly: true`.
- [ ] No SQL string, no `echo`; the calls of steps 5 and 6 give the answers of the tables.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 05: the ISBN migration, create_book, update_book and delete_book"
git tag lesson-05
```
Reference: [`checkpoints/lesson-05`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-05).

## Exercise

Kindred has an ISBN: `978-0-8070-8305-5`. Give it to book 3 with a migration (data can change in a migration too), so
that every copy of Bookshelf gets it.

<details><summary>Show the solution</summary>

```bash
docker compose exec dev-box php caligrafer.php migrate make set_kindred_isbn
```
In the file it wrote:
```sql
-- set_kindred_isbn
UPDATE books SET isbn = '978-0-8070-8305-5' WHERE id = 3 AND isbn IS NULL;
```
Then `docker compose exec dev-box php caligrafer.php migrate` prints ` OK   <number>_set_kindred_isbn.sql` and
` 1 migration(s) run on caligrafy`, and `call get_book id=3` shows `"isbn": "978-0-8070-8305-5"`. `AND isbn IS NULL`
keeps an ISBN someone already set. The checkpoint does not have this migration: if you keep it, the next lessons show
Kindred with its ISBN.
</details>

---
Previous: [Lesson 04 - Your first capabilities](04-Your-First-Capabilities) · Next: [Lesson 06 - Web pages](06-Web-Pages)
