# Caligrafy Quill Course: the Manual way and the Claude Code way

![Caligrafy](https://github.com/DoryAzar/caligrafy/blob/master/public/images/resources/banner_white.png)

One course that replaces the two original Caligrafy courses
([caligrafy-course](https://github.com/caligrafy/caligrafy-course) and
[caligrafy-vue-course](https://github.com/caligrafy/caligrafy-vue-course)).
You build **Showcase**, the same project portfolio app as in the original courses:

- **Part 1 - Server side:** routes, namespaced controllers, models on MySQL, Pug views with the Caligrafy
  black and white Bootstrap 5 look, forms, validation, authentication and a REST API with real HTTP status codes.
- **Part 2 - Front end:** the same app in Vue 3.5, first as a Vue-as-a-library client app served by Caligrafy,
  then as a Vite 8 single page application with Vue Router and Pinia consuming the Caligrafy REST API.

Every lesson has two tracks:

| Track | What you do | Prefer it when |
|---|---|---|
| **Manual** | Type the exact commands, files and code yourself | You meet a concept for the first time, or you need to understand every line |
| **With Claude** | Use Claude Code with the `caligrafy-quill` plugin (`/caligrafy-quill:new-resource`, `/caligrafy-quill:new-app`, `/caligrafy-quill:server`, `/caligrafy-quill:db`, the `caligrafy-reviewer` agent) and check its result | You already understand the concept and want the boilerplate done fast, or you want a second pair of eyes |

We recommend doing each lesson **Manual first, then With Claude** on a fresh git branch, and comparing both with
the checkpoint code in this repository. Every "With Claude" section ends with a **Check** list: what to verify in
Claude's output before you accept it.

**[Start the course on the Wiki](https://github.com/caligrafy/caligrafy-quill-course/wiki)**
(the same pages are in the [`wiki/`](wiki) folder of this repository).

## Prerequisites

- Basic PHP, SQL, HTML/CSS and JavaScript
- Git and [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or PHP 8.3+, Composer and MySQL 8 for a local stack)
- Node.js 20.19+ or 22.12+ for Part 2 (the Vite SPA)
- For the "With Claude" track: [Claude Code](https://claude.com/claude-code). The `caligrafy-quill` plugin ships inside the
  framework (`claude-plugin/`) and is enabled by the project's `.claude/settings.json` once you trust the folder.

## Setup (Docker)

```bash
git clone git@github.com:caligrafy/caligrafy-quill.git showcase
cd showcase
.bin/caligrafer server start   # composer install + initialize + Docker: app :8080, phpMyAdmin :8077, MySQL :3306
```
`initialize` creates `application/` and `.env` and **removes the framework's `.git`** so the project becomes yours
(`git init` it afterwards). In `.env` set `DB_ACTIVATE=true`, `DB_HOST=mysql`, `DB_DATABASE=showcase`, `APP_ROOT=`.
[Lesson 00](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Setup) explains every step and the
Claude Code alternative (`/caligrafy-quill:server`). Never commit `.env`; only `.env.example` belongs in git.

## Lessons

| # | Lesson | From the original course |
|---|---|---|
| 00 | [Setup: Docker, Caligrafy Quill and Claude Code](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Setup) | both |
| | **Part 1 - Server side with Caligrafy views** | caligrafy-course |
| 01 | [Database primer](https://github.com/caligrafy/caligrafy-quill-course/wiki/01-Database-Primer) | 1-database-primer |
| 02 | [Routes, requests and controllers](https://github.com/caligrafy/caligrafy-quill-course/wiki/02-Routes-and-Controllers) | 2-routes-requests-controllers |
| 03 | [Models and relationships](https://github.com/caligrafy/caligrafy-quill-course/wiki/03-Models-and-Relationships) | 3-models-relationships |
| 04 | [Pug views and forms](https://github.com/caligrafy/caligrafy-quill-course/wiki/04-Views-and-Forms) | 4-views-forms |
| 05 | [Validation and authentication](https://github.com/caligrafy/caligrafy-quill-course/wiki/05-Validation-and-Authentication) | 5-authentication-validation |
| 06 | [REST API and HTTP status codes](https://github.com/caligrafy/caligrafy-quill-course/wiki/06-REST-API-and-Status-Codes) | 8-apis |
| | **Part 2 - The same app in Vue** | caligrafy-vue-course |
| 07 | [Vue as a library](https://github.com/caligrafy/caligrafy-quill-course/wiki/07-Vue-as-a-Library) | client apps |
| 08 | [Vite SPA and Vue Router](https://github.com/caligrafy/caligrafy-quill-course/wiki/08-Vite-SPA-and-Router) | myvue |
| 09 | [Pinia and the Caligrafy API](https://github.com/caligrafy/caligrafy-quill-course/wiki/09-Pinia-and-the-API) | store (Vuex is now Pinia) |
| 10 | [Review, test, doctor and upgrade](https://github.com/caligrafy/caligrafy-quill-course/wiki/10-Review-Test-and-Upgrade) | new |

Metadata/social sharing and payments from the original course are documented in the
[Caligrafy Quill wiki](https://github.com/caligrafy/caligrafy-quill/wiki) and are not repeated here.

## Checkpoint code

The finished code of each part lives in [`checkpoints/`](checkpoints) as an **overlay** on a freshly initialized
Caligrafy Quill project: only the files you write or change, at their real paths.

| Folder | Git tag | Contains | Apply on |
|---|---|---|---|
| `checkpoints/part-1-server` | `part-1` | `application/`: routes, `Showcase\ProjectController`, `Project` model and SQL, Pug views | a fresh initialized project |
| `checkpoints/part-2-vue` | `part-2` | `application/` changes, `public/showcase` (Vue as a library), `public/showcase-spa` (Vite SPA) | part 1 |

```bash
cp -R checkpoints/part-1-server/. ../showcase/
cp -R checkpoints/part-2-vue/. ../showcase/          # part 2 only
diff -ru checkpoints/part-1-server/application ../showcase/application   # compare with your own work
```

## Credits

Created by [Dory Azar](https://github.com/DoryAzar). Built on the original
[Caligrafy course](https://github.com/caligrafy/caligrafy-course) and
[PHP meets Vue JS course](https://github.com/caligrafy/caligrafy-vue-course), updated for
[Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill) (PHP 8.3+, Bootstrap 5, Vue 3.5, Vite 8, Pinia).
Released under the [MIT License](LICENSE).
