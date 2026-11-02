# Lesson 4 - Review what Claude built

**You will** try Bookshelf in every way people and programs use it, find where its code lives, and have it reviewed,
before you ship it. **Time:** 45 minutes.

---

## 1. Try every experience

Claude built each feature **once**, as a capability, and every experience calls it. Try them; they should agree.

**The web page.** Open `http://localhost:8080/books` (or the port Claude gave you). As a visitor you can browse and
filter. Sign up (a username of letters and digits), sign in, then add an author and a book, edit it, delete it.
Leave the title empty and add it: the page tells you what is missing.

**The phone.** In your browser, open the developer tools and switch to a phone size (375 px wide): the page fits.
Online, in [Lesson 5](Lesson-5-Ship-It), you will install it on a real phone.

**The REST API**, for other programs:
```bash
curl -s -H "Accept: application/json" http://localhost:8080/api/books
curl -s -X POST -H "Accept: application/json" -d "title=Test" http://localhost:8080/api/books
```
The first lists the books. The second is refused (401): a visitor may not add one, exactly as on the page.

**An AI agent.** Signed in on the page, use the **AI agents** card, at the bottom: give the token a name, create it,
and copy the `claude mcp add ...` line it shows. Run that line in a terminal, start a new `claude` session, and ask:
```
Which books do we have, and who wrote them?
```
Claude answers from your app, through the same capabilities. The token is shown once: never paste it in a chat.

---

## 2. Know where things live

Ask Claude:
```
Show me where each part of Bookshelf lives, in one short table.
```

| What | Where |
|---|---|
| The tables | `application/models/<Folder>/create_db.sql` |
| The capabilities, one file each | `application/services/<Folder>/` |
| The addresses: pages, REST, MCP | `application/config/routing/web.php` |
| The page | `application/views/<Folder>/index.pug` and `public/js/` |

Open one capability, for example the one that creates a book. Find its **description** (what an AI agent reads), its
**rules** (`$rules`: what a valid input is) and its **scope** (who may call it). Ask Claude to explain any line you do
not understand.

---

## 3. Have it reviewed

```
Run doctor, and ask the caligrafy-reviewer agent to review everything you wrote.
```
`doctor` checks the project's setup; the reviewer reads the code for Caligrafy mistakes and security issues. Ask Claude
to fix what they find, then to check every experience again.

---

## Check

- [ ] The page, the REST API and the AI agent show the same books.
- [ ] A visitor is refused a change everywhere: on the page, over REST and through an agent without a token.
- [ ] An invalid input gives the same message on the page and over REST.
- [ ] You opened one capability and understood its description, rules and scope.
- [ ] `doctor` and the reviewer found nothing left to fix.

When every box is ticked, you approve what Claude built, not just what it planned.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | The page does not open | ask Claude to check the server (`/caligrafy-quill:server status`) |
> | The agent does not see the app | start a new `claude` session after `claude mcp add`; check the address and the port |
> | Experiences disagree | tell Claude what you saw: it is a bug, and Claude fixes it in the capability, once |

**By hand:** each experience, built yourself: [06 Web pages](06-Web-Pages), [07 The REST API](07-The-REST-API),
[08 AI agents](08-AI-Agents), [09 A Vue app](09-A-Vue-App), [10 Phones and tablets](10-Phones-and-Tablets),
[11 People and permissions](11-People-and-Permissions), [12 Tests, review, doctor](12-Ship-It).

**Next:** [Lesson 5 - Ship it](Lesson-5-Ship-It)
