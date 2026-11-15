# Lesson 17 - The assistant: every capability as a card

**In this lesson** Bookshelf gets an assistant. One line of `web.php`, **`Route::snap()`**, turns every capability into a
**Snap Card** a person can use, and gives every signed-in person a Home that shows what needs them, and a conversation
that answers with cards. You open it, you give the cards better names and Home its numbers in one file of hints, you
make the reading report run in the background, and, if you want, you let Claude understand free text.

**Before you start:** lesson 16 is done (tag `lesson-16`), `docker compose ps` shows the containers running, you can
sign in as `reader1` (an administrator since lesson 11), and Kindred and Invisible Cities are lent (lesson 13).
`docker compose exec dev-box php caligrafer.php version` prints `Caligrafy Quill 2.26.0` or later: if it prints an older
version, run `docker compose exec dev-box php caligrafer.php update 2.26.0` first.

**Time:** 60 minutes.

---

## New concepts

**A Snap Card** is a capability as a person uses it: the form of its inputs, the confirmation of a change, the list or
the record it answers. Nothing is designed by hand: each card is built from what the capability already declares (its
`$rules`, its `$descriptions`, `readOnly`, `destructive`), and its data is shown in your design system. A **card view**
(`application/views/snap/<capability>.pug`) makes a card look exactly like your app: your page's own markup, at the
size of a card. Cards come in five families and nine types. You always name a
card by its family and its type: an **Ask card: Confirm**, a **Show card: List**.

| Family | Type | What it does | Bookshelf |
|---|---|---|---|
| **Ask** | Form | asks for the inputs still missing | Add a book |
| **Ask** | Choice | one pick | which book to return |
| **Ask** | Confirm | what will change, before it changes | lend *Parable of the Sower* to Sam |
| **Show** | Answer | one record, a value, a message | a book |
| **Show** | List | rows that open | the catalog |
| **Go** | Shortcut | a card a person saved, one click away | "My catalog" |
| **Track** | Task | work running in the background | the reading report |
| **Offer** | Suggestion | the moment they usually do something, or an alert | 2 books overdue |
| **Offer** | Routine | do it for them on a rhythm | check the loans every Monday |

**A change only ever runs from its Confirm.** The Ask card: Confirm carries a one-time token tied to the person, the
capability and these exact inputs. The assistant never sees it and never changes anything itself: it can only show the
card. A change that can be undone (an update, a creation) offers **Undo** right after.

**Home and the task space.** `/assistant` is one page with two modes:
- **Home** decides what the person should look at first: what needs them (an alert, a task waiting for an answer), the
  numbers that call for an action, things to browse, what was just done, their shortcuts. The assistant says it in at
  most two lines.
- **The task space** is the conversation: the assistant's line on the page, the cards under it, and what to do next
  as suggestions. It keeps the context: "it", "this one", "him" mean what is on screen.

**Hints** make cards better: a title, the columns of a list, the line the assistant says when it is done, the numbers
of Home. They live in one file, `application/config/snap.php`. Cards work without it.

**`async: true`** in `#[Capability(...)]` makes a capability run in the background when someone asks for it through
Snap: the person gets an Track card: Task at once, the queue runs the work as that person, and the card shows where it
is. REST, the agents and the pages still call it directly.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - One line, and its tables

In `application/config/routing/web.php`, after the line `Route::action('/loans/{book_id}/renew', ...)`, add:

```php
    // Snap Cards and the assistant: every capability as a card, and an assistant that answers with them
    Route::snap();
```

Then its tables and a starter hints file:

```bash
docker compose exec dev-box php caligrafer.php snap install
```
Expected:
```text
 Snap's tables are ready (caligrafy_snap_*)
 Wrote application/config/snap.php (optional card hints)

 Snap Cards and the assistant
  [ok] Tables            caligrafy_snap_*
  [ok] Route             Route::snap(); in application/config/routing/web.php
  [ok] Card hints        application/config/snap.php (optional: cards work without it)
  [ok] Background tasks  the queue runs them: ...
  [--] Routines          array('name' => 'snap-routines', 'capability' => 'snap_routines_due', ...)
  [--] Assistant model   ANTHROPIC_API_KEY in .env (without it the assistant answers from the cards' names)
```
*Background tasks* is `[ok]` because lesson 15 put `queue_work` on the timer. The two `[--]` come later in this lesson.

`Route::snap()` declared six addresses:

| Address | What it is |
|---|---|
| `/assistant` | Home and the task space (signed-in people) |
| `/assistant/ask` | what the person typed, said, attached or clicked |
| `/assistant/noticed` | what the assistant noticed, and their routines |
| `/snap` | every card the person may use; `/snap/c/<capability>` one card at its own address |
| `/snap/run`, `/snap/call/...` | a card sent, and the `snap_*` capabilities the pages call |

**Check**

- [ ] Signed out, `http://localhost:8080/assistant` sends you to sign in; sign in as reader1 and you are back on it.
- [ ] Home greets you (`Good afternoon, Reader1`) and, with Kindred and Invisible Cities still out, the first line says
      what needs you.
- [ ] `http://localhost:8080/snap` shows one card per capability you may use.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Snap is almost ready` | The tables are missing | `docker compose exec dev-box php caligrafer.php snap install` |
> | `404` on `/assistant` | `Route::snap();` is not in `web.php`, or is below `// MUST NOT BE REMOVED` | Put it with the other routes |
> | `Unknown command: snap` | The project is older than 2.23.0 | `docker compose exec dev-box php caligrafer.php update 2.26.0` |

### Step 2 - Use it

On Home, click **What else can I do?**: an Go card: Shortcut (the menu) lists every card, *See* first, *Do* after.
Then try a card from start to end:

1. Type `add a book` and press Enter (or ⌥K / Alt+K first, to reach the input). The assistant says
   `To add a book, I just need title, published year and author.` and an Ask card: Form shows only those three fields.
   **Add summary** and **Add ISBN** are suggestions above it: the optional details, offered, not imposed.
2. Fill in `Dune`, `1965`, choose any author, **Add the book**: an Ask card: Confirm says what will change. Nothing is
   added yet.
3. **Yes, add the book**: `Done! Dune is added.` with confetti, and **Undo** on the card.
4. **Undo**: it is a change too, so it asks first (an Ask card: Confirm to delete *Dune*). Confirm it.

Each click gets a line from the assistant, and the suggestions always say what you may do next (never a dead end:
**Back to Home** and **What else can I do?** are always there).

**Check**

- [ ] The Form showed only what was missing, and the line named the card (`To add a book, ...`).
- [ ] Nothing changed before **Yes, add the book**; `docker compose exec dev-box php caligrafer.php audit --limit 3`
      shows `create_book` then `delete_book`, by reader1.
- [ ] A book's card offers **Lend a book** or **Record a return**: both, for now. Step 3 fixes that.

**How a card looks.** Type `the catalog`: its rows are designed from the data, in Caligrafy's look, and every card
works this way without anything to write. Make the catalog look exactly like your `/books` list with a **card view**:
a small Pug view made for the card, in your page's own markup.

**File `application/views/snap/list_books.pug`**

```pug
//- snap card of list_books, from Bookshelf/books.pug 9b3c7ec6
//- The catalog as a card: the rows of /books, each book opening its own card; the form to add a book stays on the page
ul.cq-rows
    each $b in $rows
        li.cq-row
            .cq-row-main
                p.cq-row-title
                    a(href="{$home}books/{$b['id']}", data-snap-open='get_book', data-snap-input=json_encode(array('id' => $b['id'])))= $b['title']
                p.cq-row-meta= $b['author']['name'] . ', ' . $b['published_year']
```

It is the list of `books.pug`, nothing else: no title band, no form (a card that changes things has its own actions).
`$rows` are the books the capability returned; `data-snap-open` opens a book's card in the conversation. The first line
names the page it comes from and that page's fingerprint: `9b3c7ec6` for the `books.pug` of this course. If you
changed the page, print yours:

```bash
docker compose exec dev-box sh -c "shasum application/views/Bookshelf/books.pug | cut -c1-8"
```

Then check it:

```bash
docker compose exec dev-box php caligrafer.php snap views
```

```text
  [ok] list_books                 from Bookshelf/books.pug
  [--] get_book                   no card view: a card designed from its data
```

`/caligrafy-quill:assistant` writes card views for every main list and record the same way; when `books.pug` changes,
`snap views` shows `[!!]`: write the card view again.

- [ ] `the catalog` shows your rows, sized as a card; clicking a book opens its card; `/books` itself has not changed.

### Step 3 - Hints: one file, better cards

`snap install` wrote a starter `application/config/snap.php`, all commented out. Replace it with the hints of Bookshelf.

**File `application/config/snap.php`**: copy it from the checkpoint,
[`checkpoints/lesson-17/application/config/snap.php`](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/lesson-17/application/config/snap.php).
What its keys do:

| Key | In Bookshelf | What it does |
|---|---|---|
| `about` | `a library app: ...` | what the assistant is the assistant of |
| `title`, `columns`, `empty` | `list_books`: *The catalog*, title, author, year | the card's name, a list's columns, what an empty list says |
| `done` | `lend_book`: `Done! {heading} is lent. Enjoy the read.` | the assistant's line once done |
| `applies` | `lend_book`: `not_in` the loans; `return_book`: `in` the loans | when an action can be done to a record |
| `undo` | `lend_book`: `return_book` | what undoes it (Undo the loan) |
| `alert` | `list_loans`: when `overdue` | an Offer card: Suggestion on Home, tagged *Overdue*, with **Send reminders** |
| `insight` | `list_books`: New this month; `list_loans`: Out now | a number on Home, only with the action it calls for |
| `discover` | `list_books` | the books Home invites to browse: more by an author they looked at, new, on the shelf |
| `empty_next` | `list_loans`: `lend_book` | an empty list suggests what would fill it |
| `working` | `reading_report` | what a background task says while it runs (step 4) |

Every card already worked in step 2. A hint only changes how one reads.

**Check**

- [ ] Home now shows **At a glance**: `New this month` with **Browse the catalog**, `Out now` with a ring and
      **Record a return**; and **Needs you**: an Overdue banner with **Send reminders** if a loan is late.
- [ ] **On the shelf for you** shows books that are not lent, each tagged with why it is there (*New*, *On the shelf*).
- [ ] Open *Parable of the Sower* (it is on the shelf): it offers **Lend a book**, not **Record a return**. Open
      *Kindred* (it is out): the other way round.
- [ ] Ask `books that are out`, return them all on the loans page, ask again: `Every book is on the shelf.` and the
      suggestion **Lend a book**.

### Step 4 - Work in the background

A report that takes a while must not keep a page waiting. Create it:
```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/ReadingReport --scope books:read --read-only
```

**File `application/services/Bookshelf/ReadingReport.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;

// reading_report: a read that takes a while: async, so Snap runs it in the background as a task (Track: Task card)
#[Capability(
    name: 'reading_report',
    description: 'Builds the reading report of the Bookshelf: for each author, how many books and how many are out. Takes a little while.',
    scope: 'books:read',
    readOnly: true,
    async: true
)]
class ReadingReport extends Service
{
    public $rules = array('pause' => 'integer|min_numeric,0|max_numeric,30');
    public $descriptions = array('pause' => 'Optional: seconds to take, to show a slow report (8 by default)');

    public function handle(array $input, Context $context)
    {
        sleep((int) ($input['pause'] ?? 8));   // the slow part of a real report
        $books = Database::table('books')->select('id', 'author_id')->get();
        $out = array_flip(array_map('intval', Database::table('loans')->whereNull('returned_at')->pluck('book_id')));
        $report = array();
        foreach (Database::table('authors')->select('id', 'name')->orderBy('name')->get() as $author) {
            $author = (array) $author;
            $own = array_filter($books, function ($b) use ($author) { return (int) ((array) $b)['author_id'] === (int) $author['id']; });
            $report[] = array('name' => $author['name'], 'books' => count($own),
                'out_now' => count(array_filter($own, function ($b) use ($out) { return isset($out[(int) ((array) $b)['id']]); })));
        }
        return $report;
    }
}
```

The reminders of lesson 15 are work in the background too. In `application/services/Loans/RemindLoans.php`, add
`async: true` to its `#[Capability(...)]`:

```php
#[Capability(
    name: 'remind_loans',
    description: 'Notifies every administrator of the loans due today or late, by push and email. Returns how many loans are due today, how many are late, and how many administrators got the message.',
    scope: 'loans:write',
    level: 1,
    async: true
)]
```
The morning job of lesson 15 still calls it directly: `async` only changes what Snap does.

Then, in `application/config/schedule.php`, add the job that runs the routines people set up (step 6):

```php
    // every minute: the routines people set up with the assistant (lesson 17) that are due, each run as its owner
    array('name' => 'snap-routines', 'capability' => 'snap_routines_due', 'every' => '1m', 'timeout' => 50),
```

**Check**

- [ ] Ask `reading report`: a Track card: Task at once, `Waiting to start`, its time counting, then
      `Counting the books of each author…`; within a minute `Done! Reading report is ready.` and the report, one row per
      author. Back on Home, **Just done** shows it.
- [ ] `docker compose exec dev-box php caligrafer.php snap` shows `[ok] Routines`.
- [ ] `docker compose exec dev-box php caligrafer.php audit --limit 5` shows `reading_report` by reader1: the queue ran it
      as the person who asked, not with its own access.

### Step 5 - Claude, for free text (optional)

Without a model, the assistant answers the names of your cards (`the catalog`, `lend a book`), your shortcuts, `What can
I do?`, and short follow-ups on what is on screen (`delete it`). With one, it understands anything.

1. Add the Claude library to the project, once (since 2.26.0 it is not in every project; commit `composer.json` and
   `composer.lock`): `docker compose exec dev-box php caligrafer.php add snap`.
2. Make a key at **console.anthropic.com**, *API keys*, *Create key*. It is a secret: it costs money when used.
3. Open `.env` in your editor, find the line `ANTHROPIC_API_KEY=` and paste the key right after the `=`. Save. Never
   print it in a terminal and never commit it (`.env` is in `.gitignore`).

Now ask, one after the other, in the same conversation:

| You type | The assistant |
|---|---|
| `who wrote Parable of the Sower?` | `Octavia E. Butler wrote Parable of the Sower.` and the book's card |
| `what else did she write?` | her other books, as a Show card: List |
| `lend it to Sam for a week` | an Ask card: Confirm: the book on screen, `Sam`, `Days: 7`. Nothing is lent until you click |
| `delete it` | at once, without the model: an Ask card: Confirm to delete it. Cancel |

It reads every request with what came before: the earlier turns, what is on your screen, what you clicked. It may look
things up with the read-only capabilities you may call, as you. A change always comes back as its Confirm. The **+**
attaches a picture, a PDF or a text file (a photo of a book's cover, a list of titles): Claude reads it and fills the
card. ⌥M / Alt+M talks instead of typing, where the browser can listen.

**Check**

- [ ] `docker compose exec dev-box php caligrafer.php snap` shows `[ok] Assistant model   claude-opus-5-5`.
- [ ] "it", "she" and "this one" meant what was on screen; the Confirm carried every detail you gave (7 days).

### Step 6 - What the assistant notices

Open **What I've noticed** (the link at the bottom of `/assistant`). Noticing is **off** until a person turns it on.
When on, the assistant keeps which card they used, with which inputs and when; nothing else. Something done on 4
different days or more, at a similar time, becomes a pattern: an Offer card: Suggestion at that moment on Home, then an
Offer card: Routine to do it for them every week or every day, at the level they choose (remind me, prepare it for me
to confirm, do it and tell me). The `snap-routines` job of step 4 runs the routines that do it for them.

**Check**

- [ ] Turn noticing on. Open a few books, then go back to Home: **On the shelf for you** now says
      `Because you looked at ...` on another book of the same author.
- [ ] Turn it off: the page says everything noticed is forgotten.

### Step 7 - Tests

Snap's capabilities are capabilities: you test them like yours. The tests need the Snap tables and two people, in a
database of their own. Create it once:

```bash
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS bookshelf_test"
for f in application/models/Auth/create_db.sql application/models/Bookshelf/create_db.sql application/models/Loans/create_db.sql application/migrations/*.sql framework/settings/platform.sql; do
  docker compose exec -T mysql mysql -uroot -proot bookshelf_test < "$f"
done
docker compose exec mysql mysql -uroot -proot bookshelf_test -e "INSERT IGNORE INTO users (id, username, passcode, permissions) VALUES (1, 'admin@test.example', '-', 1), (2, 'reader@test.example', '-', 0)"
```
and add `DB_TEST_DATABASE=bookshelf_test` to `.env`.

Copy the tests of the checkpoint into `tests/`: the nine `Snap*Test.php` files of
[`checkpoints/lesson-17/tests`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-17/tests).
They never call Claude: they fake it with `Caligrafy\Snap\Planner::$transport`, a function that answers what Claude
would:

```php
Caligrafy\Snap\Planner::$transport = function (array $request) {
    return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1',
        'name' => 'show_cards', 'input' => array('caption' => 'Here it is.', 'cards' => array(array('capability' => 'list_books'))))));
};
```

In `qa/bookshelf/smoke.sh`, above `# REST`, add:

```bash
# the assistant (lesson 17)
check 302 "assistant: visitors to sign in" "$BASE/assistant"
check 200 "every card, for a visitor"    "$BASE/snap"
check 404 "snap/call: snap_* only"       -X POST "${JSON[@]}" -d '{}' "$BASE/snap/call/list_books"
check 401 "snap/call: a visitor's call"  -X POST "${JSON[@]}" -d '{}' "$BASE/snap/call/snap_home"
```

```bash
docker compose exec dev-box php caligrafer.php test
bash qa/bookshelf/smoke.sh
```
Expected: `OK (70 tests, 408 assertions)` without a key (with `ANTHROPIC_API_KEY` set: `Tests: 70, Assertions: 406,
Skipped: 1`: the test of the answers without a model skips), and `All checks passed.`

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Table 'bookshelf_test.caligrafy_snap_...' doesn't exist` | `platform.sql` did not run on the test database | Run the loop above again |
> | A task test says `failed` instead of `done` | Users 1 and 2 are missing from the test database | Run the `INSERT IGNORE INTO users` line |
> | `404 snap/call` answers something else | `Route::snap();` is missing from `web.php` | Step 1 |

---

## Check

- [ ] `web.php` has `Route::snap();`, and `snap.php`, `ReadingReport.php`, `RemindLoans.php`, `schedule.php` and
      `smoke.sh` are identical to the checkpoint.
- [ ] `php caligrafer.php snap` shows `[ok]` on every line but, if you skipped step 5, *Assistant model*.
- [ ] A change ran only after its Confirm; Undo asked first too.
- [ ] Home shows Needs you, At a glance, On the shelf for you, Just done; a book offers only what can be done to it.
- [ ] The reading report ran in the background and ended `Done! Reading report is ready.`
- [ ] The tests and the smoke test pass.

## With Claude

```text
/caligrafy-quill:assistant
```
Claude adds `Route::snap();`, runs `snap install`, writes the hints of `application/config/snap.php` for your
capabilities, marks slow capabilities `async: true`, adds the `snap-routines` job, and asks you for a key if you want
free text.

**Check** before you accept:
- [ ] `web.php` has one new line, `Route::snap();`, and no route of its own for the assistant.
- [ ] No capability was made `webOnly` for Snap, and none changes data without its Confirm.
- [ ] The key is in `.env`, not in a file you commit.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 17: the assistant, every capability as a card"
git tag lesson-17
git push origin main --tags
```
Reference: [`checkpoints/lesson-17`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-17).
Learn more: [Snap Cards and the Assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant).

## Exercise

Readers ask the same thing every Monday morning: which books are out. Make Home show it to them without asking.

With Claude, ask: *"Give list_loans a Monday nudge: when the loans are checked on 4 Mondays in a row, Snap offers a
routine. Turn noticing on for my account and explain what I will see."*

<details><summary>Show the solution</summary>

Nothing to write: noticing is Snap's own. Turn it on in **What I've noticed**, then check the loans (`books that are
out`) on Monday mornings. On the 4th Monday, around the same hour, Home's **For you** shows an Offer card: Suggestion,
`It is Monday morning: open books that are out?`, with why (`You did this 4 Mondays in a row, around 9 am.`); **Yes**
opens them. Then an Offer card: Routine asks `Do this for you every Monday around 9 am?`, at the level you pick, and an
Ask card: Confirm sets it up: setting up a routine is a change, so it asks first. **What I've noticed** lists it, with
**Stop**.

To see it now instead of in four weeks, the tests do it: `SnapOffersTest` records the same request on four Mondays and
checks the offer and the routine.
</details>

## Quiz

1. Name the card that appears when you click **Lend** with a book and a borrower, by its family and its type.
2. The assistant is asked "delete every book". What can happen without your click?
3. Why is `reading_report` `async: true`, and what do REST and the agents get when they call it?
4. `lend_book` has the hint `applies: not_in list_loans`. What does a person see on the card of a book that is out?

<details><summary>Answers</summary>

1. An Ask card: Confirm: what will change, before it changes.
2. Nothing: the assistant can only show cards. A change comes back as its Ask card: Confirm, one per book, and only
   your click runs it, with a token tied to you, that capability and those inputs.
3. It takes a while, so Snap runs it in the queue and answers at once with a Track card: Task. REST, the agents and
   the pages still call it directly and get the report: `async` only changes what Snap does.
4. No **Lend a book**: it offers **Record a return** instead. A card only offers what can be done to that record now.
</details>

---
Previous: [Lesson 16 - What a page or a form cannot say](16-Every-Request-Declared) · Next: [Appendix A - Routes and controllers](A-Routes-and-Controllers)
