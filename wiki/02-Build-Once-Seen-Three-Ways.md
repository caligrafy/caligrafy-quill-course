# Lesson 02 - Build once, seen three ways

**In this lesson** you open the Tasks example that quickstart added, read the one small class that adds a task, and
use it three ways: from the web page, from another program over REST, and from an AI agent over MCP. You see the same
rule give the same message everywhere. Then you remove the example, so that the project is ready for Bookshelf.

**Before you start:** tag `lesson-01`; the containers run (`docker compose ps` shows three containers `Up`).

**Time:** 40 to 60 minutes.

---

## New concepts

### The capability `create_task`

A **capability** is one thing the application can do, written once as a small PHP class in `application/services/`.
The Tasks example has five: `list_tasks`, `get_task`, `create_task`, `update_task`, `delete_task`. Each class says four
things:

| What | Where in the class | In `create_task` |
|---|---|---|
| its name and what it does | the `#[Capability(...)]` line above the class (an **attribute**: a label PHP reads) | `name: 'create_task'`, `description: 'Adds a task...'` |
| who may use it | the same line | `scope: 'tasks:write'`, `public: true` (anyone, for now) |
| the inputs it accepts, and their rules | `$rules` | `title` required, at most 200 characters |
| what it does | `handle()` | saves the task in the `tasks` table and returns it |

A capability **returns data or refuses**. It never draws a page and never answers HTTP itself. That is what lets every
experience use it.

### Three experiences, one capability

| Experience | Who uses it | How it reaches `create_task` |
|---|---|---|
| Web page `/tasks` | people in a browser | the page's JavaScript calls `createTask()` from the generated client `public/js/capabilities.js` |
| **REST API** `POST /api/tasks` | other programs | a **route** in `web.php`: `Route::capability('POST', '/api/tasks', 'create_task')` |
| **MCP** `/mcp` | AI agents (Claude Code, Cursor) | the tool `create_task` (`Route::mcp('/mcp')`, added by quickstart) |

- **REST API**: a way for programs to talk to your application with web requests. They send and receive **JSON** (data
  written like `{"title": "Read lesson 02"}`) instead of HTML pages.
- **HTTP status code**: the number every answer starts with: `200` OK, `201` created, `422` invalid inputs, `404` not
  found, `401` not signed in.
- **MCP** (Model Context Protocol): the standard way AI agents discover and call **tools**. Caligrafy turns every
  capability into a tool, with its description and input rules.
- **Personal access token**: a secret key that a program or an agent sends with every request
  (`Authorization: Bearer <token>`). Agents need one; you create it with `caligrafer.php token create`.
- **Audit log**: a record of the calls (when, through which experience, who, which capability, the result).

Whatever the experience, every call goes through the same steps: find the capability, check who calls, check the
inputs against `$rules`, run `handle()`, record it in the audit log. So **an empty title gets the same message
everywhere**.

---

## Steps

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
  the `scope` `tasks:write` (the permission a token needs; lesson 07), and `public: true` (anyone may call it: fine for
  a demo, closed before going live in lesson 11).
- `$rules`: `title` is `required` and at most 200 characters (`max_len,200`); `notes` is optional. Only the inputs named
  in `$rules` ever reach `handle()`.
- `$filters`: `trim` removes spaces at both ends before the rules run, so a title of only spaces counts as empty.
- `$descriptions`: one sentence per input, for agents and for the generated client.
- `handle()`: runs only when the rules pass. It saves a row and returns it. (The example writes it with a model
  method; Bookshelf uses the query builder from lesson 04.)

There is no `echo`, no HTML, no HTTP code here: only the rule and the work.

### Step 2 - Experience 1: the web page

Open http://localhost:8080/tasks.

1. Leave *Task* empty and click *Add task*: under the form, in red, **`The Title field is required`**.

That message is not written in the page: it came from the `$rules` of `create_task`. Keep this page open.

### Step 3 - Experience 2: the REST API

In a terminal (in the `bookshelf` folder), send an empty title, the same mistake as on the page.
`-w ' [%{http_code}]\n'` prints the status code after the answer:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": ""}' -w ' [%{http_code}]\n' http://localhost:8080/api/tasks
```
Expected:
```text
{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required"}}} [422]
```
**The same message as on the page.** Now a valid one:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "Read lesson 02"}' -w ' [%{http_code}]\n' http://localhost:8080/api/tasks
```
Expected (the `id` is the next free number: 1 if you added no task on the page; your dates differ):
```text
{"data":{"id":1,"title":"Read lesson 02","notes":"","done":0,"created_at":"2026-11-07 23:16:26","modified_at":"2026-11-07 23:16:26"}} [201]
```
Look at the open page: within a few seconds the new task appears, without reloading (the page asks `list_tasks` every
few seconds).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `curl: (7) Failed to connect to localhost port 8080` | The server is stopped | `docker compose up -d dev-box` |
> | An HTML page instead of JSON | `-H "Accept: application/json"` is missing | Copy the whole command |
> | `"fields":{"title":...}` for a title you did send | The JSON is malformed (quotes changed by a word processor) | Type the command in the terminal; use straight quotes `'` and `"` |
> | Windows PowerShell: `curl` options rejected | PowerShell's `curl` is another program | Run the course commands in the Ubuntu (WSL) terminal |

### Step 4 - Create a token for the agent

```bash
docker compose exec dev-box php caligrafer.php token create "my agent" --scopes "*"
```
Expected:
```text
 Token #1 "my agent" created with *

 cq_...

 It is shown only now: store it as a secret (Authorization: Bearer <token>).
```
In place of `cq_...` you see the whole token. `--scopes "*"` gives it every scope. **Copy the token now** into a
password manager or a file **outside** the project: it is shown only once, and lessons 04, 05 and 08 use it again.
A token with every scope is for learning only: lesson 11 revokes it and gives your agent a narrower one.
Never commit it, never paste it into a chat.

### Step 5 - Experience 3: an AI agent

Put the token in a variable of this terminal, without showing it on screen: run the first line, paste the token,
press Enter. The second line prints its length, not the token: it must not be `0`.
```bash
read -rs TOKEN
echo ${#TOKEN}
```
Ask the MCP endpoint which tools your token sees:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
```
Expected, ten tools: the five of Tasks and the five of the framework's user accounts (only an administrator may use
those: lesson 11).
```text
"name":"create_task"
"name":"delete_task"
"name":"delete_user"
"name":"get_task"
"name":"get_user"
"name":"list_tasks"
"name":"list_users"
"name":"register_user"
"name":"update_task"
"name":"update_user"
```
Call `create_task` with an empty title, as an agent does:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"create_task","arguments":{"title":""}}}' http://localhost:8080/mcp
```
Expected: a tool error, `"isError":true`, whose text is the same refusal:
```text
{"jsonrpc":"2.0","id":2,"result":{"content":[{"type":"text","text":"{\"error\":{\"code\":\"invalid\",\"message\":\"Some of the inputs are invalid\",\"fields\":{\"title\":\"The Title field is required\"}}}"}],"isError":true}}
```

**With Claude Code** (installed in lesson 00), connect it to your application (replace `cq_...` with your token):
```bash
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer cq_..."
claude mcp list
```
Expected: a line with `bookshelf`, its address and `Connected`. Start `claude` in the `bookshelf` folder and ask
`Add a task to read lesson 04.` Claude asks your permission to use the tool `create_task` the first time: approve it,
and the task appears on the open page. With **Cursor**, put the same address and header in the global
`~/.cursor/mcp.json`, never in the project (`git add -A` would commit the token):
```json
{ "mcpServers": { "bookshelf": { "url": "http://localhost:8080/mcp", "headers": { "Authorization": "Bearer <the token>" } } } }
```

**One rule, written once, the same message on the page, over REST and for the agent.** That is build once.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Unauthorized: send Authorization: Bearer <personal access token>` | `$TOKEN` is empty (a new terminal window) or mistyped | `read -rs TOKEN` again and paste the whole `cq_...` value |
> | `claude mcp list` shows `bookshelf` as failed | Wrong address, server stopped, or the token was cut when pasted | `docker compose ps`; `claude mcp remove bookshelf`, then `claude mcp add ...` again with the whole token |
> | Claude does not know the tool | Claude Code was already open, or started in another folder | Quit Claude Code and start `claude` again in the `bookshelf` folder (`/mcp` shows the connected servers) |
> | You lost the token | It is shown only once | Create another one: `token create "my agent 2" --scopes "*"` |

### Step 6 - Read the audit log

```bash
docker compose exec dev-box php caligrafer.php audit --limit 3
```
Expected (your times differ):
```text
 2026-11-07 23:16:39  mcp   token  1            create_task                  invalid  Some of the inputs are invalid
 2026-11-07 23:16:26  rest  guest  -            create_task                  ok
 2026-11-07 23:16:25  rest  guest  -            create_task                  invalid  Some of the inputs are invalid
```
The columns: time, experience (`rest`, `mcp`...), who (`guest` = nobody signed in, `token 1` = your token #1),
capability, result, message. Every change and every refusal is recorded, whatever the experience. (Times are in the
time zone of `SERVER_TIMEZONE` in `.env`, New York by default.)

### Step 7 - Remove the example

The course builds Bookshelf, not Tasks. Remove the 9 example files and the client generated for them:
```bash
rm -r application/services/Tasks application/models/Tasks application/views/Tasks public/js/tasks.js public/js/capabilities.js
```
Expected: no output. (Lesson 09 generates the client of Bookshelf.)

In `application/config/routing/web.php`, delete the `// TASKS EXAMPLE` comment, the 6 routes below it, and the empty
line that follows them. The file is then:

**File `application/config/routing/web.php`** (the Tasks routes removed; keep `Route::capabilities`, `Route::mcp` and `Route::notifications`):

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
    // Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::page('/', 'default/index');   // a page: the framework's page controller gives it who is signed in
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // Auth Routes - Uncomment only if AUTHENTICATION activated above
    // Route::get('/home', 'Caligrafy\\Controllers\\AuthController@home');
    // Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    // Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
	// Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');
    // Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    // Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    // Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');

    // User Administration Routes - Uncomment only if AUTHENTICATION activated above
	// Route::get('/admin', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@editUserForm');
	// Route::put('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@updateUser');
	// Route::delete('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@deleteUser');

    // GPT AI controller
    // Route::get('/ai', 'Caligrafy\\Controllers\\AIController');
    // Route::post('/ai', 'Caligrafy\\Controllers\\AIController@query');

    // ROUTING TO JAVASCRIPT APPS
    // Make sure you create a JS app or a Vue app in Caligrafer first
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController'); // you can specify any path as long as it ends with appName

    
    // BOT PAGE ROUTES
    Route::get('/bot/{appName}', 'Caligrafy\\Controllers\\ClientController');  // A botSkillId needs to be provided in the URL. For Example: ?botSkillId=62196ce3-0b22-4aea-b2f2-d39730c03797
    
    // BOT DATA ROUTES
    // routes need to start with reserved path __bots__ 
    Route::post('/__bots__/{appName}/communicate', 'Caligrafy\\Controllers\\WatsonController@communicate');
    Route::post('/__bots__/{appName}/{appId}', 'Caligrafy\\Controllers\\WatsonController@connect');
    Route::delete('/__bots__/{appName}', 'Caligrafy\\Controllers\\WatsonController@end');   

    /* ML Routes
     *
     * If you don't specify an up, it will go to the face, body detector.
     * Specify an app as a URI Parameter ?app=<name of app>
     * 
     * There are 3 possible apps: featureextractor, neuralnetwork and posedetector
    */
    Route::get('/ml/{appName}', 'Caligrafy\\Controllers\\ClientController'); 
    

    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

Drop the `tasks` table:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "DROP TABLE tasks"
```
Expected: only `mysql: [Warning] Using a password on the command line interface can be insecure.` (harmless: it is the
development password).

Check that the page is gone and only the framework's capabilities are left:
```bash
curl -s -w ' [%{http_code}]\n' http://localhost:8080/tasks
docker compose exec dev-box php caligrafer.php capabilities
```
Expected: `[ERROR 1005] Page Not Found [404]`, then 13 lines, the framework's own capabilities, there in every
project: the user accounts (`delete_user`, `get_user`, `list_users`, `register_user`, `update_user`), the
notifications (`notify_*`) and the background jobs (`queue_work`). None of them is about tasks.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `/tasks` still shows the Tasks page | The server checks PHP files for changes every 2 seconds, or `web.php` was not saved | Save `web.php`, wait 2 seconds and reload |
> | A PHP error on every page after editing `web.php` | A line too many or too few was deleted (a missing `;` or `}`) | Compare with the File block above, or copy `web.php` from the checkpoint |
> | `ERROR 1051 (42S02): Unknown table 'caligrafy.tasks'` | The table was already dropped | Nothing to do |

---

## Check

- [ ] The empty title gave `The Title field is required` on the page, over REST (422) and over MCP (`"isError":true`).
- [ ] Your token is stored outside the project.
- [ ] `web.php` still has `Route::capabilities('/capabilities');`, `Route::mcp('/mcp');`, `Route::notifications();`
      and `Route::run();`, and no line that mentions tasks.
- [ ] `application/services/` holds only `Users`, and `public/js/` has no `tasks.js` and no `capabilities.js`.
- [ ] `capabilities` lists 13 capabilities, and `/tasks` answers 404.

## With Claude

Do steps 1 to 6 yourself: they are the point of the lesson. Claude can explain and clean up. In the `bookshelf`
folder, start `claude` and ask:
```text
Explain application/services/Tasks/CreateTask.php line by line for a beginner, and show which lines of
application/config/routing/web.php serve it over REST and MCP. Do not change any file.
```
Then:
```text
Remove the Tasks example of quickstart: delete application/services/Tasks, application/models/Tasks,
application/views/Tasks, public/js/tasks.js and public/js/capabilities.js; in application/config/routing/web.php delete
only the "// TASKS EXAMPLE" comment, the 6 routes below it and the empty line after them; drop the tasks table with
/caligrafy-quill:db. Ask me before deleting anything.
```

**Check** before you accept:
- [ ] Claude listed what it would delete and waited for your OK.
- [ ] `web.php` is identical to the File block of step 7 (`diff` it with the checkpoint).
- [ ] Claude dropped only `tasks`: no `caligrafy_*` framework table (your token lives in `caligrafy_tokens`).
- [ ] Claude did not print your token or the contents of `.env`.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 02: the Tasks example tried and removed"
git tag lesson-02
```
Reference: [`checkpoints/lesson-02`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-02)
(`web.php` without the Tasks routes).

## Quiz

1. Where is the message `The Title field is required` written, and why do the page, REST and the agent all show it?
2. Which line of `web.php` turned `create_task` into `POST /api/tasks`?
3. Why must the token never be committed, and what do you do if you lose it?
4. What does `public: true` mean, and why is it fine here but not in a live application?

<details><summary>Show the solution</summary>

1. Nowhere by hand: it comes from the rule `'title' => 'required|...'` in `$rules` of `CreateTask.php`. Every
   experience calls the same capability, and every call checks the inputs against `$rules` before `handle()`.
2. `Route::capability('POST', '/api/tasks', 'create_task');`
3. Anyone who has it can use your application as far as its scopes allow (`*`: everything). If you lose it, create a
   new one with `token create` and revoke the old one with `token revoke <id>` (lesson 07).
4. Anyone, signed in or not, with or without a token, may call it. Fine for a demo on your computer; before going live
   you remove it so that only signed-in people or tokens with the scope may write (lesson 11).
</details>

---
Previous: [Lesson 01 - Your project in one command](01-Your-Project-in-One-Command) · Next: [Lesson 03 - Your data: tables and models](03-Your-Data)
