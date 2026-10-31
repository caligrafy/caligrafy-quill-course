# Lesson 10 - Phones and tablets

**In this lesson** Bookshelf reaches phones and tablets in two ways: the web pages become an app you can install to
the home screen, and you build **bookshelf-spa**, a Vue single page application made with Vite (a list, a form and a
page per book). Both use the same capabilities, with zero new server code.

**Before you start:** lesson 09 is done (tag `lesson-09`), the containers run (`docker compose ps`), and Node.js is
installed on your computer: `node --version` prints `v20.19` or newer (or `v22.12` or newer on the 22 line). Chrome
or Edge makes the install part easier to try.

**Time:** 60 to 75 minutes.

---

## New concepts

**Install to home screen.** A browser can install a web application: it then opens from its own icon, full screen,
without the address bar, like an app from a store. The browser needs a **web app manifest**,
`public/manifest.webmanifest`: a small JSON file with the name, the colors and the icons of the app. The Caligrafy
layout already links it. Installing needs HTTPS, or `localhost` on your own computer.

**Single page application (SPA).** The browser loads one HTML page and one JavaScript program. After that, clicking a
link does not ask the server for a new page: JavaScript swaps the screen and calls the capabilities for data. It feels
like a native app on a phone or a tablet.

| Word | What it is |
|---|---|
| Node.js, npm | JavaScript outside the browser, and its package installer. `npm install` downloads the libraries listed in `package.json` into `node_modules/` |
| Vite | the tool that runs and builds the SPA. `npm run dev` serves it on http://localhost:5173 and reloads the browser when you save a file |
| `.vue` file (single file component) | one piece of the screen in one file: `<template>` (the HTML with the Vue syntax of lesson 09) and `<script>` (its data and methods) |
| router (Vue Router) | maps an address to a screen: `#/` shows `BooksPage`, `#/books/3` shows `BookPage` with `id` = `3`. `<router-link>` makes a link that changes the screen without reloading; `<router-view>` is where the current screen appears |
| hash history | addresses written after a `#` (`index.html#/books/3`). The part after `#` never goes to the server, so the app works from any folder with no server setting |
| proxy | in development the SPA runs on port 5173 and Caligrafy on 8080. Vite **forwards** every call to `/capabilities` to `http://localhost:8080`: the browser only talks to one site |
| build | `npm run build` turns the app into a few plain files in `dist/` that Caligrafy's Apache serves. No Node.js is needed to use them |

**Why the session still works.** The JS client calls `/capabilities/...` on the address of the page. With the proxy,
the call leaves for 5173 and Vite passes it, with its cookies, to 8080. Cookies belong to the host name `localhost`,
whatever the port, so in [Lesson 11](11-People-and-Permissions) a person signed in on 8080 is signed in in the SPA too.

**Build once, in practice.** The SPA gets its own copy of the JS client, generated from the same capabilities:
`php caligrafer.php client public/bookshelf-spa/src/capabilities.js`. Lists, search, add and delete all come from
lessons 04, 05 and 08. The only server files you touch are a manifest and two route lines that turn on the install
offer.

---

## Manual track

### Part 1 - Install the web app

### Step 1 - Name the app

In `public/manifest.webmanifest`, replace the first three values (`Caligrafy`, `Caligrafy`,
`Caligrafy Quill application`) with:
```json
    "name": "Bookshelf",
    "short_name": "Bookshelf",
    "description": "The books we love",
```
`short_name` is the name under the icon on a phone. Leave the other lines as they are (`start_url` and `scope` point to
the root of your application; the icons are Caligrafy's). The full file is in the checkpoint.

### Step 2 - Offer the install on the catalog

In `application/config/routing/web.php`, replace the route of `/`:
```php
    Route::get('/', function() { return view('Bookshelf/books', array('install' => true)); });
```
and the route of `/books` in the `// BOOKSHELF PAGES` block:
```php
    Route::get('/books', function () { return view('Bookshelf/books', array('install' => true)); });   // install: offer to add it to the home screen
```
`install` tells the layout to add its install module: an **Install** button on Chrome, Edge and Android (when the
browser says the app can be installed), or a short hint on iPhone and iPad (Share, then Add to Home Screen). The full
file is in the checkpoint.

### Step 3 - Install it

Open http://localhost:8080/books in Chrome or Edge on your computer. Install it with the Install button of the page,
or with the install icon at the right of the address bar. Bookshelf opens in its own window, without the address bar,
and its icon is with your other apps.

A phone on your Wi-Fi cannot install it from your computer: it would open `http://<your computer's address>:8080`,
which is neither HTTPS nor `localhost`. Try it on a phone once Bookshelf is deployed with HTTPS.

### Part 2 - The SPA

### Step 4 - Create the SPA

From the `bookshelf` folder:
```bash
docker compose exec dev-box php caligrafer.php create bookshelf-spa
```
It copies the Vite template to `public/bookshelf-spa`, then tries `npm install` inside the container, which has no
Node.js:
```
 Project created in the public folder.
 Type cd public/bookshelf-spa to access it anytime

 Installing packages...

sh: 1: npm: not found

VueJS project successfully created and all packages have been installed.
```
The last line is wrong: `npm: not found` means nothing was installed. Install the packages with the Node.js of your
computer:
```bash
cd public/bookshelf-spa
npm install
cd ../..
```
Expected: `added 273 packages` (the numbers can vary) and you are back in `bookshelf`.

npm may add `10 moderate severity vulnerabilities`. They all come from one library, `minimatch`, used by ESLint,
the code checker of `npm run lint` ([GHSA-g3hv-75wg-m249](https://github.com/advisories/GHSA-g3hv-75wg-m249)): not
from Vue, and nothing of it goes into your app (`npm audit --omit=dev` finds 0). **Do not run
`npm audit fix --force`**: it would replace ESLint 10 with ESLint 4 from 2017. To clear the warning, in
`public/bookshelf-spa/package.json` add these lines between the `devDependencies` block and `"engines"`:
```json
  "overrides": {
    "minimatch": "^11.0.1"
  },
```
then reinstall:
```bash
cd public/bookshelf-spa
rm -rf node_modules package-lock.json
npm install
npm audit
cd ../..
```
Expected: `found 0 vulnerabilities`. (Templates of Caligrafy Quill releases after 2.2.0 already have the override.)

### Step 5 - Set up Vite

Every path from here is relative to `public/bookshelf-spa`.

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

Keep `src/main.js` of the template: it loads Caligrafy's CSS (`tokens.css` included), so the page components look
the same as on the pages. (It also prepares axios, Pinia and an API key: the course does not use them.)

### Step 6 - The screens

**File `src/App.vue`** (the frame of every screen):

```html
<!-- The frame of every screen: the Caligrafy header, then the page of the current address
     (:key loads the page again when only the id changes, from #/books/1 to #/books/2) -->
<template>
  <show-navigation></show-navigation>
  <router-view :key="$route.fullPath"></router-view>
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

**File `src/components/pages/BooksPage.vue`** (the list with search, and the form):

```html
<!-- The catalog: the list and the form side by side on tablets and computers, one under the other on phones -->
<template>
  <main class="cq-page cq-page--wide">
    <div class="cq-card">
      <div class="cq-card-head">
        <h2 class="cq-title">Bookshelf</h2>
        <p class="cq-note">The same catalog as the web pages, the REST API and AI agents.</p>
      </div>

      <div class="cq-section cq-split">
        <!-- the list, filtered as you type (search_books) -->
        <div>
          <label for="query">Search</label>
          <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time..." autocomplete="off">
          <ul class="cq-list">
            <li class="cq-item" v-for="book in books" :key="book.id">
              <div class="cq-item-body">
                <p class="cq-item-title"><router-link :to="{ name: 'book', params: { id: book.id } }">{{ book.title }}</router-link></p>
                <p class="cq-item-meta">{{ book.published_year }}</p>
              </div>
            </li>
          </ul>
          <p class="cq-empty" v-if="!books.length">No book here: add one.</p>
        </div>

        <!-- the form (create_book): the messages come from its rules -->
        <form class="cq-form" @submit.prevent="add" novalidate>
          <div class="cq-fields">
            <div>
              <label for="title">* Title</label>
              <input id="title" class="form-control" type="text" v-model="form.title" maxlength="200">
            </div>
            <div>
              <label for="author_id">* Author</label>
              <select id="author_id" class="form-select" v-model="form.author_id">
                <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
              </select>
            </div>
            <div>
              <label for="published_year">* Year</label>
              <input id="published_year" class="form-control" type="number" v-model="form.published_year" placeholder="1969">
            </div>
          </div>
          <button class="btn btn-primary cq-submit" type="submit">Add book</button>
          <p class="cq-error" role="alert">{{ error }}</p>
        </form>
      </div>
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
    this.books = await listBooks()
  },
  methods: {
    search () {
      clearTimeout(this.timer)
      this.timer = setTimeout(async () => {
        this.books = this.query.trim().length < 2 ? await listBooks() : await searchBooks({ query: this.query })
      }, 250)
    },
    async add () {
      this.error = ''
      try {
        await createBook(this.form)
        this.form.title = ''
        this.form.published_year = ''
        this.books = await listBooks()
      } catch (e) {
        this.error = e instanceof CapabilityError ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong. Try again.'
      }
    }
  }
}
</script>
```

**File `src/components/pages/BookPage.vue`** (one book, and Delete):

```html
<!-- One book (get_book), and a Delete button (delete_book) -->
<template>
  <main class="cq-page">
    <div class="cq-card" v-if="found">
      <div class="cq-card-head">
        <h2 class="cq-title">{{ found.book.title }}</h2>
        <p class="cq-note">by {{ found.author.name }}, {{ found.book.published_year }}</p>
      </div>
      <div class="cq-section">
        <p>{{ found.book.summary || 'No summary yet.' }}</p>
        <button class="btn btn-secondary" type="button" @click="remove">Delete</button>
      </div>
      <div class="cq-foot"><router-link :to="{ name: 'home' }">All books</router-link></div>
    </div>

    <div class="cq-card" v-else>
      <div class="cq-card-head">
        <h2 class="cq-title">{{ error ? 'Not found' : 'Loading...' }}</h2>
        <p class="cq-note">{{ error }}</p>
      </div>
      <div class="cq-foot"><router-link :to="{ name: 'home' }">All books</router-link></div>
    </div>
  </main>
</template>

<script>
import { getBook, deleteBook } from '../../capabilities.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data () {
    return { found: null, error: '' }
  },
  async mounted () {
    try {
      this.found = await getBook({ id: this.id })
    } catch (e) {
      this.error = e.message   // for example "There is no book #42"
    }
  },
  methods: {
    async remove () {
      if (!confirm('Delete "' + this.found.book.title + '"?')) return
      await deleteBook({ id: this.id })
      this.$router.push({ name: 'home' })
    }
  }
}
</script>
```

Delete the welcome screen of the template, which nothing uses any more:
```bash
rm public/bookshelf-spa/src/components/pages/HomePage.vue
```

What to notice:
- `import { listBooks, ... } from '../../capabilities.js'`: in a Vite app, a normal `import` works (no `import()`
  trick as in lesson 09).
- `props: true` in the router passes `id` from the address to `BookPage`.
- `:key="$route.fullPath"` in `App.vue`: without it, going from `#/books/1` to `#/books/3` keeps showing book 1,
  because Vue reuses the screen that is already there and `mounted()` does not run again.

### Step 7 - Give the SPA its client

From the `bookshelf` folder:
```bash
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
```
Expected: ` public/bookshelf-spa/src/capabilities.js written: 7 capabilities, calls /capabilities (Route::capabilities('/capabilities') in web.php)`.

### Step 8 - Run it in development

Open a second terminal in the `bookshelf` folder (Vite keeps that terminal busy while it runs):
```bash
cd public/bookshelf-spa
npm run dev
```
Expected:
```
  VITE v8.3.5  ready in 895 ms

  ➜  Local:   http://localhost:5173/
```
Open http://localhost:5173/:
1. The header, then the card **Bookshelf**: the list on the left, the form on the right (on a wide window).
2. Type `magic` in Search: only A Wizard of Earthsea stays.
3. Type `Ghost` as title, leave Year empty, click **Add book**: `The Published Year field is required`, the message
   of `create_book`.
4. Click **A Wizard of Earthsea**: the address becomes `#/books/1` and the book shows. Click **All books**.
5. Type `http://localhost:5173/#/books/42` in the address bar: **Not found** and `There is no book #42`.
6. At phone width (375 px in the device toolbar): one column, the form under the list.

Stop the server with <kbd>Ctrl</kbd>+<kbd>C</kbd> when you are done.

### Step 9 - Build it

Still in `public/bookshelf-spa`:
```bash
npm run build
```
Expected (the names after `index-` vary):
```
dist/assets/index-....css   35.78 kB
dist/assets/index-....js   149.94 kB
✓ built in 322ms
```
Caligrafy serves the built app: open http://localhost:8080/public/bookshelf-spa/dist/index.html, then add
`#/books/1` and then `#/books/3` to the address: Kindred shows. No Node.js runs now: these are plain files.
`dist/` and `node_modules/` are in the SPA's `.gitignore`: git does not keep them, you build again after a change.
Go back with `cd ../..`.

You now have three experiences of one catalog: the pages, the finder and the SPA. Same capabilities, same messages,
same look (`tokens.css`).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | No Install button and no install icon | Not Chrome or Edge, already installed, or you clicked **Not now** before | Try Chrome; **Not now** is remembered in the browser (`localStorage`, `caligrafy-install-dismissed`): clear the site data of localhost |
> | `npm: command not found` on your computer | Node.js is not installed, or the terminal was opened before | Install Node.js (lesson 00) and open a new terminal |
> | `Vite requires Node.js version 20.19+ or 22.12+` | Your Node.js is too old | Install the current LTS |
> | The SPA shows no books; the terminal of `npm run dev` shows `http proxy error` | Caligrafy is not reachable on 8080 (containers stopped, or another port) | `docker compose ps`; if you changed the port in lesson 01, use it in `vite.config.js` |
> | `Failed to resolve import "../../capabilities.js"` | Step 7 was skipped | Generate the SPA client, then reload |
> | `Failed to resolve import "../components/pages/HomePage.vue"` | `src/router/index.js` is still the template | Replace it with the file of step 6 |
> | `#/books/1` then `#/books/3` still shows book 1 | `:key="$route.fullPath"` is missing in `App.vue` | Add it as in step 6 |
> | The built page is blank, 404 for `/assets/index-...js` in the Network tab | `base: './'` is missing | Add it to `vite.config.js` and build again |
> | The built page loads but no books | You opened `dist/index.html` as a file (`file:///...`) | Open it through http://localhost:8080/public/bookshelf-spa/dist/index.html |
> | Changes do not show on port 8080 | The built files are from the last build | `npm run build` again |
> | `Permission denied` or `EACCES` when you save or create a file (Linux, or WSL) | The container writes the files it creates as root | In the `bookshelf` folder run `sudo chown -R "$USER": .`, then retry (again after a `caligrafer` command that creates files) |

---

## With Claude

Part 1, in `claude` in the `bookshelf` folder:
```
Make Bookshelf installable: in public/manifest.webmanifest set name and short_name to "Bookshelf" and description
to "The books we love" (change nothing else). In web.php, the closures of GET / and GET /books pass
array('install' => true) to view('Bookshelf/books'). Change nothing else.
```
Part 2:
```
/caligrafy-quill:new-app create bookshelf-spa
```
then:
```
Turn public/bookshelf-spa into the Bookshelf SPA. Generate its client with
php caligrafer.php client public/bookshelf-spa/src/capabilities.js and import the functions from it (no axios, no
API key, no Pinia). vite.config.js: base './' and server.proxy { '/capabilities': 'http://localhost:8080' }.
index.html: title Bookshelf. Router: createWebHashHistory, '/' named home -> BooksPage, '/books/:id' named book ->
BookPage with props: true. App.vue: show-navigation then <router-view :key="$route.fullPath">. BooksPage: page
components (main.cq-page.cq-page--wide > .cq-card, .cq-section.cq-split): on the left a search field (searchBooks
250 ms after typing pauses, listBooks under 2 characters) and the list with router-links to the books; on the right
a form (title, author select from listAuthors, year) that calls createBook and shows a CapabilityError's field
messages in p.cq-error. BookPage: getBook({ id }), title, "by <author>, <year>", summary, a Delete button
(confirm, deleteBook, back home), "Not found" and the error message when getBook fails. Delete
src/components/pages/HomePage.vue. Do not change any server file. Run npm install on my computer, not in Docker.
```

**What Claude should do:** change the manifest and two routes; run `create`, then `npm install` on your computer,
`client`, write the six files of steps 5 and 6, and delete `HomePage.vue`.

**Check** before you accept:
- [ ] `git diff application` shows only the two `array('install' => true)` lines; no new route, no new service.
- [ ] `vite.config.js` has `base: './'` and the `/capabilities` proxy.
- [ ] The router uses `createWebHashHistory()`; `App.vue` has `:key="$route.fullPath"`.
- [ ] The pages import from `../../capabilities.js`; no `apiKey`, `VITE_API_KEY` or `Authorization` in the files it wrote.
- [ ] Step 8 works (search, the 422 message, `#/books/42` Not found, one column on a phone), then step 9.

**If Claude's result differs:** if it uses `createWebHistory()`, the addresses have no `#`, but then the server
must send `index.html` for every address of the app, which this course does not set up: ask for hash history. If it adds a
Pinia store, it works, but it is code you do not need: ask it to call the client from the pages. If it runs
`npm install` in the container and it fails, run it yourself (step 4). If it runs `npm audit fix --force`, stop it: use the `minimatch` override of step 4 instead.

---

## Checkpoint

From the `bookshelf` folder:
```bash
git add -A
git commit -m "Lesson 10: the Vite app for phones and tablets, install to home screen"
git tag lesson-10
```
Reference: [`checkpoints/lesson-10`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-10)
(only the files you write or change: the template files of the SPA are not repeated there).
Learn more: [7. Views, install](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install) and
[9.8.1 Setting up the Vue application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application).

## Quiz

1. In development, what would happen to the calls of the JS client without the `proxy` of `vite.config.js`?
2. Why does the router use hash addresses (`#/books/3`)?
3. Which server files did you change to build the SPA?

<details><summary>Show the solution</summary>

1. They go to `http://localhost:5173/capabilities/...`, and Vite, which knows nothing about capabilities, does not
   answer with their JSON. The proxy forwards them to Caligrafy on 8080, cookies included.
2. The part after `#` never reaches the server: the built app works from `public/bookshelf-spa/dist/index.html`
   (or any folder) and a reload never asks Apache for `/books/3`.
3. None. The manifest and the two `install` routes are for the installable pages (part 1). The SPA only has a
   generated client of the capabilities that already existed.
</details>

---
Previous: [Lesson 09 - A Vue app](09-A-Vue-App) · Next: [Lesson 11 - People and permissions](11-People-and-Permissions)
