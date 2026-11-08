# Lesson 6 - Change it, ship it again

**You will** add a feature to Bookshelf with the same three steps: describe the change, approve it, ship it. Then you
will give every page another look. **Time:** 45 minutes.

---

## Describe the change

The book club now lends its books. In Claude, in the `bookshelf` folder:
```text
Members can lend a book to someone: record who borrowed it and when, and mark it returned.
A book that is on loan shows it, on its page and in the list. Show me the plan first.
```
"Show me the plan first" matters: for a change, as for the first version, nothing should be built before your OK.
Claude uses the same tools as for the first version: `/caligrafy-quill:new-resource` for a new table with its
capabilities, `/caligrafy-quill:new-capability` for each new action. You can also call them yourself, for example
`/caligrafy-quill:new-capability lend_book loans:write`.

## Approve it

Read the plan with the questions of [Lesson 3](Lesson-3-Approve-The-Plan):

- **Tables**: a `loans` table linked to `books` (`book_id`), with the borrower, the date lent and the date returned.
- **How the tables change**: the new `loans` table has no data yet, so it can come in its own script
  (`application/models/Loans/create_db.sql`) or in a migration. But Bookshelf is online and `books` holds data: a
  change to it (a new column, for example) comes as a **migration**, a numbered file in `application/migrations/`
  that runs once, on your computer and at the next deploy. Claude never edits a `create_db.sql` that already ran.
- **Capabilities**: lend a book, return it, list the loans; lending a book that is already on loan is refused (a
  *conflict*).
- **Who may do what**: members lend and return; visitors only see that a book is on loan.

Change what is wrong, then:
```text
OK. Build it and check it here.
```
Claude writes the new feature **once**, as capabilities, adds its REST lines, creates the table (and runs any
migration), generates the JS client again and adds tests. Every experience gets it at once: the page, REST and AI agents.

Review it as in [Lesson 4](Lesson-4-Review-It), with three more things to look for:

| File | Look for |
|---|---|
| `application/models/Loans/create_db.sql`, or `application/migrations/<number>_<name>.sql` | the new table with `CREATE TABLE IF NOT EXISTS` and its `FOREIGN KEY` to `books`; in a migration, only the change (`ALTER TABLE books ADD COLUMN ...`); nothing that drops or deletes |
| `application/services/Loans/LendBook.php` (or the name Claude chose) | `$rules` for the book and the borrower; a refusal with `ServiceError::conflict(...)` when the book is on loan; the scope `loans:write` |
| the list of books | the loan comes with each book from the query builder (`with(...)`), not from one query per book |

When Claude wrote a migration, ask it to show `php caligrafer.php migrate status`: each one says `ran`.

Then try it: lend a book on the page, see it as on loan over REST, and ask your AI agent "Which books are on loan?".
Create a **new token** for the agent first: a token keeps the scopes it was created with, so an older one cannot lend.
Try to lend the same book twice: every experience refuses it with the same message.

## Ship it again

```text
/caligrafy-quill:ship bookshelf-<you>
```
This time there is nothing to create: Claude commits, pushes and deploys. The deploy creates the new table and runs
any new migration once, online, and your books and accounts stay.

---

## Another look

Caligrafy has its own look, and 85 design styles you can switch to with one setting. In Claude:
```text
/caligrafy-quill:design
```
Claude asks which style you want, with a few suggestions, and can show you the whole list. Pick one
(`/caligrafy-quill:design <style>`). Claude:

- reads the style's `DESIGN.md` (its colors, fonts, corners and its do's and don'ts);
- writes **one** file, `public/css/designs/<style>.css`, loaded after Caligrafy's own stylesheets. It changes no page
  and no Caligrafy file;
- sets `DESIGN_STYLE=<style>` in `.env` (`php caligrafer.php design use <style>`);
- checks every page at desktop and phone width, signed in and out, and shows you screenshots.

Did you not like it? `/caligrafy-quill:design off` brings the Caligrafy look back at once.

To see it online, `.env` does not go to GitHub: ask Claude to set the style on Caligrafy Cloud
(`php caligrafer.php cloud secret set bookshelf-<you> DESIGN_STYLE=<style>`: a setting, not a secret), then ship it
again. Settings apply at the next deploy.

---

## Check

- [ ] The plan for loans was shown before anything was built, and you approved it.
- [ ] No `create_db.sql` that already ran was edited; a change to a table with data came as a migration, and
      `migrate status` shows it `ran`.
- [ ] A book on loan shows it on the page, over REST and to an AI agent.
- [ ] Lending a book already on loan is refused everywhere, with the same message.
- [ ] Online, after shipping again, your earlier books and accounts are still there.
- [ ] The new look is one file in `public/css/designs/`, and `design off` brings the Caligrafy look back.

**By hand:** [13 Capstone: Loans](13-Capstone-Loans) builds the same feature yourself. The
framework pages: [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations),
[Design Styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles).

**Next:** [Lesson 7 - Sell it](Lesson-7-Sell-It)
