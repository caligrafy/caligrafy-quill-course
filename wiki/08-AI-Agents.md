# Lesson 08 - AI agents

**In this lesson** you add one new capability, `search_books`, and an AI agent (Claude Code) uses it, together with
the six capabilities you already have, to answer questions and change the catalog. The same capability also reaches
the REST endpoint and the JS client, without one extra line of server code.

**Before you start:** lesson 07 is done (tag `lesson-07`), the containers run (`docker compose ps` shows
`bookshelf-dev-box-1`, `bookshelf-mysql-1` and `bookshelf-phpmyadmin-1`), and you still have the token of
[Lesson 02](02-Build-Once-Seen-Three-Ways) (`cq_1_...`, scope `*`). For the agent part you need Claude Code
(`claude --version`).

**Time:** 40 minutes.

---

## New concepts

**AI agent.** A program built on a language model (here Claude, in Claude Code) that can do more than answer with
text: it can decide to call **tools** of other applications, read their answers and continue. Ask "which books by
Octavia E. Butler do we have?" and the agent calls your `list_authors` and `list_books`, then answers in a sentence.

**MCP** (Model Context Protocol). The standard way an agent finds and calls the tools of an application. The agent
sends small JSON messages (in the JSON-RPC format) to one address, `/mcp`: `tools/list` ("what can you do?") and
`tools/call` ("run this tool with these inputs"). Quickstart already added that address to `web.php` in lesson 01:
`Route::mcp('/mcp');`.

**Tool.** What an agent sees of a capability. You never write a tool: the framework builds it from the capability.

| Part of the tool | Comes from |
|---|---|
| name (`search_books`) | `name` of `#[Capability]` |
| description | `description` of `#[Capability]`: the agent reads it to choose the right tool |
| input schema (which inputs, which are required) | `$rules` and `$descriptions` |
| `readOnlyHint: true` | `readOnly: true`: the tool changes nothing, the agent may call it freely |
| `destructiveHint: true` | `destructive: true`: the tool deletes data, the agent asks you before calling it |
| `openWorldHint: false` | always false: the tool works on your application only, not on the internet |

**Token** (a personal access token). A secret like `cq_1_9f2c...` that a program sends in the header
`Authorization: Bearer <token>` to say who it is. Each token has a name, **scopes** (the rights it carries, such as
`books:read` or `*` for everything) and can be revoked on its own. MCP over HTTP accepts tokens only: no token, no
tools. Until [Lesson 11](11-People-and-Permissions) every Bookshelf capability is `public: true`, and a public
capability ignores scopes, so the scopes do not limit anything yet. Lesson 11 closes the doors and shows scoped tokens.

**Two ways to connect an agent**

| Transport | How | When |
|---|---|---|
| HTTP | the agent sends its messages to `http://localhost:8080/mcp` with a token | the normal way, and the only way once the app is deployed |
| stdio | the agent starts `php caligrafer.php mcp` itself and talks to it through its **st**andard **i**nput and **o**utput (no network) | a local agent on your own computer, for development |

**Build once, in practice.** In this lesson you write one class. You do not touch `web.php`, the pages or a
controller. Yet `search_books` is at once:
- a tool for every agent connected to `/mcp`,
- callable over HTTP at `POST /capabilities/search_books` (the generic endpoint of `Route::capabilities`),
- a function `searchBooks()` of the JS client, ready for the Vue app of [Lesson 09](09-A-Vue-App).

---

## Manual track

### Step 1 - Write `search_books`

Create the file with Caligrafer:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/SearchBooks --scope books:read --read-only
```
Expected:
```
 Created application/services/Bookshelf/SearchBooks.php: capability 'search_books' (scope books:read)
 Fill in $rules and handle(), then call it from a controller ($this->call('search_books')), REST (Route::capability) or MCP (Route::mcp)
```
Replace the content of the file:

**File `application/services/Bookshelf/SearchBooks.php`** (public, read-only, one required input):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// search_books: finds books by words of their title or summary. Anyone may read it
#[Capability(
    name: 'search_books',
    description: 'Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". Returns id, title, year and summary of each book found.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class SearchBooks extends Service
{
    public $rules = array('query' => 'required|min_len,2|max_len,100');
    public $filters = array('query' => 'trim');
    public $descriptions = array('query' => 'The words to look for');

    public function handle(array $input, Context $context)
    {
        // % matches any text: '%magic%' finds "magic" anywhere in the title or the summary
        return (new Book('books'))->search('title,summary', '%' . $input['query'] . '%');
    }
}
```

What to notice:
- `query` is `required`, at least 2 and at most 100 characters; `trim` removes the spaces around it first.
- `search('title,summary', '%magic%')` is a method of every Caligrafy model: it finds the rows whose `title` or
  `summary` contains the text. `%` means "any text" (SQL `LIKE`).
- The `description` gives two examples and says what comes back. It is written for an agent, not for you.

Check that the framework found it:
```bash
docker compose exec dev-box php caligrafer.php capabilities
```
Expected: seven capabilities, among them ` search_books   public   read   Finds the books of the catalog whose title or summary ...`.

### Step 2 - Call it over HTTP

```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"query": "magic"}' http://localhost:8080/capabilities/search_books
```
Expected: one book, A Wizard of Earthsea (its summary says "learns magic"): `{"data":[{"id":1,"title":"A Wizard of Earthsea",...}]}`.

```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"query": "zzzz"}' http://localhost:8080/capabilities/search_books
```
Expected: `{"data":[]}` (nothing found is not an error).

```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"query": "a"}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/search_books
```
Expected: `[422]` and `"fields":{"query":"The Query field needs to be at least 2 characters"}`. That message comes from
`$rules`: every experience (page, app, agent) will get exactly the same one.

### Step 3 - Regenerate the JS client

Rule from [Lesson 06](06-Web-Pages): run it after every change to a capability.
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
```
Expected: ` public/js/capabilities.js written: 7 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)`.
Check: `grep -n "searchBooks" public/js/capabilities.js` prints the line
`export const searchBooks = (input = {}) => call('search_books', input)`.

### Step 4 - See what an agent sees

Put the token of lesson 02 in a variable of your terminal (it stays in this terminal window only):
```bash
TOKEN=cq_1_paste-yours-here
```
Ask `/mcp` for its tools, exactly as an agent does:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp
```
Expected: one long line of JSON with 7 tools: `create_book`, `delete_book`, `get_book`, `list_authors`,
`list_books`, `search_books`, `update_book`. Find in it:
- the four read tools have `"readOnlyHint":true`;
- `delete_book` has `"destructiveHint":true`; `create_book` and `update_book` have both hints `false`;
- every tool has `"openWorldHint":false`;
- `create_book` has the properties `title`, `published_year`, `summary`, `author_id`, and `"required"` lists
  `title`, `published_year` and `author_id` (from its `$rules`).

Now without the token:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' -w " [%{http_code}]\n" http://localhost:8080/mcp
```
Expected: `{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Unauthorized: send Authorization: Bearer <personal access token>"}} [401]`.
Public capabilities are open to everyone on REST, but `/mcp` itself always wants a token.

### Step 5 - Let Claude Code use Bookshelf

You connected Claude Code to `/mcp` in lesson 02. Check that the connection still works:
```bash
claude mcp list
```
Expected: a line `bookshelf ... Connected`. (If it is missing, add it again with
`claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer $TOKEN"`.)

Start `claude` in the `bookshelf` folder (the `claude mcp add` of lesson 02 registers the server for that folder
only) and ask:
```
Which books by Octavia E. Butler are in the catalog? Then add Bloodchild (1995) by her.
```
What happens:
1. Claude Code asks permission before it uses each tool the first time. Approve. For the read tools you can choose
   "don't ask again".
2. Claude calls `list_authors` and `list_books` (to find her id and her books), then `create_book`.
3. It answers with something like: "The catalog had two books by Octavia E. Butler: Kindred (1979) and Parable of the
   Sower (1993). I added Bloodchild (1995) under her name ..."

Check on the other experiences: open http://localhost:8080/books, click the tab **Octavia E. Butler**: Bloodchild is
there. Then read the audit log:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 5
```
Expected: a line `... mcp   token  1   create_book   ok`. (Read-only capabilities are logged only when they are
refused, so the two reads do not appear.)

Now ask Claude to `Delete Bloodchild from the catalog`. Because `delete_book` is `destructive`, Claude Code asks you
to confirm before calling it. You can say no: the book stays.

If Claude Code was already open before step 1, it may not see `search_books` yet: type `/mcp` in Claude Code to
reconnect, or start a new session. Then ask `Find the books about time travel`: Claude calls `search_books`.

### Step 6 - Look at your tokens

```bash
docker compose exec dev-box php caligrafer.php token list
```
Expected: ` #1    active   my agent   *  last used 2026-10-31 12:48:25` (your date and time). Only a hash of the token is
stored: nobody can read the token back, which is why lesson 02 told you to keep it.

### Step 7 - Optional: a local agent over stdio

From the `bookshelf` folder:
```bash
claude mcp add bookshelf-local -- docker compose exec -T dev-box php caligrafer.php mcp
```
Claude Code now starts `php caligrafer.php mcp` in the container itself. On each start it prints
`caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)`: over stdio, without a token, the
agent can do anything, like any command you type yourself. Ask it "list the books of author 2": it answers with
Kindred and Parable of the Sower (and Bloodchild, if you kept it in step 5). Use stdio only on your own computer; remove it when you are done:
`claude mcp remove bookshelf-local`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `capabilities` does not list `search_books` | The file is not in `application/services/Bookshelf/`, or the `#[Capability(...)]` block has a typo | Compare with the file of step 1; `docker compose exec dev-box php -l application/services/Bookshelf/SearchBooks.php` shows syntax errors |
> | `{"error":{"code":"not_found",...}}` (404) on `/capabilities/search_books` | The name in the address differs from `name:` | Use `search_books`, all lower case |
> | `[ERROR 3000] Security Error - Cross Site Forgery` (403) on `/mcp` | The `Accept: application/json` header is missing from curl | Add it (real MCP clients always send it) |
> | 401 `Unauthorized: send Authorization: Bearer ...` with your token | `$TOKEN` is empty or cut (`echo $TOKEN`), or it comes from another project | Paste the whole `cq_1_...` value again; if it is lost, create a new token (lesson 02) and add the server again |
> | `claude mcp list` does not show `bookshelf ... Connected` | The containers are stopped, or the token is wrong | `docker compose up -d dev-box`; `claude mcp remove bookshelf`, then add it again |
> | Claude answers without calling any tool | It does not know the tools are for this question | Say "Using the bookshelf tools, ..." in your question |

---

## With Claude

In the `bookshelf` folder, start `claude` and type:
```
/caligrafy-quill:new-capability search_books books:read read-only
Add the capability search_books in application/services/Bookshelf/SearchBooks.php (class SearchBooks, no namespace,
like the other Bookshelf services). It is public: true and readOnly: true. Input: query, required, min_len 2,
max_len 100, filter trim, description "The words to look for". It returns the books whose title or summary
contains the query, with (new Book('books'))->search('title,summary', '%' . $input['query'] . '%').
Description for agents: what it finds, two examples ("time travel", "magic"), and what it returns.
Do not add routes: /capabilities and /mcp already exist. Regenerate public/js/capabilities.js.
Do not create a token: I already have one.
```

**What Claude should do:** run `php caligrafer.php service Bookshelf/SearchBooks --scope books:read --read-only`,
fill the file, run `capabilities` and `client`, and test with curl.

**Check** before you accept:
- [ ] `name: 'search_books'`, `scope: 'books:read'`, `readOnly: true`, `public: true`; no `destructive`.
- [ ] `$rules` is `'query' => 'required|min_len,2|max_len,100'` and `$filters` has `trim`.
- [ ] `handle()` only returns data: no `echo`, no `view()`, no `redirect()`, and it reads only `$input['query']`.
- [ ] `web.php` did not change (`git diff application/config/routing/web.php` prints nothing).
- [ ] The three curl calls of step 2 give 1 book, `{"data":[]}` and 422.
- [ ] `public/js/capabilities.js` has `searchBooks` (7 capabilities).
- [ ] No token was created or written in a file.

**If Claude's result differs:** if Claude adds a route such as `Route::capability('GET', '/api/books/search', ...)`,
it works, but it is not in the checkpoint: ask it to remove it (lesson 09 uses the JS client). If it puts the class in
a namespace, ask it to remove the namespace like the other services. Then do steps 4 to 6 yourself: they are about
what you see as a user of the agent. Descriptions in other words are fine: the `capabilities` output will show yours instead of the one in the Manual
track.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 08: search_books for AI agents"
git tag lesson-08
```
Reference: [`checkpoints/lesson-08`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-08).
The token is not in your project: never commit it. Learn more in the framework guide
[11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Exercise

`search_books` returns each book with its `author_id`, but its description does not say so. Ask Claude Code (with
the `bookshelf` server): `Using the bookshelf tools, who wrote the book about a plantation in Maryland?` and watch
which tools it calls. Then improve the description so that an agent knows how to get the author's name, regenerate
the client and ask again.

<details><summary>Show the solution</summary>

A better description says what comes back and which tool gives the rest:
```php
description: 'Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". Returns id, title, year, summary and author_id of each book found: use get_book or list_authors for the name of the author.',
```
Then:
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
```
With it, the agent searches for "plantation", then calls `get_book` (or `list_authors`) with the id it found and
answers "Octavia E. Butler". The exact tools Claude picks can vary from one session to another: the description makes
the right path easy to find. Rules for good descriptions: say what it does, what it returns, and which other tool
gives a missing id.

The checkpoint keeps the first description: to stay identical to it, undo the change with
`git checkout application/services/Bookshelf/SearchBooks.php public/js/capabilities.js` (or keep yours and remember
the difference).
</details>

---
Previous: [Lesson 07 - The REST API](07-The-REST-API) · Next: [Lesson 09 - A Vue app](09-A-Vue-App)
