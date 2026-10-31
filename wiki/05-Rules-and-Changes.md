# Lesson 05 - Rules: adding, changing and deleting books

**In this lesson** you write the three capabilities that change the catalog: `create_book`, `update_book` and
`delete_book`. Their rules (a title is required, a year is a number, the author must exist) are written once, and
you see the same refusal, with the same message, from `curl` and from an AI agent.

**Before you start:** lesson 04 is done (tag `lesson-04`): `docker compose exec dev-box php caligrafer.php capabilities`
lists `get_book`, `list_authors` and `list_books`, and `docker compose ps` shows the 3 containers running.

**Time:** 45 minutes.

---

## New concepts

**$rules: what a valid input is.** Each input gets a list of rules separated by `|`. Caligrafy checks them with the
GUMP library before `handle()` runs ([Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)):

| Rule | Meaning | Message when it fails |
|---|---|---|
| `required` | must be present and not empty | `The Title field is required` |
| `integer` | a whole number | `The Published Year field must be a number without a decimal` |
| `max_len,200` | at most 200 characters | |
| `min_numeric,1450` / `max_numeric,2100` | a number between these values | |

A rule other than `required` is **skipped when the input is empty**. So `'published_year' => 'integer'` accepts a
call without a year, and `'required|integer'` does not.

**$filters: clean first.** Filters change the inputs **before** the rules: `'title' => 'trim'` removes the spaces at
both ends, so `"  "` becomes `""` and fails `required`.

**$descriptions.** One sentence per input: the JS client and AI agents read them to know what to send. Write
"The id of its author (list_authors)", and an agent knows which tool gives that id.

**Checks that need the database go in `handle()`.** A rule cannot know whether author 9 exists. `handle()` checks it
and refuses with `throw ServiceError::invalid(array('author_id' => 'There is no such author'));`: the same shape as a
rule's message, so callers see no difference.

**The order of every call.** Whatever the channel, the framework does the same steps, and stops at the first
refusal:

| Step | Refusal | HTTP status |
|---|---|---|
| 1. Find the capability | `not_found` | 404 |
| 2. Check who calls (skipped when `public`) | `unauthenticated` / `forbidden` | 401 / 403 |
| 3. Filter, then check the inputs against `$rules` | `invalid`, with one message per field | 422 |
| 4. Run `handle()` (it may throw a `ServiceError`) | the kind of the error | |
| 5. Write the **audit log** (every refusal, and every success that changes data) | | |

**ServiceError kinds.** What `handle()` may throw, and the HTTP status each channel uses for it:

| Error | Status | When |
|---|---|---|
| `ServiceError::invalid(array('field' => 'message'))` | 422 | an input is wrong |
| `ServiceError::notFound('There is no book #60')` | 404 | the thing does not exist |
| `ServiceError::forbidden()` | 403 | this caller may not do it |
| `ServiceError::conflict('...')` | 409 | it clashes with the current state (lesson 13) |
| `ServiceError::unauthenticated()` | 401 | nobody is signed in |

**destructive.** `destructive: true` marks a capability that deletes or overwrites data. AI agents see it and ask
the person before calling it. Reads are `readOnly`, deletes are `destructive`, and everything else (create, update)
is neither.

**public, for now.** The three new capabilities are `public: true`, so you can try them without signing in. Writing
should not stay open to everyone: lesson 11 removes `public: true` and adds sign-in and tokens. Each file says so in a
comment.

---

## Manual track

Run every command from the `bookshelf` folder.

### Step 1 - create_book

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
```
Expected: ` Created application/services/Bookshelf/CreateBook.php: capability 'create_book' (scope books:write)` and
the `Fill in $rules and handle()...` line. No `--read-only` this time: it changes data. Replace the content of the file:

**File `application/services/Bookshelf/CreateBook.php`** (the rules of a valid book, in one place):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id. The author must already exist: use list_authors to find author_id.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
)]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,2100',
        'summary' => 'max_len,2000',
        'author_id' => 'required|integer'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim');
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published, for example 1969',
        'summary' => 'Optional: one or two sentences about the book',
        'author_id' => 'The id of its author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        if (!(new Author('authors'))->find($input['author_id'])) {
            throw ServiceError::invalid(array('author_id' => 'There is no such author'));
        }
        return (new Book('books'))->create($input);
    }
}
```
- `summary` has `max_len,2000` but no `required`: it is optional.
- `create($input)` inserts the row and returns the new book, with its id.

### Step 2 - update_book and delete_book

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
    description: 'Changes the title, year, summary or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
)]
class UpdateBook extends Service
{
    public $rules = array(
        'id' => 'required|integer',
        'title' => 'max_len,200',
        'published_year' => 'integer|min_numeric,1450|max_numeric,2100',
        'summary' => 'max_len,2000',
        'author_id' => 'integer'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim');
    public $descriptions = array(
        'id' => 'The id of the book to change',
        'title' => 'A new title',
        'published_year' => 'A new year of publication',
        'summary' => 'A new summary',
        'author_id' => 'The id of a new author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        $books = new Book('books');
        if (!$books->find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        if (isset($input['title']) && $input['title'] === '') {
            throw ServiceError::invalid(array('title' => 'The Title field is required'));
        }
        if (isset($input['author_id']) && !(new Author('authors'))->find($input['author_id'])) {
            throw ServiceError::invalid(array('author_id' => 'There is no such author'));
        }

        // keep only the inputs that were given
        $changes = array();
        foreach (array('title', 'published_year', 'summary', 'author_id') as $field) {
            if (isset($input[$field])) {
                $changes[$field] = $input[$field];
            }
        }
        return $changes ? $books->update($changes, $input['id']) : $books->find($input['id']);
    }
}
```
- Only `id` is `required`: a caller may change just the summary. That is also why `title` has no `required` rule,
  and why `handle()` refuses an empty title itself, with the same message as the rule.
- `$changes` keeps only the inputs that were sent, so a missing input never erases a column.

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
        $books = new Book('books');
        if (!$books->find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        $books->delete($input['id']);
        return array('deleted' => (int) $input['id']);
    }
}
```

Check:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected (the third column is the kind: `read`, `writes` or `destroys`):
```
 create_book                  public         writes    Adds a book to the Bookshelf catalog and returns it with its id. The author must already exist: use list_authors to find author_id.
 delete_book                  public         destroys  Deletes a book of the catalog, by its id. This cannot be undone.
 get_book                     public         read      Returns one book of the catalog (id, title, year, summary) and its author (id, name, bio), by the id of the book.
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title: id, title, year, author id and author name. Give author_id to list only the books of one author.
 update_book                  public         writes    Changes the title, year, summary or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book.
```

### Step 3 - Add a book, then break the rules

Every call below is `POST /capabilities/<name>` with a JSON body, as in lesson 04. `-w` prints the status at the end.

A valid book:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "The Dispossessed", "published_year": 1974, "author_id": 1}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/create_book
```
Expected: `{"data":{"id":6,"title":"The Dispossessed","published_year":1974,"summary":null,"author_id":1,...}} [201]`.
**201** means "created". This endpoint answers 201 for every successful call of a capability that changes data
(even a delete), because the call is a POST; lesson 07 gives each action its own status.

Two wrong inputs:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "", "published_year": "soon", "author_id": 9}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/create_book
```
Expected: `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required","published_year":"The Published Year field must be a number without a decimal"}}} [422]`.
Author 9 does not exist, but there is no message about it: the rules failed first, so `handle()` never ran.

Valid inputs, but an author that does not exist:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "Ghost", "published_year": 2001, "author_id": 9}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/create_book
```
Expected: `{"error":{"code":"invalid",...,"fields":{"author_id":"There is no such author"}}} [422]`: the check in `handle()`.

### Step 4 - Change and delete

Use the id that create_book returned (6 if you followed every step):
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"id": 6, "summary": "An anarchist physicist crosses to the twin world."}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/update_book
```
Expected: the book with its new summary, the title and year unchanged: `{"data":{"id":6,"title":"The Dispossessed","published_year":1974,"summary":"An anarchist physicist crosses to the twin world.",...}} [201]`.

Now change only the `-d` part of the same command:

| `-d` | Expected | Status |
|---|---|---|
| `'{"id": 6, "title": ""}'` | `"fields":{"title":"The Title field is required"}` | 422 |
| `'{"id": 60, "title": "x"}'` | `{"error":{"code":"not_found","message":"There is no book #60"}}` | 404 |

Delete the book:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"id": 6}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/delete_book
```
Expected: `{"data":{"deleted":6}} [201]`. Run the same command again: `{"error":{"code":"not_found","message":"There is no book #6"}} [404]`.
You do not need to add The Dispossessed back: the next lessons add their own books.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | Every input is reported as missing, or curl waits for more input (`>`) | The JSON of `-d '...'` is broken: a quote is missing, or copying changed `'` into `’` | Press Ctrl+C, then copy the whole command again or type the quotes yourself |
> | A book is created although the title is empty | `$rules` or `$filters` was mistyped, so the rule is not applied | Compare the file with the File block above: `'title' => 'required|max_len,200'` |
> | `The Published Year field is required` although you sent it | The input name is wrong (`year`, `publishedYear`) | Use the names of `$rules`: `published_year`, `author_id` |
> | `"summary":"..."` was erased by an update | You changed the `foreach` of update_book | Only the inputs that are set may go into `$changes` |
> | `There is no such author` for author 1 | The authors were not loaded (lesson 03) | Run `seed.sql` again (lesson 03) |
> | `capabilities` shows `writes` for delete_book | `destructive: true` is missing | Add it to the `#[Capability(` block |

### Step 5 - The same rule, the same message, for an AI agent

Your agent of lesson 02 can now add books too. Send it the same wrong book over MCP (the token is in `$TOKEN`; in a
new terminal window, run `read -rs TOKEN` again and paste it):
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"create_book","arguments":{"title": "", "published_year": "soon", "author_id": 9}}}' http://localhost:8080/mcp
```
Expected: a tool error, `"isError":true`, whose text is the same `{"error":{"code":"invalid",...,"fields":{"title":"The Title field is required","published_year":"The Published Year field must be a number without a decimal"}}}` as in step 3.

You wrote the rule once. `curl` got it, the agent gets it, and in lesson 06 the web page shows it under the form.
An agent that gets this error can read the field messages and correct its call by itself.

In Claude Code, ask:
```
Delete the book Kindred.
```
Claude finds the id with `list_books`, then, because `delete_book` is destructive, asks you to confirm before
calling it. Say no: Kindred is used in the next lessons. If you said yes by mistake, run
`docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/seed.sql`: `INSERT IGNORE` puts
book 3 back.

Then look at the **audit log**, the list of calls that changed data or were refused:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 10
```
Expected: one line per call of steps 3 to 5 (time, channel, who, id, capability, outcome, message), for example
`... mcp   token  1   create_book   invalid  Some of the inputs are invalid` for the agent's call, and lines with
`rest   guest  -   create_book   ok` or `invalid` for your `curl` calls (`POST /capabilities/<name>` is the `rest`
channel). The read-only calls of lesson 04 are not there: reads are only
logged when they are refused.

---

## With Claude

Start Claude Code in the `bookshelf` folder. Run the plugin command once per capability:

```
/caligrafy-quill:new-capability create_book books:write
In application/services/Bookshelf/CreateBook.php (caligrafer service Bookshelf/CreateBook). public: true with the
comment "// for now: lesson 11 closes it". Rules: title required|max_len,200; published_year
required|integer|min_numeric,1450|max_numeric,2100; summary max_len,2000; author_id required|integer. Filters: trim
on title and summary. A description for every input (author_id: 'The id of its author (list_authors)'). handle():
if the author does not exist, throw ServiceError::invalid(array('author_id' => 'There is no such author')), else
create the book and return it. Description: 'Adds a book to the Bookshelf catalog and returns it with its id. The
author must already exist: use list_authors to find author_id.' No routes.
```
```
/caligrafy-quill:new-capability update_book books:write
In application/services/Bookshelf/UpdateBook.php, public: true (same comment). Input id required|integer; title
max_len,200, published_year integer|min_numeric,1450|max_numeric,2100, summary max_len,2000, author_id integer, all
optional; trim title and summary. 404 "There is no book #<id>" when the book does not exist; an empty title is
refused with ServiceError::invalid(array('title' => 'The Title field is required')); an unknown author_id with
'There is no such author'. Change only the inputs that are given and return the book. No routes.
```
```
/caligrafy-quill:new-capability delete_book books:write destructive
In application/services/Bookshelf/DeleteBook.php, public: true (same comment), destructive: true. Input id
required|integer. 404 "There is no book #<id>" when it does not exist; otherwise delete it and return
array('deleted' => (int) id). Description: 'Deletes a book of the catalog, by its id. This cannot be undone.'
No routes.
```

**What Claude should do:** scaffold and fill the three files of steps 1 and 2, then try them with `curl` (step 3 and 4).

**Check** before you accept:
- [ ] Names `create_book`, `update_book`, `delete_book`, all `scope: 'books:write'` and `public: true` with the "lesson 11" comment.
- [ ] None of them has `readOnly: true`; only `delete_book` has `destructive: true`.
- [ ] Every input `handle()` reads is in `$rules`; in update_book only `id` is `required`.
- [ ] The author check and the empty-title check throw `ServiceError::invalid(...)` with a message per field, never `echo`.
- [ ] `capabilities` shows `writes`, `destroys`, `writes`.
- [ ] The calls of steps 3 to 5 give the expected answers (the ids may differ: use yours).

**If Claude's result differs:** Claude may write `'title' => 'required|max_len,200'` in update_book too. Then a call
that only changes the summary is refused: remove `required` from everything but `id`. Claude may also want to make
the capabilities private at once. That is right for a real application, but the course opens them until lesson 11:
keep `public: true` with the comment. Descriptions in other words are fine: the `capabilities` output will show yours instead of the one in the Manual
track.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 05: create_book, update_book and delete_book"
git tag lesson-05
```
Reference: [`checkpoints/lesson-05`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-05).

## Exercise

Bookshelf should refuse a book published in the future. Change `create_book` so that a year after this year is
refused with the message `The year cannot be in the future`, for `curl` and for agents at once. Try it with 2099.

<details><summary>Show the solution</summary>

A rule like `max_numeric` needs a fixed number, so check it in `handle()`, before `create()`:
```php
if ($input['published_year'] > (int) date('Y')) {
    throw ServiceError::invalid(array('published_year' => 'The year cannot be in the future'));
}
```
Then:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "Tomorrow", "published_year": 2099, "author_id": 1}' -w ' [%{http_code}]\n' http://localhost:8080/capabilities/create_book
```
answers 422 with `"fields":{"published_year":"The year cannot be in the future"}`. You changed one file: the MCP
tool, the generic endpoint, and the pages and REST routes you add next all refuse it the same way. (`update_book`
would need the same check. Remove the change before you commit, or keep it in both: the checkpoint does not have it.)
</details>

---
Previous: [Lesson 04 - Your first capabilities](04-Your-First-Capabilities) · Next: [Lesson 06 - Web pages](06-Web-Pages)
