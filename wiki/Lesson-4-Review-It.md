# Lesson 4 - Review what Claude built

**You will** try Bookshelf in every way people and programs use it, open each file Claude wrote and know what to look
for in it, and have it reviewed, before you ship it. **Time:** 45 minutes to an hour.

---

## 1. Try every experience

Claude built each feature **once**, as a capability, and every experience calls it. Try them; they should agree.

**The web page.** Open `http://localhost:8080/books` (or the port Claude gave you). As a visitor you can browse,
filter by author and open a book's page. Sign up with your email address and a password of at least 8 characters, then
sign in: signing up does not sign you in. A text with no `@` is refused. Add an author and a book, edit it, delete it. Leave the
title empty and add it: the message shows under the title field, and the address in your browser does not change to
one with a `?`.

**The export.** Signed in, open `http://localhost:8080/books.csv`: your browser downloads the books as a CSV file. As a
visitor, the same address sends you to the sign-in page.

**The phone.** In your browser, open the developer tools and switch to a phone size (375 px wide): the page fits, with
no sideways scrolling. Online, in [Lesson 5](Lesson-5-Ship-It), you will install it on a real phone.

**The REST API**, for other programs:
```bash
curl -s -H "Accept: application/json" http://localhost:8080/api/books
curl -s -X POST -H "Accept: application/json" -d "title=Test" http://localhost:8080/api/books
```
The first lists the books, a page at a time: `{"data":{"data":[...],"total":...,"page":1,"per_page":20,"pages":...}}`. The second is
refused, exactly as on the page:
```json
{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}}
```

**An AI agent.** Signed in on the page, use the **AI agents** card: give the token a name, create it, and copy the
`claude mcp add ...` line it shows. The token is shown once: never paste it in a chat. Run that line in a terminal,
start a new `claude` session, and ask:
```text
Which books do we have, and who wrote them?
```
Claude answers from your app, through the same capabilities (MCP, the protocol AI agents use to call tools). Ask it to
add a book without a title: it gets the same message the page showed.

---

## 2. Open what Claude wrote

Ask Claude:
```text
Show me every file you wrote for Bookshelf, in one short table: what it is and where it lives.
```
Then open each one. Claude follows the same layout in every Caligrafy app, so this table is also your map for the
next ones:

| What | Where |
|---|---|
| The tables and the sample data | `application/models/<Folder>/create_db.sql` |
| The later changes to the tables | `application/migrations/` (empty until [Lesson 6](Lesson-6-Change-It)) |
| The models, one per table | `application/models/<Folder>/<Thing>.php` |
| The capabilities, one file each | `application/services/<Folder>/` |
| The token card's capability | `application/services/Tokens/CreateMyToken.php` |
| The addresses: sign-in, pages, forms, the export, REST, MCP | `application/config/routing/web.php` |
| The pages | `application/views/<Folder>/index.pug` (and `show.pug` for a book), with `public/js/<things>.js` when the page calls the JS client |
| The JS client, generated from the capabilities | `public/js/capabilities.js` |
| The tests | `tests/<Folder>Test.php` |
| What a shared link shows (title, description) | `metadata()` in `application/config/config.php` |

### What to look for in each

**The tables** (`create_db.sql`). Each table starts with `CREATE TABLE IF NOT EXISTS` and has an `id`, a
`created_at` and a `modified_at`. `books` has an `author_id` with a `FOREIGN KEY` to `authors`. The sample rows use
`INSERT IGNORE` with fixed ids. Nothing says `DROP`, `DELETE` or `TRUNCATE`: the script runs again at every deploy,
and those words would erase your data online.

**The models** (`Book.php`, `Author.php`). One property per column. No query of their own, and no method for the link:
the capabilities do the reading, and `with('author')` follows the column `author_id`.

**The capabilities.** Open the one that creates a book. Above the class, `#[Capability(...)]` gives its `name`, its
`description` (what an AI agent reads to choose it: it must be clear), its `scope` (`books:write`), and flags:
`readOnly: true` on reads, `destructive: true` on deletes, `public: true` only on reads anyone may do. In the class,
`$rules` says what a valid input is (`'title' => 'required|max_len,255'`), and `handle()` does the work and refuses
with `ServiceError` (`invalid`, `notFound`, `forbidden`, `conflict`). Then open the one that lists the books: it reads
with the **query builder**, in one chain and with no SQL text, for example
`Book::query()->latest()->with('author')->paginate(20, $input['page'] ?? 1)`. `with('author')` loads every author in
one query, instead of one query per book; `paginate()` returns one page, with the total.

**Signing in with an email.** `application/models/Auth/create_db.sql` has `username varchar(254)` (or a migration
changes it). `application/services/Users/CaligrafyRegisterUser.php` has the rule `valid_email|max_len,254` and the filter
`trim|lower_case`. The forms say Email, and the field is still named `username`: no separate `email` column is needed. The
message of a failed sign-in says email, not username (`messages` of `Route::auth`).

**The token card's capability** (`CreateMyToken`). It refuses to run when it is called with a token (only a signed-in
person on the site creates tokens), gives the new token the app's own scopes, never `*`, and an end date (90 days).

**The addresses** (`web.php`). Look for, in this order:
- `Auth::authentication('User', 'users');` and `Route::auth(['home' => '/books']);`: the sign-in, sign-out and sign-up
  pages, in one line, with failed sign-ins limited;
- `Route::page(...)`: one line per page, for example
  `Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);`: the page gets its data from a
  read-only capability. Options you may meet: `comeBack` (a visitor signs in and comes back), `visitorsOnly` (a page
  members do not need), `query` (what the view reads from the address, never `$_GET`);
- `Route::form(...)`, when a page sends a classic form: the form posts to a capability, and on success goes to the
  page named by `success`, with a short message (`flash`). Options you may meet: `same` (two fields that must match),
  `accepted` (a box that must be ticked), `signsIn` (the person is signed in by the form), `inputs` (values only the
  server sets);
- `Route::action(...)`, only for what a page or a form cannot say: a link that decides where to go, a payment page. Its
  capability says `webOnly: true`, so REST and AI agents do not see it. Nothing else should be web-only;
- `Route::download('/books.csv', ...)`: the export, from a read-only capability;
- `Route::capability('GET', '/api/books', 'list_books');` and one line per capability: the REST API;
- one `Route::capabilities('/capabilities');` (what the JS client calls) and one `Route::mcp('/mcp');` (AI agents);
- `Route::run();`, the last line of the `try` block.

A `Route::webhook(...)` line appears only in apps that take payments or receive another service's events: Bookshelf
has none.

**The pages** (`.pug`). They start with `extends ../default/master/site` and use Caligrafy's classes (`cq-hero`,
`cq-field`, `cq-rows`, `cq-empty-state`...), with no `style` block. The top bar does not move from page to page; if a link
is marked as the current page, it has a color or an underline, not a heavier weight. Every `form` has `method='POST'`. Each field has its
message right under it (`p.cq-field-error`). A form that posts through `Route::form` carries `=csrf()`, a hidden
anti-forgery token. A page that calls the JS client (`createBook(...)` in `public/js/<things>.js`) never holds a key. A
client app (`public/<app>/index.php`) loads `app.js?v=...` and its scripts through `loadVue()`: the number makes browsers
fetch the new scripts after a deploy.

**The tests** (`tests/<Folder>Test.php`). Each capability is called as a member, and its refusals are checked: an
invalid input, a visitor.

`application/controllers/` has nothing of Bookshelf's own: every request is one line of `web.php`, and since Caligrafy
Quill 2.21 no application needs a controller. If Claude wrote one, ask it to declare that request instead.

Ask Claude to explain any line you do not understand.

---

## 3. Have it reviewed

```text
Run doctor and the tests, and ask the caligrafy-reviewer agent to review everything you wrote.
```
`doctor` checks the project's setup. The tests call every capability. The reviewer reads the code for Caligrafy
mistakes and security issues: SQL built from text, a capability that writes without a scope or deletes without
`destructive`, a `public` capability that writes, a form without `=csrf()`, a controller where one line of `web.php`
fits, a query inside a loop over rows. Ask Claude to fix what they find, then to check every experience again.

---

## Check

- [ ] The page, the REST API and the AI agent show the same books.
- [ ] A visitor is refused a change everywhere: on the page, over REST and through an agent without a token.
- [ ] An invalid input gives the same message on the page, over REST and to the agent, under its own field on the page.
- [ ] You signed up with your email: the address is in `username`, in lower case, and a text with no `@` is refused.
- [ ] The export downloads for a member and sends a visitor to sign in.
- [ ] `create_db.sql` has no `DROP`, `DELETE` or `TRUNCATE`, and its sample rows use `INSERT IGNORE`.
- [ ] You opened one capability and understood its description, rules, scope and flags.
- [ ] The list capabilities use the query builder (`with()`, `paginate()`), with no SQL text.
- [ ] `web.php` has `Route::auth`, a `Route::page` per page, the REST lines, one `Route::capabilities` and one
      `Route::mcp`; no controller of Bookshelf's own; any `Route::action` has a capability that says `webOnly: true`.
- [ ] `doctor`, the tests and the reviewer found nothing left to fix.

When every box is ticked, you approve what Claude built, not just what it planned.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | The page does not open | ask Claude to check the server (`/caligrafy-quill:server status`) |
> | The agent does not see the app | start a new `claude` session after `claude mcp add`; check the address and the port |
> | The token card says *Create tokens on the site, signed in* | sign in on the page first; an agent cannot create tokens |
> | Experiences disagree | tell Claude what you saw: it is a bug, and Claude fixes it in the capability, once |

**By hand:** each experience, built yourself: [06 Web pages](06-Web-Pages), [07 The REST API](07-The-REST-API),
[08 AI agents](08-AI-Agents), [09 A Vue app](09-A-Vue-App), [10 Phones and tablets](10-Phones-and-Tablets),
[11 People and permissions](11-People-and-Permissions), [12 Tests, review, doctor](12-Ship-It),
[16 What a page or a form cannot say](16-Every-Request-Declared). What a controller and a hand-written form did, and why
no application needs them now: [A Routes and controllers](A-Routes-and-Controllers),
[B Classic forms](B-Classic-Forms). The framework pages behind this lesson:
[Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries),
[Declarative Routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes),
[Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

**Next:** [Lesson 5 - Ship it](Lesson-5-Ship-It)
