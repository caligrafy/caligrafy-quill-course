# Lesson 12 - Pinia, styling and the production build

**In this lesson** the SPA gets a **Pinia store** that holds the books for every page, a page to add a book (with
the API's error messages), a Delete button, and you build it for production with `npm run build`: the result is
served by Caligrafy itself, without Vite.

**Before you start:** lesson 11 is done (tag `lesson-11`). In a first terminal, the containers run; in a second
terminal, in `bookshelf/public/bookshelf-spa`, `npm run dev` runs.

---

## New concepts

**Why a store?** In lesson 11 each page loaded its own data. When the form page adds a book, the list page should
know about it without asking the API again. A **store** is one object, shared by all components, that holds the
data and the functions that change it.

**Pinia** is the official store of Vue (it replaces Vuex, used by the older Caligrafy courses). The template already
installs it (`app.use(createPinia())` in `src/main.js`). A store is defined with `defineStore('name', {...})`:

| Part | Holds | Used as |
|---|---|---|
| `state: () => ({ books: [] })` | the data | `store.books` |
| `getters: { count: (state) => state.books.length }` | values computed from the state | `store.count` |
| `actions: { async fetchBooks() {...} }` | functions, often calling the API; `this` is the store | `store.fetchBooks()` |

A component gets the store with `useBookStore()`; we put it in `data()` as `store`, so the template reads
`store.books`. When an action changes `this.books`, every component that shows it updates.

**`async` / `await`.** `const response = await callApi.get(...)` waits for the answer and gives it back, or throws
the error to the `catch` of a `try { } catch (error) { }`. It is the same as `.then()` / `.catch()` of lesson 10,
written top to bottom.

**Styling in the SPA.** `src/main.js` imports Caligrafy's `styles.css`, `theme.css` and `animate.css` from
`public/css/`, and `index.html` loads Bootstrap 5.3.8: the classes of lesson 06 work as they are. Do **not** add
`caligrafy-header` or `caligrafy-layout` in the SPA (the template's header already has the right margins).

**The production build.** `npm run build` compiles the app into `public/bookshelf-spa/dist/`: an `index.html` and
a few files in `dist/assets/`. Those are plain files: Caligrafy's Apache serves them at
`http://localhost:8080/public/bookshelf-spa/dist/index.html`, on the same site as the API, so no proxy is needed.
The API key is **inside** the built JavaScript (same rule as lesson 10: it is public).

---

## Manual track

All paths are relative to `public/bookshelf-spa`.

### Step 1 - The store

**File `src/common/store.js`** - replace its whole content with:

```js
/*
 * store.js - the Bookshelf state, shared by every page (Pinia)
 *   import { useBookStore } from '@/common/store'
 *   const store = useBookStore()
 */
import { defineStore } from 'pinia'
import { callApi, apiConfig } from './app.js'

// framework and API errors answer {"error": {"code": ..., "message": "..."}}
function errorMessage(error) {
  const data = error.response ? error.response.data : null
  return data && data.error ? data.error.message : 'The server could not be reached. Is Caligrafy running?'
}

export const useBookStore = defineStore('books', {
  // the shared data
  state: () => ({
    books: [],
    authors: [],
    current: null,      // the book of the book page
    currentAuthor: null,
    errors: {},         // one message per invalid field, from a 422 answer
    message: '',        // the last error message
    loading: false
  }),

  // values computed from the state
  getters: {
    count: (state) => state.books.length
  },

  // functions that change the state; they call the API
  actions: {
    async fetchBooks() {
      this.loading = true
      this.message = ''
      try {
        const response = await callApi.get('/api/books', apiConfig)
        this.books = response.data.books
      } catch (error) {
        this.message = errorMessage(error)
      } finally {
        this.loading = false
      }
    },

    async fetchAuthors() {
      try {
        const response = await callApi.get('/api/authors', apiConfig)
        this.authors = response.data.authors
      } catch (error) {
        this.message = errorMessage(error)
      }
    },

    async fetchBook(id) {
      this.current = null
      this.currentAuthor = null
      this.message = ''
      try {
        const response = await callApi.get('/api/books/' + id, apiConfig)
        this.current = response.data.book
        this.currentAuthor = response.data.author
      } catch (error) {
        this.message = errorMessage(error)
      }
    },

    // returns the new book, or null when the API refused it
    async createBook(book) {
      this.errors = {}
      this.message = ''
      try {
        const response = await callApi.post('/api/books', book, apiConfig)   // 201 Created
        this.books.push(response.data.book)
        return response.data.book
      } catch (error) {
        if (error.response && error.response.status === 422) {
          this.errors = error.response.data.errors
        } else {
          this.message = errorMessage(error)
        }
        return null
      }
    },

    // returns true when the book was deleted
    async deleteBook(id) {
      this.message = ''
      try {
        await callApi.delete('/api/books/' + id, apiConfig)
        this.books = this.books.filter((book) => book.id !== id)
        return true
      } catch (error) {
        this.message = errorMessage(error)
        return false
      }
    }
  }
})
```

- `errors` gets the `{"errors": {...}}` of a **422** answer; `message` gets the `error.message` of the others
  (401 "Sign in first", 404 "Book not found").
- `createBook()` returns the new book, or `null` when the API refused it.

### Step 2 - The list page uses the store

**File `src/components/pages/BooksPage.vue`** - replace its whole content with:

```vue
<!-- The list of books: route '/' -->
<template>
  <div class="wrapper width-max-600">
    <div class="spacer-2"></div>
    <h2 class="medium-text semibold horizontal-center">Bookshelf</h2>
    <p class="horizontal-center">{{ store.loading ? 'Loading...' : store.count + ' books' }}</p>
    <p class="error horizontal-center" v-if="store.message">{{ store.message }}</p>
    <p class="horizontal-center">
      <router-link class="btn btn-primary" :to="{ name: 'new-book' }">Add a book</router-link>
    </p>
    <div class="spacer-2"></div>
    <router-link v-for="book in store.books" :key="book.id" :to="{ name: 'book', params: { id: book.id } }">
      <div class="card color0 dropshadow equally-spaced-1">
        <h3 class="medium-text semibold textcolor5 reset-margin">{{ book.title }}</h3>
        <p class="default-text textcolor5">{{ book.published_year }}</p>
      </div>
    </router-link>
  </div>
</template>

<script>
import { useBookStore } from '../../common/store.js'

export default {
  name: 'BooksPage',
  data() {
    return { store: useBookStore() }
  },
  mounted() {
    this.store.fetchBooks()
  }
}
</script>
```

### Step 3 - The book page, with Delete

**File `src/components/pages/BookPage.vue`** - replace its whole content with:

```vue
<!-- One book: route '/books/:id'; the router gives the id as a prop -->
<template>
  <div class="wrapper width-max-600">
    <div class="spacer-2"></div>
    <p class="error horizontal-center" v-if="store.message">{{ store.message }}</p>
    <div class="card color0 dropshadow equally-spaced-1" v-if="store.current">
      <h2 class="medium-text semibold reset-margin">{{ store.current.title }}</h2>
      <div class="spacer-1"></div>
      <p class="default-text textcolor5">by {{ store.currentAuthor.name }}, {{ store.current.published_year }}</p>
      <p>{{ store.current.summary }}</p>
      <button class="btn btn-secondary" @click="remove">Delete</button>
    </div>
    <div class="spacer-2"></div>
    <p class="horizontal-center">
      <router-link class="btn btn-secondary" :to="{ name: 'home' }">All books</router-link>
    </p>
  </div>
</template>

<script>
import { useBookStore } from '../../common/store.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data() {
    return { store: useBookStore() }
  },
  mounted() {
    this.store.fetchBook(this.id)
  },
  watch: {
    // the router reuses this page when only the id changes (#/books/3 -> #/books/4): load the new book
    id() {
      this.store.fetchBook(this.id)
    }
  },
  methods: {
    async remove() {
      if (!confirm('Delete "' + this.store.current.title + '"?')) {
        return
      }
      if (await this.store.deleteBook(this.store.current.id)) {
        this.$router.push({ name: 'home' })
      }
    }
  }
}
</script>
```

`this.$router.push({ name: 'home' })` goes to the list after the delete, like a `redirect()` in PHP. The `watch`
on `id` is the one of lesson 11, now calling the store.

### Step 4 - The form page

**File `src/components/pages/NewBookPage.vue`**:

```vue
<!-- The form to add a book: route '/books/new' -->
<template>
  <div class="wrapper width-max-600">
    <div class="spacer-2"></div>
    <div class="card color0 dropshadow equally-spaced-1">
      <h2 class="medium-text semibold reset-margin">Add a book</h2>
      <div class="spacer-1"></div>
      <p class="error" v-if="store.message">{{ store.message }}</p>
      <form @submit.prevent="save">
        <div class="mb-3">
          <label class="default-text textcolor5" for="title">* title</label>
          <input class="form-control" id="title" type="text" v-model="form.title">
          <p class="error" v-if="store.errors.title">{{ store.errors.title }}</p>
        </div>
        <div class="mb-3">
          <label class="default-text textcolor5" for="author_id">* author</label>
          <select class="form-select" id="author_id" v-model="form.author_id">
            <option value="">Choose an author</option>
            <option v-for="author in store.authors" :key="author.id" :value="author.id">{{ author.name }}</option>
          </select>
          <p class="error" v-if="store.errors.author_id">{{ store.errors.author_id }}</p>
        </div>
        <div class="mb-3">
          <label class="default-text textcolor5" for="published_year">* year</label>
          <input class="form-control" id="published_year" type="number" v-model="form.published_year">
          <p class="error" v-if="store.errors.published_year">{{ store.errors.published_year }}</p>
        </div>
        <div class="mb-3">
          <label class="default-text textcolor5" for="summary">summary</label>
          <textarea class="form-control" id="summary" rows="3" v-model="form.summary"></textarea>
          <p class="error" v-if="store.errors.summary">{{ store.errors.summary }}</p>
        </div>
        <input class="btn btn-primary" type="submit" value="Save">
      </form>
    </div>
    <div class="spacer-2"></div>
    <p class="horizontal-center">
      <router-link class="btn btn-secondary" :to="{ name: 'home' }">Cancel</router-link>
    </p>
  </div>
</template>

<script>
import { useBookStore } from '../../common/store.js'

export default {
  name: 'NewBookPage',
  data() {
    return {
      store: useBookStore(),
      form: { title: '', author_id: '', published_year: '', summary: '' }
    }
  },
  mounted() {
    this.store.errors = {}
    this.store.message = ''
    this.store.fetchAuthors()
  },
  methods: {
    async save() {
      const book = await this.store.createBook(this.form)
      if (book) {
        this.$router.push({ name: 'book', params: { id: book.id } })
      }
    }
  }
}
</script>
```

### Step 5 - The routes

`/books/new` must come **before** `/books/:id`, for the same reason as in `web.php`.

**File `src/router/index.js`** - replace its whole content with:

```js
import { createRouter, createWebHashHistory } from 'vue-router'
import BooksPage from '../components/pages/BooksPage.vue'
import BookPage from '../components/pages/BookPage.vue'
import NewBookPage from '../components/pages/NewBookPage.vue'

// Hash history: the page URLs look like index.html#/books/3, so they work from any folder, without server rules
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: BooksPage },
    { path: '/books/new', name: 'new-book', component: NewBookPage },
    { path: '/books/:id', name: 'book', component: BookPage, props: true }
  ]
})

export default router
```

### Step 6 - The page title

**File `index.html`** - replace its whole content with:

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

### Step 7 - Try it in development

1. Open http://localhost:5173/ and click **Add a book**, then **Save** with an empty form: *Sign in first* in red
   (the API refuses changes from visitors: lesson 09).
2. Open http://localhost:8080/login in the same browser and sign in as `reader1`. The session cookie belongs to
   `localhost`, and the browser sends it to `localhost:5173` too; the proxy passes it to Caligrafy.
3. Back on http://localhost:5173/#/books/new, click **Save** with an empty form: three red messages under the
   fields.
4. Fill in `The Lathe of Heaven`, Ursula K. Le Guin, `1971`, **Save**: you land on the page of the new book.
5. Click **All books**: the book is in the list (from the store, without a reload). Open it and click **Delete**,
   confirm: you are back on the list, without it.

### Step 8 - Build for production

Stop `npm run dev` (<kbd>Ctrl</kbd>+<kbd>C</kbd>), then:
```bash
npm run build
```
Expected (the file names contain a hash that changes with the content):
```
vite v8.3.5 building client environment for production...
transforming...
✓ 87 modules transformed.
rendering chunks...
computing gzip size...
dist/index.html                   0.77 kB │ gzip:  0.42 kB
dist/assets/index-DznWjDjk.css   31.57 kB │ gzip:  5.96 kB
dist/assets/index-uAA5fnL7.js   155.80 kB │ gzip: 58.42 kB

✓ built in 891ms
```
Open http://localhost:8080/public/bookshelf-spa/dist/index.html: the same app, served by Caligrafy (port 8080), with
no Vite running. Sign-in, add and delete work the same way.

Check from the terminal that the built page is served:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/public/bookshelf-spa/dist/index.html
```
Expected: `200`.

`dist/` is listed in the SPA's `.gitignore`: it is a result, rebuilt from the sources, so it is not committed.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `getActivePinia()` was called but there was no active Pinia | `useBookStore()` was called outside a component, at the top of a file | Call it in `data()` as in the pages |
> | "Sign in first" although you signed in | You signed in on `127.0.0.1:8080` and use `localhost:5173` (or the reverse): cookies are per host name | Use `localhost` for both |
> | The built page is blank, the Console shows 404 for `/assets/index-...js` | `base: './'` is missing in `vite.config.js` | Add it (lesson 11, step 3) and build again |
> | The built page loads but the books do not | You opened `dist/index.html` as a file (`file:///...`) | Open it through http://localhost:8080/public/bookshelf-spa/dist/index.html |
> | `403 Forbidden` on the built page | The path contains a denied folder name (for example you built into `node_modules/`) | Keep the default `dist/` |
> | Changes do not appear on port 8080 | The built files are from the last `npm run build` | Build again after every change |

---

## With Claude

```
In public/bookshelf-spa, add a Pinia store src/common/store.js (useBookStore, defineStore 'books') with state
books, authors, current, currentAuthor, errors, message, loading; getter count; actions fetchBooks,
fetchAuthors, fetchBook(id), createBook(book) (POST /api/books; 422 -> errors, other errors -> message from
error.response.data.error.message; returns the book or null) and deleteBook(id). Use callApi and apiConfig
from src/common/app.js. Make BooksPage and BookPage use the store, add a Delete button on BookPage and a
NewBookPage form at '/books/new' (route before '/books/:id') with the errors under each field, in the
Caligrafy classes. Change the title in index.html to Bookshelf. Then run npm run build.
```

**What Claude should do:** write the files of steps 1 to 6 and run `npm run build`.

**Check** before you accept:
- [ ] `defineStore` from `pinia` (not Vuex `createStore`, no `mutations`).
- [ ] The store is used through `useBookStore()` inside components.
- [ ] 422 errors go to `errors`, and the form shows them under each field.
- [ ] `/books/new` is before `/books/:id` in the router.
- [ ] `npm run build` ends with `✓ built`, and step 8's URL answers `200`.

**If Claude's result differs:** Claude may write the store with the *setup* syntax
(`defineStore('books', () => { const books = ref([]) ... })`) and components with `<script setup>`: it is
another valid way to write Vue, but different from this course; ask for the Options syntax if you want to compare
line by line. If Claude puts the API key directly in `store.js`, ask it to use `apiConfig` (the key must only come
from `.env`).

---

## Checkpoint

```bash
cd ../..
git add -A
git commit -m "Lesson 12: Pinia store, form page, production build"
git tag lesson-12
```
Reference: [`checkpoints/lesson-12`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-12).

## Exercise

Add an *Edit* page to the SPA at `/books/:id/edit` that loads the book into the form and saves it with
`PUT /api/books/{id}`. Which store action do you add?

<details><summary>Show the solution</summary>

In the store, next to `createBook`:
```js
    async updateBook(id, book) {
      this.errors = {}
      this.message = ''
      try {
        const response = await callApi.put('/api/books/' + id, book, apiConfig)
        this.current = response.data.book
        return response.data.book
      } catch (error) {
        if (error.response && error.response.status === 422) {
          this.errors = error.response.data.errors
        } else {
          this.message = errorMessage(error)
        }
        return null
      }
    },
```
Then copy `NewBookPage.vue` to `EditBookPage.vue` with `props: ['id']`, fill `form` in `mounted()` after
`await this.store.fetchBook(this.id)` (`this.form = { title: this.store.current.title, ... }`), call
`updateBook(this.id, this.form)` in `save()`, and add the route
`{ path: '/books/:id/edit', name: 'edit-book', component: EditBookPage, props: true }`.
</details>

---
Previous: [Lesson 11 - The Vite SPA and Vue Router](11-Vite-SPA-and-Vue-Router) · Next: [Lesson 13 - Review, tests, doctor and upgrade](13-Review-Tests-Doctor-Upgrade)
