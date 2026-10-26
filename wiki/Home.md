# Caligrafy Quill Course

Build **Showcase**, a project portfolio app, with [Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill):
first on the server with Pug views (Part 1), then as a Vue front end on the same REST API (Part 2).
This course replaces [caligrafy-course](https://github.com/caligrafy/caligrafy-course) and
[caligrafy-vue-course](https://github.com/caligrafy/caligrafy-vue-course): same project, current APIs
(PHP 8.3+, Bootstrap 5 with the Caligrafy black and white theme, Vue 3.5, Vite 8, Pinia).

## Two tracks in every lesson

- **Manual**: the exact commands, files and code. Do this first whenever the concept is new to you.
- **With Claude**: the Claude Code prompts and `caligrafy-quill` plugin commands that do the same work, and a
  **Check** list of what to verify before you accept Claude's result.

A good rhythm: do the Manual track, commit, then redo the lesson With Claude on a branch and compare
(`git diff manual..claude`). Prefer Claude for boilerplate you already understand (a new CRUD resource, a new app,
starting the server, running SQL) and for reviews; prefer Manual when learning, debugging framework behavior, or
when a change is small enough to type faster than to describe.

## Lessons
- [[00 Setup|00-Setup]]
- **Part 1 - Server side**: [[01 Database primer|01-Database-Primer]], [[02 Routes and controllers|02-Routes-and-Controllers]],
  [[03 Models and relationships|03-Models-and-Relationships]], [[04 Views and forms|04-Views-and-Forms]],
  [[05 Validation and authentication|05-Validation-and-Authentication]], [[06 REST API and status codes|06-REST-API-and-Status-Codes]]
- **Part 2 - Vue**: [[07 Vue as a library|07-Vue-as-a-Library]], [[08 Vite SPA and router|08-Vite-SPA-and-Router]],
  [[09 Pinia and the API|09-Pinia-and-the-API]], [[10 Review, test, doctor and upgrade|10-Review-Test-and-Upgrade]]

## Checkpoints
Finished code per part: [`checkpoints/part-1-server`](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-1-server) (tag `part-1`) and
[`checkpoints/part-2-vue`](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-2-vue) (tag `part-2`), as overlays on a fresh initialized project.
