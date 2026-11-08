# Caligrafy Course

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

**Describe it. Approve it. Ship it.** With Caligrafy, Claude turns your idea into a complete app online. This course
teaches you to do it well: describe what you want clearly, approve with confidence, and ship. You build **Bookshelf**,
a catalog of books for a book club, then an app of your own.

Nine short lessons, on Caligrafy Quill 2.21. No code to write: Claude writes it, and you learn what to look for, and
where, before you say OK.

**[Start the course on the Wiki](https://github.com/caligrafy/caligrafy-quill-course/wiki)** (the same pages are in
the [`wiki/`](wiki) folder of this repository).

## What you need

[Git](https://git-scm.com/), [Docker Desktop](https://www.docker.com/products/docker-desktop/),
[Claude Code](https://claude.com/claude-code) and a [GitHub](https://github.com/) account.
[Lesson 0](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-0-Get-Ready) checks each one. The framework's own short version of this course is the
[Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude).

## Lessons

| # | Lesson | You learn |
|---|---|---|
| | **Start** | |
| 0 | [Get ready](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-0-Get-Ready) | the four tools, the plugin's commands, and three rules for working with Claude |
| 1 | [Your first app in one conversation](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-1-Your-First-App) | the three steps once, quickly, on a tiny app |
| | **Describe it** | |
| 2 | [Describe it](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-2-Describe-It) | what a good description says; starting from a proven prompt |
| | **Approve it** | |
| 3 | [Approve the plan](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-3-Approve-The-Plan) | reading the plan: tables, capabilities, who may do what, pages |
| 4 | [Review what Claude built](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-4-Review-It) | every experience, each file Claude wrote and what to look for in it, a review |
| | **Ship it** | |
| 5 | [Ship it](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-5-Ship-It) | online, with your own GitHub repository, and kept online |
| 6 | [Change it, ship it again](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-6-Change-It) | a new feature with the same three steps, a migration; another look |
| 7 | [Sell it](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-7-Sell-It) | your app's prompt on the marketplace of caligrafy.com, and your 25% |
| | **Your turn** | |
| 8 | [Your own idea](https://github.com/caligrafy/caligrafy-quill-course/wiki/Lesson-8-Your-Own-Idea) | your app, described, approved and shipped |

## By hand

Everything Claude does is plain Caligrafy you can write yourself. The **By hand** track builds Bookshelf again, every
file typed by you, so that you understand every line Claude writes. Take it after the main lessons, or whenever a
lesson's **By hand** link makes you curious. It uses Caligrafy Quill 2.21.2, and needs a little HTML, CSS, JavaScript,
PHP and SQL.

**Start:** [00 Your tools](https://github.com/caligrafy/caligrafy-quill-course/wiki/00-Introduction-and-Tools) · [01 Your project in one command](https://github.com/caligrafy/caligrafy-quill-course/wiki/01-Your-Project-in-One-Command) · [02 Build once, seen three ways](https://github.com/caligrafy/caligrafy-quill-course/wiki/02-Build-Once-Seen-Three-Ways)  
**Build once:** [03 Your data](https://github.com/caligrafy/caligrafy-quill-course/wiki/03-Your-Data) · [04 Your first capabilities](https://github.com/caligrafy/caligrafy-quill-course/wiki/04-Your-First-Capabilities) · [05 Rules and changes](https://github.com/caligrafy/caligrafy-quill-course/wiki/05-Rules-and-Changes)  
**Experience everywhere:** [06 Web pages](https://github.com/caligrafy/caligrafy-quill-course/wiki/06-Web-Pages) · [07 The REST API](https://github.com/caligrafy/caligrafy-quill-course/wiki/07-The-REST-API) · [08 AI agents](https://github.com/caligrafy/caligrafy-quill-course/wiki/08-AI-Agents) · [09 A Vue app](https://github.com/caligrafy/caligrafy-quill-course/wiki/09-A-Vue-App) · [10 Phones and tablets](https://github.com/caligrafy/caligrafy-quill-course/wiki/10-Phones-and-Tablets)  
**People and trust:** [11 People and permissions](https://github.com/caligrafy/caligrafy-quill-course/wiki/11-People-and-Permissions)  
**Ship it:** [12 Tests, review, doctor](https://github.com/caligrafy/caligrafy-quill-course/wiki/12-Ship-It) · [13 Capstone: Loans](https://github.com/caligrafy/caligrafy-quill-course/wiki/13-Capstone-Loans) · [14 Put it online](https://github.com/caligrafy/caligrafy-quill-course/wiki/14-Put-It-Online) · [15 Reminders: a job and notifications](https://github.com/caligrafy/caligrafy-quill-course/wiki/15-Reminders)  
**Every request declared:** [16 What a page or a form cannot say](https://github.com/caligrafy/caligrafy-quill-course/wiki/16-Every-Request-Declared)  
**Under the hood (the older ways):** [A Routes and controllers](https://github.com/caligrafy/caligrafy-quill-course/wiki/A-Routes-and-Controllers) · [B Classic forms](https://github.com/caligrafy/caligrafy-quill-course/wiki/B-Classic-Forms)

## Checkpoint code (By hand)

The [`checkpoints/`](checkpoints) folder holds the finished code of every by-hand lesson, as an **overlay**: only the
files you write or change in your project, at their real paths. Each folder contains everything up to that lesson
(`lesson-02` to `lesson-16`, `appendix-a`, `appendix-b`). Lesson 01 writes no code, and lesson 02 removes the
Tasks example: its files are not in any checkpoint.

Compare your project with a checkpoint (run from the folder that contains both `bookshelf` and this repository):
```bash
diff -ru caligrafy-quill-course/checkpoints/lesson-07/application bookshelf/application
```
Catch up to a checkpoint (it overwrites the files of that lesson in your project):
```bash
cp -R caligrafy-quill-course/checkpoints/lesson-07/. bookshelf/
```
The checkpoints never contain `.env` files, keys or tokens: lesson 01 and the `token` command create them in your
project. Lesson 02's checkpoint holds only `web.php`. The scripts that create and change the tables are in the
checkpoints, the data you added is not. After catching up, create the tables and apply the changes:
```bash
php caligrafer.php sql application/models/Bookshelf/create_db.sql
php caligrafer.php migrate
```
(with the Docker dev server, put `docker compose exec dev-box` before each command). From lesson 10 on, also run
`npm install` in `public/bookshelf-spa`.

`tools/check-pages.py` checks that every file shown in a by-hand lesson page is identical to its checkpoint.

## Credits

Created by [Dory Azar](https://github.com/DoryAzar). Based on the original
[Caligrafy course](https://github.com/caligrafy/caligrafy-course) and
[PHP meets Vue JS course](https://github.com/caligrafy/caligrafy-vue-course), rewritten for
[Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill).
Released under the [MIT License](LICENSE).
