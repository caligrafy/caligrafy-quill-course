# Lesson 01 - Your project in one command

**In this lesson** you create the Bookshelf project with one command, `quickstart`, which starts the server, sets up
the database and adds a ready example feature (Tasks). You open it in the browser, check everything with `doctor`,
take a tour of the folders and make the project your own git repository.

**Before you start:** [lesson 00](00-Introduction-and-Tools) is done: Git, Docker Desktop (running) and an editor
are installed.

**Time:** 20 to 30 minutes. The first start builds the PHP image: from under a minute to a few minutes, depending on
your connection. Later starts take seconds.

---

## New concepts

**A project is a copy of the framework.** You do not install Caligrafy somewhere: you **clone** (download with git)
the framework into a new folder, and that folder becomes your project. Your code goes in `application/` and
`public/`; the framework stays in `framework/`.

**A release.** `--branch v2.2.0` gets exactly the version 2.2.0 of the framework, the one this course was written
and checked with, even after newer versions come out.

**Caligrafer.** The command line tool of Caligrafy. It has two doors:

| You type | Runs | Used for |
|---|---|---|
| `.bin/caligrafer quickstart --example` | on your computer, and starts Docker for you | only the first setup |
| `docker compose exec dev-box php caligrafer.php <command>` | PHP inside the `dev-box` container | every other command (`doctor`, `sql`, `service`, `token`...) |

`docker compose exec dev-box <command>` means "run this command inside the running container `dev-box`". You
never need PHP on your computer.

**`quickstart`.** One command that does the whole setup, and tells you what it did:
1. creates `application/` (your code) and `.env`, and generates your secret keys;
2. installs the PHP libraries (with **Composer**, the PHP package tool, in Docker) into `vendor/`;
3. starts the three containers (`dev-box`, `mysql`, `phpmyadmin`);
4. creates the database `caligrafy` and the framework tables (tokens for AI agents, the audit log);
5. adds the routes `/capabilities` (for JavaScript) and `/mcp` (for AI agents);
6. with `--example`, adds a small **Tasks** feature that you use in lesson 02.

It also **deletes the `.git` folder**: that folder is the history of the framework, and your project starts its own.
Every step can be run again: `quickstart` only does what is missing and never overwrites files.

**`.env`.** A text file of `NAME=value` lines: the settings and secrets of **this** installation (keys, database
connection). It is **never** committed or shared: the project's `.gitignore` already excludes it.

**`doctor`.** A read-only health check: it looks at your project and changes nothing.

---

## Manual track

### Step 1 - Clone the framework

Choose the folder where you keep your projects (here `~/code`) and clone the release into a new folder `bookshelf`:
```bash
mkdir -p ~/code
cd ~/code
git clone --branch v2.2.0 https://github.com/caligrafy/caligrafy-quill.git bookshelf
cd bookshelf
```
Expected: `Cloning into 'bookshelf'...`, then a line
`warning: refs/tags/v2.2.0 7f356ac43bf0e32126aec1eff0ad9bebbb7bef36 is not a commit!` and a note that you are in
`'detached HEAD' state`. Both are **harmless**: a release tag points to a fixed version, not to a branch.

**From now on, every command of the course runs in this `bookshelf` folder**, unless a lesson says otherwise.

### Step 2 - Run quickstart

Docker Desktop must be running. Then:
```bash
.bin/caligrafer quickstart --example
```
It first asks:
```
quickstart initializes the project, which deletes the .git folder of the framework (your project starts its own history).
Continue? [y/N]
```
Type `y` and press Enter. You may see `Installing the PHP libraries (Composer in Docker)...`, then
`Starting the server (the first start builds the PHP image: a few minutes)...`. When it is done (about 20 seconds
when the image is already built), it prints:
```
Caligrafy quickstart (Docker server)
  OK    Project: application/ and .env created, keys generated
  OK    .env: the database of the Docker server (caligrafy)
  OK    Database: caligrafy ready on mysql
  OK    Framework tables: personal access tokens and the audit log
  OK    Routes: /capabilities (JS client) and /mcp (AI agents)
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
Eight `OK` lines: your project is ready. The "Next" lines are what lessons 02 and 04 do, step by step.

Check the containers:
```bash
docker compose ps
```
Expected: three lines, `bookshelf-dev-box-1`, `bookshelf-mysql-1` and `bookshelf-phpmyadmin-1`, each with a status
that starts with `Up`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Docker is not running: start Docker Desktop, then run .bin/caligrafer quickstart again.` | Docker Desktop is closed | Start it, wait until the engine runs, run step 2 again |
> | `port is already allocated` (8080, 8077 or 3306) | Another program uses the port (another project, MAMP, a local MySQL) | Stop that program, or open `docker-compose.yaml` and change the **left** number of that `ports` line (for example `"8081:80"` instead of `"8080:80"`), run step 2 again and use your port everywhere the course says 8080 |
> | `Database: could not reach mysql` | MySQL takes a few seconds on its very first start | Run `.bin/caligrafer quickstart --example` again: it only does what is missing |
> | The PHP image build stops or fails | A slow or filtered network | Run step 2 again (it restarts where it stopped); behind a company proxy, see the Docker notes of [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started) |
> | `permission denied: .bin/caligrafer` | The file lost its "executable" mark (copied instead of cloned) | `chmod +x .bin/caligrafer`, then step 2 |
> | `No such file or directory: .bin/caligrafer` | You are not in the `bookshelf` folder | `cd ~/code/bookshelf` |
> | `Permission denied` or `EACCES` when you save or create a file (Linux, or WSL) | The container writes the files it creates as root | In the `bookshelf` folder run `sudo chown -R "$USER": .`, then retry (again after a `caligrafer` command that creates files) |

### Step 3 - Open the example

Open http://localhost:8080/tasks in your browser: the **Tasks** page, a black and white card with a form
(Task, Notes, *Add task*), tabs *All*, *Open*, *Done*, and an empty list. Add a task, tick it done, edit it,
delete it. You will look at how it works in lesson 02.

From the terminal, the same tasks as data:
```bash
curl -H "Accept: application/json" http://localhost:8080/api/tasks
```
Expected: `{"data":[]}` (or your tasks, if you left some on the page).

### Step 4 - Check the setup with doctor

```bash
docker compose exec dev-box php caligrafer.php doctor
```
Expected: the first line `Caligrafy doctor (read-only): Caligrafy Quill 2.2.0`, then one line per check, and the
last line:
```
 13 OK, 0 WARN, 0 FAIL, 6 SKIP
```
`SKIP` means "not configured": the 6 SKIP lines are optional services this course does not use (OpenAI, Groq,
Stripe, Coinbase, Watson, Mail). What matters is `0 FAIL`. `doctor` never prints your secrets: it shows only the
end of each key.

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
| `application/config/routing/web.php` | the **routes**: which address shows which page, which capability answers which API call | yes |
| `application/services/` | your **capabilities** (Tasks for now; Bookshelf from lesson 04) | yes |
| `application/models/` | your **models** (one PHP class per table) and their SQL scripts | yes |
| `application/views/` | your web pages, written in **Pug** (lesson 06) | yes |
| `application/controllers/` | page logic that runs on the server (lesson 06) | yes |
| `public/` | files served as they are: `css/`, `js/` (`capabilities.js`), and your client apps (lessons 09, 10) | yes |
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
git add -A
git status --short | grep -E " \.env$|vendor/" || echo "no .env and no vendor: good"
git commit -m "Lesson 01: my Bookshelf project"
git tag lesson-01
```
Expected: `Initialized empty Git repository in .../bookshelf/.git/`, then `no .env and no vendor: good` (`.env` and
`vendor/` are excluded by `.gitignore`), then the commit summary with `401 files changed`. Check the tag:
```bash
git tag
```
Expected: `lesson-01`.

> **Never run `php caligrafer.php initialize` in this folder again:** like quickstart's first run, it deletes `.git`,
> which now holds **your** history. Running `quickstart` again is safe: the project is already initialized, so it
> does not touch `.git`.

### Every day: start and stop

| To... | Run (in the `bookshelf` folder) |
|---|---|
| start the server | `docker compose up -d dev-box` (it starts `mysql` and `phpmyadmin` too) |
| stop it at the end of the day | `docker compose stop` |
| see the state of the containers | `docker compose ps` |

Use `stop`, **not** `down`: `down` deletes the containers, and the MySQL container keeps your data inside itself.

---

## With Claude

Two ways, both reach the same project.

**A. Let Claude create the project.** In the folder where you keep your projects (`~/code`), start `claude` and ask:
```
Clone the Caligrafy Quill release v2.2.0 into a new folder named bookshelf:
git clone --branch v2.2.0 https://github.com/caligrafy/caligrafy-quill.git bookshelf
Then, inside bookshelf, set it up with quickstart. It deletes the .git folder of the framework: ask me first,
and once I agree, run .bin/caligrafer quickstart --example --yes. Then run docker compose exec dev-box php caligrafer.php doctor and show me
the summary line. Do not print the contents of .env.
```

**B. Clone yourself (step 1), then start Claude inside the project.**
```bash
claude
```
Claude asks whether you trust the files in this folder: answer **Yes**. That enables the `caligrafy-quill` plugin
from `.claude/settings.json`. Type `/` and look for `/caligrafy-quill:server`, `/caligrafy-quill:db`,
`/caligrafy-quill:new-capability`. Then:
```
/caligrafy-quill:server start
```
For a project that was never set up, the plugin's server skill prefers `.bin/caligrafer quickstart`; add
"with the --example option" if it asks. Claude cannot answer the y/N question of quickstart (it has no terminal: the
script exits), so it must ask you before deleting the framework's `.git`, then run
`.bin/caligrafer quickstart --example --yes` once you agree.

**What Claude should do:** clone (A), ask you about `.git`, run `quickstart --example --yes`, show the eight `OK`
lines, run `doctor`, and report `13 OK, 0 WARN, 0 FAIL, 6 SKIP`.

**Check** before you accept:
- [ ] Claude **asked you** before running `quickstart` with `--yes` (it deletes the framework's `.git`).
- [ ] `docker compose ps` shows the three containers `Up`.
- [ ] http://localhost:8080/tasks shows the Tasks page.
- [ ] `doctor` ends with `0 FAIL`.
- [ ] Claude never printed the contents of `.env` (keys, passwords).
- [ ] Then make the git repository yourself (step 6), or ask Claude to run exactly those five commands.

**If Claude's result differs:** if Claude ran `docker compose up` without `quickstart`, the project has no
`application/` folder and the pages fail: ask it to run `.bin/caligrafer quickstart --example`. If a port is taken,
Claude may propose to change it in `docker-compose.yaml`: accept, and use the new port everywhere. If Claude wants
to run `php caligrafer.php initialize` in a project that already has your commits, say **no**.

---

## Checkpoint

You made the tag `lesson-01` in step 6. There is no checkpoint folder for this lesson: you wrote nothing by hand,
the files quickstart creates are the same for everyone, and `.env` must never be shared.

## Quiz

1. What does `quickstart` do to the `.git` folder, and why?
2. You run `.bin/caligrafer quickstart --example` a second time. What happens?
3. Which command checks the setup, and what tells you everything is fine?
4. Why `docker compose stop` and not `docker compose down`?

<details><summary>Show the solution</summary>

1. It deletes it (after asking): that folder was the history of the framework, and your project starts its own
   (`git init -b main` in step 6).
2. Nothing breaks: quickstart only does what is missing and never overwrites files. It is also the fix when the first
   run says `Database: could not reach mysql`.
3. `docker compose exec dev-box php caligrafer.php doctor`; the last line shows `0 FAIL` (`13 OK, 0 WARN, 0 FAIL, 6 SKIP`).
4. `down` deletes the containers, and the `mysql` container keeps the data inside itself: your data would be lost.
</details>

---
Previous: [Lesson 00 - What you will build, and your tools](00-Introduction-and-Tools) · Next: [Lesson 02 - Build once, seen three ways](02-Build-Once-Seen-Three-Ways)
