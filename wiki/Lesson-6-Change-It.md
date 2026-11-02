# Lesson 6 - Change it, ship it again

**You will** add a feature to Bookshelf with the same three steps: describe the change, approve it, ship it. Then you
will give every page another look. **Time:** 45 minutes.

---

## Describe the change

The book club now lends its books. In Claude, in the `bookshelf` folder:
```
Members can lend a book to someone: record who borrowed it and when, and mark it returned.
A book that is on loan shows it, on its page and in the list. Show me the plan first.
```
"Show me the plan first" matters: for a change, as for the first version, nothing should be built before your OK.

## Approve it

Read the plan with the questions of [Lesson 3](Lesson-3-Approve-The-Plan):

- **Tables**: a `loans` table linked to `books`, with the borrower, the date lent and the date returned.
- **Capabilities**: lend a book, return it, list the loans; lending a book that is already on loan is refused.
- **Who may do what**: members lend and return; visitors only see that a book is on loan.

Change what is wrong, then:
```
OK. Build it and check it here.
```
Claude writes the new feature **once**, as capabilities, and every experience gets it at once: the page, REST and AI
agents. Review it as in [Lesson 4](Lesson-4-Review-It): lend a book on the page, see it as on loan over REST, and ask
your AI agent "Which books are on loan?". Try to lend the same book twice: every experience refuses it with the same
message.

## Ship it again

```
/caligrafy-quill:ship bookshelf-<you>
```
This time there is nothing to create: Claude commits, pushes and deploys. The new table is added online, and your
books and accounts stay.

---

## Another look

Caligrafy has its own look, and 85 design styles you can switch to with one setting. In Claude:
```
/caligrafy-quill:design
```
Claude asks which style you want and can show you the list. Pick one (`/caligrafy-quill:design <style>`): Claude
restyles every page and checks it at desktop and phone width. Did you not like it? `/caligrafy-quill:design off`
brings the Caligrafy look back. Ship it again to see it online.

---

## Check

- [ ] The plan for loans was shown before anything was built, and you approved it.
- [ ] A book on loan shows it on the page, over REST and to an AI agent.
- [ ] Lending a book already on loan is refused everywhere, with the same message.
- [ ] Online, after shipping again, your earlier books and accounts are still there.

**By hand:** [13 Capstone: Loans](13-Capstone-Loans) builds the same feature yourself.

**Next:** [Lesson 7 - Sell it](Lesson-7-Sell-It)
