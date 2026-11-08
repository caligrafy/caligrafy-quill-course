# Lesson 00 - What you will build, and your tools

> **By hand track.** This is the first lesson of the [By hand](Home#by-hand) track: Bookshelf built again, every file
> typed by you. New to Caligrafy? Start with the main lessons, from [Lesson 0 - Get ready](Lesson-0-Get-Ready).

**In this lesson** you learn the one idea of the course (build once, experience everywhere), see what Bookshelf will
be at the end, learn how the lessons work, and install every tool the course uses. You write no code yet.

**Before you start:** a computer with macOS, Windows 10/11 or Linux, and an internet connection.

**Time:** 30 to 60 minutes, most of it downloads.

---

## New concepts

### Caligrafy Quill

Caligrafy Quill is a **PHP framework**: a set of PHP files that already solve what every web application needs
(reading the address, talking to a database, showing pages, checking forms, signing people in), so that you only
write what is specific to your application. The course uses the release **Caligrafy Quill 2.19.7**, and every output
on these pages was produced with it.

### Build once, experience everywhere

People and programs reach an application in many ways: a web page in a browser, an app on a phone, another program
that calls a **REST API**, or an **AI agent** such as Claude that uses your application for you. Each of these ways is
an **experience**.

In most projects every experience has its own code, and the rules drift apart: the web form refuses an empty title,
the API accepts it. Caligrafy works the other way round:

| Word | What it means | Example in Bookshelf |
|---|---|---|
| **Capability** | One thing your application can do, written once as a small PHP class: its name, its inputs and their rules, who may use it, and what it does | `create_book`: adds a book |
| **Experience** | One way to reach the application. It calls the capabilities and never repeats their rules | the catalog page, `POST /api/books`, the tool `create_book` of an AI agent |
| **Build once** | You write the capability once; every experience uses it | an empty title gets the same message on the page, over REST and for the agent |

```text
                       ┌── web pages (people in a browser)
                       ├── a Vue app (people on a desktop)
  capability ──────────┼── a phone and tablet app
  create_book          ├── REST API (other programs)
  (rules + work)       └── AI agents (Claude, Cursor)
```

You see this for real in [lesson 02](02-Build-Once-Seen-Three-Ways): one feature, three experiences, the same error
message everywhere.

### The way you build, in this track

Every lesson uses one recommended way for each job, always the same one:

| To... | You write | Lesson |
|---|---|---|
| create the project | `.bin/caligrafer quickstart`, then the Docker dev server | 01 |
| add a table | its first version in `create_db.sql`, run by `php caligrafer.php sql` | 03 |
| change a table that holds data | a **migration**: a numbered SQL file, run by `php caligrafer.php migrate` | 05 |
| describe a row | a **model** class | 03 |
| add a feature | a **capability**, which reads and writes with the **query builder** (`Book::where(...)`) | 04, 05 |
| show a page, handle a form, offer a file | one line of `web.php` each: `Route::page`, `Route::form`, `Route::download` | 06 |
| a REST API | `Route::capabilities` and `Route::capability` | 07 |

Older ways (controllers, SQL scripts, hand-written form handling) still work. The appendices show them, once you know
the recommended way.

## What you will build: Bookshelf

**Bookshelf** is a small catalog of books and their authors. At the end of the course it has:

- a **catalog page** (list, filter by author, add a book) and a **page per book** (edit, delete);
- a **REST API** (`/api/books`, `/api/authors`) for other programs, and the authors as a **CSV file**;
- **AI agent tools**: Claude can search the catalog and add books for you;
- a **Vue app** and a **phone and tablet app** that you can install on the home screen;
- **sign-in with permissions**: visitors read, signed-in people add, administrators delete, each agent has its own
  limited key;
- **loans**: who borrowed which book, until when;
- and it is **online**, deployed from your GitHub repository.

| Part | Lessons | What you do |
|---|---|---|
| Start | 00 to 02 | Install the tools, create the project, see build once on a ready example |
| Build once | 03 to 05 | The tables, the models, the capabilities and their rules, a change with a migration |
| Experience everywhere | 06 to 10 | Web pages, the REST API, AI agents, a Vue app, phones and tablets |
| People and trust | 11 | Sign-in, permission levels, tokens with limited scopes |
| Ship it | 12 to 15 | Tests and review, a last feature on your own (loans), Bookshelf online, reminders |
| Under the hood (optional) | A, B | What the one-line routes replace: controllers and classic forms |

## How the lessons work

Every lesson has the same parts:

- **New concepts**: each new word, explained the first time it appears.
- **Steps**, numbered. Each step names the file (its full path from the project folder) and shows its **complete**
  content, or the command to run and the **output you should see** ("Expected"). The course never hides code with
  `...`. Dates, ids of tokens and secret values in the outputs differ on your computer.
- **If it doesn't work**: the errors beginners really get at that step, their cause and their fix.
- **Check**: what to look at before you go on.
- **With Claude**: the same result with Claude Code and the project's plugin, and what to check in what it wrote.
- **Checkpoint**: you commit your work and add a git **tag** (`lesson-03`...), a name for that moment of your project.

### Checkpoints: compare and catch up

The finished code of every lesson is in the
[`checkpoints/`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints) folder of the course
repository. Each checkpoint is an **overlay**: only the files you write or change from lesson 02 on, as they are at the
end of that lesson. Get the course repository once, next to your project:

```bash
git clone https://github.com/caligrafy/caligrafy-quill-course.git
```

- **Compare** your project with a checkpoint (run in your `bookshelf` folder):
  ```bash
  diff -ru ../caligrafy-quill-course/checkpoints/lesson-03/application application
  ```
  Lines that start with `-` or `+` show the differences. Lines that start with `Only in ...` are normal: they name files
  that only one side has (the framework's own files, for example).
- **Catch up** (copy the files of a checkpoint over your project):
  ```bash
  cp -R ../caligrafy-quill-course/checkpoints/lesson-05/. .
  docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
  docker compose exec dev-box php caligrafer.php migrate
  ```
  A checkpoint holds code, not data, and it cannot delete files: if you catch up past lesson 02, also remove the Tasks
  example (lesson 02, step 7). The `sql` command creates the tables and the sample books (lesson 03), `migrate` runs the
  changes of the later lessons that your database does not have yet (lesson 05 on). Both are safe to run again.

### Conventions used on every page

- A `bash` block is a command for your terminal: type it (or paste it) and press Enter, in your project folder
  `bookshelf` unless the lesson says otherwise.
- A block named **File `path`** is the **complete** content of that file.
- `http://localhost:8080` is your application. If you change the port in lesson 01, use yours.

---

## Steps

### Step 1 - Open a terminal

The **terminal** is the window where you type commands.

- **macOS**: the app *Terminal* (Applications > Utilities).
- **Windows**: install WSL 2 with Ubuntu: open *PowerShell as administrator*, type `wsl --install`, restart, then open
  the app *Ubuntu*. Run **every** command of the course in the Ubuntu terminal, and keep your project inside the
  Ubuntu home folder (`~/`), not under `C:\`: Docker is much faster there.
- **Linux**: your usual terminal.

`curl`, a small program that sends web requests from the terminal, is already there on macOS, Linux and WSL:
```bash
curl --version
```
Expected: a first line that starts with `curl 7.` or `curl 8.`.

### Step 2 - Git

**Git** keeps the history of your project: every checkpoint is a git commit with a tag.

- macOS: type `git --version`. If Git is missing, macOS offers to install the command line tools: accept.
- Windows (Ubuntu terminal) and Linux: `sudo apt update && sudo apt install -y git`.

```bash
git --version
```
Expected: `git version 2.x.y`, with Git **2.28 or newer** (lesson 01 uses `git init -b main`).

Tell Git your name and email (they are written in every commit):
```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
```

### Step 3 - Docker Desktop

**Docker** runs small isolated Linux machines, called **containers**, on your computer. Your project uses three:

| Container | What it is | Address |
|---|---|---|
| `dev-box` | PHP 8.5 and Apache: runs your application | http://localhost:8080 |
| `mysql` | MySQL 8.4: the database (user `root`, password `root`) | port 3306 |
| `phpmyadmin` | a web page to look at the database | http://localhost:8077 |

So you do not install PHP or MySQL yourself. **Docker Compose** starts the three together, from the file
`docker-compose.yaml` of the project.

1. Download Docker Desktop from https://www.docker.com/products/docker-desktop/ and install it.
   (Windows: in Docker Desktop > Settings > Resources > WSL integration, turn on *Ubuntu*.)
2. Start Docker Desktop and wait until it says that the engine is running.
3. Check:

```bash
docker --version
docker compose version
```
Expected: `Docker version 2x.y.z` and `Docker Compose version v2.x.y`. The course needs Compose **2.24 or newer**.

> Docker Desktop must be running whenever you work on the course.

### Step 4 - A code editor

Install [Visual Studio Code](https://code.visualstudio.com/) (any editor works). Useful extensions: *PHP Intelephense*
(PHP), *language-pug* (Pug views, lesson 06), *Vue - Official* (lessons 09 and 10).

Pug files are **indentation-based**: set your editor to insert **spaces**, 4 per level. In VS Code: bottom right, click
*Spaces/Tab Size* > *Indent Using Spaces* > 4.

### Step 5 - Node.js 20.19+ or 22.12+ (only for lesson 10)

**Node.js** runs JavaScript outside the browser; **npm**, installed with it, downloads JavaScript libraries. Only the
phone and tablet app of [lesson 10](10-Phones-and-Tablets) needs them. Install the LTS version from https://nodejs.org/
(on Windows, inside Ubuntu, for example with nvm: `nvm install --lts`). You can do this step later.

```bash
node --version
npm --version
```
Expected: `v20.19.0` or higher on the 20 line, or `v22.12.0` or higher; and an npm version.

### Step 6 - Claude Code (for the With Claude sections)

**Claude Code** is Claude working in your terminal: it reads your project, runs commands and writes files, asking you
first. Install it from https://claude.com/claude-code and sign in (a Claude subscription or an Anthropic API account).
On Windows, install it inside Ubuntu (WSL). Then:
```bash
claude --version
```
Expected: a version number followed by `(Claude Code)`.

Nothing else to install: the **caligrafy-quill plugin** ships inside every Caligrafy Quill project (folder
`claude-plugin/`), and the project's `.claude/settings.json` enables it the first time you start `claude` in the project
and answer *Yes* to the question about trusting the folder.

| What the plugin gives Claude | Name | Used in lesson |
|---|---|---|
| Skill (loads by itself) | `caligrafy`: the framework documentation, a checked API reference and known pitfalls | all |
| Command | `/caligrafy-quill:server start\|stop\|status` | 01 |
| Command | `/caligrafy-quill:db`: runs SQL scripts and migrations, lists tables | 03, 05 |
| Command | `/caligrafy-quill:new-capability <name> [scope] [read-only\|destructive]` | 04, 05 |
| Command | `/caligrafy-quill:new-app <app\|create\|bot\|facedetect\|ml> <name>` | 09, 10 |
| Command | `/caligrafy-quill:ship`, `/caligrafy-quill:deploy`, `/caligrafy-quill:update` | 14 |
| Agent | `caligrafy-reviewer`: reviews your Caligrafy code | 12 |

**Working with Claude:**
- Claude can be wrong. Go through the **Check** list before you accept a result.
- Claude asks before it runs commands and edits files. Read what it proposes; say no to anything you do not
  understand, and ask it to explain.
- Never paste secrets (the contents of `.env`, tokens, passwords) into the chat.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `command not found: git` (or `docker`, `node`, `claude`) | The tool is not installed, or the terminal was open before the install | Install it, then close and reopen the terminal |
> | `Cannot connect to the Docker daemon` | Docker Desktop is not running | Start it and wait until the engine runs |
> | `docker compose version` prints a version below 2.24, or `docker: 'compose' is not a docker command` | An old Docker | Update Docker Desktop |
> | Windows: `docker` works in PowerShell but not in Ubuntu | WSL integration is off | Docker Desktop > Settings > Resources > WSL integration: turn on *Ubuntu* |
> | `node --version` prints `v18...` or `v20.10...` | Node.js too old for lesson 10 | Install the current LTS from https://nodejs.org/ |

---

## Check

There is no code and no tag in this lesson. Check that you have all of these:

- [ ] `git --version` prints 2.28 or newer, and `git config --global user.name` prints your name
- [ ] Docker Desktop runs, and `docker compose version` prints 2.24 or newer
- [ ] an editor that indents with 4 spaces
- [ ] `curl --version` prints a version
- [ ] (lesson 10) `node --version` prints 20.19+ or 22.12+
- [ ] (With Claude) `claude --version` prints a version

## With Claude

Once Claude Code is installed, it can check the other tools for you. In any folder, start `claude` and ask:
```text
Check the tools of the Caligrafy Quill course on this computer without installing or changing anything:
git --version, docker --version, docker compose version (2.24 or newer), whether the Docker engine is running
(docker info), curl --version, node --version (20.19+ or 22.12+, only needed for lesson 10).
Give me a table: tool, version found, OK or what to do.
```

**Check** before you accept:
- [ ] Claude ran only the version commands and `docker info`: it installed nothing and changed no setting.
- [ ] The table says Compose 2.24 or newer, and the Docker engine running.
- [ ] Any line that is not OK comes with an install step from this page.

## Quiz

1. What is the difference between a capability and an experience?
2. Why does the course not ask you to install PHP and MySQL?
3. You are stuck at the end of lesson 05. How do you get the finished code and the tables of that lesson?

<details><summary>Show the solution</summary>

1. A capability is one thing the application can do (`create_book`), written once with its rules. An experience is one
   way to reach the application (a web page, the REST API, an AI agent, a phone app): it calls the capability and
   never repeats its rules.
2. They run inside Docker containers (`dev-box` with PHP 8.5 and Apache, `mysql` with MySQL 8.4) that Docker Compose
   starts from the project.
3. Copy the overlay over your project, `cp -R ../caligrafy-quill-course/checkpoints/lesson-05/. .`, then run the
   `sql` command of `create_db.sql` and `migrate`: they create the tables and apply the changes. To only compare, use
   `diff -ru`.
</details>

---
Next: [Lesson 01 - Your project in one command](01-Your-Project-in-One-Command)
