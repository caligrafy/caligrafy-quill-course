# Lesson 2 - Describe it

**You will** write the description of **Bookshelf**, the app of this course, and learn what makes a description
good. You will also see how to start from a proven prompt instead. **Time:** 20 minutes.

---

## What a good description says

Claude builds what you describe, and fills the gaps with sensible defaults. The fewer the gaps, the closer the first
version is to what you want. A good description answers five questions:

| Question | Bookshelf |
|---|---|
| **Who** uses it? | a book club: visitors and members |
| **What** do they do with it? | browse the books, filter them by author, open a page per book; members add, edit and delete books and authors, and download the catalog as a spreadsheet file |
| **What** does it remember? | books (title, year, a short summary) and their authors (a name) |
| **Who may** do what? | anyone reads; only members who signed up change anything |
| **What is not** in this version? | no loans yet (that is [Lesson 6](Lesson-6-Change-It)) |

Compare:

| Too vague | Good |
|---|---|
| "An app for books." | "Bookshelf: a catalog of books and their authors for a book club. Anyone can browse the books, filter them by author and open a page per book. Members who signed up add, edit and delete books and authors, and can download the catalog as a CSV file. A book has a title, an author, a year and a short summary; an author has a name. No loans yet." |

The vague one leaves Claude to guess who uses it, what a book is and who may change it. The good one is still only
five sentences: it describes what people do, not how to build it. You do not say "a table", "a route" or "an API":
Claude turns each sentence into those parts, and shows you which in the plan.

Each kind of sentence becomes a part of the plan you will read in [Lesson 3](Lesson-3-Approve-The-Plan):

| You say | Claude plans |
|---|---|
| "a book has a title, an author, a year" | a table and its columns |
| "members add, edit and delete books" | a capability per action, and who may call it |
| "anyone can browse" | capabilities anyone may call; the others need a signed-in member |
| "open a page per book" | a page |
| "download the catalog as a CSV file" | an export address |
| "every morning", "remind", "alert me" | a job on a timer, a notification |
| "they must accept the rules to join" | a box on the sign-up form |
| "after signing in, back to the page they were on" | an option of that page |
| "a link that picks a book at random", "a link in a message that renews a loan" | one small web-only action |

---

## Your description of Bookshelf

Write it in a file, so that you can reuse and improve it. Start from the good one above and make it yours (another
field for a book, a club name...). Keep it small: Claude aims for 1 to 3 tables and 4 to 12 capabilities in a first
version. A small app that works beats a big one that does not.

You will give it to Claude in [Lesson 3](Lesson-3-Approve-The-Plan).

---

## Or start from a proven prompt

**Explore**, the marketplace of [caligrafy.com](https://caligrafy.com/marketplace), shows apps built with Caligrafy,
live: you can try each one. For each, you can buy the **prompt** that recreates it with Caligrafy, using Claude: a
description written from the app's real code and checked by Caligrafy. It is longer than yours, and in this order:

1. the idea in one sentence;
2. every capability: its name, its inputs and their rules, who may use it, what it does;
3. the data: the tables, their fields and how they link;
4. the jobs on a timer and the work done in the background, if any;
5. every screen and what it shows and does;
6. how the web reaches it: forms, sign-in, an export, a payment provider's callback, file fields;
7. the services and settings it needs (AI, payments, email), by name, never with a key;
8. how it goes online (Caligrafy Cloud, `/caligrafy-quill:ship`).

A prompt you bought stays in your **Library** on caligrafy.com. Copy it from there, and give it to Claude instead of
your own sentence:
```text
/caligrafy-quill:idea <the prompt>
```
Then you approve and ship it exactly as in the next lessons. Reading one is also the best way to learn to write
yours.

---

## Check

- [ ] Your description says who uses the app, what they do, what it remembers and who may change it.
- [ ] It says what is **not** in this version.
- [ ] It fits in a few sentences and describes what people do, not how to build it.

**Exercise.** Write a second description, for an idea of **your own**, with the same five questions. You will build it
in [Lesson 8](Lesson-8-Your-Own-Idea).

**By hand:** [02 Build once, seen three ways](02-Build-Once-Seen-Three-Ways) explains what a capability is, and
[03 Your data](03-Your-Data) turns the facts of this description into tables yourself.

**Next:** [Lesson 3 - Approve the plan](Lesson-3-Approve-The-Plan)
