# Caligrafy Quill Course

![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

A step by step course for people who have **never used Caligrafy Quill**, the **"Build Once, Experience
Everywhere"** framework. You build **Bookshelf**, a small catalog of books and authors, from an empty folder to a
complete application where every feature is written **once**, as a capability, and used everywhere: web pages, a
REST API, AI agents such as Claude and Cursor, a Vue app, and an installable app for phones and tablets.

**[Start the course on the Wiki](https://github.com/caligrafy/caligrafy-quill-course/wiki)** (the same pages are in
the [`wiki/`](wiki) folder of this repository).

## Who this course is for

You know a little HTML, CSS and JavaScript, a little PHP (variables, arrays, functions, classes) and a little SQL
(`SELECT`, `INSERT`). You have never used Caligrafy, Docker, Pug, Vue, Vite or MCP: every one of them is explained
the first time it appears.

## What you need

| Tool | Version | Needed for |
|---|---|---|
| A terminal | macOS Terminal, Ubuntu on WSL 2 (Windows), any Linux terminal | everything |
| [Git](https://git-scm.com/) | 2.x | everything |
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | Docker Compose 2.24+ | everything: PHP 8.5 + Apache, MySQL 8.4 and phpMyAdmin run in containers |
| A code editor | for example [VS Code](https://code.visualstudio.com/) | everything |
| [Node.js](https://nodejs.org/) | 20.19+ or 22.12+ | lesson 10 (the Vite app) |
| [Claude Code](https://claude.com/claude-code) | current | the "With Claude" track, and the AI agent of lessons 02, 08 and 13 |

[Lesson 00](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Introduction-and-Tools) installs and checks
each tool. The first command of the course gives you a running application:
```bash
git clone --branch v2.2.0 https://github.com/caligrafy/caligrafy-quill.git bookshelf
cd bookshelf
.bin/caligrafer quickstart --example
```

## Two tracks in every lesson

| Track | What you do |
|---|---|
| **Manual** | You type every command and write every file. Every step says which file, gives its complete content (no `...`), what you should see, and how to check it worked. |
| **With Claude** | You give [Claude Code](https://claude.com/claude-code) the exact prompt of the lesson. The `caligrafy-quill` plugin, which ships inside every Caligrafy Quill project, gives Claude the framework documentation, commands (`/caligrafy-quill:new-capability`, `/caligrafy-quill:server`, `/caligrafy-quill:db`, `/caligrafy-quill:new-app`) and a reviewer agent (`caligrafy-reviewer`). The lesson tells you what Claude should do and gives a **Check** list to review it. |

Do the **Manual** track first: it is how you learn what the framework does. Use the **With Claude** track on a
second pass, or for a lesson you already understand, and always go through its Check list.

Every lesson also has an **If it doesn't work** box with the real errors of that step, a **checkpoint** (commit +
git tag) and a short **quiz or exercise** with its solution.

## Lessons

| # | Lesson | You learn |
|---|---|---|
| | **Part 0 - Start** | |
| 00 | [What you will build, and your tools](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Introduction-and-Tools) | capabilities and experiences, the two tracks, Git, Docker, Node.js, Claude Code |
| 01 | [Your project in one command](https://github.com/caligrafy/caligrafy-quill-course/wiki/01-Your-Project-in-One-Command) | `quickstart`, the folders, `doctor`, your own git repository |
| 02 | [Build once, seen three ways](https://github.com/caligrafy/caligrafy-quill-course/wiki/02-Build-Once-Seen-Three-Ways) | one capability used from a page, `curl` and Claude; the audit log |
| | **Part 1 - Build once** | |
| 03 | [Your data](https://github.com/caligrafy/caligrafy-quill-course/wiki/03-Your-Data) | tables, `php caligrafer.php sql`, models and relationships |
| 04 | [Your first capabilities](https://github.com/caligrafy/caligrafy-quill-course/wiki/04-Your-First-Capabilities) | `#[Capability]`, `$rules`, `handle()`, read-only and public capabilities |
| 05 | [Rules: adding, changing and deleting books](https://github.com/caligrafy/caligrafy-quill-course/wiki/05-Rules-and-Changes) | validation, `ServiceError`, destructive capabilities |
| | **Part 2 - Experience everywhere** | |
| 06 | [Web pages](https://github.com/caligrafy/caligrafy-quill-course/wiki/06-Web-Pages) | Pug, the page components, the JS client, a controller that calls a capability |
| 07 | [The REST API](https://github.com/caligrafy/caligrafy-quill-course/wiki/07-The-REST-API) | `Route::capability`, JSON, HTTP status codes |
| 08 | [AI agents](https://github.com/caligrafy/caligrafy-quill-course/wiki/08-AI-Agents) | MCP, tools and hints, tokens, Claude Code and Cursor, descriptions that agents understand |
| 09 | [A Vue app](https://github.com/caligrafy/caligrafy-quill-course/wiki/09-A-Vue-App) | Vue as a library on the JS client, search as you type |
| 10 | [Phones and tablets](https://github.com/caligrafy/caligrafy-quill-course/wiki/10-Phones-and-Tablets) | install to the home screen, a Vite single page application, Vue Router, the build |
| | **Part 3 - People and trust** | |
| 11 | [People and permissions](https://github.com/caligrafy/caligrafy-quill-course/wiki/11-People-and-Permissions) | sign in, scopes, levels, tokens for agents, the audit log |
| | **Part 4 - Ship it** | |
| 12 | [Ship it: tests, review, doctor](https://github.com/caligrafy/caligrafy-quill-course/wiki/12-Ship-It) | PHPUnit, a smoke test, `doctor`, the reviewer, `upgrade`, going live |
| 13 | [Capstone: one more feature, everywhere](https://github.com/caligrafy/caligrafy-quill-course/wiki/13-Capstone-Loans) | Loans, built once and used by every experience, with `/caligrafy-quill:new-capability` |
| | **Appendix - Under the hood (optional)** | |
| A | [Routes and controllers by hand](https://github.com/caligrafy/caligrafy-quill-course/wiki/A-Routes-and-Controllers) | `Route::get`, route variables, controllers, models, `api()` |
| B | [Classic forms and controller APIs](https://github.com/caligrafy/caligrafy-quill-course/wiki/B-Classic-Forms) | `csrf()`, validation in a controller, and turning it into a capability |

## Checkpoint code

The [`checkpoints/`](checkpoints) folder holds the finished code of every lesson, as an **overlay**: only the files
you write or change in your project, at their real paths. Each folder contains everything up to that lesson
(`lesson-02` to `lesson-13`, `appendix-a`, `appendix-b`). Lesson 01 writes no code, and lesson 02 removes the Tasks
example: its files are not in any checkpoint.

Compare your project with a checkpoint (run from the folder that contains both `bookshelf` and this repository):
```bash
diff -ru caligrafy-quill-course/checkpoints/lesson-07/application bookshelf/application
```
Catch up to a checkpoint (it overwrites the files of that lesson in your project):
```bash
cp -R caligrafy-quill-course/checkpoints/lesson-07/. bookshelf/
```
The checkpoints never contain `.env` files, keys or tokens: those are created in your project by lesson 01 and the
`token` command. The SQL scripts are in the checkpoints, the data is not: run them with
`php caligrafer.php sql` as the lessons show. The Vite app of lesson 10 only holds the files you change in
`public/bookshelf-spa`: create the app first with lesson 10.

`tools/check-pages.py` checks that every file shown in a lesson page is identical to its checkpoint.

## Credits

Created by [Dory Azar](https://github.com/DoryAzar). Based on the original
[Caligrafy course](https://github.com/caligrafy/caligrafy-course) and
[PHP meets Vue JS course](https://github.com/caligrafy/caligrafy-vue-course), rewritten for
[Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill).
Released under the [MIT License](LICENSE).
