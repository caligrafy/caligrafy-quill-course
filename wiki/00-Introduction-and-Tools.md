# Lesson 00 - What you will build, and your tools

**In this lesson** you learn the one idea of the course (build once, experience everywhere), see what Bookshelf
will be at the end, learn how the lessons work, and install every tool the course uses. You do not write code yet.

**Before you start:** a computer with macOS, Windows 10/11 or Linux, and an internet connection.

**Time:** 30 to 60 minutes, most of it downloads.

---

## New concepts

### Caligrafy Quill

Caligrafy Quill is a **PHP framework**: a set of PHP files that already solve what every web application needs
(reading the address, talking to a database, showing pages, checking forms, signing people in), so that you only
write what is specific to your application. The course uses the release **Caligrafy Quill 2.2.0**.

### Build once, experience everywhere

People and programs reach an application in many ways: a web page in a browser, an app on a phone or a tablet,
another program that calls a **REST API**, or an **AI agent** such as Claude that uses your application for you.
Each of these ways is an **experience**.

In most projects every experience has its own code, and the rules drift apart: the web form refuses an empty title,
the API accepts it. Caligrafy works the other way round:

| Word | What it means | Example in Bookshelf |
|---|---|---|
| **Capability** | One thing your application can do, written once as a small PHP class: its name, its inputs and their rules, who may use it, and what it does | `create_book`: adds a book |
| **Experience** | One way to reach the application; it calls the capabilities, it never repeats their rules | the catalog page, `POST /api/books`, the tool `create_book` of an AI agent |
| **Build once** | You write the capability once; every experience uses it | an empty title gets the same message on the page, over REST and for the agent |

```
                       ┌── web pages (people in a browser)
                       ├── a Vue app (people on a desktop)
  capability ──────────┼── a phone and tablet app
  create_book          ├── REST API (other programs)
  (rules + work)       └── AI agents (Claude, Cursor)
```

You will feel this in [lesson 02](02-Build-Once-Seen-Three-Ways): one feature, three experiences, the same error
message everywhere.

## What you will build: Bookshelf

**Bookshelf** is a small catalog of books and their authors. At the end of the course it has:

- a **catalog page** (list, filter by author, add and delete books) and a **page per book** (edit it);
- a **REST API** (`/api/books`, `/api/authors`) for other programs;
- **AI agent tools**: Claude can search the catalog and add books for you;
- a **Vue finder app** (search as you type, details on the side);
- a **phone and tablet app** that you can install on the home screen;
- **sign-in with permissions**: visitors read, signed-in people add, administrators delete, each agent has its own
  limited key;
- **loans**: who borrowed which book, until when.

| Part | Lessons | What you do |
|---|---|---|
| Part 0 - Start | 00 to 02 | Install the tools, create the project in one command, see build once on a ready example |
| Part 1 - Build once | 03 to 05 | The tables, then the Bookshelf capabilities and their rules |
| Part 2 - Experience everywhere | 06 to 10 | Web pages, the REST API, AI agents, a Vue app, phones and tablets |
| Part 3 - People and trust | 11 | Sign-in, permission levels, tokens with limited scopes |
| Part 4 - Ship it | 12, 13 | Tests, review, `doctor`; a last feature (loans) built everywhere on your own |
| Appendix (optional) | A, B | Under the hood: routes, controllers and classic forms by hand (any time after lesson 06; the exercise of B after lesson 13) |

## How the course works

### Two tracks

Every lesson has two tracks that reach the same result.

| Track | You... | Needs |
|---|---|---|
| **Manual** | type every command and write every file yourself | the tools of this page |
| **With Claude** | give Claude Code the prompt of the lesson, then check and test what it did | the tools of this page, Claude Code and a Claude account |

Do the **Manual** track first if you want to learn how things work. The **With Claude** track shows how to get the
same result with Claude Code and, above all, how to **check** what Claude wrote: every With Claude section has a
**Check** list.

### What every lesson has

- **New concepts**: each new word, explained the first time it appears.
- **Steps**: what to type, what you should see ("Expected:"), and how to check it.
- **If it doesn't work**: a table of the errors beginners really get at that step, their cause and their fix.
- **Checkpoint**: you commit your work and add a git **tag** (`lesson-03`...), a name for that moment of your project.
- **Quiz or Exercise**, with the solution hidden under "Show the solution".

### Checkpoints: compare and catch up

The finished code of every lesson is in the
[`checkpoints/`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints) folder of the course
repository. Each checkpoint is an **overlay**: it holds only the files written or changed since lesson 01, at their
state at the end of that lesson. Get the course repository once, next to your project:

```bash
git clone https://github.com/caligrafy/caligrafy-quill-course.git
```

- **Compare** your project with a checkpoint (run in your `bookshelf` folder):
  ```bash
  diff -ru ../caligrafy-quill-course/checkpoints/lesson-03/application application
  ```
  Lines that start with `-` or `+` show the differences. Lines that start with `Only in ...` are normal: they name
  files that only one side has (framework files, for example).
- **Catch up** (copy the files of a checkpoint over your project):
  ```bash
  cp -R ../caligrafy-quill-course/checkpoints/lesson-03/. .
  ```
  A checkpoint holds code, not data, and it cannot delete files. If you catch up past lesson 02, also do the
  removal of the Tasks example of lesson 02. Past lesson 10, run `create bookshelf-spa` and `npm install` (lesson 10
  step 4) before you copy. Then regenerate the clients (the `client` commands of the lesson) and run the SQL
  scripts of the lessons you skipped.

### Conventions used on every page

- A `bash` block is a command for your terminal: type it (or paste it) and press Enter, in the folder the lesson
  names, almost always your project folder `bookshelf`.
- A block named **File `path`** is the **complete** content of that file. The course never hides code with `...`.
- `http://localhost:8080` is your application. If you change the port in lesson 01, use yours.

---

## Manual track

Do the steps in order. After each one, run the check command.

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
| `dev-box` | PHP 8.5 + Apache: runs your application | http://localhost:8080 |
| `mysql` | MySQL 8.4: the database (user `root`, password `root`) | port 3306 |
| `phpmyadmin` | a web page to look at the database | http://localhost:8077 |

So you do not install PHP or MySQL yourself. **Docker Compose** starts the three together, from a file of the project.

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

Pug files are **indentation-based**: set your editor to insert **spaces**, 4 per level. In VS Code: bottom right,
click *Spaces/Tab Size* > *Indent Using Spaces* > 4.

### Step 5 - Node.js 20.19+ or 22.12+ (only for lesson 10)

**Node.js** runs JavaScript outside the browser; **npm**, installed with it, downloads JavaScript libraries. Only the
phone and tablet app of [lesson 10](10-Phones-and-Tablets) needs them. Install the LTS version from
https://nodejs.org/ (or with `nvm install --lts`). On Windows, install Node.js inside Ubuntu (WSL), where the course
commands run (for example with nvm: `nvm install --lts`), not with the Windows installer. You can do this step later.

```bash
node --version
npm --version
```
Expected: `v20.19.0` or higher on the 20 line, or `v22.12.0` or higher; and an npm version.

### Step 6 - Claude Code (With Claude track)

**Claude Code** is Claude working in your terminal: it reads your project, runs commands and writes files, asking you
first.

1. Install it by following the instructions on https://claude.com/claude-code, then sign in (you need a Claude
   subscription or an Anthropic API account). On Windows, install it inside Ubuntu (WSL), where the course commands
   run, not with the Windows installer.
2. Check:
   ```bash
   claude --version
   ```
   Expected: a version number followed by `(Claude Code)`, for example `2.1.316 (Claude Code)`. Any current version works.

Nothing else to install: the **caligrafy-quill plugin** ships inside every Caligrafy Quill project (folder
`claude-plugin/`). A **plugin** adds knowledge and commands to Claude Code. The project's `.claude/settings.json`
enables it the first time you start `claude` in the project and answer *Yes* to the question about trusting the
folder.

| What the plugin gives Claude | Name | Used in lesson |
|---|---|---|
| Skill (loads by itself) | `caligrafy`: the framework documentation, a checked API reference and known pitfalls | all |
| Command | `/caligrafy-quill:server start\|stop\|status` | 01 |
| Command | `/caligrafy-quill:db <file.sql\|tables\|query "SQL">` | 03 |
| Command | `/caligrafy-quill:new-capability <name> [scope] [read-only\|destructive]` | 04, 05, 08, 13 |
| Command | `/caligrafy-quill:new-app <app\|create\|bot\|facedetect\|ml> <name>` | 09, 10 |
| Command | `/caligrafy-quill:new-resource` (classic MVC) | appendix |
| Agent | `caligrafy-reviewer`: reviews your Caligrafy code | 12 |

**How to work with Claude in this course**
- Claude can be wrong. Go through the **Check** list before you accept a result.
- Claude asks before it runs commands and edits files. Read what it proposes. Say no to anything you do not
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

## With Claude

Once Claude Code is installed, it can check the other tools for you. In any folder:
```bash
claude
```
then ask:
```
Check the tools of the Caligrafy Quill course on this computer without installing or changing anything:
git --version, docker --version, docker compose version (it must be 2.24 or newer), whether the Docker engine
is running (docker info), curl --version, node --version (20.19+ or 22.12+, only needed for lesson 10).
Give me a table: tool, version found, OK or what to do.
```

**What Claude should do:** run each command (asking you first), and answer with a table of versions and what is
missing or too old.

**Check** before you accept:
- [ ] Claude ran only the version and `docker info` commands: it installed nothing and changed no setting.
- [ ] The table says Compose 2.24 or newer, and the Docker engine running.
- [ ] Any line that is not OK comes with an install step from this page.

**If Claude's result differs:** if it offers to install something with a command you do not know, say no and use
the step of this page instead. If `docker info` fails, start Docker Desktop and ask again.

---

## Your tools checklist

There is no code and no tag in this lesson. Check that you have all of these:

- [ ] `git --version` prints a 2.x version, and `git config --global user.name` prints your name
- [ ] Docker Desktop runs, `docker compose version` prints 2.24 or newer
- [ ] an editor that indents with 4 spaces
- [ ] `curl --version` prints a version
- [ ] (lesson 10) `node --version` prints 20.19+ or 22.12+
- [ ] (With Claude track) `claude --version` prints a version

## Quiz

1. What is the difference between a capability and an experience?
2. Why does the course not ask you to install PHP and MySQL?
3. You are stuck at the end of lesson 05. How do you get the finished code of that lesson?

<details><summary>Show the solution</summary>

1. A capability is one thing the application can do (`create_book`), written once with its rules. An experience is
   one way to reach the application (a web page, the REST API, an AI agent, a phone app): it calls the capability
   and never repeats its rules.
2. They run inside Docker containers (`dev-box` with PHP 8.5 + Apache, `mysql` with MySQL 8.4) that Docker Compose
   starts from the project.
3. Copy the overlay over your project: `cp -R ../caligrafy-quill-course/checkpoints/lesson-05/. .` (and run that
   lesson's SQL commands if your tables are missing). To only compare, use `diff -ru`.
</details>

---
Next: [Lesson 01 - Your project in one command](01-Your-Project-in-One-Command)
