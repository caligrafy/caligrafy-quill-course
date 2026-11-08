# Lesson 10 - Phones and tablets

**In this lesson** Bookshelf reaches phones and tablets in two ways. First the catalog becomes an app people can
**install to the home screen**: a name in the web app manifest and one option on its `Route::page`. Then you build
**bookshelf-spa**, a Vue single page application made with Vite (a list, a form and a screen per book) that calls the
capabilities through its own generated client. Both use the design tokens and the page components of the Pug pages, so
the three experiences look alike. No capability, controller or rule is written.

**Before you start:** lesson 09 is done (tag `lesson-09`), `docker compose ps` shows the 3 containers running, and
Node.js is installed on your computer: `node --version` prints `v20.19` or newer (or `v22.12` or newer). Chrome or
Edge makes the install part easier to try.

**Time:** 60 to 75 minutes.

---

## New concepts

**Design tokens and page components.** Every experience of a Caligrafy app shares two stylesheets of `public/css/`:
- `tokens.css`: the **design tokens**, CSS variables such as `--cq-color-ink`, `--cq-space-4` (24px) or
  `--cq-touch-target` (44px). On a touch screen (`pointer: coarse`) buttons and fields are at least 44px high; installed
  to the home screen (`display-mode: standalone`) the page keeps clear of the notch and the rounded corners.
- `layout.css`: the **page components**, classes you already used: `cq-nav` (top bar), `cq-hero` (title band),
  `cq-band`, `cq-rows`, `cq-form-grid`, `cq-field`, and `cq-split` (one column on phones, two from 768px up).

The Pug layout links them, the finder of lesson 09 links them, and the SPA imports them: one look, everywhere.

**Install to home screen.** A browser can install a web application: it opens from its own icon, full screen, without
the address bar. The browser needs a **web app manifest**, `public/manifest.webmanifest`, a small JSON file with the
name, colors and icons; the Caligrafy layout already links it. Installing needs HTTPS, or `localhost` on your own
computer. A page offers the install when its view gets `install` set to true: the layout then adds an **Install**
button on Chrome, Edge and Android (when the browser says the app can be installed), or a short hint on iPhone and iPad
(Share, then Add to Home Screen).

**`with`.** `Route::page` gives a view the data of capabilities (`data`) and also fixed values (`with`):
`'with' => ['install' => true]` sets `$install` in the view.

**Single page application (SPA).** The browser loads one HTML page and one JavaScript program. After that, a link does
not ask the server for a new page: JavaScript changes the screen and calls the capabilities for the data. On a phone it
feels like an app.

| Word | What it is |
|---|---|
| Node.js, npm | JavaScript outside the browser, and its package installer. `npm install` downloads the libraries listed in `package.json` into `node_modules/` |
| Vite | the tool that runs and builds the SPA. `npm run dev` serves it on http://localhost:5173 and reloads the browser when you save a file |
| `.vue` file | one piece of the screen in one file: `<template>` (the HTML, with the Vue syntax of lesson 09) and `<script>` (its data and methods) |
| Vue Router | maps an address to a screen: `#/` shows `BooksPage`, `#/books/3` shows `BookPage` with `id` = `3`. `<router-link>` changes the screen without reloading; `<router-view>` is where the screen appears |
| hash history | addresses after a `#` (`index.html#/books/3`). The part after `#` never goes to the server, so the app works from any folder with no server setting |
| proxy | in development the SPA runs on port 5173 and Caligrafy on 8080. Vite **forwards** every call to `/capabilities` to `http://localhost:8080`: the browser talks to one site, with its cookies |
| build | `npm run build` turns the app into a few plain files in `dist/` that Caligrafy serves. No Node.js is needed to use them |

**The SPA's own client.** In a Vite app a normal `import { listBooks } from '../../capabilities.js'` works, so the SPA
gets its own copy of the client inside `src/`: `php caligrafer.php client public/bookshelf-spa/src/capabilities.js`.
Like the finder's, it uses the session and the CSRF token, never a key.

---

## Steps

Run every command from the `bookshelf` folder, unless a step says otherwise.

### Part 1 - Install the web app

### Step 1 - Name the app

Write the manifest. Only the first three values change from the file quickstart copied (`name` is shown when the app
starts, `short_name` under its icon on a phone):

**File `public/manifest.webmanifest`**:

```json
{
    "name": "Bookshelf",
    "short_name": "Bookshelf",
    "description": "The books we love",
    "id": "../",
    "start_url": "../",
    "scope": "../",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#ffffff",
    "icons": [
        { "src": "images/resources/icon-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
        { "src": "images/resources/icon-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },
        { "src": "images/resources/icon-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" },
        { "src": "images/resources/apple-touch-icon-180x180.png", "sizes": "180x180", "type": "image/png", "purpose": "any" }
    ]
}
```

`id`, `start_url` and `scope` are `../`: relative to the manifest, they are the root of the application, whatever its
address. The icons are Caligrafy's; replace the files of `public/images/resources/` with your own when you have them.

### Step 2 - Offer the install on the catalog

In `application/config/routing/web.php`, the first line of the `// BOOKSHELF PAGES` block gets `'with'`. The file is
then:

**File `application/config/routing/web.php`** (`'with' => ['install' => true]` on the catalog):

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
    Route::page('/books', 'Bookshelf/books', ['query' => ['author_id'], 'data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'with' => ['install' => true]]);   // install: offer to add it to the home screen
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

Check that the catalog now carries the install module:
```bash
curl -s http://localhost:8080/books | grep -o 'id="caligrafy-install"'
curl -s http://localhost:8080/books/3 | grep -c 'id="caligrafy-install"'
```
Expected: `id="caligrafy-install"`, then `0`: only the catalog offers the install. The module stays hidden until the
browser says the app can be installed.

### Step 3 - Install it

Open http://localhost:8080/books in Chrome or Edge on your computer. Install it with the **Install** button at the
bottom of the page, or with the install icon at the right of the address bar. Bookshelf opens in its own window,
without the address bar, and its icon is with your other apps. **Not now** hides the button in this browser for good.

A phone on your Wi-Fi cannot install it from your computer: it would open `http://<your computer's address>:8080`,
which is neither HTTPS nor `localhost`. Try it on a phone once Bookshelf is online with HTTPS (lesson 14).

### Part 2 - The SPA

### Step 4 - Create the SPA

```bash
docker compose exec dev-box php caligrafer.php create bookshelf-spa
```
Expected:
```text
 Project created in the public folder.
 Type cd public/bookshelf-spa to access it anytime


 VueJS project created, but its packages are NOT installed: npm is not available here (the Docker dev-box has no Node.js).
 Install them with Node.js 20.19+ or 22.12+ on your computer: cd public/bookshelf-spa && npm install

 - To run the development server: cd public/bookshelf-spa && npm run dev
 - To build it for production: npm run build (in the same folder)
```
Install the packages with the Node.js of your computer:
```bash
cd public/bookshelf-spa
npm install
cd ../..
```
Expected: `added 274 packages, and audited 275 packages` and `found 0 vulnerabilities` (the numbers can vary), and you
are back in `bookshelf`. Never run `npm audit fix --force`: it replaces ESLint 10 with an old major version.

### Step 5 - Set up Vite

Every path of the File blocks of steps 5 and 6 is relative to `public/bookshelf-spa`.

**File `vite.config.js`** (`base` for the build, `proxy` for development):

```js
import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  // relative paths in the built files: the app works from public/bookshelf-spa/dist/
  base: './',
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    // npm run dev: the calls of the JS client (/capabilities) go to Caligrafy, so the browser sees one single site
    proxy: {
      '/capabilities': 'http://localhost:8080'
    }
  }
})
```

**File `index.html`** (the one HTML page; only the title changes):

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link rel="icon" href="/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap 5 (same pinned version as scripts('bootstrap_css') / scripts('bootstrap_script') in Caligrafy) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer></script>

    <title>Bookshelf</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
```

Keep the template's `src/main.js`: it imports Caligrafy's CSS (`tokens.css`, `layout.css`, `look.css`) and adds
`cq-site` to the `<body>`, so the screens get the look of the pages. Keep `src/components/global/ShowNavigation.vue`
too: it is the top bar. (`main.js` also prepares axios, Pinia and `apiHeaders()` for calling JSON addresses with
the session; this SPA uses the generated client instead.)

### Step 6 - The screens

**File `src/App.vue`** (the frame of every screen: the top bar, the screen, the footer):

```html
<!-- The frame of every screen: the Caligrafy top bar, the page of the current address, then the footer
     (:key loads the page again when only the id changes, from #/books/1 to #/books/2) -->
<template>
  <show-navigation></show-navigation>
  <router-view :key="$route.fullPath"></router-view>
  <footer class="cq-footer">
    <p>Copyright &copy; {{ new Date().getFullYear() }}</p>
  </footer>
</template>

<script>
import ShowNavigation from './components/global/ShowNavigation.vue'

export default {
  name: 'App',
  components: { 'show-navigation': ShowNavigation }
}
</script>
```

**File `src/router/index.js`** (two addresses, two screens):

```js
import { createRouter, createWebHashHistory } from 'vue-router'
import BooksPage from '../components/pages/BooksPage.vue'
import BookPage from '../components/pages/BookPage.vue'

// Hash addresses (#/books/3): they work in development and in the built app, from any folder
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: BooksPage },
    { path: '/books/:id', name: 'book', component: BookPage, props: true }
  ]
})

export default router
```

**File `src/components/pages/BooksPage.vue`** (a title band, then the rows with search beside the form):

```html
<!-- The catalog: the list filtered as you type and the form, side by side from tablets up (one column on phones) -->
<template>
  <main>
    <section class="cq-hero cq-hero--page">
      <h1 class="cq-display">Bookshelf</h1>
      <p class="cq-lead">The same catalog as the web pages, the REST API and AI agents.</p>
    </section>

    <!-- side by side from tablets up: the list, filtered as you type (search_books), and the form (create_book) -->
    <div class="cq-split">
      <section class="cq-split-main">
        <div class="cq-field">
          <label for="query">Search</label>
          <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time..." autocomplete="off">
        </div>
        <ul class="cq-rows">
          <li class="cq-row" v-for="book in books" :key="book.id">
            <div class="cq-row-main">
              <p class="cq-row-title"><router-link :to="{ name: 'book', params: { id: book.id } }">{{ book.title }}</router-link></p>
              <p class="cq-row-meta">{{ book.author.name }}, {{ book.published_year }}</p>
            </div>
          </li>
        </ul>
        <p class="cq-empty-state" v-if="!books.length">No book here: add one.</p>
      </section>

      <!-- the messages of the form come from the rules of create_book -->
      <aside class="cq-split-detail">
        <h2 class="cq-heading">Add a book</h2>
        <form @submit.prevent="add" novalidate>
          <div class="cq-form-grid">
            <div class="cq-field">
              <label for="title">* Title</label>
              <input id="title" class="form-control" type="text" v-model="form.title" maxlength="200">
            </div>
            <div class="cq-field">
              <label for="author_id">* Author</label>
              <select id="author_id" class="form-select" v-model="form.author_id">
                <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
              </select>
            </div>
            <div class="cq-field">
              <label for="published_year">* Year</label>
              <input id="published_year" class="form-control" type="number" v-model="form.published_year" placeholder="1969">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Add book</button>
          <p class="cq-field-error" role="alert">{{ error }}</p>
        </form>
      </aside>
    </div>
  </main>
</template>

<script>
import { listBooks, searchBooks, listAuthors, createBook, CapabilityError } from '../../capabilities.js'

export default {
  name: 'BooksPage',
  data () {
    return { books: [], authors: [], query: '', form: { title: '', author_id: '', published_year: '' }, error: '', timer: null }
  },
  async mounted () {
    this.authors = await listAuthors()
    this.form.author_id = this.authors.length ? this.authors[0].id : ''
    this.books = (await listBooks()).data
  },
  methods: {
    search () {
      clearTimeout(this.timer)
      this.timer = setTimeout(async () => {
        this.books = this.query.trim().length < 2 ? (await listBooks()).data : await searchBooks({ query: this.query })
      }, 250)
    },
    async add () {
      this.error = ''
      try {
        await createBook(this.form)
        this.form.title = ''
        this.form.published_year = ''
        this.books = (await listBooks()).data
      } catch (e) {
        this.error = e instanceof CapabilityError ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong. Try again.'
      }
    }
  }
}
</script>
```

**File `src/components/pages/BookPage.vue`** (one book and Delete; a full-screen message when it is missing):

```html
<!-- One book (get_book), and a Delete button (delete_book) -->
<template>
  <main v-if="book">
    <section class="cq-hero cq-hero--page">
      <h1 class="cq-display">{{ book.title }}</h1>
      <p class="cq-lead">by {{ book.author.name }}, {{ book.published_year }}</p>
    </section>
    <section class="cq-band cq-band--tight">
      <div class="cq-inner cq-inner--narrow">
        <p>{{ book.summary || 'No summary yet.' }}</p>
        <div class="cq-buttons">
          <button class="btn btn-secondary" type="button" @click="remove">Delete</button>
        </div>
        <p class="cq-auth-note"><router-link :to="{ name: 'home' }">All books</router-link></p>
      </div>
    </section>
  </main>

  <main v-else>
    <section class="cq-hero">
      <h1 class="cq-display">{{ error ? 'Not found' : 'Loading...' }}</h1>
      <p class="cq-lead">{{ error }}</p>
      <div class="cq-cta"><router-link class="btn btn-primary" :to="{ name: 'home' }">All books</router-link></div>
    </section>
  </main>
</template>

<script>
import { getBook, deleteBook } from '../../capabilities.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data () {
    return { book: null, error: '' }
  },
  async mounted () {
    try {
      this.book = await getBook({ id: this.id })   // the book, its author in book.author
    } catch (e) {
      this.error = e.message   // for example "There is no book #42"
    }
  },
  methods: {
    async remove () {
      if (!confirm('Delete "' + this.book.title + '"?')) return
      await deleteBook({ id: this.id })
      this.$router.push({ name: 'home' })
    }
  }
}
</script>
```

Delete the template's welcome screen, which nothing uses any more:
```bash
rm public/bookshelf-spa/src/components/pages/HomePage.vue
```

What to notice:
- `import { listBooks, ... } from '../../capabilities.js'`: in a Vite app the normal `import` works.
- `(await listBooks()).data`: `list_books` returns a page; `getBook()` returns the book with `book.author`.
- `props: true` in the router passes `id` from the address to `BookPage`.
- `:key="$route.fullPath"` in `App.vue`: without it, going from `#/books/1` to `#/books/3` keeps showing book 1,
  because Vue reuses the screen and `mounted()` does not run again.
- The classes are those of the Pug pages: `cq-hero`, `cq-split` with `cq-split-main` and `cq-split-detail`,
  `cq-rows`, `cq-form-grid`, `cq-field-error`.

### Step 7 - Give the SPA its client

```bash
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```
Expected:
```text
 public/bookshelf-spa/src/capabilities.js written: 20 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)
 import { createBook, CapabilityError } from './capabilities.js'
```

### Step 8 - Run it in development

Open a second terminal in the `bookshelf` folder (Vite keeps it busy while it runs):
```bash
cd public/bookshelf-spa
npm run dev
```
Expected (the time varies):
```text
  VITE v8.3.5  ready in 724 ms

  ➜  Local:   http://localhost:5173/
```
Open http://localhost:5173/:
1. The top bar, the title **Bookshelf**, the search and the rows (title, then author and year), and the grey panel
   **Add a book** beside them.
2. Type `magic` in Search: only *A Wizard of Earthsea* stays.
3. Type `Ghost` as the title, leave Year empty, click **Add book**: `The Published Year field is required`, the message
   of `create_book`.
4. Click *A Wizard of Earthsea*: the address becomes `#/books/1` and the screen shows `by Ursula K. Le Guin, 1968`.
   Change the address to `#/books/3`: Kindred.
5. Open http://localhost:5173/#/books/42: a full-screen **Not found**, `There is no book #42`, and **All books**.
6. At phone width (375 px in the device toolbar): the form comes after the list, one field per line, no horizontal
   scroll.

Stop the server with <kbd>Ctrl</kbd>+<kbd>C</kbd>.

### Step 9 - Build it

Still in `public/bookshelf-spa`:
```bash
npm run build
```
Expected (the names after `index-` vary):
```text
dist/index.html                   0.77 kB │ gzip:  0.42 kB
dist/assets/index-V7JhSuPx.css   64.82 kB │ gzip: 12.25 kB
dist/assets/index-C_ig7SGR.js   151.08 kB │ gzip: 57.17 kB

✓ built in 111ms
```
Go back with `cd ../..`. Caligrafy serves the built app: open
http://localhost:8080/public/bookshelf-spa/dist/index.html#/books/3: Kindred, and no Node.js runs now. `dist/` is
committed: the production image of lesson 14 runs no npm, it serves these files. `node_modules/` is not (the SPA's
`.gitignore`). After a change to the SPA or to a capability, build again and commit `dist/`.

You now have three experiences of one catalog: the pages, the finder and the SPA. Same capabilities, same messages,
same look.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | No Install button and no install icon | Not Chrome or Edge, already installed, or **Not now** was clicked | Try Chrome; to see the button again, remove `caligrafy-install-dismissed` in the developer tools (Application, Local storage) |
> | `curl ... /books` prints nothing in step 2 | `'with' => ['install' => true]` is missing or on another route | Compare `web.php` with step 2 |
> | `npm: command not found` | Node.js is not installed, or the terminal was opened before | Install Node.js (lesson 00) and open a new terminal |
> | `Vite requires Node.js version 20.19+ or 22.12+` | Node.js is too old | Install the current LTS |
> | The SPA shows no books; the Vite terminal shows `http proxy error` | Caligrafy is not on 8080 (containers stopped, or another port) | `docker compose ps`; put your port in the `proxy` line of `vite.config.js` |
> | `Failed to resolve import "../../capabilities.js"` | Step 7 was skipped | Generate the SPA client, then reload |
> | `Failed to resolve import "../components/pages/HomePage.vue"` | `src/router/index.js` is still the template | Write the file of step 6 |
> | `#/books/1` then `#/books/3` still shows book 1 | `:key="$route.fullPath"` is missing in `App.vue` | Add it as in step 6 |
> | The built page is blank, 404 for `/assets/index-...js` | `base: './'` is missing | Add it to `vite.config.js` and build again |
> | The built page loads but shows no books | It was opened as a file (`file:///...`) | Open it through http://localhost:8080/public/bookshelf-spa/dist/index.html |
> | `EACCES` when you save a file (Linux, WSL) | The container created the files as root | In `bookshelf`, run `sudo chown -R "$USER": .` |

---

## Check

- [ ] `public/manifest.webmanifest` and `web.php` are identical to the File blocks; `git diff lesson-09 -- application`
      shows only the `'with'` of the catalog.
- [ ] `/books` has `id="caligrafy-install"`, `/books/3` does not.
- [ ] The six files of steps 5 and 6 are identical to the File blocks; `HomePage.vue` is deleted.
- [ ] `src/capabilities.js` was written by the `client` command.
- [ ] No `apiKey`, `VITE_API_KEY`, `axios` or `Authorization` in the files you wrote.
- [ ] Step 8 works (search, the 422 message, `#/books/42`, one column at 375 px), and the built app shows Kindred on 8080.

## With Claude

Part 1, in `claude` in the `bookshelf` folder:
```text
Make Bookshelf installable: in public/manifest.webmanifest set name and short_name to "Bookshelf" and description to
"The books we love" (change nothing else). In web.php add 'with' => ['install' => true] to the Route::page of /books,
with the comment "// install: offer to add it to the home screen". Change nothing else.
```
Part 2:
```text
/caligrafy-quill:new-app create bookshelf-spa
```
then:
```text
Turn public/bookshelf-spa into the Bookshelf SPA. Run npm install on my computer, not in Docker. Generate its client
with php caligrafer.php client public/bookshelf-spa/src/capabilities.js and import the functions from it (no axios,
no API key, no Pinia). vite.config.js: base './' and server.proxy { '/capabilities': 'http://localhost:8080' }.
index.html: title Bookshelf. Router: createWebHashHistory, '/' named home -> BooksPage, '/books/:id' named book ->
BookPage with props: true. App.vue: show-navigation (keep ShowNavigation.vue and main.js), then
<router-view :key="$route.fullPath">, then footer.cq-footer. BooksPage: section.cq-hero.cq-hero--page "Bookshelf",
then .cq-split: section.cq-split-main with a search .cq-field (searchBooks 250 ms after typing pauses,
(await listBooks()).data under 2 characters) and ul.cq-rows of router-links with "<book.author.name>, <year>"; and
aside.cq-split-detail "Add a book" with a .cq-form-grid form (title, an author select from listAuthors, year) that
calls createBook and shows a CapabilityError's field messages in p.cq-field-error. BookPage: getBook({ id }) (the book
with book.author), a title band (title, "by <author>, <year>"), the summary, Delete (confirm, deleteBook, back home);
a full-screen section.cq-hero "Not found" with the error message when getBook fails. No cq-card. Delete
src/components/pages/HomePage.vue. Do not change any server file.
```

**Check** before you accept:
- [ ] `git diff application` shows only the `'with'` line; no new route, service or controller.
- [ ] `vite.config.js` has `base: './'` and the `/capabilities` proxy; the router uses `createWebHashHistory()`;
      `App.vue` has `:key="$route.fullPath"`.
- [ ] The pages import from `../../capabilities.js`, generated by the `client` command.
- [ ] No `apiKey`, `VITE_API_KEY` or `Authorization` in the files Claude wrote; `src/main.js` still imports
      `layout.css` and `look.css`.
- [ ] Steps 8 and 9 work.

If Claude uses `createWebHistory()`, the addresses have no `#`, and the server must then answer `index.html` for every
address of the app, which this course does not set up: ask for hash history. If it runs `npm audit fix --force`, stop
it.

---

## Checkpoint

From the `bookshelf` folder:
```bash
git add -A
git commit -m "Lesson 10: the Vite app for phones and tablets, install to home screen"
git tag lesson-10
```
Reference: [`checkpoints/lesson-10`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-10)
(only the files you write or change: the template files of the SPA are not repeated there). After copying a
checkpoint, run `npm install` in `public/bookshelf-spa` again.
Learn more: [7. Views, install](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install) and
[9.8.1 Setting up the Vue application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application).

## Quiz

1. Which two files give every experience the same look, and what does each hold?
2. Why can a phone on your Wi-Fi not install Bookshelf from `http://192.168.1.20:8080`?
3. Why does the SPA call `/capabilities` on port 5173 during development and still reach Caligrafy?
4. What would break without `:key="$route.fullPath"`?
5. The SPA has `src/capabilities.js` and the finder uses `public/js/capabilities.js`. After a change to a capability,
   what do you run?

<details><summary>Answers</summary>

1. `public/css/tokens.css`, the design tokens (colors, spacing, touch target, the safe areas when installed), and
   `public/css/layout.css`, the page components (`cq-nav`, `cq-hero`, `cq-rows`, `cq-split`...).
2. Installing needs HTTPS, or `localhost` on the same computer; that address is neither.
3. The `proxy` of `vite.config.js` forwards `/capabilities` to `http://localhost:8080`, with the cookies.
4. Going from one book to another would keep showing the first: Vue reuses the screen and `mounted()` does not run.
5. Both clients: `php caligrafer.php client` and `php caligrafer.php client public/bookshelf-spa/src/capabilities.js`
   (then `npm run build` for the built SPA).
</details>

---
Previous: [Lesson 09 - A Vue app](09-A-Vue-App) · Next: [Lesson 11 - People and permissions](11-People-and-Permissions)
