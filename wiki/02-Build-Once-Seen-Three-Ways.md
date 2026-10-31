# Lesson 02 - Build once, seen three ways

**In this lesson** you open the Tasks example that quickstart added, read the one small class that adds a task, and
use it three ways: from the web page, from another program over REST, and from an AI agent. You see the same rule
give the same message everywhere. Then you remove the example, so that the project is ready for Bookshelf.

**Before you start:** tag `lesson-01`; the containers run (`docker compose ps` shows three containers `Up`).

**Time:** 40 to 60 minutes.

---

## New concepts

### The capability `create_task`

A **capability** is one thing the application can do, written once as a small PHP class in `application/services/`.
The Tasks example has five: `list_tasks`, `get_task`, `create_task`, `update_task`, `delete_task`. Each class says
four things:

| What | Where in the class | In `create_task` |
|---|---|---|
| its name and what it does | the `#[Capability(...)]` line above the class (an **attribute**: a label PHP reads) | `name: 'create_task'`, `description: 'Adds a task...'` |
| who may use it | the same line | `scope: 'tasks:write'`, `public: true` (anyone, for now) |
| the inputs it accepts, and their rules | `$rules` | `title` required, at most 200 characters |
| what it does | `handle()` | saves the task in the `tasks` table and returns it |

A capability **returns data or refuses**. It never draws a page and never answers HTTP itself. That is what lets
every experience use it.

### Three experiences, one capability

| Experience | Who uses it | How it reaches `create_task` |
|---|---|---|
| Web page `/tasks` | people in a browser | the page's JavaScript calls `createTask()` from the generated client `public/js/capabilities.js` |
| **REST API** `POST /api/tasks` | other programs | a **route** in `web.php`: `Route::capability('POST', '/api/tasks', 'create_task')` |
| **MCP** `/mcp` | AI agents (Claude Code, Cursor) | the tool `create_task` (`Route::mcp('/mcp')`, added by quickstart) |

- **REST API**: a way for programs to talk to your application with web requests. They send and receive **JSON**
  (data written like `{"title": "Read lesson 02"}`) instead of HTML pages.
- **HTTP status code**: the number every answer starts with: `200` OK, `201` created, `422` invalid inputs,
  `404` not found, `401` not signed in.
- **MCP** (Model Context Protocol): the standard way AI agents discover and call **tools**. Caligrafy turns every
  capability into a tool, with its description and input rules.
- **Personal access token**: a secret key that a program or an agent sends with every request
  (`Authorization: Bearer <token>`). Agents need one; you create it with `caligrafer.php token create`.
- **Audit log**: a record of every call (when, through which experience, who, which capability, the result).

Whatever the experience, every call goes through the same steps: find the capability, check who calls, check the
inputs against `$rules`, run `handle()`, record it in the audit log. So **an empty title gets the same message
everywhere**.

---

## Manual track

### Step 1 - Read the capability

Open `application/services/Tasks/CreateTask.php` in your editor. Do not change it. It contains:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

#[Capability(
    name: 'create_task',
    description: 'Adds a task and returns it with its id. The title is required; notes are optional.',
    scope: 'tasks:write',
    public: true
)]
class CreateTask extends Service
{
    public $rules = array('title' => 'required|max_len,200', 'notes' => 'max_len,1000');
    public $filters = array('title' => 'trim', 'notes' => 'trim');
    public $descriptions = array('title' => 'What has to be done', 'notes' => 'Optional details');

    public function handle(array $input, Context $context)
    {
        return (new Task('tasks'))->create(array('title' => $input['title'], 'notes' => $input['notes'] ?? ''));
    }
}
```

Line by line:
- `#[Capability(...)]`: the name `create_task`, the `description` (AI agents read it to decide when to use the tool),
  the `scope` `tasks:write` (the permission a token needs; lesson 11), and `public: true` (anyone may call it: fine
  for a demo, closed before going live).
- `$rules`: `title` is `required` and at most 200 characters (`max_len,200`); `notes` is optional. Only the inputs
  named in `$rules` ever reach `handle()`.
- `$filters`: `trim` removes spaces at both ends before the rules run, so a title of only spaces counts as empty.
- `$descriptions`: one sentence per input, for agents and for the generated client.
- `handle()`: runs only when the rules pass. It creates a row with the model `Task` (one PHP class per table,
  lesson 03) and returns it.

There is no `echo`, no HTML, no HTTP code here: only the rule and the work.

### Step 2 - Experience 1: the web page

Open http://localhost:8080/tasks.

1. Type `Read lesson 02` in *Task* and click *Add task*: it appears in the list.
2. Leave *Task* empty and click *Add task*: under the form, in red, **`The Title field is required`**.

That message was not written in the page: it came from the `$rules` of `create_task`. Keep this page open.

### Step 3 - Experience 2: the REST API

In a terminal (in the `bookshelf` folder), list the tasks:
```bash
curl -H "Accept: application/json" http://localhost:8080/api/tasks
```
Expected: `{"data":[...]}` with your task (`{"data":[]}` when there are none). `Accept: application/json` asks for
JSON.

Now send an empty title, the same mistake as on the page. `-w ' [%{http_code}]'` prints the status code after the
answer:
```bash
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": ""}' -w ' [%{http_code}]' http://localhost:8080/api/tasks
```
Expected:
```
{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required"}} [422]
```
**The same message as on the page.** Now a valid one:
```bash
curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "Read lesson 02"}' -w ' [%{http_code}]' http://localhost:8080/api/tasks
```
Expected (your `id` is the next free number, and the dates differ):
```
{"data":{"id":1,"title":"Read lesson 02","notes":"","done":0,"created_at":"...","modified_at":"..."}} [201]
```
Look at the open page: within a few seconds the new task appears, without reloading (the page asks `list_tasks`
every 3 seconds).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `curl: (7) Failed to connect to localhost port 8080` | The server is stopped | `docker compose up -d dev-box` |
> | An HTML page instead of JSON | `-H "Accept: application/json"` is missing | Copy the whole command |
> | `{"error":...,"fields":{"title":...}}` for a title you did send | The JSON is malformed (quotes changed by a word processor) | Type the command in the terminal; use straight quotes `'` and `"` |
> | Windows PowerShell: `curl` options rejected | PowerShell's `curl` is another program | Run the course commands in the Ubuntu (WSL) terminal |

### Step 4 - Create a token for the agent

```bash
docker compose exec dev-box php caligrafer.php token create "my agent" --scopes "*"
```
Expected:
```
 Token #1 "my agent" created with *
```
then the token itself, `cq_<id>_<secret>`, and
`It is shown only now: store it as a secret (Authorization: Bearer <token>).`

`--scopes "*"` gives it every scope. **Copy the token now** into a password manager or a file **outside** the
project: it is shown only once, you need it again in lesson 08, and lesson 11 revokes it. Never commit it, never
paste it into a chat.

### Step 5 - Experience 3: an AI agent

**With Claude Code** (installed in lesson 00). In the `bookshelf` folder, connect the agent to your application
(replace `cq_...` with your token):
```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
claude mcp list
```
Expected: a line with `bookshelf`, its address and `Connected`.

Start `claude` in the `bookshelf` folder and ask:
```
Add a task to read lesson 03.
```
Claude asks your permission to use the tool `create_task` of `bookshelf` the first time: approve it. The task appears
on the open page within a few seconds. Then try the mistake:
```
Use the bookshelf create_task tool with an empty title and show me the exact error it returns.
```
Expected: Claude reports the tool's error, `The Title field is required`.

**With Cursor:** put this in the global Cursor settings `~/.cursor/mcp.json` (your token in place of `<the token>`),
not in the project's `.cursor/mcp.json`: inside the project, `git add -A` would commit the token.
```json
{ "mcpServers": { "bookshelf": { "url": "http://localhost:8080/mcp", "headers": { "Authorization": "Bearer <the token>" } } } }
```

**Without an agent: the same calls with curl.** Put your token in a variable for this terminal (it is lost when you
close it):
```bash
TOKEN=cq_...
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp
```
Expected: JSON that lists five tools, `create_task`, `delete_task`, `get_task`, `list_tasks`, `update_task`, each with
its description and input schema. Call `create_task` with an empty title:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"create_task","arguments":{"title":""}}}' http://localhost:8080/mcp
```
Expected: a result with `"isError":true` whose text is the same error,
`{"error":{"code":"invalid",...,"fields":{"title":"The Title field is required"}}}`.

**One rule, written once, the same message on the page, over REST and for the agent.** That is build once.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `claude mcp list` shows `bookshelf` as failed | Wrong address, server stopped, or the token was cut when pasted | `docker compose ps`; `claude mcp remove bookshelf`, then `claude mcp add ...` again with the whole token |
> | Claude does not know the tool | Claude Code was already open, or started in another folder | Quit Claude Code and start `claude` again in the `bookshelf` folder (`/mcp` shows the connected servers) |
> | `Unauthorized: send Authorization: Bearer <personal access token>` | No token or a wrong token | Check `$TOKEN` (`echo ${#TOKEN}` prints its length, not the secret); create a new token if you lost it |
> | You lost the token | It is shown only once | Create another one: `token create "my agent 2" --scopes "*"` |

### Step 6 - Read the audit log

```bash
docker compose exec dev-box php caligrafer.php audit --limit 5
```
Expected: the last 5 calls, with lines like:
```
2026-10-31 12:44:20  mcp   token  1   create_task   invalid  Some of the inputs are invalid
...                  rest  guest  -   create_task   ok
```
The columns: time, experience (`rest`, `mcp`...), who (`guest` = nobody signed in, `token 1` = your token #1),
capability, result, message. Every change and every refusal is recorded, whatever the experience. (The audit time
is the PHP server's time zone, `SERVER_TIMEZONE` in `.env`; `created_at` of the rows is MySQL time, UTC in Docker:
they can differ by a few hours.)

### Step 7 - Remove the example

The course builds Bookshelf, not Tasks. Remove the 9 example files:
```bash
rm -r application/services/Tasks application/models/Tasks application/views/Tasks public/js/tasks.js
```
Expected: no output.

In `application/config/routing/web.php`, delete the `// TASKS EXAMPLE` comment, the 6 lines below it, and the empty
line that follows them:
```php
    // TASKS EXAMPLE: one set of capabilities (application/services/Tasks) served to every channel
    Route::get('/tasks', function () { return view('Tasks/index'); });   // the web page (public/js/tasks.js)
    Route::capability('GET', '/api/tasks', 'list_tasks');                // REST
    Route::capability('GET', '/api/tasks/{id}', 'get_task');
    Route::capability('POST', '/api/tasks', 'create_task');
    Route::capability('PUT', '/api/tasks/{id}', 'update_task');
    Route::capability('DELETE', '/api/tasks/{id}', 'delete_task');

```
The end of the routes now reads:
```php
    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
```
Keep `Route::capabilities` and `Route::mcp`: Bookshelf uses them. The full file is in the checkpoint.

Drop the `tasks` table:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "DROP TABLE tasks"
```
Expected: only `mysql: [Warning] Using a password on the command line interface can be insecure.` (harmless: it is
the development password).

Regenerate the JavaScript client, which still has the task functions:
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
```
Expected:
```
 public/js/capabilities.js written: 0 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
```
Check: http://localhost:8080/tasks now shows the Caligrafy home page (an address that matches no route shows the
first route, `/`). The agent connection stays: it has no tools for now, and lesson 04 gives it the Bookshelf ones.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `/tasks` still shows the Tasks page, or a change to a PHP file seems ignored | The server checks PHP files for changes every 2 seconds | Wait 2 seconds and reload |
> | A PHP error on every page after editing `web.php` | A line too many or too few was deleted (a missing `;` or `}`) | Compare with the end of the file above, or copy `web.php` from the checkpoint |
> | `ERROR 1051 (42S02): Unknown table 'caligrafy.tasks'` | The table was already dropped | Nothing to do |
> | `0 capabilities` but `public/js/capabilities.js` still has `createTask` | You ran the command in another project | Run it in the `bookshelf` folder |
> | `/tasks` still shows the Tasks page | The routes were not deleted, or the file was not saved | Save `web.php` and reload |

---

## With Claude

Do steps 1 to 6 yourself: they are the point of the lesson. Claude helps to understand and to clean up.

1. In the `bookshelf` folder, start `claude` and ask:
   ```
   Explain application/services/Tasks/CreateTask.php line by line for a beginner: the #[Capability] attribute
   (name, description, scope, public), $rules, $filters, $descriptions and handle(). Then show which lines of
   application/config/routing/web.php serve it over REST and MCP, and which file the /tasks page uses to call it.
   Do not change any file.
   ```
2. Then remove the example:
   ```
   Remove the Tasks example of quickstart: delete application/services/Tasks, application/models/Tasks,
   application/views/Tasks and public/js/tasks.js; in application/config/routing/web.php delete only the
   "// TASKS EXAMPLE" comment, the 6 routes below it and the empty line after them (keep
   Route::capabilities('/capabilities') and Route::mcp('/mcp')); drop the tasks table with /caligrafy-quill:db
   query "DROP TABLE tasks"; regenerate the client with docker compose exec dev-box php caligrafer.php client
   public/js/capabilities.js. Ask me before deleting anything.
   ```

**What Claude should do:** explain without editing (1); then (2) list what it will delete, wait for your OK, delete
the 9 files and the 8 lines (the comment, the 6 routes and the empty line after them), drop the table in the `mysql`
container, and show `public/js/capabilities.js written: 0 capabilities, ...`.

**Check** before you accept:
- [ ] `web.php` still has `Route::capabilities('/capabilities');`, `Route::mcp('/mcp');`, `// MUST NOT BE REMOVED`
      and `Route::run();`, and no line that mentions `tasks`.
- [ ] `application/services/` is empty, and `ls public/js` has no `tasks.js`.
- [ ] Claude dropped only `tasks`: not `caligrafy_tokens` or `caligrafy_audit` (your token lives there).
- [ ] http://localhost:8080/tasks shows the home page.
- [ ] Claude did not print your token or the contents of `.env`.

**If Claude's result differs:** if Claude also removed `Route::capabilities` or `Route::mcp`, put them back (see
step 7). If it deleted the framework tables, run `.bin/caligrafer quickstart` again (it creates what is missing),
then create a new token (step 4) and reconnect the agent.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 02: the Tasks example tried and removed"
git tag lesson-02
```
Reference: [`checkpoints/lesson-02`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-02)
(`web.php` without the Tasks routes, and the empty client `public/js/capabilities.js`).

## Quiz

1. Where is the message `The Title field is required` written, and why do the page, REST and the agent all show it?
2. Which line of `web.php` turned `create_task` into `POST /api/tasks`?
3. Why must the token never be committed, and what do you do if you lose it?
4. What does `public: true` mean, and why is it fine here but not in a live application?

<details><summary>Show the solution</summary>

1. Nowhere by hand: it comes from the rule `'title' => 'required|...'` in `$rules` of `CreateTask.php`. Every
   experience calls the same capability, and every call checks the inputs against `$rules` before `handle()`.
2. `Route::capability('POST', '/api/tasks', 'create_task');`
3. Anyone who has it can use your application as that token allows (`*`: everything). If you lose it, create a new
   one with `token create` (and revoke the old one: lesson 11).
4. Anyone, signed in or not, with or without a token, may call it. Fine for a demo on your computer; before going
   live you remove it so that only signed-in people or tokens with the scope may write (lesson 11).
</details>

---
Previous: [Lesson 01 - Your project in one command](01-Your-Project-in-One-Command) · Next: [Lesson 03 - Your data: tables and models](03-Your-Data)
