# Lesson 00 - What Caligrafy Quill is, and installing your tools

**In this lesson** you learn what Caligrafy Quill is, what you will build during the course, and you install every
tool the course uses. You do not write code yet.

**Time:** 30 to 60 minutes, most of it downloads.

---

## 1. What Caligrafy Quill is

Caligrafy Quill is a **PHP framework**: a set of PHP files that already solve the problems every web application has
(reading the URL, talking to a database, showing HTML pages, checking forms, signing users in), so that you only
write the parts that are specific to your application.

Caligrafy Quill follows the **MVC** pattern. MVC means that the code of every page is split in three kinds of files:

| Letter | Name | What it does | Where it lives in Caligrafy Quill |
|---|---|---|---|
| M | **Model** | Represents one table of the database: one PHP object = one row | `application/models/` |
| V | **View** | The HTML the visitor sees. Caligrafy writes views in **Pug**, a short way to write HTML | `application/views/` |
| C | **Controller** | Receives the request, asks the models for data, and chooses the view | `application/controllers/` |

A fourth piece decides which controller answers which URL: the **routes**, all in one file,
`application/config/routing/web.php`.

When a visitor opens `http://localhost:8080/books/3`, this happens:

```
browser ──GET /books/3──▶ web.php (routes) ──▶ BookController::read()
                                                   │  asks the Book model for book 3 (MySQL)
                                                   ▼
browser ◀──── HTML page ──── view "Bookshelf/book.pug" filled with the book
```

Caligrafy Quill also has:
- a **REST API** built in: the same controllers can answer with JSON to programs (a Vue app, a mobile app, `curl`)
  instead of HTML to browsers, protected by an API key;
- **Vue.js** support: small Vue apps served by Caligrafy, and full single page applications built with Vite;
- a command line tool, **Caligrafer** (`php caligrafer.php ...`), that creates projects and apps, checks your setup
  (`doctor`) and upgrades your project (`upgrade`);
- a **Claude Code plugin** that teaches Claude the framework, so Claude can write and review Caligrafy code with you.

The versions used in this course: PHP 8.3 or newer (the Docker image uses PHP 8.5), MySQL 8.4, Bootstrap 5.3.8 with
the Caligrafy black and white theme, Vue 3.5, Vite 8 with Vue Router and Pinia.

## 2. What you will build: Bookshelf

You build **Bookshelf**, a small catalog of books and their authors:

| Part | Lessons | What Bookshelf can do at the end |
|---|---|---|
| 0 - Setup | 00, 01 | A Caligrafy project runs on your computer |
| 1 - Server side | 02 to 09 | Pages listing books and authors, forms to add, edit and delete books, sign in, a JSON API |
| 2 - Vue | 10 to 12 | The same catalog as a Vue app inside Caligrafy, then as a Vite single page application |
| 3 - Keeping it healthy | 13 | Code review with Claude, automated tests, `doctor` and `upgrade` |

## 3. How the course works: two tracks

Every lesson has two tracks. Do the **Manual** track first: it teaches you how things work. The **With Claude**
track shows how to get the same result with Claude Code and, most importantly, how to check what Claude wrote.

| Track | You... | Needs |
|---|---|---|
| **Manual** | type every command and paste every file yourself | the tools of section 4 |
| **With Claude** | give Claude Code the prompt of the lesson, then review and test the result | the tools of section 4 and 5, a Claude account |

Every lesson ends with:
- **If it doesn't work**: the errors beginners really get at that step, and their fix;
- a **Checkpoint**: you commit your work and add a git tag (`lesson-03`...). The finished code of every checkpoint is in
  the [`checkpoints/`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints) folder of the course repository, so you can compare or catch up;
- a short **quiz or exercise**, with the solution hidden under "Show the solution".

**Conventions used on every page**

- A block that starts with `bash` is a command for your terminal. Type it (or paste it) and press Enter. Run it from the
  folder the lesson names, almost always your project folder `bookshelf`.
- A block that names a file (for example **File `application/config/routing/web.php`**) is the **complete** content
  of that file: select everything in the file, delete it, and paste the block. The course never uses `...` to hide code.
- `http://localhost:8080` is your application. If you changed the port in lesson 01, use yours.

## 4. Install the tools

Do the steps in order. After each one, run the check command: it must print a version number.

### 4.1 A terminal

- **macOS**: the app *Terminal* (in Applications > Utilities).
- **Windows**: install WSL 2 with Ubuntu (open *PowerShell as administrator*, type `wsl --install`, restart, then open
  the app *Ubuntu*). Run **every** command of this course in the Ubuntu terminal, and keep your project inside the
  Ubuntu home folder (`~/`), not under `C:\`: Docker is much faster there.
- **Linux**: your usual terminal.

### 4.2 Git

Git keeps the history of your project; the checkpoints of the course are git tags.

- macOS: type `git --version` in Terminal. If Git is missing, macOS offers to install the command line tools: accept.
- Windows (Ubuntu terminal) and Linux: `sudo apt update && sudo apt install -y git`.

```bash
git --version
```
Expected: `git version 2.x.y` (any 2.x version works).

Then tell Git your name and email (they are written in every commit):
```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
```

### 4.3 Docker Desktop

Docker runs, on your computer, the three servers Caligrafy needs, each in its own **container** (a small isolated
Linux machine): the PHP + Apache web server, MySQL, and phpMyAdmin (a web page to look at your database). You do not
need to install PHP or MySQL yourself.

1. Download Docker Desktop from https://www.docker.com/products/docker-desktop/ and install it.
   (Windows: in Docker Desktop > Settings > Resources > WSL integration, turn on *Ubuntu*.)
2. Start Docker Desktop and wait until it says *Engine running*.
3. Check both commands:

```bash
docker --version
docker compose version
```
Expected: `Docker version 2x.y.z` and `Docker Compose version v2.x.y`. The course needs Compose **2.24 or newer**
(lesson 01 uses a feature added in 2.24).

> Docker Desktop must be running whenever you work on the course. If a `docker` command answers
> `Cannot connect to the Docker daemon`, start Docker Desktop and try again.

### 4.4 A code editor

Install [Visual Studio Code](https://code.visualstudio.com/) (any editor works). Useful extensions: *PHP Intelephense*
(PHP), *Pug (Jade) snippets* or *language-pug* (Pug views), *Vue - Official* (part 2).

Pug files are **indentation-based**: set your editor to insert **spaces** (4 spaces per level is what the course
uses). In VS Code: bottom right, click *Spaces/Tab Size* > *Indent Using Spaces* > 4.

### 4.5 Node.js 20.19 or newer (needed from lesson 11)

The Vite single page application of part 2 needs Node.js **20.19+** or **22.12+**. Install the LTS version from
https://nodejs.org/ (or with `nvm install --lts`).

```bash
node --version
npm --version
```
Expected: `v20.19.0` or higher (`v22.12.0` or higher on the 22 line), and an npm version.

### 4.6 Optional: PHP 8.3+ and Composer on your computer

You do **not** need them: the course runs PHP inside Docker. Install them only if you already use PHP locally
(macOS: `brew install php composer`). Lesson 01 shows the one place where they change a command.

## 5. For the "With Claude" track: Claude Code and the plugin

1. Install Claude Code by following the instructions on https://claude.com/claude-code, then sign in
   (you need a Claude subscription or an Anthropic API account).
2. Check it:
   ```bash
   claude --version
   ```
3. Nothing else to install: the **caligrafy-quill plugin** ships inside every Caligrafy Quill project (folder
   `claude-plugin/`). The project's `.claude/settings.json` enables it the first time you open the project in Claude
   Code and answer *Yes* to "Do you trust the files in this folder?". Lesson 01 shows how to check that it is active.

The plugin gives Claude:

| What | Name | Used in lesson |
|---|---|---|
| Skill (loads automatically) | `caligrafy`: the whole framework documentation, a checked API reference and a list of known pitfalls | all |
| Command | `/caligrafy-quill:server start\|stop\|status` | 01 |
| Command | `/caligrafy-quill:db <file.sql\|tables\|query "...">` | 04, 08 |
| Command | `/caligrafy-quill:new-resource <Entity> [field:type ...]` | 05, 09 |
| Command | `/caligrafy-quill:new-app <app\|create\|...> <name>` | 10, 11 |
| Agent | `caligrafy-reviewer`: reviews your Caligrafy code | 13 (and any time) |
| Hook | checks the syntax of every PHP file Claude writes (`php -l`, only when PHP is installed on your computer) | all |

**How to work with Claude in this course**
- Claude can be wrong. Every "With Claude" section has a **Check** list: go through it before you accept the result.
- Claude asks before running commands and editing files. Read what it proposes. Say no to anything you do not
  understand, and ask it to explain.
- Never paste secrets (the `APP_KEY`, the `API_KEY`, passwords) into the chat.

## Checkpoint

There is no code yet. Check that you have all of these:

- [ ] `git --version` prints a version
- [ ] Docker Desktop runs, `docker compose version` prints 2.24 or newer
- [ ] an editor that indents with spaces
- [ ] (lesson 11) `node --version` prints 20.19+ or 22.12+
- [ ] (Claude track) `claude --version` prints a version

## Quiz

1. In which folder do you write the controllers of your application?
2. Which file decides which controller answers which URL?
3. Why does the course not ask you to install PHP and MySQL?

<details><summary>Show the solution</summary>

1. `application/controllers/`.
2. `application/config/routing/web.php`.
3. They run inside Docker containers (PHP 8.5 + Apache, MySQL 8.4), started by Docker Compose from the
   `docker-compose.yaml` file of the project.
</details>

---
Next: [Lesson 01 - Create your project](01-Create-Your-Project)
