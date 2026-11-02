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
| **What** do they do with it? | browse the books, filter them by author, open a page per book; members add, edit and delete them |
| **What** does it remember? | books (title, year, a short summary) and their authors (a name) |
| **Who may** do what? | anyone reads; only members who signed up change anything |
| **What is not** in this version? | no loans yet (that is [Lesson 6](Lesson-6-Change-It)) |

Compare:

| Too vague | Good |
|---|---|
| "An app for books." | "Bookshelf: a catalog of books and their authors for a book club. Anyone can browse the books, filter them by author and open a page per book. Members who signed up add, edit and delete books and authors. A book has a title, an author, a year and a short summary; an author has a name. No loans yet." |

The vague one leaves Claude to guess who uses it, what a book is and who may change it. The good one is still only
four sentences: describe what people do, not how to build it.

---

## Your description of Bookshelf

Write it in a file, so that you can reuse and improve it. Start from the good one above and make it yours (another
field for a book, a club name...). Keep it small: one or two things people remember, a handful of actions. A small
app that works beats a big one that does not.

You will give it to Claude in [Lesson 3](Lesson-3-Approve-The-Plan).

---

## Or start from a proven prompt

The [marketplace of caligrafy.com](https://caligrafy.com/marketplace) shows apps built with Caligrafy, live. For
each, you can buy the **prompt** that recreates it: a description written and tested for Caligrafy. It is longer than
yours, and in the same order:

1. the idea in one sentence;
2. what the app can do, one line each, and who may do it;
3. what it remembers;
4. its pages;
5. the services it needs (AI, payments, email), without any key;
6. how it goes online.

To use one, copy it from **My prompts** on caligrafy.com and give it to Claude instead of your own sentence:
```
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

**Next:** [Lesson 3 - Approve the plan](Lesson-3-Approve-The-Plan)
