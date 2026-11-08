# Lesson 01 - Your project in one command

**In this lesson** you create the Bookshelf project from the release Caligrafy Quill 2.19.5 with one command,
`quickstart`, which starts the Docker dev server, sets up the database and adds a ready example feature (Tasks). You
open it in the browser, check everything with `doctor`, take a tour of the folders and make the project your own git
repository.

**Before you start:** [lesson 00](00-Introduction-and-Tools) is done: Git, Docker Desktop (running) and an editor are
installed.

**Time:** 20 to 30 minutes. The first start builds the PHP image: from under a minute to a few minutes, depending on
your connection. Later starts take seconds.

---

## New concepts

**A project is a copy of the framework.** You do not install Caligrafy somewhere: you **clone** (download with git) the
framework into a new folder, and that folder becomes your project. Your code goes in `application/` and `public/`; the
framework stays in `framework/`.

**A release.** `--branch v2.19.5` gets exactly the version 2.19.5 of the framework, the one this course was written and
run with, even after newer versions come out.

**Caligrafer.** The command line tool of Caligrafy. It has two doors:

| You type | Runs | Used for |
|---|---|---|
| `.bin/caligrafer quickstart --example --docker` | on your computer, and starts Docker for you | only the first setup |
| `docker compose exec dev-box php caligrafer.php <command>` | PHP inside the `dev-box` container | every other command (`doctor`, `sql`, `service`, `token`...) |

`docker compose exec dev-box <command>` means "run this command inside the running container `dev-box`". You never
need PHP on your computer.

**`quickstart`.** One command that does the whole setup, and prints what it did:
1. **initializes** the project: creates `application/` (your code) and `.env`, and generates your secret keys;
2. installs the PHP libraries (with **Composer**, the PHP package tool, in Docker) into `vendor/`;
3. starts the **Docker dev server**: the containers `dev-box`, `mysql` and `phpmyadmin`;
4. creates the database `caligrafy` and the framework tables (tokens for AI agents, the audit log, jobs, the cache,
   migrations);
5. adds the routes `/capabilities` (the JS client and the generic API), `/mcp` (AI agents) and `/notify`
   (notification emails);
6. with `--example`, adds a small **Tasks** feature that you use in lesson 02.

`--docker` chooses the Docker dev server even when MAMP, XAMPP or Apache also runs on your computer. Initializing
**deletes the `.git` folder**: that folder is the history of the framework, and your project starts its own. Every step
can be run again: `quickstart` only does what is missing and never overwrites your files.

**`.env`.** A text file of `NAME=value` lines: the settings and secrets of **this** installation (keys, database
connection). It is **never** committed or shared: the project's `.gitignore` already excludes it.

**`doctor`.** A read-only health check: it looks at your project and changes nothing.

---

## Steps

### Step 1 - Clone the release

Choose the folder where you keep your projects (here `~/code`) and clone the release into a new folder `bookshelf`:
```bash
mkdir -p ~/code
cd ~/code
git clone --branch v2.19.5 https://github.com/caligrafy/caligrafy-quill.git bookshelf
cd bookshelf
```
Expected: `Cloning into 'bookshelf'...`, then
```text
warning: refs/tags/v2.19.5 7e9ece05a2f22e25a8744e6a473e0a8a74835ac1 is not a commit!
Note: switching to '5ac42663bae02ac56d43bc03631fb52b6af2dc41'.

You are in 'detached HEAD' state. You can look around, make experimental
...
```
Both messages are **harmless**: a release tag points to a fixed version, not to a branch.

**From now on, every command of the course runs in this `bookshelf` folder**, unless a lesson says otherwise.

### Step 2 - Run quickstart

Docker Desktop must be running. Then:
```bash
.bin/caligrafer quickstart --example --docker
```
It first asks:
```text
quickstart initializes the project, which deletes the .git folder of the framework (your project starts its own history).
Continue? [y/N]
```
Type `y` and press Enter. You see `Installing the PHP libraries (Composer in Docker)...` and the libraries, then
`Starting the server (the first start builds the PHP image: a few minutes)...` and Docker's build lines. Then:
```text
Caligrafy quickstart (Docker server)
  OK    Project: application/ and .env created, keys generated
  OK    .env: the database of the Docker server (caligrafy)
  OK    Database: caligrafy ready on mysql
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations
  OK    Routes: /capabilities (JS client), /mcp (AI agents), /notify (notification emails)
  OK    Example: 9 file(s) added (application/services/Tasks, models/Tasks, views/Tasks, public/js/tasks.js)
  OK    Example table: tasks
  OK    Example client: public/js/capabilities.js generated

Ready. Next:
  - open http://localhost:8080/tasks: the same tasks over REST (http://localhost:8080/api/tasks) and MCP (http://localhost:8080/mcp)
  - write a capability: php caligrafer.php service Books/CreateBook --scope books:write
  - let an AI agent use it: php caligrafer.php token create "my agent" --scopes "*"
    claude mcp add --transport http myapp http://localhost:8080/mcp --header "Authorization: Bearer <token>"
  - check everything: php caligrafer.php doctor
```
Eight `OK` lines: your project is ready. The "Next" lines are what lessons 02 and 04 do, step by step. When Claude Code
is installed on your computer, one more line follows, `Claude Code plugin: caligrafy-quill@caligrafy-..., read from
this folder (restart claude here to load it)`: quickstart registered the project's own copy of the plugin.

Check the containers:
```bash
docker compose ps
```
Expected: three lines, `bookshelf-dev-box-1`, `bookshelf-mysql-1` (image `mysql:8.4`) and `bookshelf-phpmyadmin-1`,
each with a status that starts with `Up`, and the ports `0.0.0.0:8080->80/tcp`, `0.0.0.0:3306->3306/tcp` and
`0.0.0.0:8077->80/tcp`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Docker is not running: start Docker Desktop, then run .bin/caligrafer quickstart again.` | Docker Desktop is closed | Start it, wait until the engine runs, run step 2 again |
> | `port is already allocated` (8080, 8077 or 3306) | Another program uses the port (another project, MAMP, a local MySQL) | Stop that program, or open `docker-compose.yaml` and change the **left** number of that `ports` line (for example `"8081:80"` instead of `"8080:80"`), run step 2 again, and use your port everywhere the course says 8080 |
> | `Database: could not reach mysql` | MySQL takes a few seconds on its very first start | Run step 2 again: it only does what is missing |
> | The PHP image build stops or fails | A slow or filtered network | Run step 2 again (it restarts where it stopped); behind a company proxy, see the Docker notes of [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started) |
> | `permission denied: .bin/caligrafer` | The file lost its "executable" mark (copied instead of cloned) | `chmod +x .bin/caligrafer`, then step 2 |
> | `No such file or directory: .bin/caligrafer` | You are not in the `bookshelf` folder | `cd ~/code/bookshelf` |
> | `Permission denied` or `EACCES` when you save or create a file (Linux, or WSL) | The container writes the files it creates as root | In the `bookshelf` folder run `sudo chown -R "$USER": .`, then retry (again after a `caligrafer` command that creates files) |

### Step 3 - Open the example

Open http://localhost:8080/tasks in your browser: the **Tasks** page, in Caligrafy's look: a light top bar, the title
*Tasks*, a form (Task, Notes, *Add task*), the choices *All*, *Open*, *Done* with their counts, and an empty list. Add
a task, tick it done, edit it, delete it. Lesson 02 shows how it works.

From the terminal, the same tasks as data:
```bash
curl -s -H "Accept: application/json" http://localhost:8080/api/tasks
```
Expected: `{"data":[]}` (or your tasks, if you left some on the page).

### Step 4 - Check the setup with doctor

```bash
docker compose exec dev-box php caligrafer.php doctor
```
Expected: the first line `Caligrafy doctor (read-only): Caligrafy Quill 2.19.5`, one line per check (among them
`OK    PHP: 8.5.12` and `OK    Database: connected to mysql://mysql:3306/caligrafy (server 8.4.13)`; your patch
versions may differ), and the last line:
```text
 15 OK, 0 WARN, 0 FAIL, 6 SKIP
```
`SKIP` means "not configured": the 6 SKIP lines are optional services this course does not use (OpenAI, Groq, Stripe,
Coinbase, Watson, Mail). What matters is `0 FAIL`. `doctor` never prints your secrets: it shows only the end of each
key.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `service "dev-box" is not running` | The containers are stopped | `docker compose up -d dev-box` |
> | A `FAIL` line | Something is missing | Each FAIL line is followed by its fix (`-> ...`): run it, then `doctor` again |
> | `FAIL Database` ... `getaddrinfo for mysql failed` | You ran `php caligrafer.php doctor` on your computer: the name `mysql` exists only inside Docker | Use `docker compose exec dev-box php caligrafer.php doctor` |

### Step 5 - Tour of the project

Open the `bookshelf` folder in your editor (in VS Code: *File > Open Folder*).

| Path | What it is | Do you edit it? |
|---|---|---|
| `application/config/routing/web.php` | the **routes**: which address shows which page, which capability answers which call | yes |
| `application/models/` | your **models** (one PHP class per table) and the `create_db.sql` of their tables | yes |
| `application/migrations/` | your **migrations**: later changes to tables that hold data (lesson 05) | yes |
| `application/services/` | your **capabilities** (Tasks for now; Bookshelf from lesson 04), next to the framework's `Users` | yes |
| `application/views/` | your web pages, written in **Pug** (lesson 06) | yes |
| `application/controllers/` | HTTP that is truly custom. Bookshelf needs none: pages, forms and downloads are one line of `web.php` each | rarely |
| `public/` | files served as they are: `css/`, `js/`, and your client apps (lessons 09, 10) | yes |
| `tests/` | your automated tests (lesson 12) | yes |
| `framework/` | the framework itself | no |
| `vendor/` | the PHP libraries installed by Composer | no |
| `.env` | settings and secrets of this installation | rarely; never commit or share it |
| `caligrafer.php`, `.bin/caligrafer` | the command line tool | no |
| `docker-compose.yaml`, `docker/` | the containers | only to change a port |
| `claude-plugin/`, `.claude/settings.json` | the Claude Code plugin and the setting that enables it | no |
| `CLAUDE.md`, `AGENTS.md` | instructions read by coding agents (Claude Code, Cursor...) | rarely |

### Step 6 - Make the project your own git repository

```bash
git init -b main
git check-ignore .env vendor
```
Expected: `Initialized empty Git repository in .../bookshelf/.git/`, then two lines, `.env` and `vendor`: git ignores
both, so they never enter your history. Then:
```bash
git add -A
git commit -m "Lesson 01: my Bookshelf project"
git tag lesson-01
git tag
```
Expected: the commit summary ends with about `629 files changed`, and `git tag` prints `lesson-01`.

> **Never run `php caligrafer.php initialize --yes` in this folder again:** it deletes `.git`, which now holds **your**
> history. Running `quickstart` again is safe: the project is already initialized, so it does not touch `.git`.

### Every day: start and stop

| To... | Run (in the `bookshelf` folder) |
|---|---|
| start the server | `docker compose up -d dev-box` (it starts `mysql` and `phpmyadmin` too) |
| stop it at the end of the day | `docker compose stop` |
| see the state of the containers | `docker compose ps` |

Use `stop`, **not** `down`: `down` deletes the containers, and the MySQL container keeps your data inside itself.

---

## Check

- [ ] `docker compose ps` shows three containers `Up`.
- [ ] http://localhost:8080/tasks shows the Tasks page, and `curl` of `/api/tasks` answers `{"data":...}`.
- [ ] `doctor` ends with `0 FAIL`.
- [ ] `git check-ignore .env vendor` prints both names, and `git tag` prints `lesson-01`.

## With Claude

Clone the release yourself (step 1), then start Claude Code inside the project:
```bash
claude
```
Answer **Yes** when Claude asks whether you trust the files in this folder: that enables the `caligrafy-quill` plugin.
Then ask:
```text
/caligrafy-quill:server start
This project was never set up: set it up with quickstart and the Tasks example on the Docker dev server. It deletes
the .git folder of the framework, so ask me first; once I agree, run .bin/caligrafer quickstart --example --docker --yes.
Then run doctor and show me its last line. Do not print the contents of .env.
```
Claude cannot answer the `y/N` question of quickstart (it has no terminal to type in), so it must ask you, then use
`--yes`.

**Check** before you accept:
- [ ] Claude **asked you** before running `quickstart` with `--yes`.
- [ ] It showed the eight `OK` lines and `15 OK, 0 WARN, 0 FAIL, 6 SKIP`.
- [ ] It never printed the contents of `.env` (keys, passwords).
- [ ] Then make the git repository yourself (step 6).

If Claude runs `docker compose up` without `quickstart`, the project has no `application/` folder and the pages fail:
ask it to run quickstart. If Claude wants to run `php caligrafer.php initialize` in a project that already has your
commits, say **no**.

---

## Checkpoint

You made the tag `lesson-01` in step 6. There is no checkpoint folder for this lesson: you wrote nothing by hand, the
files quickstart creates are the same for everyone, and `.env` must never be shared.

## Quiz

1. What does `quickstart` do to the `.git` folder, and why?
2. You run `.bin/caligrafer quickstart --example --docker` a second time. What happens?
3. Which command checks the setup, and what tells you everything is fine?
4. Why `docker compose stop` and not `docker compose down`?

<details><summary>Show the solution</summary>

1. It deletes it (after asking): that folder was the history of the framework, and your project starts its own
   (`git init -b main` in step 6).
2. Nothing breaks: quickstart only does what is missing and never overwrites files. It is also the fix when the first
   run says `Database: could not reach mysql`.
3. `docker compose exec dev-box php caligrafer.php doctor`; the last line shows `0 FAIL`.
4. `down` deletes the containers, and the `mysql` container keeps the data inside itself: your data would be lost.
</details>

---
Previous: [Lesson 00 - What you will build, and your tools](00-Introduction-and-Tools) · Next: [Lesson 02 - Build once, seen three ways](02-Build-Once-Seen-Three-Ways)
