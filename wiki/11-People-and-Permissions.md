# Lesson 11 - People and permissions

**In this lesson** Bookshelf gets accounts and rules: everyone can still read the catalog, signed-in people can add
and edit books, only administrators can delete them, and each program or agent gets a token with just the rights it
needs. You close the doors once, in three capabilities, and every experience (pages, REST, agents, the finder, the
SPA) obeys at once. The pages show only the buttons the visitor may use.

**Before you start:** lesson 10 is done (tag `lesson-10`), the containers run (`docker compose ps`), and you have the
token of lesson 02 in `$TOKEN` (`TOKEN=cq_1_...`, see [Lesson 08](08-AI-Agents)).

**Time:** 60 to 75 minutes.

---

## New concepts

**Authentication and authorization.** Authentication answers "who are you?" (a person signs in with a username and a
password; a program sends a token). Authorization answers "may you do this?" (only administrators delete books).

**Session.** HTTP forgets everything between two requests. PHP keeps a **session** per browser: a small storage on the
server, found again thanks to a cookie the browser sends with every request. When a person signs in, Caligrafy
stores the user in the session. The **CSRF** token of lesson 06 lives there too: forms (`csrf()`) and the JS client
(`X-CSRF-Token`) send it back, so another site cannot make your browser change data.

**The users table and the permission level.** Caligrafy ships an authentication module (sign-in and register pages,
`Caligrafy\Controllers\AuthController`, the `User` model). Its table `users` has `id`, `username`, `passcode` (a
one-way hash, never the password), `permissions` (0 for every new account), `created_at`, `modified_at`. The number in
`permissions` is the **permission level** of the person: 0 for a reader, 1 for an administrator in Bookshelf.

**Three ways a capability says who may call it**

| In `#[Capability]` | Meaning |
|---|---|
| `public: true` | anyone, even a visitor nobody knows. Scope and level are not checked |
| `scope: 'books:write'` | a **token** needs this scope (`books:write`, `books:*` or `*`) |
| `level: 1` | a **person** needs `permissions` of 1 or more (default `level` is 0: any signed-in person) |

**Who is calling, and what is checked**

| Caller | How it is recognized | Checked against |
|---|---|---|
| visitor | nothing | only `public` capabilities (else 401 `unauthenticated`) |
| person signed in | the session cookie | the `level` (people have no scopes) |
| **application token** (`token create ... --scopes ...`) | `Authorization: Bearer cq_...` | its **scopes only**: no person behind it, so `level` does not apply |
| token **for a person** (`token create ... --user <id>`) | `Authorization: Bearer cq_...` | its scopes **and** the `level` of that person |

Read the last two lines twice: `level` applies to people, and to tokens created with `--user`. An application token is
limited by its scopes only. If you give `books:write` to an application token, it can delete books, administrator or
not: give write scopes with care.

**Show only what is allowed.** `GET /capabilities` (the function `capabilities()` of the JS client) lists only the
capabilities the caller may call. The catalog page asks it once and shows the form if `create_book` is in the list,
Delete if `delete_book` is. Hiding a button is for comfort; the protection is in the capability, which refuses on
every channel.

---

## Manual track

### Step 1 - Create the users table

```bash
docker compose exec dev-box php caligrafer.php sql application/models/Auth/create_db.sql
```
Expected: ` application/models/Auth/create_db.sql run on caligrafy`. The script only creates the table if it is
missing: running it again never deletes users.

### Step 2 - Turn on sign-in

In `application/config/routing/web.php`:

1. Uncomment the authentication line (remove the `//`): it says which model and table hold the people.
   ```php
       Auth::authentication('User', 'users');
   ```
2. Replace the route of `/` with:
   ```php
       Route::get('/', 'Bookshelf\\BookController@catalog');
   ```
3. Replace the commented block `// Auth Routes - Uncomment only if AUTHENTICATION activated above` and its 7 lines with:
   ```php
       // SIGN IN, SIGN OUT, REGISTER: the controllers of the framework; after signing in, /home is the catalog
       Route::get('/home', 'Bookshelf\\BookController@catalog');
       Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
       Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
       Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');
       Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
       Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
       Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');
   ```
4. In the `// BOOKSHELF PAGES` block, replace the route of `/books` with:
   ```php
       Route::get('/books', 'Bookshelf\\BookController@catalog');
   ```

The sign-in page sends people to `/home` after signing in and signing out: it now shows the catalog. The full file is
in the checkpoint.

### Step 3 - Close the doors, once

In `application/services/Bookshelf/CreateBook.php` and `UpdateBook.php`, remove `public: true` (and the comma before
it). The end of the `#[Capability]` block becomes:
```php
    scope: 'books:write'
)]
```
In `DeleteBook.php`, replace the `public: true` line with a level:
```php
    destructive: true,
    level: 1   // only users whose permissions are 1 or more (administrators)
)]
```
The four reads stay `public: true`. Check:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected: the reads still say `public`, and the writes now show their scope:
```
 create_book  books:write  writes   ...
 delete_book  books:write  destroys ...
 update_book  books:write  writes   ...
```
That is the whole security change. Everything below is about telling people and programs what they may do.

### Step 4 - The pages show what the visitor may do

**File `application/controllers/Bookshelf/BookController.php`** (a new `catalog()` action; every view gets `authorized`; `update()` shows any refusal):

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;
use Caligrafy\ServiceError;

// The Bookshelf pages: the catalog, and the page of one book rendered from the get_book and update_book capabilities
class BookController extends Controller
{
    // GET /, /books and /home: the catalog (public/js/books.js does the rest)
    public function catalog()
    {
        // install: offer to add it to the home screen; authorized: the header shows Sign out
        return view('Bookshelf/books', array('install' => true, 'authorized' => authorized()));
    }

    // GET /books/{id}
    public function show()
    {
        try {
            $found = $this->call('get_book');   // the input is {id}, from the route
            return $this->page($found, $found['book']);
        } catch (ServiceError $e) {
            return $this->notFound($e);
        }
    }

    // PUT /books/{id}: the edit form of the page is sent here
    public function update()
    {
        try {
            $book = $this->call('update_book');   // the inputs are {id} and the fields of the form
            redirect('/books/' . $book['id']);
            exit;
        } catch (ServiceError $e) {
            if ($e->kind === 'not_found') {
                return $this->notFound($e);
            }
            // show the page again: the form keeps what was typed, with the messages of the capability
            http_response_code($e->status());
            $found = $this->call('get_book');
            $errors = $e->fields ?: array($e->getMessage());   // "Sign in or send a personal access token"
            return $this->page($found, array_merge($found['book'], $this->request->parameters), $errors);
        }
    }

    private function page(array $found, array $form, array $errors = array())
    {
        return view('Bookshelf/book', array(
            'book' => $found['book'],
            'author' => $found['author'],
            'form' => $form,
            'authors' => $this->call('list_authors', array()),
            'errors' => $errors,
            'authorized' => authorized()
        ));
    }

    private function notFound(ServiceError $e)
    {
        http_response_code($e->status());
        return view('Bookshelf/notfound', array('message' => $e->getMessage(), 'authorized' => authorized()));
    }
}
```

- `authorized()` is `true` when someone is signed in: the layout then shows **Sign out** in the header.
- When `update_book` refuses a visitor, `$e->fields` is empty, so the page shows the message of the capability
  (`Sign in or send a personal access token`) with its status.

**File `application/views/Bookshelf/book.pug`** (the edit form only for people signed in):

```pug
extends ../default/master/layoutblocks

//- One book, rendered on the server by Bookshelf\BookController from get_book; the form (signed in only) calls update_book

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title= $book['title']
                p.cq-note
                    | by #{$author['name']}, #{$book['published_year']}
            .cq-section
                p= $book['summary'] ?: 'No summary yet.'

            if $authorized
                form.cq-form(method='POST', action="{$home}books/{$book['id']}", novalidate)
                    =csrf()
                    =methodField('PUT')
                    .cq-fields.cq-fields--2
                        div
                            label(for='title') * Title
                            input#title.form-control(type='text', name='title', value=$form['title'], maxlength='200')
                        div
                            label(for='author_id') * Author
                            select#author_id.form-select(name='author_id')
                                each $a in $authors
                                    option(value=$a['id'], selected=$a['id'] == $form['author_id'])= $a['name']
                        div
                            label(for='published_year') * Year
                            input#published_year.form-control(type='number', name='published_year', value=$form['published_year'])
                        div
                            label(for='summary') Summary
                            input#summary.form-control(type='text', name='summary', value=$form['summary'], maxlength='2000')
                    button.btn.btn-primary.cq-submit(type='submit') Save changes
            else
                .cq-section
                    a.btn.btn-secondary(href="{$home}login") Sign in to edit this book

            if $errors
                p.cq-error(role='alert')= implode(' ', $errors)

            .cq-foot
                a(href="{$home}books") All books
```

In `application/views/Bookshelf/books.pug`, hide the form until `books.js` decides, and add a sign-in button after
the form (above `p#error.cq-error(role='alert')`):
```pug
            //- shown by books.js when the visitor may call create_book
            form#new-book.cq-form(novalidate, hidden)
```
```pug
            .cq-section#sign-in(hidden)
                a.btn.btn-secondary(href="{$home}login") Sign in to add books
```
In `public/js/books.js`, import `capabilities` too, and remember what the visitor may call:
```js
import { capabilities, listBooks, listAuthors, createBook, deleteBook, CapabilityError } from './capabilities.js'
```
```js
let allowed = new Set()   // the capabilities this visitor may call
```
In `row()`, before the Delete button is created:
```js
  if (!allowed.has('delete_book')) return li   // only administrators see Delete
```
And above `authors = await listAuthors()` at the end:
```js
// What may this visitor do? GET /capabilities lists only the capabilities they may call
allowed = new Set((await capabilities()).map((c) => c.name))
form.hidden = !allowed.has('create_book')
document.getElementById('sign-in').hidden = allowed.has('create_book')
```
The full files are in the checkpoint.

### Step 5 - Regenerate both clients

```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```
Expected: `... written: 7 capabilities ...` twice. In the comments, `create_book (writes, public)` became
`create_book (writes, books:write)`.

### Step 6 - Try it as a visitor, then as a reader

1. Open http://localhost:8080/books: the list, **Sign in to add books**, no form, no **Delete**. Open a book: **Sign in
   to edit this book** instead of the form.
2. Click **Sign in**, then the link to register (http://localhost:8080/register). Username `reader1`, passcode
   `bookshelf-2026` twice. You land on the sign-in page (`/login`). Usernames are letters and digits only; passcodes
   8 to 72 characters.
3. Sign in as `reader1`. You land on `/home`: the catalog, with **Sign out** in the header and the form. Still no
   **Delete**: reader1 has permissions 0. Add a book: it works.
4. Open a book, empty its title, **Save changes**: `The Title field is required`.

The REST API obeys the same rules:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "x", "published_year": 2000, "author_id": 1}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: `{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}} [401]`.
`curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/books` still answers `[200]`.

### Step 7 - Tokens with just the rights they need

Revoke the `*` token of lesson 02 (it can do everything). Find the id of "my agent" with
`docker compose exec dev-box php caligrafer.php token list`, then revoke it (1 if you created no other token;
otherwise replace 1 with that id):
```bash
docker compose exec dev-box php caligrafer.php token revoke 1
```
Expected: ` Token #1 revoked` (your ids may differ). The tools/list curl of lesson 08 with `$TOKEN` now answers 401 `Unauthorized`.

A token that can only read:
```bash
docker compose exec dev-box php caligrafer.php token create "catalog reader" --scopes books:read
READER=cq_2_paste-the-token-it-printed
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $READER" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected: `get_book`, `list_authors`, `list_books`, `search_books`: an agent with this token does not even see the
write tools. A write with it:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $READER" -d '{"title": "x", "published_year": 2000, "author_id": 1}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: `{"error":{"code":"forbidden","message":"This needs the scope books:write"}} [403]`.

An application token that can read and write, for Claude Code:
```bash
docker compose exec dev-box php caligrafer.php token create "Claude Code" --scopes books:read,books:write
WRITER=cq_3_paste-it-here
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $WRITER" -d '{"title": "Token test", "published_year": 2026, "author_id": 3}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: `[201]` and the new book. Keep its `id` in a variable (paste the id it returned), for example:
```bash
BOOK_ID=12
```
With the reader's tools/list command above and `$WRITER`, you see
all 7 tools.

A token that acts for reader1 (`--user 1`: 1 = reader1, the first account):
```bash
docker compose exec dev-box php caligrafer.php token create "librarian agent" --scopes "books:*" --user 1
LIBRARIAN=cq_4_paste-it-here
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $LIBRARIAN" -w " [%{http_code}]\n" http://localhost:8080/api/books/$BOOK_ID
```
Expected: `{"error":{"code":"forbidden","message":"This needs the scope books:write and permission level 1"}} [403]`:
`books:*` includes `books:write`, but reader1 has permissions 0. Its tools/list has no `delete_book`. Now the same
DELETE with `$WRITER`:
```bash
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $WRITER" -w " [%{http_code}]\n" http://localhost:8080/api/books/$BOOK_ID
```
Expected: `{"data":{"deleted":...}} [200]`. The application token has the scope, and no person, so no level applies.

Connect Claude Code with its own token instead of the revoked one:
```bash
claude mcp remove bookshelf
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer $WRITER"
```

### Step 8 - Make reader1 an administrator

```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "UPDATE users SET permissions = 1 WHERE username = 'reader1'"
```
(The password warning is harmless.) Reload the catalog: you are signed out. Changing the row of a user ends their
session: **sign in again** as reader1. Now every book has **Delete**. The librarian agent's tools/list now includes
`delete_book`, because it acts for reader1. Click **Sign out**: the book page says **Sign in to edit this book** again.

### Step 9 - Read the audit log and the other experiences

```bash
docker compose exec dev-box php caligrafer.php audit --limit 10
```
Every refusal is there, with the channel and who: `rest  token  2  create_book  forbidden  This needs the scope books:write`,
`rest  guest  -  create_book  unauthenticated  Sign in or send a personal access token`, `rest  user  1  create_book  ok`
(your ids may differ).

- The finder (http://localhost:8080/apps/finder) still works for visitors: it only reads.
- The SPA (`npm run dev` in `public/bookshelf-spa`): as a visitor, **Add book** answers `Sign in or send a personal
  access token`. Sign in on http://localhost:8080, then add a book on http://localhost:5173: it works (the cookie
  belongs to `localhost`, and the proxy forwards it). The built SPA on 8080 shares the session too.
- `token list` shows ` #4 active librarian agent books:* user 1 ...`, ` #3 active Claude Code books:read books:write ...`,
  ` #2 active catalog reader books:read ...`, ` #1 revoked my agent * ...` (your ids may differ).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | An error that ends with `Table 'caligrafy.users' doesn't exist` | Step 1 was skipped | Run the users script |
> | After signing in you see the Caligrafy welcome page | `/home` still points to `AuthController@home` | `Route::get('/home', 'Bookshelf\\BookController@catalog');` |
> | Signed in, but no **Sign out** and no form | The view did not receive `authorized`, or the clients were not regenerated | Compare `BookController`; run step 5; reload with <kbd>Shift</kbd> |
> | The form still shows to visitors | `books.pug` has no `hidden` on the form, or `books.js` is the old one | Compare with step 4 |
> | No **Delete** for the administrator | You did not sign in again after the `UPDATE` | Sign out, sign in |
> | Sign up says the inputs are invalid | A space or a dash in the username, or a passcode under 8 characters | Letters and digits; 8 to 72 characters |
> | 401 with a token you just created | The variable is empty or cut | `echo $WRITER`; paste the whole `cq_...` value |
> | Claude Code still answers `Unauthorized` | It uses the revoked token | Remove and add the server again (end of step 7) |

---

## With Claude

In `claude`, in the `bookshelf` folder:
```
/caligrafy-quill:db application/models/Auth/create_db.sql
```
then:
```
Turn on people and permissions in Bookshelf. In web.php: uncomment Auth::authentication('User', 'users'); replace
the commented "Auth Routes" block with the AuthController routes (GET /login, /logout, /notAuthorized, /register,
POST /login, /register) and GET /home; /, /books and /home use a new action Bookshelf\BookController@catalog that
returns view('Bookshelf/books', array('install' => true, 'authorized' => authorized())). Capabilities: remove
public: true from create_book and update_book; delete_book gets level: 1 instead (only permissions >= 1); the reads
stay public. BookController: every view gets 'authorized' => authorized(); in update(), only not_found shows the
not-found page; otherwise answer $e->status() and show $e->fields or, when empty, the capability's message.
book.pug: the edit form only when $authorized, else a "Sign in to edit this book" button to /login. books.pug: the
form is hidden by default, plus a hidden #sign-in section with "Sign in to add books". books.js: ask capabilities()
once; show the form only if create_book is allowed (else #sign-in), and Delete only if delete_book is allowed.
Regenerate public/js/capabilities.js and public/bookshelf-spa/src/capabilities.js. Do not create tokens or users.
```

**What Claude should do:** run the users script, then change `web.php`, the three services, `BookController`, the two
views and `books.js` as in steps 2 to 4, and regenerate both clients.

**Check** before you accept:
- [ ] `capabilities`: the 4 reads `public`; `create_book`, `update_book`, `delete_book` show `books:write`.
- [ ] `DeleteBook.php` has `level: 1` and still `destructive: true`; no `public` on the three writes.
- [ ] The protection is in the services, not only in the views: the visitor curl of step 6 answers 401.
- [ ] `books.js` uses `capabilities()`, not a hard-coded "signed in" test.
- [ ] No `echo`, `view()` or `redirect()` was added to a service; `AuthController` in `application/controllers/core` was not edited.
- [ ] No password, hash or token in the chat or in a file.
- [ ] Steps 6 to 8 give the same results.

**If Claude's result differs:** if it protects the actions in the controller with `guard()` or `authorized()` and
leaves `public: true` in the services, the page is safe but REST, the agents and the SPA are not: ask it to close the
capabilities instead. If it gives the "librarian" token no `--user`, `level` does not apply to it (it is an
application token). Do steps 6 to 8 yourself: they are the proof.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 11: sign-in, scopes, levels and tokens"
git tag lesson-11
```
Reference: [`checkpoints/lesson-11`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-11).
Tokens are never in the project. Learn more: [9.1 Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
and the tokens section of [11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Exercise

1. The SPA still shows **Add book** to visitors and **Delete** to everyone (and gets refused). Make `BooksPage.vue`
   show the form only when `create_book` is allowed, like the catalog page.
2. A token created with `--scopes books:read,books:write` and no `--user` deletes a book. reader1, signed in with
   permissions 0, cannot. Why?

<details><summary>Show the solution</summary>

1. In `public/bookshelf-spa/src/components/pages/BooksPage.vue`, import `capabilities` too, keep the names in
   `data()` and ask once in `mounted()`:
   ```js
   import { capabilities, listBooks, searchBooks, listAuthors, createBook, CapabilityError } from '../../capabilities.js'
   ```
   ```js
     data () {
       return { books: [], authors: [], allowed: [], query: '', form: { title: '', author_id: '', published_year: '' }, error: '', timer: null }
     },
     async mounted () {
       this.allowed = (await capabilities()).map((c) => c.name)
       this.authors = await listAuthors()
       this.form.author_id = this.authors.length ? this.authors[0].id : ''
       this.books = await listBooks()
     },
   ```
   In the template, add `v-if="allowed.includes('create_book')"` to the
   `<form>`, and after it `<p class="cq-note" v-else>Sign in on Bookshelf to add books.</p>`. Do the same in
   `BookPage.vue` for Delete with `delete_book`. No server change: the capabilities already say who may do what.
   (This is not in the checkpoint: undo it with `git checkout public/bookshelf-spa` if you want to stay identical.)
2. `level` applies to people, and to tokens created with `--user`. A token without `--user` is an application token:
   only its scopes count, and it has `books:write`. reader1 is a person with permissions 0, below the `level: 1` of
   `delete_book`.
</details>

---
Previous: [Lesson 10 - Phones and tablets](10-Phones-and-Tablets) · Next: [Lesson 12 - Ship it: tests, review, doctor](12-Ship-It)
