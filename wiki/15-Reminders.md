# Lesson 15 - Reminders: a job and notifications

**In this lesson** Bookshelf works on its own: every morning at 8 it looks for the books due today and the late ones,
and tells the administrators, on their phone or computer even with Bookshelf closed, and by email. You write one
capability, schedule it, turn on notifications, and add a **Turn on reminders** button to the loans page.

> **With Claude:** "every morning at 8, notify the administrators of the loans due today or late, by push and email,
> with a Turn on reminders button on the loans page" - the `new-capability` skill writes the same.

**Before you start:** lesson 14 is done (tag `lesson-14`), the containers run (`docker compose ps`), and you can sign in
as `reader1`, an administrator since lesson 11. Caligrafy Quill 2.19.0 or later (`php caligrafer.php version`).

**Time:** 40 minutes.

---

## New concepts

**A scheduled job** runs a capability on a timer, with no page open. You name the capability, its inputs and its rhythm
in one file, `application/config/schedule.php`. Nobody is signed in when it runs: the capability finds the people it
works for itself (here, every user with `permissions` 1 or more).

**A notification** reaches someone who has no page open: `Caligrafy\Notify::user($userId, $message)` sends it on every
channel that person turned on: **web push** (a browser or phone that allowed it) and **email** (a confirmed address).

| | Who starts it | Who is signed in | Where it shows |
|---|---|---|---|
| A page | someone opening it | that person | the page |
| A scheduled job | the clock | nobody | whatever the capability does |
| A notification | your code (often a job) | not needed | the device, the inbox |

---

## Manual track

### Step 1 - The capability

It is a capability like the others: written once, it also works from REST, the JS client and the command line.

**File `application/services/Loans/RemindLoans.php`**

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Notify;
use Caligrafy\Service;

// remind_loans: tells the administrators which books are due today or late; a scheduled job runs it every morning
#[Capability(
    name: 'remind_loans',
    description: 'Notifies every administrator of the loans due today or late, by push and email. Returns how many loans are due today, how many are late, and how many administrators got the message.',
    scope: 'loans:write',
    level: 1
)]
class RemindLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        $today = date('Y-m-d');
        $due = Loan::whereNull('returned_at')->where('due_on', $today)->count();         // SELECT COUNT(*)
        $late = Loan::whereNull('returned_at')->where('due_on', '<', $today)->count();
        if ($due + $late === 0) {
            return array('due' => 0, 'late' => 0, 'notified' => 0);
        }
        $books = $due + $late;
        $message = array(
            'title' => $books === 1 ? '1 book to bring back' : "$books books to bring back",
            'body' => "$due due today, $late late. Open the loans to see who has them.",
            'url' => '/loans',
            'tag' => 'loans-due'
        );
        $notified = 0;
        foreach (Database::table('users')->where('permissions', '>=', 1)->pluck('id') as $admin) {
            $sent = Notify::user($admin, $message);
            if ($sent['push'] > 0 || $sent['email']) {
                $notified++;
            }
        }
        return array('due' => $due, 'late' => $late, 'notified' => $notified);
    }
}
```

- `level: 1`: only administrators may call it from a page or with their token. The job calls it with full access.
- `count()` asks MySQL for the number (`SELECT COUNT(*)`) instead of reading the loans: the query builder of lesson 04.
- `Database::table('users')` works on a table that has no model of yours; `pluck('id')` keeps one column: the ids
  of the administrators.

### Step 2 - The schedule

**File `application/config/schedule.php`**

```php
<?php
// Scheduled jobs: capabilities on a timer, with no page open (php caligrafer.php schedule list)
return array(
    // every morning at 8, New York time: the administrators hear about the books due today and the late ones
    array('name' => 'remind-loans', 'capability' => 'remind_loans', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York'),
);
```

Check it, then run it once, now:

```bash
docker compose exec dev-box php caligrafer.php schedule list
docker compose exec dev-box php caligrafer.php schedule run remind-loans
docker compose exec dev-box php caligrafer.php schedule history
```

Expected: the list shows `remind-loans  0 8 * * *  remind_loans  America/New_York, timeout 55s`, the run prints
`remind-loans: ok`, and the history has the run. Nobody is notified yet: nobody turned notifications on.

### Step 3 - Notifications

The tables, then the web push keys (once: new keys would cut off every device that turned them on):

```bash
docker compose exec dev-box php caligrafer.php platform install
docker compose exec dev-box php caligrafer.php notify keys --subject http://localhost:8080
```

The pages the emails link to (confirm the address, unsubscribe) are already there: quickstart added them in lesson 01,
under `Route::mcp('/mcp');` in `application/config/routing/web.php`. Check that the line is still in your file:

```php
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)
```

### Step 4 - The button

In `application/views/Loans/index.pug`, above `p#error`, add:

```pug
            p#reminders(hidden)
                button#reminders-on.btn.btn-secondary(type='button') Turn on reminders on this device
```

and in its `block additional_scripts`, before `loans.js`, load the notifications helper:

```pug
    script(src="{$home}public/js/notify.js")
```

At the end of `public/js/loans.js`, add:

```js
// Administrators: the morning reminders on this device too, even with the page closed (notify.js, Notify.enable)
if (allowed.has('remind_loans')) {
  const reminders = document.getElementById('reminders')
  const button = document.getElementById('reminders-on')
  reminders.hidden = (await Notify.status()) === 'on'
  button.addEventListener('click', async () => {
    try {
      const status = await Notify.enable()   // asks the browser's permission: only from a click
      reminders.hidden = status === 'on'
      if (status === 'install-needed') errorBox.textContent = 'On iPhone and iPad, add Bookshelf to your Home Screen first.'
      if (status === 'denied') errorBox.textContent = 'Notifications are blocked in this browser\'s settings.'
    } catch (e) {
      showError(e)
    }
  })
}
```

### Step 5 - Try it

1. Sign in as `reader1`, open http://localhost:8080/loans and click **Turn on reminders on this device**; allow
   notifications when the browser asks.
2. Lend a book (**Lend**), then make it due today: in phpMyAdmin (http://localhost:8077), table `loans`, set its
   `due_on` to today's date.
3. Run the job now instead of waiting for 8:00:
   ```bash
   docker compose exec dev-box php caligrafer.php schedule run remind-loans
   ```
   A notification **1 book to bring back** appears, even if you close the Bookshelf tab first. Click it: the loans page
   opens.
4. Without a loan: `docker compose exec dev-box php caligrafer.php notify test 1` sends a test to user 1, `reader1`.

Email works too once `MAIL_*` is set in `.env` and the person confirmed an address: `Notify.email('you@example.com')`
in the browser's console sends the confirmation link.

### Step 6 - Every morning, on its own

On your computer, `php caligrafer.php server start` (or `/caligrafy-quill:server start`) starts a fourth container,
`scheduler`: it runs the jobs every minute while it is up. Online, Caligrafy Cloud runs them and makes the push keys:

```bash
php caligrafer.php cloud deploy bookshelf-sam
php caligrafer.php cloud jobs bookshelf-sam
```

Expected: `remind-loans  0 8 * * *  America/New_York  last not run yet  next <tomorrow 8:00>`.

---

## With Claude

```
/caligrafy-quill:new-capability remind_loans loans:write
```
Then: "Every morning at 8 New York time, notify the administrators of the loans due today or late, by push and email.
Add a Turn on reminders button to the loans page for administrators."

**What Claude should do:** the capability with `level: 1`, the counts with the query builder (`count()`, no SQL
text) and `Notify::user`, the schedule file, `platform install`,
`notify keys`, `Route::notifications()`, the button with `notify.js`, then `schedule run remind-loans` with `ok`.

**Check** before you accept:
- [ ] The capability works for every administrator, not for "the signed-in user" (a job has nobody signed in).
- [ ] `schedule list` shows the job; `schedule run remind-loans` prints `ok`.
- [ ] The button asks the browser's permission only when clicked.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 15: reminders"
git tag lesson-15
git push origin main --tags
```
Reference: [`checkpoints/lesson-15`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-15).

## Exercise

A reader gets a reminder too: add a `borrower_email` to a loan, and on the due date send that reader an email with
`Notify::user()` (hint: readers need an account and a confirmed address; or start with the administrators' message
listing each borrower).

[Back to the course](Home)
