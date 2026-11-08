# Lesson 3 - Approve the plan

**You will** give your description to Claude, read its plan part by part, change what is wrong, and approve it.
**Time:** 30 minutes, then Claude works.

---

## Start

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git bookshelf
cd bookshelf
claude
```
Answer *Yes* to trusting the folder, then give Claude your description from [Lesson 2](Lesson-2-Describe-It), with
your name in the app's name:
```text
/caligrafy-quill:idea bookshelf-<you>: <your description>
```

---

## Read the plan

The plan is the one moment where you decide: your OK covers everything after it. Read each part with its question:

| Part | Ask yourself | Look for |
|---|---|---|
| **Name** | is it the address I want? | `bookshelf-<you>`: 3 to 30 lowercase letters, digits and dashes, starting with a letter |
| **Tables** | does every fact of my description have a home? | `authors` (`name`) and `books` (`title`, `year`, `summary`, and `author_id`, the link to its author: a *foreign key*) |
| **Capabilities** | can people do everything I described, and nothing else? | for each table `list_`, `get_`, `create_`, `update_`, `delete_`; a way to filter books by author (an input of `list_books`); one that reads every book for the export |
| **Flags** | is each one honest about what it does? | the reads marked read-only, the deletes marked destructive; scopes such as `books:read` and `books:write` |
| **Who may do what** | is the right person allowed? | anyone reads, members change. If the plan says *everything public*, anyone online could delete your data: that is for a demo only |
| **Pages** | can people reach it all? | `/books` with the form, the list, the filter and a message when it is empty; a page per book; `/` opens `/books`; sign-in and sign-up; a page for members brings a visitor back after signing in |
| **Export** | is the file the one I asked for? | `/books.csv`, for members |
| **Sample data** | is the app useful from the start, with nothing personal? | 3 to 6 real-looking books, no real people's data, no password |

Two more parts appear only when the description asks for them: **slow work** (an AI call, an email to many: it runs in
the background) and **on a timer, alerts** (a job, who gets a notification).

A **capability** is one thing the app can do, written once. Every way of using the app (the page, the phone, other
programs over REST, AI agents) calls the same capability, with the same rules: you will see it in
[Lesson 4](Lesson-4-Review-It). A **scope** names what a capability touches, so that a token for a program or an
agent can be limited to it.

---

## Change it

If something is wrong or missing, say it in plain words, for example:
```text
Books also have a number of pages. Only administrators may delete an author.
```
Claude shows the plan again. Repeat until it is what you want.

## Approve it

This time, keep the app on your computer so that you can review it first. Answer:
```text
OK. Build it and check it here, then stop before putting it online: I'll review it first.
```
Claude sets up the project, builds every feature once and checks each of them:

- it lists the capabilities and calls each one, with a valid and an invalid input, as a member and as a visitor;
- it calls the REST API, and checks that a change without signing in is refused;
- it signs up, signs in, adds, changes and deletes on the page, sends an empty form, and looks at it at phone width;
- it connects an AI agent with a short-lived token, lists its tools and calls one, then revokes the token;
- it runs the tests, `doctor` (a check of the project's setup) and the `caligrafy-reviewer` agent.

It ends with a report: your local addresses (the page at `http://localhost:8080/books`, or another port if 8080 is
taken), a `curl` line for the REST API, how to connect an AI agent, what its checks showed, and **what it changed since
the plan**.

Read that last part as carefully as the plan. Claude adds what the app needs to be safe or usable even when you did not
ask, for example a small **AI agents** card on the page, where members create their own token, and it fixes what its
review found. It says why; if you do not want something, say so. It may also leave you a decision, such as who may
delete: answer it before you ship.

---

## Check

- [ ] Every fact of your description is a column of a table, and `books` links to `authors`.
- [ ] Every action of your description is a capability, and nothing you did not ask for is.
- [ ] Reads are read-only, deletes are destructive, and changes are for members only (or administrators, where you
      said so).
- [ ] The sample data has nothing personal and no password.
- [ ] You read what Claude changed since the plan, and agree with it.
- [ ] You told Claude to stop before putting it online, and it did.

> **If it doesn't work**
>
> | You see | Do |
> |---|---|
> | Claude builds without showing a plan | stop it (Esc) and ask: "Show me the plan first." |
> | The plan is much bigger than your description | ask Claude to keep only what you described |
> | Claude goes on to put it online | say "Stop, don't put it online yet": nothing is spent without your yes |

**By hand:** what the plan becomes, written yourself: [03 Your data](03-Your-Data),
[04 Your first capabilities](04-Your-First-Capabilities), [05 Rules and changes](05-Rules-and-Changes).

**Next:** [Lesson 4 - Review what Claude built](Lesson-4-Review-It)
