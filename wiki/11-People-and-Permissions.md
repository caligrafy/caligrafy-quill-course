# Lesson 11 - People and permissions

**In this lesson** Bookshelf gets accounts and rules: everyone can still read the catalog, signed-in people can add and
edit books, only administrators can delete them or see who has an account, and each program or agent gets a token
with just the rights it needs. People sign in with their **email address**. One line of `web.php` (`Route::auth`) gives the sign-in pages and brings people back to
the page they were on; a **join form**, one `Route::page` and one `Route::form`, creates the account and signs the new
member in. You close the doors once, in three capabilities, and every experience obeys at once: the pages, REST, the
agents, the finder and the SPA. The pages show only what the visitor may use.

**Before you start:** lesson 10 is done (tag `lesson-10`), `docker compose ps` shows the 3 containers running, and you
have token #2 "reading partner" of lesson 07.

**Time:** 60 to 75 minutes.

---

## New concepts

**Authentication and authorization.** Authentication answers "who are you?": a person signs in with an email address
and a password, a program sends a token. Authorization answers "may you do this?": only administrators delete books.

**Session.** HTTP forgets everything between two requests. PHP keeps a **session** per browser, found again thanks to a
cookie the browser sends with every request. When a person signs in, Caligrafy keeps the user in the session, under a
**new** session id (an id someone saw before the sign-in is worthless after it), and a new CSRF token (lesson 06). It
also notes how the person signed in, which lesson 16 uses.

**`Route::auth`.** One line declares the addresses of the authentication module:

| Address | What it does |
|---|---|
| `GET /login`, `POST /login` | the sign-in page and its form; after 5 wrong passwords for one account from one address in 15 minutes, that sign-in is refused until the 15 minutes end |
| `GET /logout`, `POST /logout` | signs out, then goes to `/` |
| `GET /register`, `POST /register` | a ready-made sign-up page, whose form calls the framework capability `register_user` (permissions 0). Bookshelf has no use for it: `'register' => false` removes it |
| `GET /notAuthorized` | the page (403) of a signed-in person whose level is too low |

Options: `home` (where a person lands after signing in, default `/`), `register` (`false`: no sign-up page, and no link
to it in the top bar or on the sign-in page), `messages` (what the pages say, such as `invalid` when a sign-in fails), and
others listed in
[Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes). It needs
`Auth::authentication('User', 'users')` above it: the model and the table of the people.

**Coming back after signing in.** `/login?return=/books/3` signs the person in, then goes to `/books/3` instead of
`home`. Only a path of this site is accepted: `?return=https://elsewhere.example` is ignored, so nobody can use your
sign-in page to send people to another site.

**The users table and the permission level.** The table `users` has `id`, `username`, `passcode` (a one-way hash,
never the password), `permissions` (0 for every new account), `created_at`, `modified_at`. The number in `permissions`
is the person's **permission level**: 0 for a reader, 1 for an administrator.

**Signing in with an email.** The `users` table has no email column, and it needs none: the `username` column holds the
address, in lower case, and every sign-in page, the session and `Auth::user()->username` keep working. The framework's
default is letters and digits, so four things change, all in files that initialize copied into your project:

| What changes | Where | Why |
|---|---|---|
| the column: `username varchar(254)` | `application/models/Auth/create_db.sql` | an address can be 254 characters; the column had 100. The table does not exist yet, so the first version can say so; a table with data would need a migration (lesson 05) |
| the rules: `required\|valid_email\|max_len,254`, and the filter `trim\|lower_case` | `CaligrafyRegisterUser.php` and `CaligrafyUpdateUser.php` in `application/services/Users/` | the old rule `alpha_numeric` refuses an `@`; in lower case, `Sam@Example.com` and `sam@example.com` are one account |
| the forms: the label *Email*, `type='email'`, the field still named `username` | `join.pug` and `application/views/Auth/login.pug` | the sign-in reads the field `username`; `type='email'` gives phones the keyboard with `@` |
| the words: `'messages' => ['invalid' => 'Invalid email or password']` | `Route::auth` in `web.php` | a failed sign-in should not talk about usernames |

A message that a rule writes still says `The Username field ...`: the rule knows the field by its name.

`Route::auth` also has an option `identifier`, for a table with its own `email` column. It needs that column and your own
sign-up capability, and Bookshelf needs neither.

**Who may call a capability.** Three settings of `#[Capability]`:

| Setting | Meaning |
|---|---|
| `public: true` | anyone, even a visitor nobody knows; scope and level are not checked |
| `scope: 'books:write'` | a **token** needs this scope (`books:write`, `books:*` or `*`) |
| `level: 1` | a **person** needs `permissions` of 1 or more (default 0: any signed-in person) |

And who is calling:

| Caller | Recognized by | Checked against |
|---|---|---|
| visitor | nothing | only `public` capabilities, else 401 `unauthenticated` |
| person signed in | the session cookie | the `level` (people have every scope) |
| **application token** (`token create ... --scopes ...`) | `Authorization: Bearer cq_...` | its **scopes only**: no person behind it, so no level |
| **token for a person** (`token create ... --user <id>`) | `Authorization: Bearer cq_...` | its scopes **and** the level of that person |

Read the last two lines twice. A token with `books:write` and no `--user` can delete books; a token for reader1 can
delete only once reader1 is an administrator. Give write scopes with care.

**Pages for some people only.** `Route::page` takes four options:

| Option | Who it stops | Where they go |
|---|---|---|
| `'signedIn' => true` | visitors | `/login` |
| `'level' => 1` | visitors; people below level 1 | `/login`; `/notAuthorized` |
| `'comeBack' => true`, with one of the two above | visitors | `/login?return=<this page>`, and back here once signed in |
| `'visitorsOnly' => '/books'` | people who are signed in | that path: a join page means nothing to a member |

Every page of `Route::page` also gets `$authorized` (true when someone is signed in) and `$user` (`id`, `username`,
`permissions`, or null): the views use them to show only what the visitor may use. Hiding a button is for comfort; the
protection is in the capability, which refuses on every channel.

**A form that creates an account.** Four more options of `Route::form` make a form that needs no code of its own:

| Option | What it does | In the join form |
|---|---|---|
| `same` | two fields must be the same, with the message to show under the first | `'same' => ['passcode_again' => ['passcode', 'The two passwords are not the same']]` |
| `accepted` | a box must be ticked, with the message to show under it | `'accepted' => ['rules' => 'Accept the rules of the club to join']` |
| `visitorsOnly` | people who are signed in are sent to that path, as for a page | `'visitorsOnly' => '/books'` |
| `signsIn` | names the field of the capability's answer that holds the id of the person to sign in, under a new session id | `'signsIn' => 'id'` |

`same` and `accepted` are checked **before** the capability: when one refuses, the capability is not called, so the
messages of its rules only come after those two pass. They belong to the form: the rules every experience must obey stay
in `$rules`. The form calls `register_user`, the framework's capability that answers only web pages and the command line,
and that returns `id`, `username` and `permissions`.

**The audit log.** Every write and every refusal is a line: when, through which channel (`web`, `rest`, `mcp`, `cli`),
who (`guest`, `user 1`, `token 3`), which capability, and the outcome.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - The users table, with room for an email

The script is the authentication module's own `create_db.sql`, which initialize copied into your project. Before it
runs for the first time, give `username` the room of an address (only that line changes):

**File `application/models/Auth/create_db.sql`** (`username varchar(254)`):

```sql
/* users table for the Caligrafy authentication module.
 * Safe to run more than once: it never drops an existing table.
 * passcode holds a password_hash() value (60+ characters).
 */
CREATE TABLE IF NOT EXISTS users (
    id int(15) NOT NULL AUTO_INCREMENT,
    username varchar(254) NOT NULL,   -- Bookshelf: people sign in with their email address, kept in this column
    passcode varchar(255) NOT NULL,
    permissions tinyint UNSIGNED DEFAULT 0,
    created_at datetime NOT NULL DEFAULT now(),
    modified_at datetime NOT NULL DEFAULT now() ON UPDATE now(),
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username)
)ENGINE = InnoDB DEFAULT CHARSET = utf8;

/* Existing installations created with an older script can upgrade with:
 * ALTER TABLE users MODIFY passcode varchar(255) NOT NULL;
 */
```

Run it, and look at the column:
```bash
docker compose exec dev-box php caligrafer.php sql application/models/Auth/create_db.sql
docker compose exec mysql mysql -uroot -proot caligrafy -e "SHOW COLUMNS FROM users LIKE 'username'"
```
Expected: ` application/models/Auth/create_db.sql run on caligrafy`, the password warning (harmless), then a line with
`username`, `varchar(254)` and `UNI` (each address only once). It creates the table only if it is missing: running it again
never deletes anyone.

The two capabilities that write a username get the same rule. In `register_user`, `$rules` changes, `$filters` is new, the
description and the message of a taken address change:

**File `application/services/Users/CaligrafyRegisterUser.php`** (an account is an email address, in lower case):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// Sign-up: the sign-up page of Route::auth (or AuthController@register) calls it, then the person signs in, or is signed
// in at once with Route::auth's signInAfterRegister. Signing in itself is the web session's, which capabilities never touch.
// Web pages and the command line only: over REST or MCP anyone could create accounts in bulk.
#[Capability(
    name: 'register_user',
    description: 'Creates an account that can sign in, with permissions 0. Used by the sign-up page.',
    scope: 'users:write',
    public: true
)]
class CaligrafyRegisterUser extends Service
{
    // Bookshelf: people sign in with their email address: the username column holds it, in lower case
    public $rules = array('username' => 'required|valid_email|max_len,254', 'passcode' => 'required|min_len,8|max_len,72');
    public $filters = array('username' => 'trim|lower_case');
    public $descriptions = array('username' => 'An email address: what the person signs in with', 'passcode' => 'The password: 8 to 72 characters');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->channel, array('web', 'cli'), true)) {
            throw ServiceError::forbidden('Sign up on the sign-up page');
        }
        if (User::where('username', $input['username'])->exists()) {
            throw ServiceError::conflict('An account already uses this email');
        }
        try {
            $user = User::find(User::insert(array('username' => $input['username'], 'passcode' => User::hashPassword($input['passcode']), 'permissions' => 0)));
        } catch (\Throwable $e) {
            if (CaligrafyUsers::isDuplicate($e)) {
                throw ServiceError::conflict('An account already uses this email');
            }
            throw $e;
        }
        return CaligrafyUsers::profile($user);
    }
}
```

`update_user` is how administrators and their agents change an account: without this change, it would still refuse any
address:

**File `application/services/Users/CaligrafyUpdateUser.php`** (the same rule):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'update_user',
    description: 'Changes the username, the password or the permissions of a person who can sign in (0 for everyone, 1 or more for administrators). Only the inputs given change. Returns the user, never the password.',
    scope: 'users:write',
    level: CaligrafyUsers::ADMIN_LEVEL
)]
class CaligrafyUpdateUser extends Service
{
    public $rules = array('id' => 'required|integer', 'username' => 'valid_email|max_len,254', 'passcode' => 'min_len,8|max_len,72',
        'permissions' => 'integer|min_numeric,0|max_numeric,255');
    public $filters = array('username' => 'trim|lower_case');   // Bookshelf: the same rule as register_user: an email address, in lower case
    public $descriptions = array('id' => 'The id of the user', 'username' => 'A new email address',
        'passcode' => 'A new password: 8 to 72 characters', 'permissions' => '0 for everyone, 1 or more for administrators');

    public function handle(array $input, Context $context)
    {
        CaligrafyUsers::requireAdmin($context);
        $user = User::find($input['id']);
        if (!$user) {
            throw ServiceError::notFound("No user #{$input['id']}");
        }
        if (!CaligrafyUsers::mayManage($context, $user->permissions ?? 0)) {
            throw ServiceError::forbidden('You cannot manage someone above your own level');
        }
        $changes = array();
        if (isset($input['username']) && $input['username'] !== '' && $input['username'] !== $user->username) {
            if (User::where('username', $input['username'])->where('id', '!=', $user->id)->exists()) {
                throw ServiceError::invalid(array('username' => 'An account already uses this email'));
            }
            $changes['username'] = $input['username'];
        }
        if (isset($input['passcode']) && $input['passcode'] !== '') {
            $changes['passcode'] = User::hashPassword($input['passcode']);
        }
        if (isset($input['permissions']) && $input['permissions'] !== '') {
            if (!CaligrafyUsers::mayManage($context, $input['permissions'])) {
                throw ServiceError::invalid(array('permissions' => 'You cannot give more than your own level'));
            }
            $changes['permissions'] = (int) $input['permissions'];
        }
        if (!$changes) {
            return CaligrafyUsers::profile($user);
        }
        $changes['modified_at'] = date('Y-m-d H:i:s');
        try {
            User::where('id', $user->id)->update($changes);
            return CaligrafyUsers::profile(User::find($user->id));
        } catch (\Throwable $e) {
            if (CaligrafyUsers::isDuplicate($e)) {
                throw ServiceError::invalid(array('username' => 'An account already uses this email'));
            }
            throw $e;
        }
    }
}
```

Try the rule from the command line (this creates no account, because it refuses):
```bash
docker compose exec dev-box php caligrafer.php call register_user username=reader1 passcode=bookshelf-2026
```
Expected:
```json
{
    "error": {
        "code": "invalid",
        "message": "Some of the inputs are invalid",
        "fields": {
            "username": "The Username field must be a valid email address"
        }
    }
}
```

### Step 2 - Sign-in with an email, a page for administrators, and the join form

Four changes in `application/config/routing/web.php`:
1. Uncomment `Auth::authentication('User', 'users');` (remove the `//`).
2. Replace the commented block `// Auth Routes - Uncomment only if AUTHENTICATION activated above` and its 7 lines with
   the `// SIGN IN, SIGN OUT` comment and `Route::auth(...)`, with the `messages` of the sign-in.
3. In `// BOOKSHELF PAGES`, after the `Route::download` line, add the page `/people`, with `'level' => 1` and `'comeBack' => true`.
4. Below it, add the `// JOIN THE CLUB` block: the page and the form.

The file is then:

**File `application/config/routing/web.php`** (`Auth::authentication`, `Route::auth`, the `/people` page and the `/join` page and form):

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

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

- `Route::auth` has `'register' => false`: the join form is now the only way to create an account, so no door skips the
  rules of the club. `messages` replaces what a failed sign-in says.
- `/people` gets its data from `list_users`, a capability of the framework (`users:read`, level 1, never a password).
- `Route::form('/join', 'register_user', ...)` answers the same address as the page, with POST. `{username}` in `flash`
  comes from the capability's answer, like `{id}` in lesson 06.

Write the views. The sign-in page is the framework's, copied into your project: only its field changes (the `$field`
lines are gone, the label says Email, `type='email'`, 254 characters):

**File `application/views/Auth/login.pug`** (the field keeps the name `username`):

```pug
extends ../default/master/site

block title
  | Sign in · Caligrafy

block content
  if $authorized
    section.cq-hero
      h1.cq-display You are signed in
      p.cq-lead Welcome to Caligrafy.
      .cq-cta
        a.btn.btn-primary(href="{$home}") Continue
        a.btn.btn-secondary(href="{$home}logout") Sign out
  else
    main.cq-auth
      h1 Sign in
      include ../default/modules/notice
      form#login_form(method='POST', enctype="multipart/form-data", action="{$home}login")
        =csrf()
        //- Bookshelf: people sign in with their email address, which the username column holds: the field keeps its name
        .cq-field
          label(for='username') Email
          //- after a failed sign-in, what the person typed is kept (Route::auth gives it in $user, 2.19.3)
          input#username.form-control(type='email', name='username', value=isset($user) && is_object($user) ? ($user->username ?? '') : '', required, autocomplete='email', autocapitalize='none', maxlength='254')
        .cq-field
          label(for='passcode') Password
          input#passcode.form-control(type='password', name='passcode', required, autocomplete='current-password', maxlength='72')
        button.btn.btn-primary(type='submit') Sign in
      //- no sign-up in Route::auth (register => false): no link to it (2.19.6)
      - $canSignUp = isset($signUp) ? $signUp : \Caligrafy\AuthRoutes::signUpOpen()
      if $canSignUp
        .cq-or Or
        a.btn.btn-secondary.cq-btn-block(href="{$home}register") Create account
```

Then the two pages of Bookshelf:

**File `application/views/Bookshelf/people.pug`**:

```pug
extends ../default/master/site

//- The people who can sign in: Route::page('/people', ..., ['level' => 1]) opens it to administrators only, and gives
//- it $people (list_users, a framework capability: id, username, permissions; never a password)

block title
    | People · Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display People
        p.cq-lead Who can sign in to Bookshelf. Permissions 1 or more: an administrator.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            ul.cq-rows
                each $p in $people
                    li.cq-row
                        .cq-row-main
                            p.cq-row-title= $p['username']
                            p.cq-row-meta
                                | #{$p['permissions'] >= 1 ? 'Administrator' : 'Reader'} · since #{$p['created_at']}
            p.cq-auth-note
                a(href="{$home}books") All books
```

**File `application/views/Bookshelf/join.pug`** (a page that is only a form: the narrow column of the sign-in page):

```pug
extends ../default/master/site

//- Join the club: Route::form('/join', 'register_user') checks the two passwords and the box, calls the capability,
//- signs the new member in and goes to the catalog. A refusal comes back here with errors(), old() and flash('error').
//- Passwords are never kept for old(). The field is named username: that column holds the address

block title
    | Join the club · Bookshelf

block content
    main.cq-auth
        h1 Join the club

        form(method='POST', action="{$home}join", novalidate)
            =csrf()
            .cq-field
                label(for='username') * Email
                input#username.form-control(type='email', name='username', value=old('username'), maxlength='254', autocomplete='email', autocapitalize='none')
                if errors('username')
                    p.cq-field-error= errors('username')
            .cq-field
                label(for='passcode') * Password
                input#passcode.form-control(type='password', name='passcode', maxlength='72', autocomplete='new-password')
                if errors('passcode')
                    p.cq-field-error= errors('passcode')
            .cq-field
                label(for='passcode_again') * The same password again
                input#passcode_again.form-control(type='password', name='passcode_again', maxlength='72', autocomplete='new-password')
                if errors('passcode_again')
                    p.cq-field-error= errors('passcode_again')
            .cq-field
                label(for='rules')
                    input#rules(type='checkbox', name='rules', value='1', checked=old('rules') == '1')
                    |  I accept the rules of the club: be kind, and bring the books back
                if errors('rules')
                    p.cq-field-error= errors('rules')
            button.btn.btn-primary(type='submit') Join
        if flash('error')
            p.cq-field-error(role='alert')= flash('error')

        p.cq-auth-note
            a(href="{$home}login") Already a member? Sign in
```

Try it as a visitor:
```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost:8080/people
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/join
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/register
```
Expected: `302 http://localhost:8080/login?return=%2Fpeople` (`%2F` is a `/` written safely in an address), `200`,
`404`. The **Sign in** link of the top bar now leads to a real page, and **Create account** is gone: there is no
`/register`.

### Step 3 - Close the doors, once

`create_book` and `update_book` lose `public: true`; `delete_book` gets `level: 1` in its place. The four reads stay
public. The three files are then:

**File `application/services/Bookshelf/CreateBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id. The ISBN is optional and unique.',
    scope: 'books:write'   // signed-in people, and tokens with books:write
)]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'isbn' => 'alpha_numeric_dash|max_len,20|unique,books.isbn',
        'author_id' => 'required|integer|exists,authors.id'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim', 'isbn' => 'trim');
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published, for example 1969 (not after this year)',
        'summary' => 'Optional: one or two sentences about the book',
        'isbn' => 'Optional: its ISBN, digits and dashes, for example 978-0-441-47812-5; two books never share one',
        'author_id' => 'The id of its author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        // an empty ISBN is no ISBN: NULL, which the unique index allows on many books
        $input['isbn'] = ($input['isbn'] ?? '') === '' ? null : $input['isbn'];
        $id = Book::insert($input);   // one INSERT, every value bound; returns the id of the new book
        return Book::with('author')->find($id);
    }
}
```

**File `application/services/Bookshelf/UpdateBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// update_book: changes only the inputs that are given
#[Capability(
    name: 'update_book',
    description: 'Changes the title, year, summary, ISBN or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book with its author.',
    scope: 'books:write'   // signed-in people, and tokens with books:write
)]
class UpdateBook extends Service
{
    public $rules = array(
        'id' => 'required|integer',
        'title' => 'max_len,200',
        'published_year' => 'integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'isbn' => 'alpha_numeric_dash|max_len,20|unique,books.isbn,id,id',   // unique, except on this book itself
        'author_id' => 'integer|exists,authors.id'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim', 'isbn' => 'trim');
    public $descriptions = array(
        'id' => 'The id of the book to change',
        'title' => 'A new title',
        'published_year' => 'A new year of publication',
        'summary' => 'A new summary',
        'isbn' => 'A new ISBN (empty removes it)',
        'author_id' => 'The id of a new author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        if (!Book::find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        // keep only the inputs that were given: a missing input never erases a column
        $changes = array();
        foreach (array('title', 'published_year', 'summary', 'isbn', 'author_id') as $field) {
            if (isset($input[$field])) {
                $changes[$field] = $input[$field];
            }
        }
        // a book always has a title, a year and an author: given empty, they are refused like a missing required input
        $errors = array();
        foreach (array('title' => 'Title', 'published_year' => 'Published Year', 'author_id' => 'Author Id') as $field => $label) {
            if (($changes[$field] ?? null) === '') {
                $errors[$field] = "The $label field is required";
            }
        }
        if ($errors) {
            throw ServiceError::invalid($errors);
        }
        if (($changes['isbn'] ?? null) === '') {
            $changes['isbn'] = null;
        }
        if ($changes) {
            Book::where('id', $input['id'])->update($changes);   // only this book: an update needs a where
        }
        return Book::with('author')->find($input['id']);
    }
}
```

**File `application/services/Bookshelf/DeleteBook.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// delete_book: destructive, so AI agents ask before calling it
#[Capability(
    name: 'delete_book',
    description: 'Deletes a book of the catalog, by its id. This cannot be undone.',
    scope: 'books:write',
    destructive: true,
    level: 1   // people whose permissions are 1 or more (administrators), and tokens with books:write
)]
class DeleteBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book to delete');

    public function handle(array $input, Context $context)
    {
        // delete() returns how many rows it deleted: 0 when there is no such book
        if (!Book::where('id', $input['id'])->delete()) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return array('deleted' => (int) $input['id']);
    }
}
```

Check:
```bash
docker compose exec dev-box php caligrafer.php capabilities | grep "_book\|_author"
```
Expected (descriptions cut here):
```text
 create_book                  books:write    writes    Adds a book to the Bookshelf catalog ...
 delete_book                  books:write    destroys  Deletes a book of the catalog, by its id. This cannot be undone.
 get_book                     public         read      Returns one book of the catalog with its author ...
 list_authors                 public         read      Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.
 list_books                   public         read      Lists the books of the Bookshelf catalog, sorted by title, ...
 search_books                 public         read      Finds the books of the catalog whose title or summary contains ...
 update_book                  books:write    writes    Changes the title, year, summary, ISBN or author of a book ...
```
That is the whole security change. Everything below tells people and programs what they may do.

### Step 4 - The pages show what the visitor may do

The forms need no change to be safe: `Route::form` sends a visitor who submits to `/login`, and a reader who deletes
gets the 403 page. The views only hide what the visitor may not use, and give visitors the way in: **Join the club** on
the catalog, and on a book **Sign in to edit this book**, whose link is `login?return=/books/<id>`: the person comes back
to that book.

**File `application/views/Bookshelf/books.pug`** (the form for people signed in, else Sign in and Join):

```pug
extends ../default/master/site

//- The catalog: Route::page gives it $books (list_books: a page of books), $authors (list_authors) and $author_id
//- (the author of the address, ?author_id=2, as a string; '' when there is none).
//- The form posts to Route::form('/books', 'create_book'): a refusal comes back here with errors(), old() and flash('error').
//- $authorized and $user (id, username, permissions) come from Route::page: who is signed in

block title
    | Bookshelf

block content
    section.cq-hero.cq-hero--page
        h1.cq-display Bookshelf
        p.cq-lead The books we love. The same catalog that the REST API and AI agents use.

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')

            //- the form only for people signed in: create_book refuses visitors on every channel anyway
            if $authorized
                form(method='POST', action="{$home}books", novalidate)
                    =csrf()
                    .cq-form-grid.cq-form-grid--2
                        .cq-field
                            label(for='title') * Title
                            input#title.form-control(type='text', name='title', value=old('title'), maxlength='200')
                            if errors('title')
                                p.cq-field-error= errors('title')
                        .cq-field
                            label(for='author_id') * Author
                            select#author_id.form-select(name='author_id')
                                each $a in $authors
                                    option(value=$a['id'], selected=$a['id'] == old('author_id', $author_id))= $a['name']
                            if errors('author_id')
                                p.cq-field-error= errors('author_id')
                        .cq-field
                            label(for='published_year') * Year
                            input#published_year.form-control(type='number', name='published_year', value=old('published_year'), placeholder='1969')
                            if errors('published_year')
                                p.cq-field-error= errors('published_year')
                        .cq-field
                            label(for='isbn') ISBN
                            input#isbn.form-control(type='text', name='isbn', value=old('isbn'), placeholder='Optional', maxlength='20')
                            if errors('isbn')
                                p.cq-field-error= errors('isbn')
                    .cq-field
                        label(for='summary') Summary
                        input#summary.form-control(type='text', name='summary', value=old('summary'), placeholder='Optional', maxlength='2000')
                    button.btn.btn-primary(type='submit') Add book
                if flash('error')
                    p.cq-field-error(role='alert')= flash('error')
            else
                p.cq-auth-note
                    a.btn.btn-primary(href="{$home}login") Sign in to add books
                    |  
                    a.btn.btn-secondary(href="{$home}join") Join the club

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            h2.cq-heading Books
            if $books['total'] == 0
                p.cq-empty-state No books here yet: add the first one above.
            else
                ul.cq-rows
                    each $b in $books['data']
                        li.cq-row
                            .cq-row-main
                                p.cq-row-title
                                    a(href="{$home}books/{$b['id']}")= $b['title']
                                p.cq-row-meta
                                    a(href="{$home}books?author_id={$b['author_id']}")= $b['author']['name']
                                    | , #{$b['published_year']}
                //- the next and previous pages keep the author of the address (?author_id=2)
                - $filter = $author_id !== '' ? '&author_id=' . (int) $author_id : '';
                p.cq-auth-note
                    | Page #{$books['page']} of #{$books['pages']}, #{$books['total']} books
                    if $books['page'] > 1
                        |  · 
                        a(href="{$home}books?page=" . ($books['page'] - 1) . $filter) Previous
                    if $books['page'] < $books['pages']
                        |  · 
                        a(href="{$home}books?page=" . ($books['page'] + 1) . $filter) Next
            p.cq-auth-note
                a(href="{$home}books") All books
                |  · 
                a(href="{$home}authors.csv") The authors as a spreadsheet (CSV)
```

**File `application/views/Bookshelf/book.pug`** (the edit form for people signed in, Delete for administrators, sign-in that comes back):

```pug
extends ../default/master/site

//- One book: Route::page gives it $book (get_book: the book with its author) and $authors (list_authors).
//- The edit form posts to Route::form (PUT, update_book), the delete form to Route::form (DELETE, delete_book)

block title
    = $book['title'] . ' · Bookshelf'

block content
    section.cq-hero.cq-hero--page
        h1.cq-display= $book['title']
        p.cq-lead
            | by #{$book['author']['name']}, #{$book['published_year']}

    section.cq-band.cq-band--tight
        .cq-inner.cq-inner--narrow
            if flash('success')
                p.cq-alert(role='status')= flash('success')
            p= $book['summary'] ?: 'No summary yet.'
            if $book['isbn']
                p.cq-row-meta ISBN #{$book['isbn']}

            //- the edit form for people signed in, Delete for administrators (permissions 1 or more):
            //- the capabilities refuse the others on every channel, the page only hides what they may not use
            if $authorized
                form(method='POST', action="{$home}books/{$book['id']}", novalidate)
                    =csrf()
                    =methodField('PUT')
                    .cq-form-grid.cq-form-grid--2
                        .cq-field
                            label(for='title') * Title
                            input#title.form-control(type='text', name='title', value=old('title', $book['title']), maxlength='200')
                            if errors('title')
                                p.cq-field-error= errors('title')
                        .cq-field
                            label(for='author_id') * Author
                            select#author_id.form-select(name='author_id')
                                each $a in $authors
                                    option(value=$a['id'], selected=$a['id'] == old('author_id', $book['author_id']))= $a['name']
                            if errors('author_id')
                                p.cq-field-error= errors('author_id')
                        .cq-field
                            label(for='published_year') * Year
                            input#published_year.form-control(type='number', name='published_year', value=old('published_year', $book['published_year']))
                            if errors('published_year')
                                p.cq-field-error= errors('published_year')
                        .cq-field
                            label(for='isbn') ISBN
                            input#isbn.form-control(type='text', name='isbn', value=old('isbn', $book['isbn']), maxlength='20')
                            if errors('isbn')
                                p.cq-field-error= errors('isbn')
                    .cq-field
                        label(for='summary') Summary
                        input#summary.form-control(type='text', name='summary', value=old('summary', $book['summary']), maxlength='2000')
                    button.btn.btn-primary(type='submit') Save changes
                if flash('error')
                    p.cq-field-error(role='alert')= flash('error')
                if $user['permissions'] >= 1
                    form(method='POST', action="{$home}books/{$book['id']}", onsubmit="return confirm('Delete this book? This cannot be undone.')")
                        =csrf()
                        =methodField('DELETE')
                        p.cq-auth-note
                            button.btn.btn-secondary(type='submit') Delete this book
            p.cq-auth-note
                if !$authorized
                    a.btn.btn-primary(href="{$home}login?return=/books/{$book['id']}") Sign in to edit this book
                    |  
                a(href="{$home}books") All books
```

`$user['permissions'] >= 1` mirrors the `level: 1` of `delete_book`.

### Step 5 - Regenerate both clients

```bash
docker compose exec dev-box php caligrafer.php client
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```
Expected: `... written: 20 capabilities ...` twice. In their comments, `create_book (writes, public)` became
`create_book (writes, books:write)`. Rebuild the SPA: `cd public/bookshelf-spa && npm run build && cd ../..`.

### Step 6 - As a visitor, then as a reader

1. Open http://localhost:8080/books: the list, a **Sign in to add books** button and **Join the club**, no form. Open a
   book: **Sign in to edit this book**, no form, no Delete.
2. Click **Join the club** (http://localhost:8080/join) and try the refusals, in this order:
   - Click **Join** with nothing filled in: under the box, `Accept the rules of the club to join`, and under the form
     `Some of the inputs are invalid`. The capability was not called.
   - Tick the box, leave the rest empty, **Join**: `The Username field is required` and `The Passcode field is required`.
     Now the capability refused. The box is still ticked.
   - Email `reader1`, password `bookshelf-2026` in both fields, **Join**: `The Username field must be a valid email
     address` under the field.
   - Email `reader1@example.com`, password `bookshelf-2026`, then `other-password` in the second field: `The two passwords
     are not the same` under the second field; the email is still in its field, the two password fields are empty.
3. Type `Reader1@Example.com` as the email, `bookshelf-2026` in both password fields, tick the box, **Join**. Passwords are
   8 to 72 characters. You land on `/books` with `Welcome to the club, reader1@example.com`, in lower case, **Sign out** in
   the top bar, and the form: the form signed you in. Below, the course calls this account **reader1**.
4. Open http://localhost:8080/join again: you are sent to `/books`. The page is for visitors only.
5. Add *The Word for World Is Forest*, Ursula K. Le Guin, 1972: it works (`"id":12` if you followed every lesson).
   Open it: the edit form, and no **Delete**: reader1 has permissions 0.
6. Open http://localhost:8080/people: the **notAuthorized** page. reader1 is signed in, but below level 1.
7. Click **Sign out**. Open http://localhost:8080/books/3 and click **Sign in to edit this book**: the address is
   `/login?return=/books/3`, and the page asks for an **Email**. Type a wrong password: `Invalid email or password`, and
   your address is still in its field. Then sign in with `reader1@example.com`: you are back on Kindred, not on the catalog.

The REST API obeys the same rules. As a visitor:
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{"title": "x", "published_year": 2000, "author_id": 1}' -w " [%{http_code}]\n" http://localhost:8080/api/books
curl -s -o /dev/null -H "Accept: application/json" -w "[%{http_code}]\n" http://localhost:8080/api/books
```
Expected:
```text
{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}} [401]
[200]
```
From the command line, `call --guest` calls as a visitor (without it, `call` has full access, like any command you run
on your own computer):
```bash
docker compose exec dev-box php caligrafer.php call create_book title=Ghost published_year=2001 author_id=1 --guest
```
Expected:
```json
{
    "error": {
        "code": "unauthenticated",
        "message": "Sign in or send a personal access token"
    }
}
```

### Step 7 - Tokens with just the rights they need

Token #2 (`books:read`, `read -rs PARTNER` if your terminal lost it) now sees only the reads:
```bash
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $PARTNER" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' http://localhost:8080/mcp | grep -o '"name":"[a-z_]*"'
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $PARTNER" -d '{"title": "x", "published_year": 2000, "author_id": 1}' -w " [%{http_code}]\n" http://localhost:8080/api/books
```
Expected:
```text
"name":"get_book"
"name":"list_authors"
"name":"list_books"
"name":"register_user"
"name":"search_books"
{"error":{"code":"forbidden","message":"This needs the scope books:write"}} [403]
```
The public tools (`register_user` is the capability of the join form, which only answers web pages), and a 403 on the
write: lesson 07's promise kept.

Token #1 (`*`, lesson 02) can do everything: revoke it.
```bash
docker compose exec dev-box php caligrafer.php token revoke 1
```
Expected: ` Token #1 revoked`. The `tools/list` of lesson 08 with `$TOKEN` now answers 401 `Unauthorized`.

An **application token** for Claude Code, which reads and writes:
```bash
docker compose exec dev-box php caligrafer.php token create "Claude Code" --scopes books:read,books:write
```
Expected: ` Token #3 "Claude Code" created with books:read books:write`, then the token. `read -rs WRITER`, paste it.

A **token for reader1** (`--user 1`: it is the first account), with every books scope:
```bash
docker compose exec dev-box php caligrafer.php token create "librarian agent" --scopes "books:*" --user 1
```
Expected: ` Token #4 "librarian agent" created with books:*`. `read -rs LIBRARIAN`, paste it.

Add a book with the application token, then try to delete it with each token (use the id the first command prints):
```bash
curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $WRITER" -d '{"title": "Token test", "published_year": 2026, "author_id": 3}' -w " [%{http_code}]\n" http://localhost:8080/api/books
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $LIBRARIAN" -w " [%{http_code}]\n" http://localhost:8080/api/books/13
curl -s -X DELETE -H "Accept: application/json" -H "Authorization: Bearer $WRITER" -w " [%{http_code}]\n" http://localhost:8080/api/books/13
```
Expected:
```text
{"data":{"id":13,"title":"Token test",...}} [201]
{"error":{"code":"forbidden","message":"This needs the scope books:write and permission level 1"}} [403]
{"data":{"deleted":13}} [200]
```
`books:*` includes `books:write`, but the librarian acts for reader1, who has permissions 0. The application token has
the scope and no person, so no level applies. The librarian's `tools/list` has no `delete_book`.

Connect Claude Code with its own token in place of the revoked one:
```bash
claude mcp remove bookshelf
claude mcp add --transport http bookshelf http://localhost:8080/mcp --header "Authorization: Bearer $WRITER"
```

### Step 8 - The first administrator

The first administrator is set in the database, once:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "UPDATE users SET permissions = 1 WHERE username = 'reader1@example.com'"
```
(The password warning is harmless.) Reload the catalog: you are signed out. A change to a user's row ends their
session: **sign in again** as reader1. Now every book has **Delete this book**, and http://localhost:8080/people lists
`reader1@example.com`, `Administrator`. The librarian's `tools/list` now has `delete_book`: it acts for reader1.

The next administrators need no SQL: `update_user`, a framework capability for administrators, changes `permissions`
(through an agent with a token of reader1 that has `users:write`). Nobody can give a level above their own, and a token
without `--user` cannot manage people at all.

### Step 9 - Who did what

```bash
docker compose exec dev-box php caligrafer.php audit --limit 8
```
Expected (your times differ):
```text
 2026-11-07 23:49:23  rest  token  3            delete_book                  ok
 2026-11-07 23:49:23  rest  token  4            delete_book                  forbidden  This needs the scope books:write and permission level 1
 2026-11-07 23:49:23  rest  token  3            create_book                  ok
 2026-11-07 23:49:06  rest  token  2            create_book                  forbidden  This needs the scope books:write
 2026-11-07 23:49:05  cli   guest  -            create_book                  unauthenticated  Sign in or send a personal access token
 2026-11-07 23:49:04  rest  guest  -            create_book                  unauthenticated  Sign in or send a personal access token
 2026-11-07 23:48:56  web   user   1            create_book                  ok
 2026-11-07 23:48:48  web   guest  -            register_user                ok
```
(If you tried something else on the way, such as a delete as reader1 before step 8, it has its own line: the last 8
are then not these.) And the tokens:
```bash
docker compose exec dev-box php caligrafer.php token list
```
Expected:
```text
 #4    active   librarian agent                books:*  user 1  last used 2026-11-07 23:49:33
 #3    active   Claude Code                    books:read books:write  last used 2026-11-07 23:49:23
 #2    active   reading partner                books:read  last used 2026-11-07 23:49:06
 #1    revoked  my agent                       *  last used 2026-11-07 23:41:55
```
The other experiences: the finder still works for visitors (it only reads). The SPA answers **Add book** from a
visitor with `Sign in or send a personal access token`; sign in on http://localhost:8080, and **Add book** works on
http://localhost:5173 too (the cookie belongs to `localhost`, and the proxy forwards it).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | An error that ends with `Table 'caligrafy.users' doesn't exist` | Step 1 was skipped | Run the users script |
> | `Invalid email or password` on the sign-in page | A wrong address or password | Capital letters in the address do not matter, those of the password do; after 5 failures wait 15 minutes (`php caligrafer.php cache clear` empties the count while you develop) |
> | Signed in, but no **Sign out** and no form | The view reads another variable than `$authorized` | Compare the views with step 4 |
> | The form still shows to visitors | `if $authorized` is missing, or its indentation is wrong (Pug nests by indentation) | Compare with step 4 |
> | No **Delete** for the administrator | You did not sign in again after the `UPDATE` | Sign out, sign in |
> | `The Username field may only contain letters and numbers` | `CaligrafyRegisterUser.php` still has the rule `alpha_numeric` | Compare with step 1 |
> | `The Username field must be a valid email address` or `The Passcode field needs to be at least 8 characters` | The address has no `@` or a space, or the password is short | A full address; 8 to 72 characters |
> | `An account already uses this email` under the join form | The account exists | Sign in, or use another address |
> | A long address is cut, or MySQL says `Data too long for column 'username'` | The table was created before step 1 changed the column (`CREATE TABLE IF NOT EXISTS` skips it) | Write a migration: `ALTER TABLE users MODIFY username varchar(254) NOT NULL;` |
> | The sign-in page still asks for a *Username* | `application/views/Auth/login.pug` is still the template | Compare with step 2 |
> | `Accept the rules of the club to join` with the box ticked | The name of the box is not `rules`, or it has no `value` | Compare `join.pug` with step 2 |
> | After **Join** you land on `/books` but are not signed in | `'signsIn' => 'id'` is missing | Compare with step 2 |
> | `/register` still answers | `'register' => false` is missing | Compare with step 2 |
> | After signing in you land on the catalog, not on the page you were on | The address had no `?return=`, or it named another site | Compare the links with step 4; `comeBack` writes it for you |
> | 401 with a token you just created | The variable is empty or cut | `echo ${#WRITER}` prints 45; paste the whole `cq_...` value again |
> | Claude Code answers `Unauthorized` | It uses the revoked token | Remove and add the server again (end of step 7) |

---

## Check

- [ ] `web.php`, `Auth/create_db.sql`, `CaligrafyRegisterUser.php`, `CaligrafyUpdateUser.php`, `Auth/login.pug`, `people.pug`,
      `join.pug`, the three services and the two views are identical to the File blocks.
- [ ] `users.username` is `varchar(254)`; `reader1` is refused as an account, `Reader1@Example.com` is stored in lower
      case and signs in; a wrong password says `Invalid email or password`.
- [ ] `capabilities`: the 4 reads `public`; `create_book`, `update_book`, `delete_book` show `books:write`; `DeleteBook.php`
      has `level: 1` and `destructive: true`.
- [ ] As a visitor: no form, `/people` sends to `/login?return=%2Fpeople`, `POST /api/books` answers 401.
- [ ] The join form refuses an unticked box and two different passwords with the messages of step 6; a good one signs
      you in; `/join` then sends you to `/books`; `/register` answers 404.
- [ ] `/login?return=/books/3` comes back to Kindred.
- [ ] As reader1 (level 0): the form, no Delete, `/people` is notAuthorized; as administrator: Delete and `/people`.
- [ ] Token #2 sees 5 tools and gets 403 on writes; the librarian (#4) gets 403 on delete until reader1 is an
      administrator; the application token (#3) deletes.
- [ ] No controller in `application/controllers/Bookshelf/`; no password, hash or token in a file.

## With Claude

In `claude`, in the `bookshelf` folder:
```text
Change application/models/Auth/create_db.sql so that username is varchar(254), then run it with /caligrafy-quill:db.
People of Bookshelf sign in with their email, kept in the username column, in lower case: in
application/services/Users/CaligrafyRegisterUser.php use required|valid_email|max_len,254 with the filter trim|lower_case,
and "An account already uses this email" for a taken address; in CaligrafyUpdateUser.php valid_email|max_len,254 with the
same filter. In application/views/Auth/login.pug label the field Email, type='email', maxlength 254, keeping
name='username'.
```
then:
```text
Turn on people and permissions in Bookshelf. In web.php: uncomment Auth::authentication('User', 'users'); replace the
commented "Auth Routes" block with one line, Route::auth(['home' => '/books', 'register' => false, 'messages' =>
['invalid' => 'Invalid email or password']]) (no AuthController routes); add Route::page('/people', 'Bookshelf/people', ['level' => 1, 'comeBack' => true, 'data' => ['people' =>
'list_users']]) after the Route::download line, and its view (title band "People", one cq-row per person: username,
"Administrator" when permissions >= 1 else "Reader", and created_at).
A join form, with no capability of our own: Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']) and
Route::form('/join', 'register_user', with visitorsOnly '/books'; same ['passcode_again' => ['passcode', 'The two
passwords are not the same']]; accepted ['rules' => 'Accept the rules of the club to join']; signsIn 'id'; success
'/books'; flash 'Welcome to the club, {username}'). Its view join.pug: main.cq-auth, =csrf(), the field username labeled
Email with type='email' and maxlength 254, passcode, passcode_again and the checkbox rules with value 1, each with errors(), old() for the username and the box,
flash('error') under the form, a link to /login.
Capabilities: remove public: true from create_book and update_book; delete_book gets level: 1 in its place; the reads
stay public. Views: books.pug shows its form only if $authorized, else a "Sign in to add books" button to /login and a
"Join the club" button to /join; book.pug shows the edit form only if $authorized and the delete form only if
$user['permissions'] >= 1, else a "Sign in to edit this book" button to login?return=/books/<id>.
Regenerate public/js/capabilities.js and public/bookshelf-spa/src/capabilities.js. Do not create tokens or users.
```

**Check** before you accept:
- [ ] The protection is in the capabilities, not only in the views: the visitor curl of step 6 answers 401.
- [ ] `web.php` has `Route::auth([...])` with `'register' => false` and no `AuthController` route; no controller; the
      join form is `Route::page` and `Route::form` and no new capability.
- [ ] The passwords and the box are checked by `same` and `accepted`, not by JavaScript or by a new capability.
- [ ] The address is in the `username` column, with `valid_email`, `max_len,254` and `lower_case`: no new `email` column,
      and the field of the forms is still named `username`.
- [ ] The views use `$authorized` and `$user`, and no PHP block that reads the session.
- [ ] No password, hash or token in the chat or a file.
- [ ] Steps 6 to 8 give the same results: do them yourself, they are the proof.

If Claude writes a controller that checks `guard()` and leaves `public: true` in the capabilities, the page is safe but
REST, the agents and the SPA are not: ask it to close the capabilities instead, and to declare the page. If it adds a
capability for the join form, ask why: `register_user` already does it. If it gives the librarian token no
`--user`, the level does not apply to it.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 11: sign-in, scopes, levels and tokens"
git tag lesson-11
```
Reference: [`checkpoints/lesson-11`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-11).
After copying a checkpoint, run the `sql` command of step 1 (the users table) and join again. Learn more:
[9.1 Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication) and the tokens section of
[11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Exercise

1. Add a page `/mine` that only signed-in people may open, with the title "Welcome, <username>". A visitor who opens it
   must come back to it after signing in. Which options of `Route::page` do you use, and where does the username come
   from?
2. A token created with `--scopes books:read,books:write` and no `--user` deletes a book. reader1, signed in with
   permissions 0, cannot. Why?

<details><summary>Show the solution</summary>

1. `signedIn` and `comeBack`, and the `$user` that every `Route::page` view gets. In `web.php`, in `// BOOKSHELF PAGES`:
   ```php
       Route::page('/mine', 'Bookshelf/mine', ['signedIn' => true, 'comeBack' => true]);
   ```
   and `application/views/Bookshelf/mine.pug`:
   ```pug
   extends ../default/master/site

   block content
       section.cq-hero.cq-hero--page
           h1.cq-display Welcome, #{$user['username']}
   ```
   A visitor who opens http://localhost:8080/mine goes to `/login?return=%2Fmine`, and after signing in reader1 sees
   `Welcome, reader1@example.com`. The page needs no `data`: `$user` is there on every page. (Not in the checkpoint: delete both before you commit.)
2. The level applies to people, and to tokens created with `--user`. A token without `--user` is an application token:
   only its scopes count, and it has `books:write`. reader1 is a person with permissions 0, below the `level: 1` of
   `delete_book`.
</details>

---
Previous: [Lesson 10 - Phones and tablets](10-Phones-and-Tablets) · Next: [Lesson 12 - Ship it: tests, review, doctor](12-Ship-It)
