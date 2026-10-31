# Lesson 07 - The REST API

**In this lesson** Bookshelf gets a REST API under `/api/...`, for other programs: a mobile app, a script, another
website. It is six lines in `web.php`: each line maps a URL to a capability you already have. No controller, no
rule, no SQL is written again.

**Before you start:** lesson 06 is done (tag `lesson-06`): http://localhost:8080/books shows the catalog, and
`docker compose ps` shows the 3 containers running.

**Time:** 40 minutes.

---

## New concepts

**API and REST.** An **API** is a set of URLs made for programs instead of people: they answer JSON, not HTML. In a
**REST** API, the URL names a thing (`/api/books/3`: book 3) and the **HTTP method** says what to do with it:

| Method + URL | Capability | Action |
|---|---|---|
| `GET /api/books` | `list_books` | list the books (`?author_id=3` for one author) |
| `GET /api/books/{id}` | `get_book` | read one book and its author |
| `POST /api/books` | `create_book` | add a book |
| `PUT /api/books/{id}` | `update_book` | change a book |
| `DELETE /api/books/{id}` | `delete_book` | delete a book |
| `GET /api/authors` | `list_authors` | list the authors |

**A route to a capability.** `Route::capability('GET', '/api/books/{id}', 'get_book');` says: a GET on this URL
calls `get_book`. The capability's **inputs** come from three places, merged:
- the **route variables**: `{id}` in `/api/books/{id}`;
- the **query string**: `?author_id=3` after the URL;
- the **body**: JSON (with `Content-Type: application/json`) or form fields.

The rules, the checks of `handle()`, the 404s: everything comes from the capability, exactly as for the page and the
agent.

**The envelope.** Every answer has one of two shapes, the same ones you saw in lessons 04 and 05:
- success: `{"data": ...}`;
- refusal: `{"error": {"code": "...", "message": "...", "fields": {...}}}` (`fields` only when inputs are wrong).

**HTTP status.** The number a program reads first, to know how the call went:

| Status | Name | When, in Bookshelf |
|---|---|---|
| 200 | OK | a read, an update, a delete that worked |
| 201 | Created | `POST /api/books` added a book |
| 401 | Unauthorized | the capability is not public and the caller is not signed in and sent no token (lesson 11) |
| 403 | Forbidden | the token does not have the scope (lesson 11) |
| 404 | Not Found | `ServiceError::notFound`: no such book |
| 409 | Conflict | `ServiceError::conflict`: it clashes with the current state (lesson 13) |
| 422 | Unprocessable Content | `ServiceError::invalid` or a rule: wrong inputs, with `fields` |

Unlike `POST /capabilities/<name>` (201 for every successful change), REST routes answer 201 only when a POST
creates, and 200 for PUT and DELETE.

**Who may call.** Programs prove who they are with a personal access token in the header
`Authorization: Bearer <token>`, as your agent does. Until lesson 11 every Bookshelf capability is public, so the
API works without a token. Programs must send `Accept: application/json`: it tells Caligrafy that this is an API
call (JSON answers, no CSRF form token needed).

---

## Manual track

Run every command from the `bookshelf` folder.

### Step 1 - Six routes

In `application/config/routing/web.php`, add these lines above `// MUST NOT BE REMOVED` and `Route::run();` (below
the `// BOOKSHELF PAGES` block of lesson 06):
```php
    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');
```
That is the whole API. The full file is in the checkpoint.

`/api/books/{id}` and `/books/{id}` never clash: their first parts differ (`api`, `books`). And `GET /api/books/{id}`
and `PUT /api/books/{id}` are the same URL with two methods: the method chooses the capability.

### Step 2 - Read

In every command, `-H "Accept: application/json"` asks for JSON and `-w " [%{http_code}]\n"` prints the status.

The books of Italo Calvino (author 3), through the query string:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" "http://localhost:8080/api/books?author_id=3"
```
Expected: `{"data":[{"id":5,"title":"Invisible Cities","published_year":1972,"author_id":3,"author":"Italo Calvino"}]} [200]`.
Put the URL in quotes when it has a `?`: some terminals (zsh) give `?` a meaning of their own.

One book, through the route variable `{id}`:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/books/1
```
Expected: `{"data":{"book":{"id":1,"title":"A Wizard of Earthsea",...},"author":{...}}} [200]`.

A book that does not exist:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/books/42
```
Expected: `{"error":{"code":"not_found","message":"There is no book #42"}} [404]`: the `ServiceError::notFound` of
get_book, the same that the book page shows as a Not found card.

`-i` prints the headers of the answer; `head -1` keeps the first line, the status line:
```bash
curl -s -i -H "Accept: application/json" http://localhost:8080/api/authors | head -1
```
Expected: `HTTP/1.1 200 OK`.

### Step 3 - Write

Add a book with JSON. The title has an apostrophe, so the JSON is written between double quotes, with `\"` for each
quote inside it:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"title\": \"If on a Winter's Night a Traveler\", \"published_year\": 1979, \"author_id\": 3}" -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: `{"data":{"id":8,"title":"If on a Winter's Night a Traveler","published_year":1979,"summary":null,"author_id":3,...}} [201]`.
The id is the next free number: 8 if you followed every lesson. Use **your** id below.

Programs can also send form fields, like an HTML form (no `Content-Type: application/json`):
```bash
curl -s -X POST -H "Accept: application/json" -d 'title=Bloodchild&published_year=1995&author_id=2' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: the new book (Bloodchild, id 9) and `[201]`.

Change the year of book 8 (only the year: update_book changes only what you send):
```bash
curl -s -X PUT -H "Accept: application/json" -H "Content-Type: application/json" -d '{"published_year": 1980}' -w " [%{http_code}]\n" http://localhost:8080/api/books/8
```
Expected: the book with `"published_year":1980` and `[200]`. The `id` came from the URL, the year from the body.

A book without a year:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "No year", "author_id": 3}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected: `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"published_year":"The Published Year field is required"}}} [422]`.
Submit the catalog form with a title and no year: the page shows the same message.

Delete Bloodchild (id 9, or yours):
```bash
curl -s -X DELETE -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/books/9
```
Expected: `{"data":{"deleted":9}} [200]`.

### Step 4 - One catalog, three experiences

Open http://localhost:8080/books: *If on a Winter's Night a Traveler* is there, with 1980, under Italo Calvino;
Bloodchild is gone. In Claude Code, ask "Which books by Italo Calvino do we have?": the agent sees the same two
books. You changed the catalog through REST; the page and the agent read it through the same capabilities.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 3000] Security Error - Cross Site Forgery` (403) on POST, PUT or DELETE | `-H "Accept: application/json"` is missing, so Caligrafy treats the call as a browser form without its CSRF token | Add the header |
> | An HTML page (the catalog) instead of JSON | The URL matches no route (a typo, or step 1 is missing): an unknown address shows the first route, `/` | Check the URL and the six lines of step 1 |
> | `zsh: no matches found: http://localhost:8080/api/books?author_id=3` | zsh reads the `?` | Put the URL in double quotes |
> | `curl` waits with `dquote>` or `>` | A quote of the `-d` part is missing | Press Ctrl+C and copy the command again |
> | Every field reported missing on a JSON POST | `-H "Content-Type: application/json"` is missing, so the JSON is not read | Add the header |
> | `There is no book #8` on PUT | Your book has another id | Use the id the POST returned |
> | `"author_id":"There is no such author"` | The author id does not exist | Use 1, 2 or 3 (`curl -s -H "Accept: application/json" http://localhost:8080/api/authors`) |

---

## With Claude

Start Claude Code in the `bookshelf` folder and give it this prompt:

```
Add a REST API to Bookshelf on the existing capabilities, with no new controller or service. In
application/config/routing/web.php, add a "// BOOKSHELF API: the same capabilities, for other applications (JSON)"
block above Route::run(), below the BOOKSHELF PAGES block, with Route::capability for:
GET /api/books list_books, GET /api/books/{id} get_book, POST /api/books create_book,
PUT /api/books/{id} update_book, DELETE /api/books/{id} delete_book, GET /api/authors list_authors.
Then test every route with curl (always -H "Accept: application/json" and -w " [%{http_code}]\n"):
list with ?author_id=3, read book 1 and book 42, create a book with JSON and one with form fields, update the year
of the first one, create a book without a year, delete the second one. Show me each command and its status.
```

**What Claude should do:** add the six lines of step 1, nothing else, then run the calls of steps 2 and 3.

**Check** before you accept:
- [ ] `web.php` has exactly six new `Route::capability` lines, above `Route::run();`, with the methods, URLs and names of the table above.
- [ ] No new file in `application/controllers/` or `application/services/`, and no capability was changed.
- [ ] The statuses: 200 for reads, PUT and DELETE; 201 for each POST that creates; 404 for book 42; 422 for the book without a year.
- [ ] Every `curl` sends `Accept: application/json`; no command prints or writes your token.

**If Claude's result differs:** Claude may suggest a controller with `api()` for the REST routes (the classic way,
see [Appendix A](A-Routes-and-Controllers)). Refuse: it would copy the rules and lose the capability's checks and
audit. If Claude adds a token to the curl commands, it is not needed yet (the capabilities are public until lesson 11),
and the token must never be written into a file of the project.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 07: the REST API"
git tag lesson-07
```
Reference: [`checkpoints/lesson-07`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-07).

## Exercise

A partner wants the books of one author at a nicer URL: `GET /api/authors/{author_id}/books`. Add it, then try it
with author 2. How many lines of PHP did you write, and which rules apply?

<details><summary>Show the solution</summary>

One line, in the `// BOOKSHELF API` block:
```php
    Route::capability('GET', '/api/authors/{author_id}/books', 'list_books');
```
The route variable `{author_id}` becomes the input `author_id` of list_books:
```bash
curl -s -H "Accept: application/json" -w " [%{http_code}]\n" http://localhost:8080/api/authors/2/books
```
returns the books of Octavia E. Butler (Kindred, Parable of the Sower, and any you added) with `[200]`.
The rules are those of
list_books (`author_id` must be an integer: `/api/authors/abc/books` is refused with 422 and
`"fields":{"author_id":"The Author Id field must be a number without a decimal"}`). Nothing else changed:
the capability, its description and its rules are shared with the page, the agent and the other routes. (The
checkpoint does not have this route: remove it before you commit, or keep it in your own project.)

</details>

---
Previous: [Lesson 06 - Web pages](06-Web-Pages) · Next: [Lesson 08 - AI agents](08-AI-Agents)
