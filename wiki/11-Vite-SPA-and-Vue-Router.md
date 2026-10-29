# Lesson 11 - The Vite single page application and Vue Router

**In this lesson** you create **bookshelf-spa**, a Vue application built with **Vite**: the list of books and a
page per book, with real page URLs handled in the browser by **Vue Router**, reading the same REST API. You run it
with the Vite development server on http://localhost:5173.

**Before you start:** lesson 10 is done (tag `lesson-10`), and Node.js 20.19+ or 22.12+ is installed
(`node --version`, lesson 00).

---

## New concepts

**Single page application (SPA).** The browser loads **one** HTML page and JavaScript; after that, clicking a link
does not load a new page from the server: JavaScript swaps the content and calls the API for data. Pages feel
instant, and the server only serves JSON.

**Vite.** A tool that runs on Node.js. In development (`npm run dev`) it serves your app on
http://localhost:5173 and reloads the browser as soon as you save a file. For production (`npm run build`, lesson
12) it bundles everything into a few optimized files. With Vite you write **single file components** and use
`import` between files.

**Single file component (`.vue` file).** One file per piece of the page, with three parts:
```vue
<template> the HTML, with the Vue syntax of lesson 10 </template>
<script> export default { name, props, data(), methods, mounted() } </script>
<style> optional CSS </style>
```

**Vue Router.** Maps the part of the URL after `#` to a component: `#/` shows `BooksPage`, `#/books/3` shows
`BookPage` with `id` = `3`. `<router-link :to="...">` makes a link that changes the page without reloading;
`<router-view>` (in `App.vue`) is where the current page appears.

**Hash URLs.** We use `createWebHashHistory()`: the page URLs look like `index.html#/books/3`. The part after `#` is
never sent to the server, so the app works from any folder and needs no server configuration. (The other mode,
`createWebHistory()`, gives URLs without `#` but needs the web server to send `index.html` for every URL.)

**The Caligrafy SPA template.** `php caligrafer.php create <name>` copies `framework/librairies/app` (Vue 3.5,
Vite 8, Vue Router, Pinia, axios, ESLint) to `public/<name>/` and runs `npm install`. It also contains a `.env`
file with `VITE_API_KEY`, your API key (written there by `initialize`). In Vite code, environment values are read
with `import.meta.env.VITE_...`; `src/common/app.js` already builds the API headers from it:
```js
export const apiConfig = { headers: { Authorization: 'Bearer ' + config.apiKey, Accept: 'application/json', ... } }
export const callApi = axios;
```

**The development proxy.** The app runs on `localhost:5173` and the API on `localhost:8080`: for the browser
these are two different sites. We tell Vite to **forward** every request whose path starts with `/api` to
`http://localhost:8080`: the browser only talks to 5173, and the session cookie of Caligrafy goes along.

---

## Manual track

### Step 1 - Create the app

From the `bookshelf` folder:
```bash
docker compose exec dev-box php caligrafer.php create bookshelf-spa
```
It copies the template to `public/bookshelf-spa`, then tries `npm install` **inside the container**, where Node.js
is not installed. The output contains:
```
 Installing packages...

sh: 1: npm: not found

VueJS project successfully created and all packages have been installed.
```
The last line is wrong in this case: nothing was installed (`npm: not found`). That is expected: install the packages
with the Node.js of your computer:
```bash
cd public/bookshelf-spa
npm install
```
Expected (the numbers vary): `added 273 packages, and audited 274 packages in 2s` ... `found 0 vulnerabilities`. The packages go
in `public/bookshelf-spa/node_modules/`, which git ignores.

(With PHP and Node.js installed on your computer, `php caligrafer.php create bookshelf-spa` does both steps.)

**Stay in `public/bookshelf-spa`** for the rest of this lesson: every path below is relative to it.

### Step 2 - Start the development server

```bash
npm run dev
```
Expected:
```
  VITE v8.3.5  ready in 940 ms

  ➜  Local:   http://localhost:5173/
  ➜  Network: use --host to expose
  ➜  Vue DevTools: Open http://localhost:5173/__devtools__/ as a separate window
  ➜  Vue DevTools: Press Option(⌥)+Shift(⇧)+D in App to toggle the Vue DevTools
```
(The small dark button at the bottom of the page is the Vue DevTools of the template: it only exists in development.)
Open http://localhost:5173/: the Caligrafy welcome cards, in the SPA version. Leave this terminal running and
**open a second terminal** in `bookshelf/public/bookshelf-spa` for the next steps. (To stop the server later:
<kbd>Ctrl</kbd>+<kbd>C</kbd> in its terminal.)

### Step 3 - The proxy and the build settings

**File `vite.config.js`** - replace its whole content with:

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
    // npm run dev: requests to /api/... are forwarded to Caligrafy, so the browser sees one single site
    proxy: {
      '/api': 'http://localhost:8080'
    }
  }
})
```

`base: './'` makes the built files (lesson 12) use relative paths. Vite restarts by itself when this file changes.

Check the proxy from the terminal (it prints the books, through port 5173):
```bash
curl -s -H "Accept: application/json" -H "Authorization: Bearer $(grep '^VITE_API_KEY=' .env | cut -d= -f2-)" http://localhost:5173/api/books | head -c 80; echo
```
Expected: `{"books":[{"table":"books","id":1,"title":"A Wizard of Earthsea","published_year`

### Step 4 - The list page

**File `src/components/pages/BooksPage.vue`**:

```vue
<!-- The list of books: route '/' -->
<template>
  <div class="wrapper width-max-600">
    <div class="spacer-2"></div>
    <h2 class="medium-text semibold horizontal-center">Bookshelf</h2>
    <p class="horizontal-center">{{ books.length }} books</p>
    <p class="error horizontal-center" v-if="message">{{ message }}</p>
    <div class="spacer-2"></div>
    <router-link v-for="book in books" :key="book.id" :to="{ name: 'book', params: { id: book.id } }">
      <div class="card color0 dropshadow equally-spaced-1">
        <h3 class="medium-text semibold textcolor5 reset-margin">{{ book.title }}</h3>
        <p class="default-text textcolor5">{{ book.published_year }}</p>
      </div>
    </router-link>
  </div>
</template>

<script>
import { callApi, apiConfig } from '../../common/app.js'

export default {
  name: 'BooksPage',
  data() {
    return { books: [], message: '' }
  },
  mounted() {
    callApi.get('/api/books', apiConfig)
      .then((response) => { this.books = response.data.books })
      .catch(() => { this.message = 'The books could not be loaded. Is Caligrafy running?' })
  }
}
</script>
```

The URL is `/api/books`, with a leading `/`: it always means `http://localhost:5173/api/books`, which the proxy
forwards.

### Step 5 - The book page

**File `src/components/pages/BookPage.vue`**:

```vue
<!-- One book: route '/books/:id'; the router gives the id as a prop -->
<template>
  <div class="wrapper width-max-600">
    <div class="spacer-2"></div>
    <p class="error horizontal-center" v-if="message">{{ message }}</p>
    <div class="card color0 dropshadow equally-spaced-1" v-if="book">
      <h2 class="medium-text semibold reset-margin">{{ book.title }}</h2>
      <div class="spacer-1"></div>
      <p class="default-text textcolor5">by {{ author.name }}, {{ book.published_year }}</p>
      <p>{{ book.summary }}</p>
    </div>
    <div class="spacer-2"></div>
    <p class="horizontal-center">
      <router-link class="btn btn-secondary" :to="{ name: 'home' }">All books</router-link>
    </p>
  </div>
</template>

<script>
import { callApi, apiConfig } from '../../common/app.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data() {
    return { book: null, author: null, message: '' }
  },
  mounted() {
    this.load()
  },
  watch: {
    // the router reuses this page when only the id changes (#/books/3 -> #/books/4): load the new book
    id() {
      this.load()
    }
  },
  methods: {
    load() {
      this.book = null
      this.message = ''
      callApi.get('/api/books/' + this.id, apiConfig)
        .then((response) => {
          this.book = response.data.book
          this.author = response.data.author
        })
        .catch((error) => {
          this.message = error.response && error.response.status === 404 ? 'There is no book with this number.' : 'The book could not be loaded.'
        })
    }
  }
}
</script>
```

`props: ['id']` receives the `:id` of the route, because the route says `props: true` (next step). The `watch` on
`id` matters: when you go from `#/books/3` to `#/books/4`, Vue Router **reuses** the page that is already on screen
and only changes its `id`, so `mounted()` does not run again. Without the watch, the page would keep showing book 3.

### Step 6 - The routes

**File `src/router/index.js`** - replace its whole content with:

```js
import { createRouter, createWebHashHistory } from 'vue-router'
import BooksPage from '../components/pages/BooksPage.vue'
import BookPage from '../components/pages/BookPage.vue'

// Hash history: the page URLs look like index.html#/books/3, so they work from any folder, without server rules
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: BooksPage },
    { path: '/books/:id', name: 'book', component: BookPage, props: true }
  ]
})

export default router
```

The template's `HomePage.vue` is no longer used; you can leave it or delete it.

### Step 7 - Try it

Open http://localhost:5173/ (the address becomes `http://localhost:5173/#/`). You see the Caligrafy header,
**Bookshelf**, "5 books" and the cards. Click **Kindred**: the address becomes `#/books/3`, the page shows the book,
and the browser did **not** reload (watch the Network tab: only one `api/books/3` request). Click **All books**,
then the browser's **Back** button: the router follows. Now type http://localhost:5173/#/books/42 in the address bar: *There is no book with this
number.* (this is the `watch` at work: the page was already open on book 3).

![The SPA](https://raw.githubusercontent.com/caligrafy/caligrafy-quill-course/main/docs/images/lesson-11-spa.jpg)

Edit `BooksPage.vue`, change `{{ books.length }} books` into `{{ books.length }} books on the shelf`, save: the
browser updates within a second, without reloading. Undo the change.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `npm: command not found` on your computer | Node.js is not installed, or the terminal was open before you installed it | Install Node.js (lesson 00) and open a new terminal |
> | `Vite requires Node.js version 20.19+ or 22.12+` | Your Node.js is too old | Install the current LTS |
> | "The books could not be loaded. Is Caligrafy running?" | The containers are stopped, or `vite.config.js` has no proxy (then `/api/books` goes to Vite, which answers 404) | `docker compose ps` in the `bookshelf` folder; compare `vite.config.js` with step 3 and restart `npm run dev` |
> | `[vite] http proxy error: /api/books ... ECONNREFUSED` in the terminal of `npm run dev` | Caligrafy is not reachable on port 8080 | Start the containers; if you changed the port in lesson 01, use it in `vite.config.js` |
> | `Access Denied` (401) in the Network tab | `.env` of the SPA has no `VITE_API_KEY` | Copy the `API_KEY` value of the project `.env` into `public/bookshelf-spa/.env` as `VITE_API_KEY=...`, then restart `npm run dev` |
> | `Port 5173 is in use, trying another one...` | Another Vite server runs | Use the URL Vite prints (`5174`...) or stop the other one |
> | Going from one book to another in the address bar keeps showing the first book | The `watch` on `id` is missing in `BookPage.vue` | Add it as in step 5 |
> | `Failed to resolve import "../components/pages/BookPage.vue"` | The file name or folder differs (case-sensitive) | Compare with steps 4 to 6 |

---

## With Claude

```
/caligrafy-quill:new-app create bookshelf-spa
```
Then:
```
In public/bookshelf-spa (the Caligrafy Vite SPA template), make the app read the Bookshelf REST API:
vite.config.js with base './' and a dev proxy of /api to http://localhost:8080; Vue Router with
createWebHashHistory and two routes: '/' (BooksPage: GET /api/books, a card per book linking to the book page)
and '/books/:id' with props (BookPage: GET /api/books/{id}, title, author, year, summary, a 404 message).
Use callApi and apiConfig from src/common/app.js, Options API, and the Caligrafy classes (card color0
dropshadow equally-spaced-1, medium-text semibold, textcolor5, btn btn-secondary). No Pinia yet.
```

**What Claude should do:** run `php caligrafer.php create bookshelf-spa` (or copy `framework/librairies/app` and
run `npm install`, which is the same), then write the files of steps 3 to 6.

**Check** before you accept:
- [ ] API calls use `import.meta.env.VITE_API_KEY` (through `app.js`), never `process.env` and never a key
      pasted into the code.
- [ ] The proxy forwards `/api` to the port where Caligrafy really runs.
- [ ] The router uses `createWebHashHistory()` (or Claude explains what server rule `createWebHistory()` needs).
- [ ] `npm run dev` starts without errors and step 7 works.
- [ ] `git status` does not list `node_modules/` or `.env` files.

**If Claude's result differs:** if Claude cannot run `npm install` (no Node.js where it runs), run it yourself as in
step 1. If Claude sets `axios.defaults.baseURL = 'http://localhost:8080'` instead of a proxy, the browser blocks the
calls as cross-site requests (or drops the session cookie): ask for the proxy. If it adds `caligrafy-header` or
`caligrafy-layout` classes, remove them: the SPA's own stylesheet order already gives the right header.

---

## Checkpoint

Stop `npm run dev` (<kbd>Ctrl</kbd>+<kbd>C</kbd>), go back to the project folder and commit:
```bash
cd ../..
git add -A
git status --short | grep -E "node_modules|\.env" || echo "no node_modules and no .env: good"
git commit -m "Lesson 11: Vite SPA with Vue Router"
git tag lesson-11
```
Reference: [`checkpoints/lesson-11`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-11)
(only the files you change in `public/bookshelf-spa`).

## Quiz

1. Why does `BooksPage.vue` call `/api/books` and not `http://localhost:8080/api/books`?
2. What is the difference between `<a href="#/books/3">` and `<router-link :to="{ name: 'book', params: { id: 3 } }">`?

<details><summary>Show the solution</summary>

1. In development, `/api/books` goes to Vite (port 5173), whose proxy forwards it to Caligrafy: same site for the
   browser, so no cross-site blocking and the session cookie is sent. In production (lesson 12) the built app is
   served by Caligrafy itself, so `/api/books` is the API directly.
2. Both work with hash URLs, but `router-link` builds the URL from the route **name**, so it keeps working if you
   change the path in `router/index.js`, and it marks the active link.
</details>

---
Previous: [Lesson 10 - A Vue app inside Caligrafy](10-Vue-as-a-Library) · Next: [Lesson 12 - Pinia, styling and the production build](12-Pinia-Styling-and-Build)
