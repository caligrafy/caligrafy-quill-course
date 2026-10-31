# Caligrafy Quill Course

![Caligrafy](https://github.com/DoryAzar/caligrafy/blob/master/public/images/resources/banner_white.png)

A step by step course for people who have **never used Caligrafy Quill**. You build **Bookshelf**, a small catalog
of books and authors, from an empty folder to a server-rendered web application, a REST API, a Vue app, a Vite
single page application and capabilities that an AI agent can use.

This course replaces the two original Caligrafy courses
([caligrafy-course](https://github.com/caligrafy/caligrafy-course) and
[caligrafy-vue-course](https://github.com/caligrafy/caligrafy-vue-course)), which were written for older versions
of the framework.

**[Start the course on the Wiki](https://github.com/caligrafy/caligrafy-quill-course/wiki)** (the same pages are in
the [`wiki/`](wiki) folder of this repository).

## Who this course is for

You know a little HTML, CSS and JavaScript, a little PHP (variables, arrays, functions, classes) and a little SQL
(`SELECT`, `INSERT`). You have never used Caligrafy, Docker, Pug, Vue or Vite: every one of them is explained the
first time it appears.

## What you need

| Tool | Version | Needed for |
|---|---|---|
| A terminal | macOS Terminal, Ubuntu on WSL 2 (Windows), any Linux terminal | everything |
| [Git](https://git-scm.com/) | 2.x | everything |
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | Docker Compose 2.24+ | everything: PHP 8.5 + Apache, MySQL 8.4 and phpMyAdmin run in containers |
| A code editor | for example [VS Code](https://code.visualstudio.com/) | everything |
| [Node.js](https://nodejs.org/) | 20.19+ or 22.12+ | lessons 11 and 12 (Vite) |
| [Claude Code](https://claude.com/claude-code) | current | the "With Claude" track only |
| PHP 8.3+ and Composer | optional | nothing: the course runs PHP in Docker |

[Lesson 00](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Introduction-and-Tools) installs and checks each tool.

## How to use the two tracks

Every lesson has two tracks:

| Track | What you do |
|---|---|
| **Manual** | You type every command and paste every file. Every step says which file, gives its complete content (no `...`), what you should see, and how to check it worked. |
| **With Claude** | You give [Claude Code](https://claude.com/claude-code) the exact prompt of the lesson. The `caligrafy-quill` plugin, which ships inside every Caligrafy Quill project, gives Claude the framework documentation, commands (`/caligrafy-quill:server`, `/caligrafy-quill:db`, `/caligrafy-quill:new-resource`, `/caligrafy-quill:new-app`) and a reviewer agent (`caligrafy-reviewer`). The lesson tells you what Claude should create, the **Check** list to review it, and what to do when Claude's result differs. |

Do the **Manual** track first: it is how you learn what the framework does. Use the **With Claude** track on a second
pass, or for a lesson you already understand, and always go through its Check list.

Every lesson also has an **If it doesn't work** box with the real errors of that step, a **checkpoint** (commit +
git tag) and a short **quiz or exercise** with its solution.

## Lessons

| # | Lesson | You learn |
|---|---|---|
| | **Part 0 - Setup** | |
| 00 | [What Caligrafy Quill is, and installing your tools](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Introduction-and-Tools) | MVC, the two tracks, Git, Docker, Node, Claude Code |
| 01 | [Create your project](https://github.com/caligrafy/caligrafy-quill-course/wiki/01-Create-Your-Project) | clone, `composer install`, `initialize`, `.env`, Docker, the folder tour, `doctor`, your own git repository |
| | **Part 1 - Server side with Caligrafy views** | |
| 02 | [Routes](https://github.com/caligrafy/caligrafy-quill-course/wiki/02-Routes) | `web.php`, `Route::get`, route parameters, route order, the first-route fallback |
| 03 | [Controllers](https://github.com/caligrafy/caligrafy-quill-course/wiki/03-Controllers) | namespaced controllers, actions, `$this->request`, `api()`, HTTP status codes |
| 04 | [The database](https://github.com/caligrafy/caligrafy-quill-course/wiki/04-The-Database) | MySQL in Docker, `create_db.sql`, seed data, phpMyAdmin, `doctor --only database` |
| 05 | [Models and relationships](https://github.com/caligrafy/caligrafy-quill-course/wiki/05-Models-and-Relationships) | `Model`, `associate()`, `all()`, `find()`, `hasOne()`, `hasMany()` |
| 06 | [Views and the layout](https://github.com/caligrafy/caligrafy-quill-course/wiki/06-Views-and-the-Layout) | Pug, `view()`, the default layout, the Caligrafy black and white classes |
| 07 | [Forms, CSRF and validation](https://github.com/caligrafy/caligrafy-quill-course/wiki/07-Forms-CSRF-and-Validation) | `csrf()`, `methodField()`, filters and rules, error messages, create/update/delete |
| 08 | [Authentication](https://github.com/caligrafy/caligrafy-quill-course/wiki/08-Authentication) | the Auth module, users table, register, sign in, protecting pages |
| 09 | [The REST API](https://github.com/caligrafy/caligrafy-quill-course/wiki/09-The-REST-API) | the API key, JSON requests, `curl`, 200/201/401/404/422 |
| | **Part 2 - Vue** | |
| 10 | [A Vue app inside Caligrafy](https://github.com/caligrafy/caligrafy-quill-course/wiki/10-Vue-as-a-Library) | Vue as a library, `php caligrafer.php app`, `env`, axios on the API |
| 11 | [The Vite single page application and Vue Router](https://github.com/caligrafy/caligrafy-quill-course/wiki/11-Vite-SPA-and-Vue-Router) | `php caligrafer.php create`, Vite, single file components, Vue Router, the dev proxy |
| 12 | [Pinia, styling and the production build](https://github.com/caligrafy/caligrafy-quill-course/wiki/12-Pinia-Styling-and-Build) | a Pinia store, forms with API errors, the Caligrafy classes, `npm run build` |
| | **Part 3 - Keeping it healthy** | |
| 13 | [Review, tests, doctor and upgrade](https://github.com/caligrafy/caligrafy-quill-course/wiki/13-Review-Tests-Doctor-Upgrade) | the `caligrafy-reviewer` agent, PHPUnit and smoke tests, `doctor`, `upgrade` |
| | **Part 4 - Build once** | |
| 14 | [Capabilities and AI agents](https://github.com/caligrafy/caligrafy-quill-course/wiki/14-Capabilities-and-AI-Agents) | services and `#[Capability]`, REST with `Route::capability`, personal access tokens, MCP for Claude Code, the audit log |

## Checkpoint code

The [`checkpoints/`](checkpoints) folder holds the finished code of every lesson, as an **overlay**: only the files you
write or change in your project, at their real paths. Each folder contains everything up to that lesson.

| Folder | Git tag in your project | Contents |
|---|---|---|
| [`lesson-02`](checkpoints/lesson-02) | `lesson-02` | `web.php` with your first routes |
| [`lesson-03`](checkpoints/lesson-03) | `lesson-03` | + `Bookshelf\BookController` (data in an array) |
| [`lesson-04`](checkpoints/lesson-04) | `lesson-04` | + `create_db.sql` and `seed.sql` (authors, books) |
| [`lesson-05`](checkpoints/lesson-05) | `lesson-05` | + `Book` and `Author` models, controllers reading MySQL |
| [`lesson-06`](checkpoints/lesson-06) | `lesson-06` | + Pug views |
| [`lesson-07`](checkpoints/lesson-07) | `lesson-07` | + `BookForm`, forms to add, edit and delete books |
| [`lesson-08`](checkpoints/lesson-08) | `lesson-08` | + authentication |
| [`lesson-09`](checkpoints/lesson-09) | `lesson-09` | + `Bookshelf\Api\BookApiController` |
| [`lesson-10`](checkpoints/lesson-10) | `lesson-10` | + `public/catalog` (Vue as a library) and `Bookshelf\CatalogController` |
| [`lesson-11`](checkpoints/lesson-11) | `lesson-11` | + the changed files of `public/bookshelf-spa` (Vite, Vue Router) |
| [`lesson-12`](checkpoints/lesson-12) | `lesson-12` | + the Pinia store, the form page and `vite.config.js` |
| [`lesson-13`](checkpoints/lesson-13) | `lesson-13` | + a PHPUnit test and a smoke test script |
| [`lesson-14`](checkpoints/lesson-14) | `lesson-14` | + three capabilities in `application/services/Bookshelf` and their routes |

Compare your project with a checkpoint (run from the folder that contains both `bookshelf` and this repository):
```bash
diff -ru caligrafy-quill-course/checkpoints/lesson-07/application bookshelf/application
```
Catch up to a checkpoint (it overwrites the files of that lesson in your project):
```bash
cp -R caligrafy-quill-course/checkpoints/lesson-07/. bookshelf/
```
The checkpoints never contain `.env` files or keys: those are created in your project by lesson 01. The SPA
checkpoints (`lesson-11`, `lesson-12`) only hold the files you change in `public/bookshelf-spa`: create the app first
with lesson 11, step 1.

Screenshots of the pages used in the lessons are in [`docs/images`](docs/images).

## Credits

Created by [Dory Azar](https://github.com/DoryAzar). Based on the original
[Caligrafy course](https://github.com/caligrafy/caligrafy-course) and
[PHP meets Vue JS course](https://github.com/caligrafy/caligrafy-vue-course), rewritten for
[Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill).
Released under the [MIT License](LICENSE).
