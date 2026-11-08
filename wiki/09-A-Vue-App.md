# Lesson 09 - A Vue app

**In this lesson** you build **finder**, a small Vue app served by Caligrafy at http://localhost:8080/apps/finder: you
type a few words, the list of books changes as you type, and you open a book to read about it and its author. You
generate the **JS client** of Bookshelf, one JavaScript function per capability, and the app calls `list_books`,
`search_books` and `get_book` through it. The server changes by one line of `web.php`, which declares the address of
the client apps; no capability is written.

**Before you start:** lesson 08 is done (tag `lesson-08`) and `docker compose ps` shows the 3 containers running.

**Time:** 40 minutes.

---

## New concepts

**Vue.js.** A JavaScript library that keeps a part of the page in sync with your data. You write the HTML once with a
few special attributes; when the data changes, Vue updates the page.

| In the HTML | Meaning |
|---|---|
| `{{ book.title }}` | the value of `book.title`, as text (escaped, so it is safe) |
| `v-for="book in books" :key="book.id"` | repeat this element for each book; `:key` tells Vue which row is which |
| `v-if="selected"` / `v-else` | show this element only when `selected` has a value / otherwise the next one |
| `v-model="query"` | the input and `query` always hold the same value |
| `@input="search"` | on every key typed, call the method `search` |
| `@click="open(book.id)"` | on a click, call `open` with the id |
| `@submit.prevent` | when the form is submitted, do not reload the page |
| `v-cloak` | hides the element until Vue is ready (no flash of `{{ }}`) |

In the JavaScript, `Vue.createApp({ data(), mounted(), methods })` defines the app: `data()` returns the data,
`mounted()` runs once when the app appears, `methods` are the functions the HTML calls. `app.mount('#app')` attaches it
to `<div id="app">`.

**A client app.** The simplest way to use Vue: a `<script>` tag loads it, with no build step and no Node.js. In
Caligrafy a client app is a folder `public/<name>/` with `index.php` (the HTML) and `scripts/main.js` (the Vue code);
`php caligrafer.php app <name>` creates it. `web.php` shows every client app at `/apps/<folder>`. In `index.php`, `loadEnvironment()` creates the object `env` (`env.home` is the address of the
application, `http://localhost:8080/`), and `loadVue({ scripts: ['main'] })` loads `scripts/main.js`. Lesson 10 builds
the other kind of Vue app, with Vite.

**Why `app.js` and your scripts carry a version.** A browser keeps the scripts it downloaded (its **cache**), so a visitor
could go on running last week's code after you deploy a new version. A client app therefore adds a number to the address
of each script, `?v=<number>`. When the number changes, the browser sees a new address and downloads the file.

| Where | What it versions | Where the number comes from |
|---|---|---|
| the `app.js` line of `index.php`: `APP_SERVICE_ROOT.'app.js?v='.@filemtime(PUBLIC_PATH.'js/services/app.js')` | `app.js` itself | the time the file `public/js/services/app.js` was last written |
| `loadVue({ scripts: ['main'] })` in `app.js` | your `scripts/main.js`, as `main.js?v=...` | `env.scripts_version`: the same time, which the view `Client/app` puts in `env` |

The first line is what makes the second one work: `loadVue()` lives in `app.js`, so a browser holding an old `app.js` would
never add a version to your scripts. A deploy starts from a fresh copy of your repository (lesson 14), so the time, and
the number, change at each deploy. On your computer the number changes only when `app.js` does: after you edit `main.js`,
reload with <kbd>Shift</kbd> pressed.

**A page for every client app.** The framework's view `Client/app` shows the app `public/<appName>/<app>.php`. So a
client app is a `Route::page` like any other:
```php
Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);
```
`{appName}` is the folder. `'query' => ['app' => 'index']` is a query option with a **default**: the view gets `$app`,
the value of `?app=` or else `index`. The template's `web.php` serves this address with a controller line,
`Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');`. It works, and you replace it: this way,
the route says what the page receives, and nothing else of the address reaches it.

**The JS client.** `php caligrafer.php client` reads your capabilities and writes `public/js/capabilities.js`: one
function per capability, named in camelCase (`list_books` becomes `listBooks`). Each function posts to
`/capabilities/<name>` (the `Route::capabilities` of lesson 07) and returns the `data` of the answer; a refusal throws a
`CapabilityError` with `status`, `code`, `message` and `fields`. It is generated: run the command again after you add or
change a capability, and never edit the file.

| Capability | Function | What it returns |
|---|---|---|
| `list_books` | `listBooks({ author_id, page })` | a page: the books are in `.data`, with `total`, `page`, `per_page`, `pages` |
| `search_books` | `searchBooks({ query })` | a list of books |
| `get_book` | `getBook({ id })` | one book, its author in `.author` (`book.author.name`) |

**No key in the page.** In a page of the application, the client needs no token and no key: the browser sends the
**session cookie** with each call, and the client asks `GET /capabilities` once for the **CSRF token** (a random value
the server gives its own pages, so that another site cannot send requests in your name) and sends it in the header
`X-CSRF-Token`. Lesson 11 builds sign-in on this session.

**Dynamic import.** The client is an **ES module** (a file that `export`s functions). Vue as a library runs `main.js`
as a classic script, where `import ... from` is not allowed, but the function `import()` is:
```js
this.api = await import(env.home + 'public/js/capabilities.js')
```
`this.api` then holds every function: `this.api.listBooks()`, `this.api.searchBooks({ query })`.

---

## Steps

Run every command from the `bookshelf` folder.

### Step 1 - Generate the JS client

```bash
docker compose exec dev-box php caligrafer.php client
```
Expected:
```text
 public/js/capabilities.js written: 20 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
 import { createBook, CapabilityError } from './capabilities.js'
```
20: your 7 and 13 of the framework (the accounts, the notifications and the queue: a function for each, which a visitor
cannot call). The second line shows how a module imports it. Check one function:
```bash
grep -n "export const searchBooks" public/js/capabilities.js
```
Expected: `175:export const searchBooks = (input = {}) => call('search_books', input)`. Above it, a comment repeats the
capability's description and the types of its inputs, which your editor shows as you type.

### Step 2 - Create the app

```bash
docker compose exec dev-box php caligrafer.php app finder
```
Expected:
```text
 Project created in the public folder.
 Type cd public/finder to access it anytime
```
It copied a template to `public/finder/`: `index.php` and `scripts/main.js`.

In `application/config/routing/web.php`, replace the line of `// ROUTING TO JAVASCRIPT APPS` that names `ClientController`
with the `Route::page` of the concepts above. The file is then:

**File `application/config/routing/web.php`** (`Route::page('/apps/{appName}', ...)` in place of the controller line):

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
    // Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::redirect('/', '/books');   // the home of Bookshelf is its catalog
    Route::redirect('/catalog', '/books', 301, ['keepQuery' => true]);   // the catalog's old address: the filter of its address goes along
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // Auth Routes - Uncomment only if AUTHENTICATION activated above
    // Route::get('/home', 'Caligrafy\\Controllers\\AuthController@home');
    // Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    // Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
	// Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');
    // Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    // Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    // Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');

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
    Route::page('/books', 'Bookshelf/books', ['query' => ['author_id'], 'data' => ['books' => 'list_books', 'authors' => 'list_authors']]);
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file

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

Open http://localhost:8080/apps/finder: the template already uses the client and Caligrafy's look; under the title
*My Vue app* it lists the capabilities you may call. You replace both files.

### Step 3 - The HTML

**File `public/finder/index.php`** (the look of the pages: the top bar, a title band, then the search and the rows beside the book):

```php
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>Bookshelf finder</title>

        <!-- The same look as the Caligrafy pages: Bootstrap 5, the tokens, the structure (layout.css) and the look (look.css) -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/tokens.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/layout.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/look.css'; ?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>
    </head>

    <body class="cq-site">
        <!-- Vue controls everything inside #app -->
        <div id="app" v-cloak>
            <!-- the top bar of the Caligrafy pages -->
            <nav class="cq-nav" aria-label="Main">
                <a class="cq-nav-brand" href="<?php echo session('home'); ?>"><img class="cq-nav-mark" src="<?php echo session('public').'images/resources/caligrafy-mark.svg'; ?>" alt="" width="28" height="28"><span class="semibold">Cali</span>grafy</a>
            </nav>

            <!-- the title band -->
            <section class="cq-hero cq-hero--page">
                <h1 class="cq-display">Find a book</h1>
                <p class="cq-lead">Type a few words of a title or a summary: the search_books capability answers as you type.</p>
            </section>

            <!-- side by side from tablets up: the search and the books found, the book opened (get_book) -->
            <div class="cq-split">
                <section class="cq-split-main">
                    <form @submit.prevent>
                        <div class="cq-field">
                            <label for="query">Search</label>
                            <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time, cities..." autocomplete="off">
                        </div>
                    </form>
                    <p class="cq-field-error" role="alert">{{ error }}</p>

                    <ul class="cq-rows">
                        <li class="cq-row" v-for="book in books" :key="book.id">
                            <div class="cq-row-main">
                                <p class="cq-row-title">{{ book.title }}</p>
                                <p class="cq-row-meta">{{ book.author.name }}, {{ book.published_year }}</p>
                            </div>
                            <div class="cq-row-actions">
                                <button type="button" class="btn btn-secondary" @click="open(book.id)">Open</button>
                            </div>
                        </li>
                    </ul>
                    <p class="cq-empty-state" v-if="loaded && !books.length && !error">{{ query ? 'No book matches "' + query + '".' : 'No book yet.' }}</p>
                    <p class="cq-auth-note" v-if="loaded">{{ books.length }} book(s)</p>
                </section>

                <aside class="cq-split-detail" aria-live="polite">
                    <template v-if="selected">
                        <h2 class="cq-heading">{{ selected.title }}</h2>
                        <p class="cq-lead">by {{ selected.author.name }}, {{ selected.published_year }}</p>
                        <p class="cq-lead">{{ selected.summary || 'No summary yet.' }}</p>
                        <p class="cq-row-meta">{{ selected.author.bio }}</p>
                    </template>
                    <p class="cq-empty-state" v-else>Open a book to read about it.</p>
                </aside>
            </div>
        </div>

        <!-- Vue 3 (pinned by Caligrafy), then app.js, which defines loadEnvironment() and loadVue() -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js?v='.@filemtime(PUBLIC_PATH.'js/services/app.js'); ?>"></script>
        <!-- $env holds what the server gives the app: env.home is the address of the application -->
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <!-- loads scripts/main.js of this folder -->
        <script>loadVue({ scripts: ['main'] });</script>
    </body>
</html>
```

What to notice:
- `nav.cq-nav` is the same top bar as the Pug pages. It stays put while the list grows and shrinks as you type: `layout.css`
  reserves the room of the scrollbar.
- The `app.js` line and `loadVue(...)` near the end are the template's: keep them as they are. They are the two versions
  of the concepts above.
- The `<link>` tags load the same CSS as the Pug pages: `theme.css`, `tokens.css` (the design tokens: colors, spacing,
  fonts as CSS variables), `layout.css` (the page components: `cq-nav`, `cq-hero`, `cq-rows`, `cq-split`...) and
  `look.css`. The app looks like the catalog.
- `.cq-split` puts `.cq-split-main` (search and rows) and `.cq-split-detail` (the book opened) side by side from
  tablets up, one under the other on phones.
- Each row shows `book.author.name`: `list_books` and `search_books` give each book its author. The panel shows
  `selected.author.bio`, from `get_book`.

### Step 4 - The Vue code

**File `public/finder/scripts/main.js`** (loads the client, then calls three capabilities):

```js
// The finder: a Vue app on the same capabilities as the pages, REST and AI agents
const app = Vue.createApp({
  data () {
    return {
      query: '',
      books: [],
      selected: null,   // the book of get_book, with its author
      error: '',
      timer: null,
      loaded: false     // true once the first list has arrived
    }
  },
  async mounted () {
    // the generated JS client: one function per capability (php caligrafer.php client)
    this.api = await import(env.home + 'public/js/capabilities.js')
    await this.run(async () => { this.books = (await this.api.listBooks()).data })
    this.loaded = true
  },
  methods: {
    // called on every key: wait until the typing pauses, then ask search_books
    search () {
      clearTimeout(this.timer)
      this.timer = setTimeout(() => this.run(async () => {
        this.books = this.query.trim().length < 2
          ? (await this.api.listBooks()).data
          : await this.api.searchBooks({ query: this.query })
      }), 250)
    },
    open (id) {
      this.run(async () => { this.selected = await this.api.getBook({ id }) })
    },
    // every call: clear the error, run it, show its message if it is refused
    async run (action) {
      this.error = ''
      try {
        await action()
      } catch (e) {
        this.error = e.name === 'CapabilityError' ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong. Try again.'
      }
    }
  }
})

app.mount('#app')
```

What to notice:
- `mounted()` loads the client, then shows the first page of `listBooks()`: the books are in `.data`.
  `searchBooks()` returns a plain list, so it needs no `.data`.
- `loaded` turns true once the first list has arrived. Until then the page shows no count and no "no book" message:
  nothing has been searched yet.
- `search()` waits until the typing pauses for 250 ms before it calls the server: one call per pause, not per key.
  Under 2 characters it lists the books instead of calling `search_books`, whose rule asks for 2 or more.
- `run()` wraps every call: a refusal is a `CapabilityError` with the messages of the capability's rules, the same that
  the REST API and the agent got.

### Step 5 - Try it

Open http://localhost:8080/apps/finder (reload it if it was open).
1. Under **Find a book**, the rows list every book with its author and year; the note under them says `8 book(s)` if
   you followed every lesson. The grey panel says `Open a book to read about it.`
2. Type `time` and pause: only **Kindred** stays, `1 book(s)`.
3. Click **Open**: the panel shows *Kindred*, `by Octavia E. Butler, 1979`, the summary, then the author's bio.
4. Type `zzzz`: `No book matches "zzzz".` and `0 book(s)`. Delete the letters down to `a`: every book is back.
5. Make the window narrow (or the browser's device toolbar at 375 px): one column, the panel after the list, no
   horizontal scroll.

See what the app sends: open the developer tools (<kbd>F12</kbd>), tab **Network**, reload, type `magic`. In order:
`GET capabilities.js` (the client), `GET capabilities` once (the CSRF token), `POST list_books`, then
`POST search_books`. Click one: it has an `X-CSRF-Token` header and no `Authorization` header. The first lines of the
list show `app.js?v=...` and `main.js?v=...`. From the terminal, the number the page uses:
```bash
curl -s http://localhost:8080/apps/finder | grep -oE 'app\.js\?v=[0-9]+|"scripts_version":[0-9]+'
```
Expected: the same number twice (yours differs):
```text
app.js?v=1794163411
"scripts_version":1794163411
```

Check what changed on the server:
```bash
git status --short
```
Expected:
```text
 M application/config/routing/web.php
?? public/finder/
?? public/js/capabilities.js
```
The one line of `web.php` is the only change in `application/`.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | The page shows `{{ book.title }}` or stays empty | Vue did not start: an error in `main.js` | The Console (<kbd>F12</kbd>) names the file and the line; compare with step 4 |
> | Console: `Failed to fetch dynamically imported module` and a 404 for `capabilities.js` | Step 1 was skipped | Run the `client` command of step 1 |
> | Console: `this.api.searchBooks is not a function` | The client was generated before `search_books` existed | Run the `client` command again |
> | `Something went wrong. Try again.` under the search field | The server did not answer JSON (containers stopped, a PHP error) | `docker compose ps`; call `search_books` with the curl command of lesson 08 and read the answer |
> | The rows show `, 1979` with no name | `main.js` or `index.php` reads `book.author_name` or an older shape | Each book has `author` with `name`: `book.author.name` |
> | Changes to `main.js` do not show | The browser kept the old file: the version only changes with `app.js` and at each deploy | Reload with <kbd>Shift</kbd> pressed, or tick *Disable cache* in the Network tab |
> | After a deploy, some visitors still run the old `main.js` | The `app.js` line of `index.php` has no `?v=` | Compare with step 3 |
> | `This client app does not exist. ...` on `/apps/finder` | The folder `public/finder` is missing, or `?app=` names a file that is not in it | Run step 2; open the address without `?app=` |
> | `The project could not be created` | The name is reserved (`app`, `bot`, `css`, `js`, `ml`...) | Choose another name |
> | `public/finder already exists: nothing was created` | You ran `app finder` a second time | Nothing to do: your files are untouched. To start over, delete `public/finder` first |

---

## Check

- [ ] `web.php`, `public/finder/index.php` and `public/finder/scripts/main.js` are identical to the File blocks.
- [ ] `public/js/capabilities.js` was written by the `client` command (20 capabilities) and not edited.
- [ ] `git status --short` shows the two new paths and `web.php`: no other change in `application/`.
- [ ] No `apiKey`, no `axios`, no `Authorization` in `public/finder/`.
- [ ] `index.php` loads `app.js?v=...`, and the Network tab shows `main.js?v=...` with the same number.
- [ ] `time` finds Kindred; Open shows the author's bio; at 375 px the page is one column.

## With Claude

In the `bookshelf` folder, start `claude` and type:
```text
/caligrafy-quill:new-app app finder
```
then:
```text
Turn public/finder into a Vue 3 app (Vue as a library) that finds books with the Bookshelf capabilities. First run
php caligrafer.php client. No axios, no API key: in mounted() load the client with
this.api = await import(env.home + 'public/js/capabilities.js'), then show (await this.api.listBooks()).data
(list_books returns a page). A search field calls searchBooks({ query }) 250 ms after the typing pauses (listBooks()
under 2 characters). Each row shows the title and "<book.author.name>, <year>" and has an Open button that calls
getBook({ id }) and shows, in a panel beside the list, the title, "by <author>, <year>", the summary (or "No summary
yet.") and the author's bio. Show a CapabilityError's field messages (or its message) in p.cq-field-error. Use
Caligrafy's look like the template: body.cq-site, nav.cq-nav, section.cq-hero.cq-hero--page "Find a book", then
.cq-split with section.cq-split-main (the search .cq-field, ul.cq-rows, p.cq-empty-state "No book matches "<query>"." and p.cq-auth-note "N book(s)", both shown only once the first list has arrived)
and aside.cq-split-detail. Keep the script lines of the template as they are: app.js with its ?v= and loadVue. In web.php replace the Route::get('/apps/{appName}', ...ClientController) line with
Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]); add no other route or server code.
```

**Check** before you accept:
- [ ] Claude ran `php caligrafer.php client` and did not edit `public/js/capabilities.js` by hand.
- [ ] The app calls only `listBooks`, `searchBooks` and `getBook`, through `import(env.home + 'public/js/capabilities.js')`.
- [ ] `git status --short` shows only `public/finder/`, `public/js/capabilities.js` and `web.php`, and `git diff
      application` is that one line.
- [ ] No `apiKey`, `axios` or `Authorization` header; no `cq-card`.
- [ ] `index.php` still has `app.js?v=` and `loadVue({ scripts: ['main'] })`.
- [ ] Step 5 works.

If Claude writes `import { searchBooks } from ...` at the top of `main.js`, the app does not start (a classic script
cannot use `import ... from`): ask for the dynamic `import()`. If it adds a route such as `/finder`, ask it to remove
it: `/apps/finder` is enough.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 09: the finder Vue app"
git tag lesson-09
```
Reference: [`checkpoints/lesson-09`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-09).
Learn more: [9.7.3 Requests](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests) (calling capabilities
from a Vue app).

## Exercise

Add a select **Author** under the search field. Choosing an author shows only that author's books (with `list_books`
and its `author_id`); choosing "Every author" shows them all again. Still no server code.

<details><summary>Show the solution</summary>

In `public/finder/index.php`, add a field in the form, after the Search field:
```html
                        <div class="cq-field">
                            <label for="author">Author</label>
                            <select id="author" class="form-select" v-model="authorId" @change="filter">
                                <option value="">Every author</option>
                                <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                            </select>
                        </div>
```
In `public/finder/scripts/main.js`, add `authors: []` and `authorId: ''` to `data()`, load the authors in `mounted()`,
and add the method `filter`:
```js
  async mounted () {
    this.api = await import(env.home + 'public/js/capabilities.js')
    this.run(async () => {
      this.authors = await this.api.listAuthors()
      this.books = (await this.api.listBooks()).data
    })
  },
```
```js
    filter () {
      this.query = ''
      this.run(async () => {
        this.books = (await this.api.listBooks(this.authorId ? { author_id: this.authorId } : {})).data
      })
    },
```
Choose Octavia E. Butler: `3 book(s)`. `list_authors` and `list_books` existed since lesson 04: the server learns
nothing new. To stay identical to the checkpoint, undo it with `git checkout public/finder`.
</details>

---
Previous: [Lesson 08 - AI agents](08-AI-Agents) · Next: [Lesson 10 - Phones and tablets](10-Phones-and-Tablets)
