# Lesson 15 - Reminders: a job and notifications

**In this lesson** Bookshelf works on its own: every morning at 8 it looks for the books due today and the late ones,
and tells the administrators on their phone or computer, even with Bookshelf closed, and by email. You write one
capability, put it on a timer in `application/config/schedule.php`, turn on notifications, and give the loans page two
buttons: **Turn on reminders on this device**, and **Send the reminders now**, which hands the work to the **queue**.

**Before you start:** lesson 14 is done (tag `lesson-14`), `docker compose ps` shows the 3 containers running, and you
can sign in as `reader1`, an administrator since lesson 11. Kindred and Invisible Cities are lent (lesson 13).

**Time:** 45 minutes.

---

## New concepts

**A scheduled job** runs a capability on a timer, with no page open. One file, `application/config/schedule.php`,
names each job, its capability, its inputs and its rhythm: `every` (`1m`, `5m`, `10m`, `15m`, `30m`, `1h`, `6h`,
`12h`, `1d`, always UTC) or `cron` (five fields: minute, hour, day of the month, month, day of the week) with a
`timezone`. Nobody is signed in when a job runs: it calls the capability with full access, and the audit log records it
as `schedule <job name>`. The capability finds the people it works for itself.

**A notification** reaches someone who has no page open: `Caligrafy\Notify::user($userId, $message)` sends it on every
channel that person turned on:
- **web push**: a browser or an installed app that allowed it, through the push service of the browser. It needs a pair
  of keys (VAPID) in `.env`, made once by `php caligrafer.php notify keys`;
- **email**: an address the person confirmed, once `MAIL_*` is set in `.env`.

It refuses a message that is too long (`title` 80, `body` 300 characters) and sends at most `NOTIFY_RATE_PER_HOUR`
(30) messages per person and hour. `public/js/notify.js` turns push on in a page: `Notify.enable()` asks the browser's
permission, which a browser grants only after a click.

**The queue** runs work in the background: `Caligrafy\Queue::push('remind_loans')` records a **job** (a capability and
its inputs) and returns at once; a **worker** runs it a moment later, and tries again (3 times by default) when it
fails. A page stays fast, and a slow delivery never makes a visitor wait. The worker is the framework's capability
`queue_work`, put on the timer like any job.

| | Who starts it | Who is signed in | Where the result shows |
|---|---|---|---|
| A page | someone opening it | that person | the page |
| A job of the queue | your code (`Queue::push`) | nobody: full access | what the capability does |
| A scheduled job | the clock | nobody: full access | what the capability does |
| A notification | your code (often a job) | not needed | the device, the inbox |

**Who runs the clock.** On your computer, the `scheduler` container of `docker-compose.yaml` runs
`php caligrafer.php schedule work`: every minute, the jobs that are due. Online, Caligrafy Cloud reads the jobs from the
`caligrafy-schedule` line that the deploy step prints (lesson 14) and runs them.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The capabilities

```bash
docker compose exec dev-box php caligrafer.php service Loans/RemindLoans --scope loans:write
docker compose exec dev-box php caligrafer.php service Loans/SendReminders --scope loans:write
```
Replace their content:

**File `application/services/Loans/RemindLoans.php`** (counts the loans with the query builder, then notifies each administrator):

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
        // nobody is signed in when a job runs: the capability finds the people it works for itself
        $today = date('Y-m-d');
        $due = Loan::whereNull('returned_at')->where('due_on', $today)->count();
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
            $sent = Notify::user($admin, $message);   // every channel this person turned on: push, email
            if ($sent['push'] > 0 || $sent['email']) {
                $notified++;
            }
        }
        return array('due' => $due, 'late' => $late, 'notified' => $notified);
    }
}
```

**File `application/services/Loans/SendReminders.php`** (the button: the same work, in the queue):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Queue;
use Caligrafy\Service;

// send_reminders: the "Send the reminders now" button; remind_loans runs a moment later, in the queue
#[Capability(
    name: 'send_reminders',
    description: 'Sends the reminders of the loans due today or late to the administrators now, in the background (remind_loans in the queue). Returns the number of the job.',
    scope: 'loans:write',
    level: 1
)]
class SendReminders extends Service
{
    public function handle(array $input, Context $context)
    {
        // the page answers at once; the queue runs remind_loans, with 3 tries if a delivery fails
        return array('job' => Queue::push('remind_loans'));
    }
}
```

- `level: 1`: only administrators, and the jobs (full access), may call them.
- `count()` asks MySQL for the number (`SELECT COUNT(*)`): no row is read.
- `remind_loans` notifies every user with `permissions` 1 or more, never "the signed-in user": nobody is signed in when
  the clock runs it.
- `send_reminders` only pushes `remind_loans` to the queue and returns the number of the job.

### Step 2 - The schedule

**File `application/config/schedule.php`** (two jobs: the reminders every morning, the queue every minute):

```php
<?php
// Scheduled jobs: capabilities on a timer, with no page open (php caligrafer.php schedule list)
return array(
    // every morning at 8, New York time: the administrators hear about the books due today and the late ones
    array('name' => 'remind-loans', 'capability' => 'remind_loans', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York'),
    // every minute: the background jobs of the queue (Queue::push), such as the reminders of the button
    array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55),
);
```

```bash
docker compose exec dev-box php caligrafer.php schedule list
```
Expected:
```text
 remind-loans             0 8 * * *      remind_loans             America/New_York, timeout 55s
 queue                    every 1m       queue_work               UTC, timeout 55s
```
`schedule list --json` prints what a host reads (`{"version":1,"jobs":[...]}`).

### Step 3 - Notifications

The tables of the framework (the notification tables are among them; it never deletes anything), then the web push
keys, **once** (new keys would cut off every device that turned push on):
```bash
docker compose exec dev-box php caligrafer.php platform install
docker compose exec dev-box php caligrafer.php notify keys --subject mailto:you@example.com
```
Expected:
```text
 Framework tables ready: caligrafy_tokens, caligrafy_audit, caligrafy_job_runs, the notifications tables, caligrafy_jobs, caligrafy_cache and caligrafy_migrations

 Web push keys written to .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT=mailto:you@example.com). Keep them: new keys cut off every subscribed browser
```
The subject is a contact for the push services: a `mailto:` address or an `https://` address. Run the command again:
`The web push keys are already in .env: kept ...`. The pages that notification emails link to (confirm an address,
unsubscribe) are already there: `Route::notifications();` in `web.php`, since lesson 01.

### Step 4 - The buttons

In `application/config/routing/web.php`, add the route of **Send the reminders now** in the `// LOANS` block, after
the return form. The file is then:

**File `application/config/routing/web.php`** (`Route::form('/loans/remind', 'send_reminders', ...)`):

```php
<?php
/**
 * web.php
 * Web Routes are defined in this file
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {
    
    // API ACCESS - add comments if you don't want to allow API access on routes
    Auth::activateAPI();
    
    
    // AUTHENTICATION - Remove comment if you have an authentication implemented
    Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::redirect('/', '/books');   // the home of Bookshelf is its catalog
    Route::redirect('/catalog', '/books', 301, ['keepQuery' => true]);   // the catalog's old address: the filter of its address goes along
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // SIGN IN, SIGN OUT: /login, /logout and /notAuthorized; after signing in, /login?return=<page>, else the catalog
    Route::auth(['home' => '/books', 'register' => false, 'messages' => ['invalid' => 'Invalid email or password']]);   // no /register: people join with the form of /join

    // User Administration Routes - Uncomment only if AUTHENTICATION activated above
	// Route::get('/admin', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@editUserForm');
	// Route::put('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@updateUser');
	// Route::delete('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@deleteUser');

    // GPT AI controller
    // Route::get('/ai', 'Caligrafy\\Controllers\\AIController');
    // Route::post('/ai', 'Caligrafy\\Controllers\\AIController@query');

    // ROUTING TO JAVASCRIPT APPS
    // Make sure you create a JS app or a Vue app in Caligrafer first
    Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);   // public/<appName>/index.php, or ?app=<file>

    
    // BOT PAGE ROUTES
    Route::get('/bot/{appName}', 'Caligrafy\\Controllers\\ClientController');  // A botSkillId needs to be provided in the URL. For Example: ?botSkillId=62196ce3-0b22-4aea-b2f2-d39730c03797
    
    // BOT DATA ROUTES
    // routes need to start with reserved path __bots__ 
    Route::post('/__bots__/{appName}/communicate', 'Caligrafy\\Controllers\\WatsonController@communicate');
    Route::post('/__bots__/{appName}/{appId}', 'Caligrafy\\Controllers\\WatsonController@connect');
    Route::delete('/__bots__/{appName}', 'Caligrafy\\Controllers\\WatsonController@end');   

    /* ML Routes
     *
     * If you don't specify an up, it will go to the face, body detector.
     * Specify an app as a URI Parameter ?app=<name of app>
     * 
     * There are 3 possible apps: featureextractor, neuralnetwork and posedetector
    */
    Route::get('/ml/{appName}', 'Caligrafy\\Controllers\\ClientController'); 
    

    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; each form calls one capability
    Route::page('/books', 'Bookshelf/books', ['query' => ['author_id'], 'data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'with' => ['install' => true]]);   // install: offer to add it to the home screen
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file
    Route::page('/people', 'Bookshelf/people', ['level' => 1, 'comeBack' => true, 'data' => ['people' => 'list_users']]);   // administrators only

    // JOIN THE CLUB: the only way to create an account; people already signed in go to the catalog
    Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
    Route::form('/join', 'register_user', [
        'visitorsOnly' => '/books',
        'same' => ['passcode_again' => ['passcode', 'The two passwords are not the same']],
        'accepted' => ['rules' => 'Accept the rules of the club to join'],
        'signsIn' => 'id',
        'success' => '/books',
        'flash' => 'Welcome to the club, {username}'
    ]);

    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');

    // LOANS: one more feature, every experience: a page and its forms, REST, the JS clients and AI agents
    Route::page('/loans', 'Loans/index', ['signedIn' => true, 'comeBack' => true, 'data' => ['loans' => 'list_loans', 'books' => 'list_books']]);
    Route::form('/loans', 'lend_book', ['success' => '/loans', 'flash' => 'Lent to {borrower} until {due_on}']);
    Route::form('/loans/{book_id}/return', 'return_book', ['success' => '/loans', 'flash' => 'Returned by {borrower}']);
    Route::form('/books/{book_id}/borrow', 'lend_book', ['inputs' => function () { return ['borrower' => Auth::user()->username ?? '', 'days' => 14]; }, 'success' => '/loans', 'flash' => 'It is yours until {due_on}']);   // the server says who borrows
    Route::form('/loans/remind', 'send_reminders', ['success' => '/loans', 'flash' => 'The reminders are on their way (job {job})']);   // administrators
    Route::capability('GET', '/api/loans', 'list_loans');
    Route::capability('POST', '/api/loans', 'lend_book');
    Route::capability('POST', '/api/books/{book_id}/return', 'return_book');

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

In `application/views/Loans/index.pug`, add the **Reminders** band at the end of `block content`, and a
`block additional_scripts` that loads `notify.js` for administrators. The file is then:

**File `application/views/Loans/index.pug`** (the Reminders band and its script, for administrators):

```pug
extends ../default/master/site

//- Loans: Route::page gives it $loans (list_loans: the books that are out) and $books (list_books: a page of books).
//- The lend form posts to Route::form('/loans', 'lend_book'), each Returned button to Route::form (return_book).
//- Administrators get the reminders: on this device (notify.js), and now (send_reminders, in the queue)

block title
    | Loans · Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display Loans
        p.cq-lead Who borrowed which book, and until when.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')

            form(method='POST', action="{$home}loans", novalidate)
                =csrf()
                .cq-form-grid.cq-form-grid--2
                    .cq-field
                        label(for='book_id') * Book
                        select#book_id.form-select(name='book_id')
                            each $b in $books['data']
                                option(value=$b['id'], selected=$b['id'] == old('book_id'))= $b['title']
                        if errors('book_id')
                            p.cq-field-error= errors('book_id')
                    .cq-field
                        label(for='borrower') * Borrower
                        input#borrower.form-control(type='text', name='borrower', value=old('borrower'), maxlength='254')
                        if errors('borrower')
                            p.cq-field-error= errors('borrower')
                    .cq-field
                        label(for='days') Days
                        input#days.form-control(type='number', name='days', value=old('days'), placeholder='14')
                        if errors('days')
                            p.cq-field-error= errors('days')
                button.btn.btn-primary(type='submit') Lend
            if flash('error')
                p.cq-field-error(role='alert')= flash('error')

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            h2.cq-heading Out now
            if count($loans) == 0
                p.cq-empty-state Every book is on the shelf.
            else
                ul.cq-rows
                    each $loan in $loans
                        li.cq-row
                            .cq-row-main
                                p.cq-row-title
                                    a(href="{$home}books/{$loan['book_id']}")= $loan['book']['title']
                                p.cq-row-meta
                                    | #{$loan['borrower']}, due #{$loan['due_on']}
                                    if $loan['overdue']
                                        strong  · late
                            .cq-row-actions
                                form(method='POST', action="{$home}loans/{$loan['book_id']}/return")
                                    =csrf()
                                    button.btn.btn-secondary(type='submit') Returned
            p.cq-auth-note
                a(href="{$home}books") All books

    //- remind_loans and send_reminders have level 1: only administrators see their buttons
    if $user['permissions'] >= 1
        section.cq-band.cq-band--tight
            .cq-inner.cq-inner--narrow
                h2.cq-heading Reminders
                p Every morning at 8, the administrators hear about the books due today and the late ones.
                p.cq-auth-note
                    button#reminders-on.btn.btn-secondary(type='button') Turn on reminders on this device
                p#reminders-status.cq-auth-note(role='status')
                form(method='POST', action="{$home}loans/remind")
                    =csrf()
                    button.btn.btn-secondary(type='submit') Send the reminders now

block additional_scripts
    if $user['permissions'] >= 1
        script(src="{$home}public/js/notify.js")
        script.
            // web push on this device: the browser asks its permission, only after a click
            (function () {
                var button = document.getElementById('reminders-on'), status = document.getElementById('reminders-status');
                var say = { on: 'Reminders are on, on this device.', denied: 'Notifications are blocked in the settings of this browser.',
                    'install-needed': 'On iPhone and iPad, add Bookshelf to the Home Screen first.', unsupported: 'This browser has no notifications.' };
                Notify.status().then(function (now) { if (now === 'on') { button.hidden = true; status.textContent = say.on; } });
                button.addEventListener('click', function () {
                    Notify.enable().then(function (now) {
                        button.hidden = now === 'on';
                        status.textContent = say[now] || '';
                    }).catch(function (e) { status.textContent = e.message; });
                });
            })();
```

Regenerate both clients (25 capabilities: `remindLoans` and `sendReminders` are new):
```bash
docker compose exec dev-box php caligrafer.php client
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```

### Step 5 - Try it

Make Kindred due today and Invisible Cities late (the password warning is harmless):
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "UPDATE loans SET due_on = CURDATE() WHERE book_id = 3 AND returned_at IS NULL; UPDATE loans SET due_on = CURDATE() - INTERVAL 2 DAY WHERE book_id = 5 AND returned_at IS NULL"
```
1. Sign in as reader1 and open http://localhost:8080/loans: Invisible Cities says `Sam, due <two days ago> · late`.
   Under **Reminders**, click **Turn on reminders on this device** and allow notifications when the browser asks: the
   line says `Reminders are on, on this device.`
2. Run the morning job now instead of waiting for 8:00:
   ```bash
   docker compose exec dev-box php caligrafer.php schedule run remind-loans
   ```
   Expected: ` remind-loans: ok`, and the notification **2 books to bring back**, `1 due today, 1 late. Open the loans
   to see who has them.`, even with the Bookshelf tab closed. Click it: the loans page opens (signed out, you sign in
   first and come back to it: `comeBack`, lesson 13).
3. `docker compose exec dev-box php caligrafer.php call remind_loans` shows what it did:
   `"due": 1, "late": 1, "notified": 1` (`0` when no device and no confirmed email is on).
4. Click **Send the reminders now**: `The reminders are on their way (job 1)`. The job waits in the queue:
   ```bash
   docker compose exec dev-box php caligrafer.php queue status
   docker compose exec dev-box php caligrafer.php queue work --once
   ```
   Expected:
   ```text
    1 pending, 0 running, 0 done, 0 failed

    Working the queue default until it is empty
    #1 remind_loans: done
    1 done, 0 to try again, 0 failed
   ```
   and the notification again.
5. A test message without any loan: `docker compose exec dev-box php caligrafer.php notify test 1` sends one to user 1,
   reader1, and prints `"push"`, the number of devices reached.

Who did what:
```bash
docker compose exec dev-box php caligrafer.php schedule history
docker compose exec dev-box php caligrafer.php audit --capability remind_loans --limit 3
```
The history lists each run of a job with its outcome. The audit log (your times differ):
```text
 2026-11-08 00:05:19  cli   queue  remind_loans #1 remind_loans                 ok
 2026-11-08 00:05:18  cli   cli    -            remind_loans                 ok
 2026-11-08 00:05:10  cli   schedule remind-loans remind_loans                 ok
```
from the bottom: the morning job (`schedule`, its name), the `call` of the command line, and job 1 of the queue.

Email works too once `MAIL_*` is set in `.env` and reader1 confirmed an address: `Notify.email('you@example.com')` in
the browser's console on a Bookshelf page sends the confirmation link. The address someone signs in with (lesson 11) does
not count: a notification goes only to an address that was confirmed through this link.

### Step 6 - Every minute, on its own

Start the scheduler container (it was not started with the others):
```bash
docker compose up -d scheduler
docker compose logs scheduler
```
Expected: ` Running the scheduled jobs every minute (Ctrl-C to stop)`. From now on the queue empties itself within a
minute, and tomorrow at 8:00 New York time the reminders go out. Online, deploy (lesson 14): Caligrafy Cloud reads the
two jobs from the deploy step's `caligrafy-schedule` line, and `cloud jobs bookshelf-sam` shows their last and next
runs. Caligrafy Cloud also gives the online app its own `VAPID_*` keys (`cloud secret list bookshelf-sam` shows their
names), so there is nothing to set for push online; your local keys stay in `.env`, never in git.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Give the push services a contact: ...` | The subject is not `mailto:...` or `https://...` | `--subject mailto:you@example.com` |
> | `schedule list` prints the problems of `schedule.php` | A typo in a field, or both `every` and `cron` | One of them per job; the names of step 2 |
> | `Push notifications are not set up on this app (php caligrafer.php notify keys)` under the button | Step 3 was skipped | Run `notify keys` |
> | `Notifications are blocked in the settings of this browser.` | Notifications were refused once | Allow them in the site settings of the browser |
> | `On iPhone and iPad, add Bookshelf to the Home Screen first.` | iOS sends push only to installed apps | Install it (lesson 10), open it from its icon |
> | `"notified": 0` | No device turned push on, and no confirmed email | Click the button first (step 5) |
> | The Reminders band is missing | You are signed in as a reader (level 0) | Sign in as an administrator |
> | The job stays `pending` | No worker runs | `queue work --once`, or start the scheduler (step 6) |

---

## Check

- [ ] The two capabilities, `schedule.php`, `web.php` and `Loans/index.pug` are identical to the File blocks.
- [ ] `schedule list` shows the two jobs; `schedule run remind-loans` prints `ok`.
- [ ] `call remind_loans` counts 1 due and 1 late; the notification arrives on the device you turned on.
- [ ] **Send the reminders now** queues job 1, and `queue work --once` runs it: `done`.
- [ ] The scheduler container runs; `.env` (with the `VAPID_*` keys) is not committed.

## With Claude

```text
/caligrafy-quill:new-capability remind_loans loans:write
Every morning at 8 New York time, notify the administrators (users with permissions >= 1) of the loans due today or
late, by push and email, with Caligrafy\Notify::user: title "N books to bring back", body "X due today, Y late. Open
the loans to see who has them.", url /loans, tag loans-due. level: 1; count with the query builder (count()), no SQL
string. Add a send_reminders capability (loans:write, level 1) that only Queue::push('remind_loans') and returns the
job, a Route::form POST /loans/remind for it, and on Loans/index.pug, for administrators only, a "Turn on reminders on
this device" button with public/js/notify.js (Notify.enable() on click) and a "Send the reminders now" form. Put
remind_loans and queue_work in application/config/schedule.php. Run platform install and
notify keys --subject mailto:you@example.com. Do not print .env. No test file, no reviewer.
```

**Check** before you accept:
- [ ] The capability works for every administrator, not for "the signed-in user" (a job has nobody signed in).
- [ ] `schedule list` shows both jobs; `schedule run remind-loans` prints `ok`.
- [ ] The button asks the browser's permission only when clicked.
- [ ] `notify keys` ran once; no key appears in the chat or in a committed file.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 15: reminders"
git tag lesson-15
git push origin main --tags
```
Reference: [`checkpoints/lesson-15`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-15).
Learn more: [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs),
[Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications) and
[Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs).

## Exercise

When a book comes back, tell the administrators by push, in the background so that the page answers at once. Which
line do you add to `return_book`, and what does the queued capability need?

<details><summary>Show the solution</summary>

A capability that sends one message, called by the queue, and one `Queue::push` in `return_book`.
`application/services/Loans/NotifyReturn.php` (`php caligrafer.php service Loans/NotifyReturn --scope loans:write`):
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Notify;
use Caligrafy\Service;

// notify_return: tells the administrators that a book came back (the queue runs it)
#[Capability(
    name: 'notify_return',
    description: 'Tells every administrator that a lent book came back, by its title.',
    scope: 'loans:write',
    level: 1
)]
class NotifyReturn extends Service
{
    public $rules = array('title' => 'required|max_len,60');

    public function handle(array $input, Context $context)
    {
        $notified = 0;
        foreach (Database::table('users')->where('permissions', '>=', 1)->pluck('id') as $admin) {
            $sent = Notify::user($admin, array('title' => 'A book came back', 'body' => $input['title'] . ' is on the shelf again.', 'url' => '/loans'));
            $notified += $sent['push'] > 0 || $sent['email'] ? 1 : 0;
        }
        return array('notified' => $notified);
    }
}
```
In `ReturnBook.php`, before the `return`, add `use Caligrafy\Queue;` at the top and:
```php
        Queue::push('notify_return', array('title' => Book::find($open->book_id)->title));
```
Return a book on the loans page: `queue status` shows `1 pending`, and the worker (or `queue work --once`) sends the
message. The input is checked by the rules of `notify_return` when the job runs: a title over 60 characters fails the
job (`queue failed` lists it), it does not break the return. Not in the checkpoint: remove both before you commit.
</details>

---
Previous: [Lesson 14 - Put it online](14-Put-It-Online) · Next: [Lesson 16 - What a page or a form cannot say](16-Every-Request-Declared)
