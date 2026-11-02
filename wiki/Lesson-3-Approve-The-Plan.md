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
```
/caligrafy-quill:idea bookshelf-<you>: <your description>
```

---

## Read the plan

The plan is the one moment where you decide. Read each part with its question:

| Part | Ask yourself | Look for |
|---|---|---|
| **Name** | is it the address I want? | `bookshelf-<you>`: lowercase letters, digits and dashes |
| **Tables** | does every fact of my description have a home? | `authors` (`name`) and `books` (`title`, `year`, `summary`, and `author_id`, the link to its author) |
| **Capabilities** | can people do everything I described, and nothing else? | for each table: `list`, `get`, `create`, `update`, `delete`; and a way to filter books by author |
| **Who may do what** | is the right person allowed? | anyone reads, members change. If the plan says *everything public*, anyone online could delete your data: that is for a demo only |
| **Pages** | can people reach it all? | `/books` with the list, the form and the filter; a page per book |
| **Sample data** | is the app useful from the start, with nothing personal? | a few real-looking books, no real people's data |

A **capability** is one thing the app can do, written once. Every way of using the app (the page, the phone, other
programs, AI agents) calls the same capability, with the same rules: you will see it in
[Lesson 4](Lesson-4-Review-It).

---

## Change it

If something is wrong or missing, say it in plain words, for example:
```
Books also have a number of pages. Only administrators may delete an author.
```
Claude shows the plan again. Repeat until it is what you want.

## Approve it

This time, keep the app on your computer so that you can review it first. Answer:
```
OK. Build it and check it here, then stop before putting it online: I'll review it first.
```
Claude sets up the project, builds every feature once and checks each of them. It ends with a report: your local
addresses (the page at `http://localhost:8080/books`, or another port if 8080 is taken), a `curl` line for the REST
API, the line to connect an AI agent, what its checks showed, and **what it changed since the plan**.

Read that last part as carefully as the plan. Claude sometimes adds what the app needs to be safe (for example a way
for members to see and revoke their own tokens) and fixes what its review found. It says why; if you do not want
something, say so. It may also leave you a decision, such as who may delete: answer it before you ship.

---

## Check

- [ ] Every fact of your description is a column of a table, and `books` links to `authors`.
- [ ] Every action of your description is a capability, and nothing you did not ask for is.
- [ ] Changes are for members only (or administrators, where you said so).
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
