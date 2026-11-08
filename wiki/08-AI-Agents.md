# Lesson 08 - AI agents

**In this lesson** an AI agent works with Bookshelf. You add one capability, `search_books`, and see what an agent
sees: the address `/mcp` that quickstart added in lesson 01 serves every capability as a **tool**. You talk to it the
way an agent does, with `curl`, over HTTP and over stdio; you see how a token's **scopes** decide which tools it gets;
and you connect Claude Code or Cursor with a configuration that keeps the token out of the project.

**Before you start:** lesson 07 is done (tag `lesson-07`), `docker compose ps` shows the 3 containers running, and you
have the two tokens of lessons 02 and 07: #1 "my agent" (scope `*`) and #2 "reading partner" (scope `books:read`).

**Time:** 45 minutes.

---

## New concepts

**AI agent.** A program built on a language model (Claude in Claude Code, for example) that does more than answer with
text: it chooses **tools** of other applications, calls them, reads their answers and goes on. Ask it "which books by
Octavia E. Butler do we have?" and it calls `list_authors`, then `list_books`, then answers in a sentence.

**MCP** (Model Context Protocol). The standard way an agent finds and calls the tools of an application. The agent sends
small JSON messages in the **JSON-RPC** format (`{"jsonrpc":"2.0","id":1,"method":...}`), and the application answers
each one:

| Method | What the agent asks | Answer |
|---|---|---|
| `initialize` | "who are you, which version of MCP?" | the protocol version, `serverInfo` (with the Caligrafy version), and a short instruction |
| `notifications/initialized` | "I am ready" (a notification: no `id`) | nothing (HTTP 202) |
| `tools/list` | "what can you do?" | the tools this token may use |
| `tools/call` | "run this tool with these arguments" | the capability's answer, or its refusal with `"isError":true` |

`Route::mcp('/mcp');` in `web.php` (quickstart wrote it) serves these messages, one per `POST`.

**Tool.** What an agent sees of a capability. You never write a tool: Caligrafy builds it from the capability.

| Part of the tool | Comes from |
|---|---|
| `name` | `name` of `#[Capability]` |
| `description` | `description` of `#[Capability]`: the agent reads it to choose the tool, so write it for the agent |
| `inputSchema` (the inputs, their types and limits, which are required) | `$rules` and `$descriptions` |
| `readOnlyHint: true` | `readOnly: true`: it changes nothing, the agent may call it freely |
| `destructiveHint: true` | `destructive: true`: it deletes data, the agent asks you first |
| `openWorldHint: false` | always: the tool works on your application only |

**Tokens and scopes, for agents.** Over HTTP, `/mcp` always wants a personal access token
(`Authorization: Bearer cq_...`): without one it answers 401, even for public capabilities. The token decides the
tools: `tools/list` lists the capabilities that are `public`, plus those whose scope the token has. A tool that is not
listed cannot be called either (`Unknown tool`). Today every Bookshelf capability is public, so both tokens get all
of them; lesson 11 closes the writes and the lists become different.

**Two transports.** The same messages travel two ways:

| Transport | How | When |
|---|---|---|
| HTTP | the agent posts each message to `http://localhost:8080/mcp` with a token | the normal way, and the only one once the app is online |
| stdio | the agent starts `php caligrafer.php mcp` itself and writes the messages to its **st**andard **i**nput, one per line; the answers come out on its standard **o**utput | an agent on your own computer, during development |

Over stdio there is no network and no token is needed: without one, the command has **full local access**, like any
command you type. Set `CALIGRAFY_TOKEN` to make it act as a token, with that token's scopes.

**Build once, in practice.** In this lesson you write one class. You do not touch `web.php` or a page, and `search_books` is at once a tool for agents, `POST /capabilities/search_books` for programs, and a function of the
JS client that lesson 09 generates.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - A capability for searching

Create the file with Caligrafer:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/SearchBooks --scope books:read --read-only
```
Expected:
```text
 Created application/services/Bookshelf/SearchBooks.php: capability 'search_books' (scope books:read)
 Fill in $rules and handle(), then serve it: a page (Route::page data), a form (Route::form), REST (Route::capability or Route::capabilities), MCP (Route::mcp)
```
Replace the whole content of the file:

**File `application/services/Bookshelf/SearchBooks.php`** (public, read-only, one required input):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// search_books: the books whose title or summary contains some words. Anyone may read it
#[Capability(
    name: 'search_books',
    description: 'Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". Returns at most 20 books, sorted by title, each with its author (id, name, bio).',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class SearchBooks extends Service
{
    public $rules = array('query' => 'required|min_len,2|max_len,100');
    public $filters = array('query' => 'trim');
    public $descriptions = array('query' => 'The words to look for, for example "time travel"');

    public function handle(array $input, Context $context)
    {
        // LIKE on both columns, the words bound as they are: a % or _ in them is no wildcard
        return Book::with('author')
            ->search(['title', 'summary'], $input['query'])
            ->orderBy('title')
            ->limit(20)
            ->get();
    }
}
```

What to notice:
- `query` is required, 2 to 100 characters; the filter `trim` removes the spaces around it first.
- `search(['title', 'summary'], ...)` of the query builder finds the rows whose title **or** summary contains the words.
  The words are bound, never pasted into the SQL, and a `%` or `_` in them is a plain character.
- `with('author')` adds each book's author, as in `list_books`; `limit(20)` keeps the answer short.
- The `description` gives two examples and says what comes back: it is written for an agent.

Check that the framework found it:
```bash
docker compose exec dev-box php caligrafer.php capabilities | grep search_books
```
Expected:
```text
 search_books                 public         read      Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". Returns at most 20 books, sorted by title, each with its author (id, name, bio).
```

Call it over HTTP, the way you called the other capabilities in lesson 04:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"query": "magic"}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/search_books
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"query": "zzzz"}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/search_books
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"query": "a"}' -w " [%{http_code}]\n" http://localhost:8080/capabilities/search_books
```
Expected (your dates differ):
```text
{"data":[{"id":1,"title":"A Wizard of Earthsea","published_year":1968,"summary":"A gifted boy learns magic and must face the shadow he set loose.","isbn":null,"author_id":1,"created_at":"2026-11-07 23:24:36","modified_at":"2026-11-07 23:24:36","author":{"id":1,"name":"Ursula K. Le Guin","bio":"American author of science fiction and fantasy, known for the Earthsea books.","created_at":"2026-11-07 23:24:20","modified_at":"2026-11-07 23:24:20"}}]} [200]
{"data":[]} [200]
{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"query":"The Query field needs to be at least 2 characters"}}} [422]
```
Nothing found is an empty list, not an error. The 422 message comes from `$rules`: every experience gets the same.

### Step 2 - Talk to /mcp like an agent

Put token #1 in a variable without showing it: run `read -rs TOKEN`, paste the token, press Enter. `echo ${#TOKEN}`
prints its length (45), not the token.

The first message of every agent, `initialize`:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}' -w " [%{http_code}]\n" http://localhost:8080/mcp
```
Expected:
```text
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{"listChanged":false}},"serverInfo":{"name":"caligrafy-app","title":"caligrafy-app","version":"2.21.8"},"instructions":"Tools are the capabilities of this application that your token allows. Tools marked destructive delete or overwrite data: confirm with the user first."}} [200]
```
The answer has the same `id` as the question: that is how an agent matches them. `serverInfo.name` is `APP_NAME`, or
`caligrafy-app` when it is not set. Then the agent says it is ready; a notification gets no answer:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","method":"notifications/initialized"}' -w " [%{http_code}]\n" http://localhost:8080/mcp
```
Expected: ` [202]`.

The tools, one name per line (`grep -o` keeps only the names):
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected:
```text
"name":"create_book"
"name":"delete_book"
"name":"delete_user"
"name":"get_book"
"name":"get_user"
"name":"list_authors"
"name":"list_books"
"name":"list_users"
"name":"register_user"
"name":"search_books"
"name":"update_book"
"name":"update_user"
```
Your 7 capabilities and 5 of the framework's (the accounts: lesson 11). Run the same command without `| grep ...` to
read the whole answer. The entry of `delete_book` is:
```json
{"name":"delete_book","title":"Delete book","description":"Deletes a book of the catalog, by its id. This cannot be undone.","inputSchema":{"type":"object","properties":{"id":{"type":"integer","description":"The id of the book to delete"}},"additionalProperties":false,"required":["id"]},"annotations":{"readOnlyHint":false,"destructiveHint":true,"openWorldHint":false}}
```
In `create_book`'s entry, `published_year` is `{"type":"integer","minimum":1450,"maximum":2026,...}`: the rule
`max_numeric,current_year` became this year, and `"required":["title","published_year","author_id"]`.

### Step 3 - Call tools

Search, as an agent would for "a book about time":
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"search_books","arguments":{"query":"time"}}}' http://localhost:8080/mcp
```
Expected (shortened here at `...`):
```text
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"[{\"id\":3,\"title\":\"Kindred\",...}]"}],"structuredContent":{"result":[{"id":3,"title":"Kindred","published_year":1979,"summary":"A young woman is pulled back in time to a plantation in Maryland.",...,"author":{"id":2,"name":"Octavia E. Butler",...}}]},"isError":false}}
```
The answer twice: as text in `content` (every agent reads it) and as JSON in `structuredContent` (a list is put under
`result`).

Add a book, then try one without a year:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"create_book","arguments":{"title":"Bloodchild","published_year":1995,"author_id":2}}}' http://localhost:8080/mcp
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"create_book","arguments":{"title":"Bloodchild","author_id":2}}}' http://localhost:8080/mcp
```
Expected: the first answers `"isError":false` with the new book (`"id":11` if you followed every lesson); the second:
```text
{"jsonrpc":"2.0","id":5,"result":{"content":[{"type":"text","text":"{\"error\":{\"code\":\"invalid\",\"message\":\"Some of the inputs are invalid\",\"fields\":{\"published_year\":\"The Published Year field is required\"}}}"}],"isError":true}}
```
A refusal is a tool result with `"isError":true`, not a protocol error: the agent reads the message and tries again
with a year. A tool that does not exist is a protocol error:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"find_books","arguments":{}}}' http://localhost:8080/mcp
```
Expected: `{"jsonrpc":"2.0","id":6,"error":{"code":-32602,"message":"Unknown tool: find_books"}}`.

Open http://localhost:8080/books: Bloodchild is in the catalog. The agent wrote through `create_book`, with the rules
the form uses.

### Step 4 - The token decides

Without a token:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' -w " [%{http_code}]\n" http://localhost:8080/mcp
```
Expected:
```text
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Unauthorized: send Authorization: Bearer <personal access token>"}} [401]
```
With token #2 of the reading partner (`read -rs PARTNER`, paste it, Enter):
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $PARTNER" -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected:
```text
"name":"create_book"
"name":"delete_book"
"name":"get_book"
"name":"list_authors"
"name":"list_books"
"name":"register_user"
"name":"search_books"
"name":"update_book"
```
8 tools, not 12: the 4 account tools need `users:read` or `users:write`, which `books:read` does not give, so they are
not listed. The Bookshelf writes are still there because they are public; after lesson 11 this token sees only the
reads.

The audit log shows what the agent did:
```bash
docker compose exec dev-box php caligrafer.php audit --limit 3
```
Expected (your times differ):
```text
 2026-11-07 23:39:38  mcp   token  1            create_book                  invalid  Some of the inputs are invalid
 2026-11-07 23:39:38  mcp   token  1            create_book                  ok
 2026-11-07 23:39:15  mcp   token  1            search_books                 invalid  Some of the inputs are invalid
```
The channel is `mcp`, the caller `token 1`. A read that works is not logged; a refused one is (here a search of one
letter, if you tried it).

### Step 5 - The same tools over stdio

Send two messages to `php caligrafer.php mcp`, one per line, as a local agent does (`-T`: no terminal, the messages
come from the pipe):
```bash
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"search_books","arguments":{"query":"cities"}}}' | docker compose exec -T dev-box php caligrafer.php mcp
```
Expected: first, on the error output,
```text
caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)
```
then two lines of JSON: the `initialize` answer of step 2 and the `tools/call` answer with *Invisible Cities*. The
command stops when the pipe ends; an agent keeps it running and writes more lines.

Now as token #2: `-e CALIGRAFY_TOKEN` passes the variable of your terminal into the container.
```bash
export CALIGRAFY_TOKEN="$PARTNER"
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_users","arguments":{}}}' | docker compose exec -T -e CALIGRAFY_TOKEN dev-box php caligrafer.php mcp
unset CALIGRAFY_TOKEN
```
Expected: `{"jsonrpc":"2.0","id":1,"error":{"code":-32602,"message":"Unknown tool: list_users"}}`: with a token, stdio
has exactly that token's tools. With a wrong token the command prints
`caligrafer mcp: The token is unknown, revoked or expired` and stops.

### Step 6 - Connect your agent

The configuration of an agent holds the token, so it lives **in your user settings, never in the project** (`git add -A`
would commit it).

**Claude Code over HTTP.** You did it in lesson 02:
```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
claude mcp list
```
`claude mcp list` shows a line for `bookshelf` that says it is connected. If it is missing, run the first
line with your token #1 in place of `cq_...`.

**Claude Code over stdio**, for this computer only:
```bash
claude mcp add bookshelf-local -- docker compose exec -T dev-box php caligrafer.php mcp
```
Claude Code starts the command itself, from the `bookshelf` folder. It has full access: remove it when you are done
with `claude mcp remove bookshelf-local`.

**Cursor, or any client that reads an `mcpServers` file** (for Cursor: `~/.cursor/mcp.json`, in your home folder). An
HTTP server has a `url` and `headers`; a stdio server has a `command`, its `args` and an `env`. Write your token and
the full path of your `bookshelf` folder:
```json
{
  "mcpServers": {
    "bookshelf": {
      "url": "http://localhost:8080/mcp",
      "headers": { "Authorization": "Bearer cq_..." }
    },
    "bookshelf-reader": {
      "command": "docker",
      "args": ["compose", "-f", "/Users/you/bookshelf/docker-compose.yaml", "exec", "-T", "-e", "CALIGRAFY_TOKEN", "dev-box", "php", "caligrafer.php", "mcp"],
      "env": { "CALIGRAFY_TOKEN": "cq_..." }
    }
  }
}
```
`bookshelf-reader` runs over stdio as token #2 (`books:read`): `-f` names the project, because the client does not
start in its folder.

Then start `claude` in the `bookshelf` folder (or open Cursor's chat) and ask:
```text
Using the bookshelf tools, which books by Octavia E. Butler are in the catalog?
```
The agent asks your permission the first time it uses each tool, calls `list_authors` and `list_books` (or
`search_books`), and answers with Kindred, Parable of the Sower and Bloodchild. Ask it to
`Delete Bloodchild from the catalog`: because `delete_book` is destructive, it asks you before calling it. Say no.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `capabilities` does not list `search_books` | The file is not in `application/services/Bookshelf/`, or the `#[Capability(...)]` block has a typo | Compare with step 1; `docker compose exec dev-box php -l application/services/Bookshelf/SearchBooks.php` shows a syntax error |
> | `Unauthorized: send Authorization: Bearer ...` (401) with your token | `$TOKEN` is empty (another terminal window) or cut | `read -rs TOKEN` again and paste the whole `cq_...` value; a lost token is replaced with `token create` |
> | `{"error":{"code":-32600,"message":"Invalid request: ..."}}` (400) | The JSON is not one JSON-RPC 2.0 message (a typo, or a list of messages) | Copy the `-d '...'` of the step; one message per request |
> | `Unknown tool: search_books` | Claude Code or Cursor was started before step 1 | Type `/mcp` in Claude Code to reconnect, or start a new session |
> | `claude mcp list` shows `bookshelf` as failed | The containers are stopped, or the token is wrong | `docker compose ps`; `claude mcp remove bookshelf`, then add it again |

---

## Check

- [ ] `application/services/Bookshelf/SearchBooks.php` is identical to the File block; no other file changed
      (`git status --short` shows only that file).
- [ ] `search_books` answers 1 book for `magic`, `{"data":[]}` for `zzzz`, and 422 for `a`.
- [ ] `initialize` answers `"version":"2.21.8"`; `tools/list` gives 12 tools with token #1 and 8 with token #2.
- [ ] A refused `tools/call` answers `"isError":true` with the field message; an unknown tool answers `-32602`.
- [ ] Without a token, `/mcp` answers 401.
- [ ] Over stdio, `CALIGRAFY_TOKEN` of token #2 hides `list_users`.
- [ ] No token is written in a file of the project.

## With Claude

In the `bookshelf` folder, start `claude` and type:
```text
/caligrafy-quill:new-capability search_books books:read read-only
In application/services/Bookshelf/SearchBooks.php (class SearchBooks, no namespace, like the other Bookshelf
services): public: true, readOnly: true. Input query: required|min_len,2|max_len,100, filter trim, description
'The words to look for, for example "time travel"'. It returns at most 20 books whose title or summary contains the
query, sorted by title, each with its author: Book::with('author')->search(['title', 'summary'], $input['query'])
->orderBy('title')->limit(20)->get(). Description for agents: what it finds, two examples ("time travel", "magic"),
what it returns. Do not add routes: /capabilities and /mcp exist. No test file, no reviewer. Test it over REST and over /mcp
(tools/list, tools/call) with curl, reading my token from the variable $TOKEN that I set: never ask me for it.
```

**Check** before you accept:
- [ ] `name: 'search_books'`, `scope: 'books:read'`, `readOnly: true`, `public: true`; no `destructive`.
- [ ] `handle()` uses the query builder's `search()`: no SQL string, no `%` pasted around the input.
- [ ] `git diff application/config/routing/web.php` prints nothing.
- [ ] Steps 1 to 3 give the same answers with Claude's file.
- [ ] No token appears in the chat or in a file.

Then connect your agent yourself (step 6): a token never goes through the chat.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 08: search_books for AI agents"
git tag lesson-08
```
Reference: [`checkpoints/lesson-08`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-08).
Learn more: [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Exercise

An agent asks `tools/call` for `search_books` with `{"query": "Butler"}` and gets an empty list, although the catalog
has three of her books. Why, and which tool should it call instead? Check your answer with curl, then improve the
description of `search_books` so that an agent knows.

<details><summary>Show the solution</summary>

`search_books` looks in the title and the summary of the books, not in the author's name: no title or summary contains
"Butler". The agent should call `list_authors` (it gives her id, 2), then `list_books` with `{"author_id": 2}`:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"list_books","arguments":{"author_id":2}}}' http://localhost:8080/mcp
```
answers Bloodchild, Kindred and Parable of the Sower, `"total":3`. A better description says what it does **not** do:
```php
    description: 'Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". It does not search authors: for the books of an author, use list_authors, then list_books with author_id. Returns at most 20 books, sorted by title, each with its author (id, name, bio).',
```
The agent reads the new description at its next `tools/list` (in Claude Code: `/mcp`, or a new session). To stay
identical to the checkpoint, undo it with `git checkout application/services/Bookshelf/SearchBooks.php`.
</details>

---
Previous: [Lesson 07 - The REST API](07-The-REST-API) · Next: [Lesson 09 - A Vue app](09-A-Vue-App)
