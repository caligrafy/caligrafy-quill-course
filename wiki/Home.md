# Caligrafy Quill Course

A step by step course for people who have **never used Caligrafy Quill**. You build **Bookshelf**, a small catalog
of books and authors: first as a server-rendered web application with Pug views, forms, sign in and a REST API
(part 1), then as a Vue app and a Vite single page application on the same API (part 2), you keep it healthy with
code review, tests, `doctor` and `upgrade` (part 3), and you finish with what Caligrafy is made for, **build once,
experience everywhere**: features written once as capabilities, used from the API and by an AI agent (part 4).

Just want to see Caligrafy run first? The framework's [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart)
gets a working application in 5 minutes; then come back here to learn how it all works.

You need: a little HTML, CSS, JavaScript, PHP and SQL. Everything else (Docker, Caligrafy, Pug, Vue, Vite, Pinia)
is explained when it first appears.

## How to use this course

Every lesson has the same structure:
1. **New concepts**: what you are about to use, and why Caligrafy does it that way.
2. **Manual track**: numbered steps. Each step says which file, gives its **complete** content (no `...`), what
   you should see, and how to check it. Do this track first.
3. **If it doesn't work**: the real errors of that lesson and their fix.
4. **With Claude**: the exact prompt or plugin command for [Claude Code](https://claude.com/claude-code), what
   Claude should create, a **Check** list to review it, and what to do when Claude's result differs.
5. **Checkpoint**: a commit and a git tag (`lesson-05`...). The finished code of every lesson is in the
   [`checkpoints/`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints) folder of the
   course repository.
6. A short **quiz or exercise**, with its solution under "Show the solution".

## Lessons

**Part 0 - Setup**
- [Lesson 00 - What Caligrafy Quill is, and installing your tools](00-Introduction-and-Tools)
- [Lesson 01 - Create your project](01-Create-Your-Project)

**Part 1 - Server side with Caligrafy views**
- [Lesson 02 - Routes](02-Routes)
- [Lesson 03 - Controllers](03-Controllers)
- [Lesson 04 - The database](04-The-Database)
- [Lesson 05 - Models and relationships](05-Models-and-Relationships)
- [Lesson 06 - Views and the layout](06-Views-and-the-Layout)
- [Lesson 07 - Forms, CSRF and validation](07-Forms-CSRF-and-Validation)
- [Lesson 08 - Authentication](08-Authentication)
- [Lesson 09 - The REST API](09-The-REST-API)

**Part 2 - Vue**
- [Lesson 10 - A Vue app inside Caligrafy](10-Vue-as-a-Library)
- [Lesson 11 - The Vite single page application and Vue Router](11-Vite-SPA-and-Vue-Router)
- [Lesson 12 - Pinia, styling and the production build](12-Pinia-Styling-and-Build)

**Part 3 - Keeping it healthy**
- [Lesson 13 - Review, tests, doctor and upgrade](13-Review-Tests-Doctor-Upgrade)

**Part 4 - Build once**
- [Lesson 14 - Capabilities and AI agents](14-Capabilities-and-AI-Agents)

This course replaces the older [caligrafy-course](https://github.com/caligrafy/caligrafy-course) and
[caligrafy-vue-course](https://github.com/caligrafy/caligrafy-vue-course). The full framework documentation is the
[Caligrafy Quill wiki](https://github.com/caligrafy/caligrafy-quill/wiki).
