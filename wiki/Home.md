# Caligrafy Quill Course

**Build once, experience everywhere, from zero.** In this course you build **Bookshelf**, a small catalog of books
and authors, the Caligrafy way: every feature is written **once**, as a **capability**, and every experience of the
application uses it: web pages, a REST API for other programs, AI agents such as Claude, a Vue app, and an app for
phones and tablets. The same rules, the same permissions and the same audit log everywhere.

You need a little HTML, CSS, JavaScript, PHP and SQL. Everything else (Docker, Caligrafy, Pug, Vue, Vite, MCP) is
explained when it first appears. You do not install PHP or MySQL: they run in Docker.

## What you will have at the end

| Experience | What it does | Lesson |
|---|---|---|
| A catalog page and a page per book | browse, filter by author, add, edit, delete | 06 |
| A REST API | `GET /api/books`, `POST /api/books`... for other programs | 07 |
| AI agent tools | Claude or Cursor search the catalog, add books, lend them | 08, 13 |
| A Vue "finder" app | search as you type | 09 |
| A phone and tablet app | installable, list and form side by side on tablets | 10 |
| People and permissions | sign in, administrators, scoped tokens for agents | 11 |
| Loans | one more feature, built once, in every experience at once | 13 |

All of it on **ten capabilities** of a few dozen lines of PHP each.

## How to use this course

Every lesson has the same structure:
1. **New concepts**: what you are about to use, and why.
2. **Manual track**: numbered steps. Each step says what to type or which file to write (complete, no `...`),
   what you should see, and how to check it. **Do this track first**: it is how you learn.
3. **If it doesn't work**: the real errors of that step and their fix.
4. **With Claude**: the exact prompt or plugin command for [Claude Code](https://claude.com/claude-code) (the
   `caligrafy-quill` plugin ships inside every project), what Claude should do, and a **Check** list to review it.
   Use it on a second pass, or for a lesson you already understand.
5. **Checkpoint**: a commit and a git tag (`lesson-05`...). The finished code of every lesson is in the
   [`checkpoints/`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints) folder of the course
   repository.
6. A short **quiz or exercise**, with its solution under "Show the solution".

## Lessons

**Part 0 - Start**
- [Lesson 00 - What you will build, and your tools](00-Introduction-and-Tools)
- [Lesson 01 - Your project in one command](01-Your-Project-in-One-Command)
- [Lesson 02 - Build once, seen three ways](02-Build-Once-Seen-Three-Ways)

**Part 1 - Build once**
- [Lesson 03 - Your data: tables and models](03-Your-Data)
- [Lesson 04 - Your first capabilities](04-Your-First-Capabilities)
- [Lesson 05 - Rules: adding, changing and deleting books](05-Rules-and-Changes)

**Part 2 - Experience everywhere**
- [Lesson 06 - Web pages](06-Web-Pages)
- [Lesson 07 - The REST API](07-The-REST-API)
- [Lesson 08 - AI agents](08-AI-Agents)
- [Lesson 09 - A Vue app](09-A-Vue-App)
- [Lesson 10 - Phones and tablets](10-Phones-and-Tablets)

**Part 3 - People and trust**
- [Lesson 11 - People and permissions](11-People-and-Permissions)

**Part 4 - Ship it**
- [Lesson 12 - Ship it: tests, review, doctor](12-Ship-It)
- [Lesson 13 - Capstone: one more feature, everywhere](13-Capstone-Loans)

**Appendix - Under the hood (optional, any time after lesson 06; the exercise of B after lesson 13)**
- [Appendix A - Routes and controllers by hand](A-Routes-and-Controllers)
- [Appendix B - Classic forms and controller APIs](B-Classic-Forms)

The course was written and checked against **Caligrafy Quill 2.2.0**. The full framework documentation is the
[Caligrafy Quill wiki](https://github.com/caligrafy/caligrafy-quill/wiki). This course replaces the older
[caligrafy-course](https://github.com/caligrafy/caligrafy-course) and
[caligrafy-vue-course](https://github.com/caligrafy/caligrafy-vue-course).
