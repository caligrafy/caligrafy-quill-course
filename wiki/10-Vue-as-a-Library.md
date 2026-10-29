# Lesson 10 - A Vue app inside Caligrafy

**In this lesson** you build the **catalog**: a page at http://localhost:8080/catalog where Vue.js lists the books
from the API, adds a book without reloading the page, shows the API's error messages under the fields, and deletes
books. It is served by Caligrafy and uses the same black and white look.

**Before you start:** lesson 09 is done (tag `lesson-09`): `GET /api/books` answers with the API key.

---

## New concepts

**Vue.js.** A JavaScript library that keeps the page in sync with your data. You describe the HTML once with
special attributes, and when the data changes, Vue updates the page:

| In the HTML | Meaning |
|---|---|
| `{{ book.title }}` | the value of `book.title`, as text (escaped) |
| `v-for="book in books" :key="book.id"` | repeat this element for each book |
| `v-if="signedIn"` / `v-else` | show this element only when `signedIn` is true / otherwise |
| `v-model="form.title"` | the input and `form.title` always have the same value |
| `@click="remove(book)"` | on click, call the method `remove` |
| `@submit.prevent="add"` | on submit, call `add` and do not reload the page |
| `:href="env.home + 'login'"` | the attribute `href` is a JavaScript expression |

In the JavaScript, `Vue.createApp({ data(), methods, mounted() })` defines the app: `data()` returns the data,
`methods` the functions, `mounted()` runs once when the app appears. `app.mount('#app')` attaches it to the
element `<div id="app">`.

**"Vue as a library".** The simplest way to use Vue: a `<script>` tag loads Vue (no build step, no Node.js).
Caligrafy calls this a **client app**: a folder `public/<name>/` with an `index.php` (the HTML) and
`scripts/main.js` (the Vue code), created by `php caligrafer.php app <name>`.

**How Caligrafy serves a client app.** The framework view `Client/app` includes `public/<appName>/index.php` and
prepares a JavaScript object **`env`** with what the app needs from the server: `env.home` (the base URL),
`env.token` (the CSRF token), `env.appName`, every variable you pass to the view, and the global variable
**`apiKey`**. A ready-made route `/apps/{appName}` already shows any client app (it is in `web.php` since
lesson 02); we add our own route `/catalog`, served by a small controller, because we want to pass one more value:
`signedIn` (is a user signed in?). Rule: the path of the route must **end with the folder name** of the app.

**Why a controller and not a closure?** Caligrafy opens the database connection when a **controller** is created.
`authorized()` reads the signed-in user from the database, so in a closure route it fails with
`[ERROR 2003] Database Query Error` as soon as someone is signed in (for visitors it works, because there is no user
to read). Any route that uses the database, `authorized()` or `user()` must be a controller route.

**axios.** A JavaScript library to call APIs: `axios.get(url, config)`, `axios.post(url, data, config)`... It
returns a *promise*: `.then(response => ...)` runs with the answer (`response.data` is the JSON),
`.catch(error => ...)` runs on a network error **or a 4xx/5xx status** (`error.response.status`,
`error.response.data`). Every call sends the headers `Authorization: Bearer <apiKey>` and
`Accept: application/json` (lesson 09). The session cookie goes along by itself (same site), so a signed-in user can
change books.

**The API key is visible.** Anyone can open the page source of `/catalog` and read `apiKey`. That is why lesson 09
made changes require a signed-in user. Never put a secret that must stay secret (a payment key, a database
password) in a client app.

---

## Manual track

### Step 1 - Create the app folder

```bash
docker compose exec dev-box php caligrafer.php app catalog
```
Expected output:
```
 Project created in the public folder.
 Type cd public/catalog to access it anytime
```
It copied the template `framework/plugins/app` to `public/catalog/`: `index.php` and `scripts/main.js`. Open
http://localhost:8080/apps/catalog: the template app shows one long line of JSON (it calls the home page `/` with
axios and the API key, and prints the answer: the books). We replace both files.

### Step 2 - The HTML of the app

**File `public/catalog/index.php`** - replace its whole content with:

```php
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bookshelf catalog</title>

        <!-- The same stylesheets as the Caligrafy pages: Caligrafy styles, Bootstrap 5, the black and white theme -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css'; ?>">
    </head>

    <body>
        <!-- Vue controls everything inside #app -->
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>

            <div class="content-section bounded-xlarge">
                <div class="wrapper width-max-600">
                    <div class="spacer-4"></div>
                    <h2 class="medium-text semibold horizontal-center">Bookshelf catalog</h2>
                    <p class="horizontal-center">{{ books.length }} books, loaded from the API</p>
                    <p class="error horizontal-center" v-if="message">{{ message }}</p>
                    <div class="spacer-2"></div>

                    <!-- the list: one card per book -->
                    <div class="card color0 dropshadow equally-spaced-1" v-for="book in books" :key="book.id">
                        <h3 class="medium-text semibold textcolor5 reset-margin">{{ book.title }}</h3>
                        <p class="default-text textcolor5">{{ book.published_year }}</p>
                        <button class="btn btn-secondary" v-if="signedIn" @click="remove(book)">Delete</button>
                    </div>

                    <div class="spacer-2"></div>

                    <!-- the form: only for signed-in users, because the API refuses changes from visitors -->
                    <div class="card color0 dropshadow equally-spaced-1" v-if="signedIn">
                        <h3 class="medium-text semibold reset-margin">Add a book</h3>
                        <div class="spacer-1"></div>
                        <form @submit.prevent="add">
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="title">* title</label>
                                <input class="form-control" id="title" type="text" v-model="form.title">
                                <p class="error" v-if="errors.title">{{ errors.title }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="author_id">* author</label>
                                <select class="form-select" id="author_id" v-model="form.author_id">
                                    <option value="">Choose an author</option>
                                    <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                                </select>
                                <p class="error" v-if="errors.author_id">{{ errors.author_id }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="published_year">* year</label>
                                <input class="form-control" id="published_year" type="number" v-model="form.published_year">
                                <p class="error" v-if="errors.published_year">{{ errors.published_year }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="summary">summary</label>
                                <textarea class="form-control" id="summary" rows="3" v-model="form.summary"></textarea>
                                <p class="error" v-if="errors.summary">{{ errors.summary }}</p>
                            </div>
                            <input class="btn btn-primary" type="submit" value="Add">
                        </form>
                    </div>
                    <p class="horizontal-center" v-else>
                        <a class="btn btn-secondary" :href="env.home + 'login'">Sign in to add books</a>
                    </p>
                    <div class="spacer-2"></div>
                </div>
            </div>

            <footer>
                <p class="small-text semi-bold horizontal-center">Copyright &copy; {{ new Date().getFullYear() }}</p>
            </footer>
        </div>

        <!-- Vue 3 and axios (pinned by Caligrafy), then app.js, which defines loadEnvironment() and loadVue() -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <!-- $env holds the data the server gives the app (home URL, API key, CSRF token, signedIn...) -->
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <!-- loads scripts/main.js of this folder -->
        <script>loadVue({ scripts: ['main'] });</script>
        <script src="<?php echo scripts('bootstrap_script'); ?>"></script>
    </body>
</html>
```

- The header uses `class="color0 caligrafy-header"`: `caligrafy-header` gives the title the same margins as the
  pages of the Pug layout, without any CSS of your own.
- `v-cloak` hides the Vue markup (the `{{ }}`) until Vue has started.
- The scripts at the end load Vue and axios (versions pinned by Caligrafy: `scripts('vue')`, `scripts('axios')`),
  then `app.js`, whose `loadEnvironment()` creates `env` and `apiKey`, and `loadVue()`, which loads
  `public/catalog/scripts/main.js`.

### Step 3 - The Vue code

**File `public/catalog/scripts/main.js`** - replace its whole content with:

```js
// The Bookshelf catalog: a Vue 3 app that reads and changes the books through the Caligrafy REST API
const app = Vue.createApp({
    data() {
        return {
            env: env,                     // set by loadEnvironment() in index.php
            signedIn: env.signedIn === true,
            books: [],
            authors: [],
            form: { title: '', author_id: '', published_year: '', summary: '' },
            errors: {},
            message: '',
            // every API call sends the API key and asks for JSON
            config: {
                headers: {
                    'Authorization': 'Bearer ' + apiKey,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            }
        };
    },

    methods: {
        // GET /api/books
        loadBooks() {
            axios.get(this.env.home + 'api/books', this.config)
                .then(response => { this.books = response.data.books; })
                .catch(error => { this.showError(error); });
        },

        // GET /api/authors (for the select of the form)
        loadAuthors() {
            axios.get(this.env.home + 'api/authors', this.config)
                .then(response => { this.authors = response.data.authors; })
                .catch(error => { this.showError(error); });
        },

        // POST /api/books: 201 with the new book, or 422 with one message per invalid field
        add() {
            this.errors = {};
            this.message = '';
            axios.post(this.env.home + 'api/books', this.form, this.config)
                .then(() => {
                    this.form = { title: '', author_id: '', published_year: '', summary: '' };
                    this.loadBooks();
                })
                .catch(error => {
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    } else {
                        this.showError(error);
                    }
                });
        },

        // DELETE /api/books/{id}
        remove(book) {
            if (!confirm('Delete "' + book.title + '"?')) {
                return;
            }
            axios.delete(this.env.home + 'api/books/' + book.id, this.config)
                .then(() => { this.loadBooks(); })
                .catch(error => { this.showError(error); });
        },

        // framework and API errors answer {"error": {"code": ..., "message": "..."}}
        showError(error) {
            const data = error.response ? error.response.data : null;
            this.message = data && data.error ? data.error.message : 'The server could not be reached.';
        }
    },

    // runs once, when the app is on the page
    mounted() {
        this.loadBooks();
        this.loadAuthors();
    }
});

app.mount('#app');
```

- `config.headers` is sent with every call: the API key and the JSON `Accept`.
- `add()` reads the **422** answer of lesson 09 and puts `errors` in the data: the `<p class="error">` under each
  field appear by themselves.
- Every URL starts with `env.home`, never with `http://localhost:8080/`.

### Step 4 - The controller and the route

**File `application/controllers/Bookshelf/CatalogController.php`**:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

class CatalogController extends Controller {

    // GET /catalog : the Vue app of public/catalog, told whether someone is signed in
    public function index()
    {
        return view('Client/app', array(
            'appName' => 'catalog',          // the folder public/catalog
            'app' => 'index',                // the file public/catalog/index.php
            'signedIn' => authorized()       // arrives in the app as env.signedIn
        ));
    }
}
```

`view('Client/app', ...)` is the framework view that includes the client app; `appName` names the folder,
`app` the file (`index` = `index.php`), and every other key (`signedIn`) arrives in the app's `env`.

**File `application/config/routing/web.php`** - replace its whole content with:

```php
<?php
/**
 * web.php - every URL of the Bookshelf application is declared in this file
 */

use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {

    // API ACCESS: lets programs that send the API key call the controller routes (lesson 09)
    Auth::activateAPI();

    // AUTHENTICATION: users are rows of the users table, read with the User model (lesson 08)
    Auth::authentication('User', 'users');

    // ROUTES. The first route must stay '/': a URL that matches no route shows the first route.
    Route::get('/', 'Bookshelf\\BookController');
    Route::get('/helloworld', function() { echo "Hello World"; });

    // Lesson 02: our first routes
    Route::get('/about', function() {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function() {
        $name = request()->fetch('name');
        echo 'Hello ' . sanitize($name) . '!';
    });

    // Books and authors
    Route::get('/books', 'Bookshelf\\BookController');            // no @action: index()
    Route::get('/books/new', 'Bookshelf\\BookController@createForm');   // before /books/{id}
    Route::get('/books/{id}', 'Bookshelf\\BookController@read');
    Route::get('/books/{id}/edit', 'Bookshelf\\BookController@editForm');
    Route::post('/books', 'Bookshelf\\BookController@create');
    Route::put('/books/{id}', 'Bookshelf\\BookController@update');
    Route::delete('/books/{id}', 'Bookshelf\\BookController@remove');
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@read');

    // Sign in, sign out, register (controllers shipped with the framework)
    Route::get('/home', 'Bookshelf\\BookController');          // where the login and logout pages go
    Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
    Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');
    Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');

    // REST API (lesson 09): JSON only, called with the API key
    Route::get('/api/books', 'Bookshelf\\Api\\BookApiController');
    Route::get('/api/authors', 'Bookshelf\\Api\\BookApiController@authors');
    Route::get('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@read');
    Route::post('/api/books', 'Bookshelf\\Api\\BookApiController@create');
    Route::put('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@update');
    Route::delete('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@remove');

    // The Vue catalog (lesson 10): public/catalog, shown by a controller that passes signedIn to the app
    Route::get('/catalog', 'Bookshelf\\CatalogController');

    // Client apps (lesson 10): public/<appName> is shown at /apps/<appName>
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');

    // MUST STAY LAST: finds the route that matches the request and runs it
    Route::run();
}
catch (Exception $e) {

    // Errors get their HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
```

### Step 5 - Use the catalog

1. Sign out if needed, and open http://localhost:8080/catalog. You see the Caligrafy header, **Bookshelf
   catalog**, "5 books, loaded from the API", one card per book and the button **Sign in to add books**.
2. Click it, sign in as `reader1`, then open http://localhost:8080/catalog again. Each card now has a **Delete**
   button, and the form **Add a book** is at the bottom.
3. Click **Add** with the form empty: three red messages appear under the fields, **without reloading the page**.
4. Fill in `The Lathe of Heaven`, Ursula K. Le Guin, `1971`, and click **Add**: the form empties and the book
   appears in the list ("6 books").
5. Click **Delete** on it and confirm: it disappears.

![The Vue catalog](https://raw.githubusercontent.com/caligrafy/caligrafy-quill-course/main/docs/images/lesson-10-catalog.jpg)

Open the developer tools (<kbd>F12</kbd>), tab **Network**: each action is one `api/books` request; click it to
see the status (200, 201, 422) and the JSON answer. In the tab **Console**, the browser logs each 4xx answer as a red
line `Failed to load resource: the server responded with a status of 422`: that is the empty form of point 3, and it
is expected. Any other red line is a bug in `main.js`.

### Step 6 - Look at what the server gave the app

In the developer tools, tab **Console**, type `env.home` and press Enter: `'http://localhost:8080/'`. Type
`env.signedIn`: `true`. That is the `env` object of step 2.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `This client app does not exist...` | The route's `appName` differs from the folder name, or the folder is not `public/catalog` | `'appName' => 'catalog'` and the folder `public/catalog` |
> | The page shows `{{ book.title }}` literally | Vue did not start: an error in `main.js` (see the Console), or `loadVue({ scripts: ['main'] })` misses | Fix the error the Console names (often a missing `,` between methods) |
> | "Access Denied" in red | The `Authorization` header is missing in `config` | Keep `'Authorization': 'Bearer ' + apiKey` |
> | `[ERROR 2003] Database Query Error` on `/catalog` when signed in | The route is a closure that calls `authorized()` | Use the controller of step 4 |
> | "Sign in first" in red when adding | You are not signed in, or you opened the catalog on another address (`127.0.0.1` instead of `localhost`: cookies are per address) | Sign in at http://localhost:8080/login and use `localhost` everywhere |
> | Changes to `main.js` do not show | The browser cached the old file | Reload with <kbd>Shift</kbd> + reload, or tick *Disable cache* in the Network tab |
> | `The project could not be created` from caligrafer | The name is reserved (`app`, `bot`, `css`, `js`, `ml`, `images`...) or the folder exists | Choose another name, or delete the folder first |

---

## With Claude

```
/caligrafy-quill:new-app app catalog
```
Then:
```
Make public/catalog a Vue 3 catalog of the Bookshelf API, in the Caligrafy black and white look (header
color0 caligrafy-header, content-section bounded-xlarge, wrapper width-max-600, card color0 dropshadow
equally-spaced-1, btn-primary/btn-secondary, p.error). It lists GET /api/books, shows a form (title,
author select from GET /api/authors, published_year, summary) that POSTs to /api/books and shows the 422
errors under each field, and a Delete button (DELETE /api/books/{id}) - form and Delete only when
env.signedIn, otherwise a link to {env.home}login. All URLs from env.home; headers Authorization Bearer
apiKey and Accept application/json. Serve it at GET /catalog from a controller
Bookshelf\CatalogController@index (not a closure: authorized() needs the database connection that a controller
opens) that returns view('Client/app', ['appName' => 'catalog', 'app' => 'index', 'signedIn' => authorized()]).
```

**What Claude should create:** with the command, Claude runs `php caligrafer.php app catalog` (or copies
`framework/plugins/app`, which is the same) and wires a route. With the second prompt it writes `index.php`,
`scripts/main.js`, the controller and the `/catalog` route, close to steps 2 to 4.

**Check** before you accept:
- [ ] Every axios call sends `Authorization: Bearer ` + `apiKey` **and** `Accept: application/json`.
- [ ] URLs are built from `env.home`.
- [ ] Vue 3 syntax (`Vue.createApp`), not Vue 2 (`new Vue`).
- [ ] No Bootstrap colors (`btn-success`, `bg-dark`): Caligrafy classes.
- [ ] The route path ends with the folder name `catalog`, and it is a controller route (sign in, then open
      `/catalog`: a closure route answers `[ERROR 2003]`).
- [ ] Step 5 works, and the Console shows no errors.

**If Claude's result differs:** if you have no PHP on your computer, Claude may not be able to run `php
caligrafer.php` itself: ask it to use `docker compose exec dev-box php caligrafer.php app catalog`. Claude may keep
the template's `/apps/{appName}` route and read the signed-in state another way (for example an
`/api/me` route): it works, but then compare carefully with step 4. If Claude puts `v-html` anywhere, ask it to use
`{{ }}`: `v-html` does not escape.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 10: Vue catalog"
git tag lesson-10
```
Reference: [`checkpoints/lesson-10`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-10).

## Exercise

Add a search box above the list that filters the books by title as you type, without calling the API again.

<details><summary>Show the solution</summary>

In `index.php`, above the list (after `<div class="spacer-2"></div>`):
```html
                    <input class="form-control" type="text" placeholder="Search by title" v-model="search">
                    <div class="spacer-2"></div>
```
and replace `v-for="book in books"` with `v-for="book in filteredBooks"`. In `main.js`, add `search: ''` to
`data()`, and after `methods: { ... },` add:
```js
    computed: {
        filteredBooks() {
            const term = this.search.toLowerCase();
            return this.books.filter(book => book.title.toLowerCase().includes(term));
        }
    },
```
A **computed** value is recalculated by Vue whenever `search` or `books` changes.
</details>

---
Previous: [Lesson 09 - The REST API](09-The-REST-API) · Next: [Lesson 11 - The Vite SPA and Vue Router](11-Vite-SPA-and-Vue-Router)
