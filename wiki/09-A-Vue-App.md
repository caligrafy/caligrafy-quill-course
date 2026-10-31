# Lesson 09 - A Vue app

**In this lesson** you build **finder**, a small Vue app served by Caligrafy at http://localhost:8080/apps/finder:
you type a few words, the list of books updates as you type, and you open a book to read about it and its author. It
is a new experience of the same catalog, built with zero new server code: it calls `search_books`, `list_books` and
`get_book` through the JS client.

**Before you start:** lesson 08 is done (tag `lesson-08`), the containers run (`docker compose ps`), and
`public/js/capabilities.js` has 7 capabilities (`grep -c "export const" public/js/capabilities.js` prints `7`).

**Time:** 40 minutes.

---

## New concepts

**Vue.js.** A JavaScript library that keeps a part of the page in sync with your data. You write the HTML once with
a few special attributes; when the data changes, Vue updates the page by itself.

| In the HTML | Meaning |
|---|---|
| `{{ book.title }}` | the value of `book.title`, shown as text (escaped, so it is safe) |
| `v-for="book in books" :key="book.id"` | repeat this element for each book; `:key` tells Vue which row is which |
| `v-if="selected"` / `v-else` | show this element only when `selected` has a value / otherwise show the next one |
| `v-model="query"` | the input and `query` always hold the same value |
| `@input="search"` | on every key typed, call the method `search` |
| `@click="open(book.id)"` | on click, call `open` with the id |
| `@submit.prevent` | when the form is submitted, do not reload the page |
| `v-cloak` | hides the element until Vue is ready (no flash of `{{ }}`) |

In the JavaScript, `Vue.createApp({ data(), mounted(), methods })` defines the app: `data()` returns the data,
`mounted()` runs once when the app appears, `methods` are the functions the HTML calls. `app.mount('#app')` attaches
it to `<div id="app">`.

**Vue as a library.** The simplest way to use Vue: a `<script>` tag loads it, with no build step and no Node.js.
Caligrafy calls this a **client app**: a folder `public/<name>/` with an `index.php` (the HTML) and
`scripts/main.js` (the Vue code). `php caligrafer.php app <name>` creates it. The route
`Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');` is already in `web.php`: it shows any
client app at `/apps/<folder name>`. (Lesson 10 builds the other kind of Vue app, with Vite.)

**`env`.** The server gives the app what it needs in a JavaScript object: `loadEnvironment()` creates `env`, and
`env.home` is the address of your application (`http://localhost:8080/`). `loadVue({ scripts: ['main'] })` then loads
`scripts/main.js`.

**Dynamic import.** The JS client `public/js/capabilities.js` is an **ES module**: a file that `export`s functions
(`export const searchBooks = ...`). A page loads a module with `<script type="module">`, but Vue as a library runs
`main.js` as a classic script, where the `import ... from` line is not allowed. A classic script can still load a
module with the function `import()`:
```js
this.api = await import(env.home + 'public/js/capabilities.js')
```
`this.api` then holds every function of the module: `this.api.listBooks()`, `this.api.searchBooks({ query })`.

**No key in the page.** The template that Caligrafer copies calls the server with axios and an API key written in
the page: anyone can read it in the page source. The JS client needs no key: the browser sends the **session cookie**
of `localhost:8080` with every call, and before a call the client fetches the **CSRF token** once (a random value the
server gives to its own pages, so another site cannot send requests in your name). Lesson 11 builds sign-in on top
of this session.

**Build once, in practice.** The server already knows how to search, list and open books (lessons 04 and 08). This
lesson adds two files in `public/finder/` and nothing else: no route, no controller, no capability.

---

## Manual track

### Step 1 - Create the app folder

```bash
docker compose exec dev-box php caligrafer.php app finder
```
Expected:
```
 Project created in the public folder.
 Type cd public/finder to access it anytime
```
It copied a template to `public/finder/`: `index.php` and `scripts/main.js`. Open
http://localhost:8080/apps/finder: it answers (status 200), with the template's content. You replace both files.

### Step 2 - The HTML of the app

**File `public/finder/index.php`** (the same look as the pages: one card, a search field, `.cq-split` for the list and the book):

```php
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bookshelf finder</title>

        <!-- The same look as the Caligrafy pages: Bootstrap 5, the black and white theme and the page components -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/tokens.css'; ?>">
    </head>

    <body>
        <!-- Vue controls everything inside #app -->
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>

            <main class="cq-page cq-page--wide">
                <div class="cq-card">
                    <div class="cq-card-head">
                        <h2 class="cq-title">Find a book</h2>
                        <p class="cq-note">Type a few words of a title or a summary: the search_books capability answers as you type.</p>
                    </div>

                    <form class="cq-form" @submit.prevent>
                        <div class="cq-fields">
                            <div>
                                <label for="query">Search</label>
                                <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time, cities..." autocomplete="off">
                            </div>
                        </div>
                    </form>
                    <p class="cq-error" role="alert">{{ error }}</p>

                    <div class="cq-section cq-split">
                        <!-- the books found -->
                        <div>
                            <ul class="cq-list">
                                <li class="cq-item" v-for="book in books" :key="book.id">
                                    <div class="cq-item-body">
                                        <p class="cq-item-title">{{ book.title }}</p>
                                        <p class="cq-item-meta">{{ book.published_year }}</p>
                                    </div>
                                    <div class="cq-actions">
                                        <button type="button" class="btn btn-secondary" @click="open(book.id)">Open</button>
                                    </div>
                                </li>
                            </ul>
                            <p class="cq-empty" v-if="!books.length">No book matches "{{ query }}".</p>
                        </div>

                        <!-- the book opened: get_book -->
                        <div v-if="selected">
                            <h3 class="cq-title">{{ selected.book.title }}</h3>
                            <p class="cq-note">by {{ selected.author.name }}, {{ selected.book.published_year }}</p>
                            <p>{{ selected.book.summary || 'No summary yet.' }}</p>
                            <p class="cq-item-meta">{{ selected.author.bio }}</p>
                        </div>
                        <p class="cq-empty" v-else>Open a book to read about it.</p>
                    </div>

                    <div class="cq-foot">{{ books.length }} book(s)</div>
                </div>
            </main>
        </div>

        <!-- Vue 3 (pinned by Caligrafy), then app.js, which defines loadEnvironment() and loadVue() -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <!-- $env holds what the server gives the app: env.home is the address of the application -->
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <!-- loads scripts/main.js of this folder -->
        <script>loadVue({ scripts: ['main'] });</script>
    </body>
</html>
```

What to notice:
- The `<link>` tags load the same CSS as the Pug pages (`theme.css`, `tokens.css`): same black and white look, same
  page components (`cq-page`, `cq-card`, `cq-list`).
- `.cq-split` puts its two children side by side from 768 px wide (tablets, computers) and one under the other on
  phones. You write no CSS for that.
- The book on the right comes from `get_book`: `selected.book` and `selected.author`, the two parts of its answer.

### Step 3 - The Vue code

**File `public/finder/scripts/main.js`** (loads the JS client, then calls three capabilities):

```js
// The finder: a Vue app on the same capabilities as the pages, REST and AI agents
const app = Vue.createApp({
  data () {
    return {
      query: '',
      books: [],
      selected: null,   // { book, author } from get_book
      error: '',
      timer: null
    }
  },
  async mounted () {
    // the generated JS client: one function per capability (php caligrafer.php client public/js/capabilities.js)
    this.api = await import(env.home + 'public/js/capabilities.js')
    this.books = await this.api.listBooks()
  },
  methods: {
    // called on every key: wait until the typing pauses, then ask search_books
    search () {
      clearTimeout(this.timer)
      this.timer = setTimeout(() => this.run(async () => {
        this.books = this.query.trim().length < 2
          ? await this.api.listBooks()
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
- `mounted()` loads the client, then fills the list with `listBooks()`.
- `search()` waits until you stop typing for 250 ms before it calls the server: one call per pause, not one per key.
  Under 2 characters it shows every book instead of calling `search_books` (whose rule asks for 2 or more).
- `run()` wraps every call: when a capability refuses, the error is a `CapabilityError` with the same messages as the
  REST API and the agent saw in lesson 08.

### Step 4 - Try it

Open http://localhost:8080/apps/finder (reload if it was open).
1. The card **Find a book** lists every book; the footer says how many (`5 book(s)`, or more if you added some).
2. Type `time` and pause: only **Kindred** stays (its summary says "pulled back in time"). Footer: `1 book(s)`.
3. Click **Open**: on the right, "Kindred", "by Octavia E. Butler, 1979", the summary, then the author's bio.
4. Type `zzzz`: `No book matches "zzzz".`
5. Make the window narrow (or open the browser's device toolbar at 375 px): the book moves under the list, one column,
   no horizontal scroll.

Check what the app sends: open the developer tools (<kbd>F12</kbd>), tab **Network**, reload, type `magic`.
You see `GET capabilities` once (the client fetches the CSRF token), then `POST list_books` and `POST search_books`.
Click one: the request has an `X-CSRF-Token` header and no `Authorization` header. No key in sight.

Check that no server code changed:
```bash
git status --short
```
Expected: one line, `?? public/finder/` (a new folder, nothing modified).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | The page shows `{{ book.title }}` or stays empty | Vue did not start: an error in `main.js` | Open the Console (<kbd>F12</kbd>): it names the file and the line; compare with step 3 |
> | Console: an error that names `public/js/capabilities.js` (404 in the Network tab) | The client file is missing | `docker compose exec dev-box php caligrafer.php client public/js/capabilities.js` |
> | Console: `this.api.searchBooks is not a function` | The client was generated before `search_books` existed | Regenerate the client (lesson 08, step 3) |
> | `/apps/finder` does not show your app | The folder name and the address differ | The folder is `public/finder`, the address `/apps/finder` |
> | "Something went wrong. Try again." under the search field | The server did not answer with JSON (containers stopped, PHP error) | `docker compose ps`; run the `list_books` curl call of lesson 04 and read its answer |
> | Changes to `main.js` do not show | The browser kept the old file | Reload with <kbd>Shift</kbd> pressed, or tick *Disable cache* in the Network tab |
> | `The project could not be created` | The folder already exists, or the name is reserved (`app`, `bot`, `css`, `js`, `ml`...) | Delete the folder first, or choose another name |

---

## With Claude

In the `bookshelf` folder, start `claude` and type:
```
/caligrafy-quill:new-app app finder
```
then:
```
Turn public/finder into a Vue 3 app (Vue as a library) that finds books with the Bookshelf capabilities. Do not
use axios or the API key: in mounted(), load the JS client with this.api = await import(env.home +
'public/js/capabilities.js'), then show listBooks(). A search field calls searchBooks({ query }) 250 ms after the
typing pauses (listBooks() when the query has fewer than 2 characters). Each book has an Open button that calls
getBook({ id }) and shows, on the right, the title, "by <author>, <year>", the summary (or "No summary yet.") and
the author's bio. Show a CapabilityError's field messages (or its message) in p.cq-error. Use the page components:
main.cq-page.cq-page--wide > .cq-card with .cq-card-head (title "Find a book"), .cq-form, .cq-section.cq-split
(list | book), .cq-list/.cq-item, a .cq-foot with "N book(s)", and link theme.css and tokens.css like the Pug layout.
Do not add routes or server code: /apps/{appName} already serves it.
```

**What Claude should do:** run `php caligrafer.php app finder`, then replace `public/finder/index.php` and
`public/finder/scripts/main.js`.

**Check** before you accept:
- [ ] No `apiKey`, no `axios`, no `Authorization` header anywhere in `public/finder/`.
- [ ] The client is loaded with `import(env.home + 'public/js/capabilities.js')`, and the app calls `listBooks`,
      `searchBooks` and `getBook` only.
- [ ] `git status --short` shows only `?? public/finder/`: no change in `web.php`, `application/` or the services.
- [ ] The page uses `cq-page`, `cq-card`, `cq-split`, `cq-list` and loads `tokens.css`.
- [ ] Step 4 works: `time` finds Kindred, Open shows the author's bio, phone width is one column.

**If Claude's result differs:** if it adds a route such as `/finder`, ask it to remove it: `/apps/finder` is enough.
If it writes `import { searchBooks } from ...` at the top of `main.js`, the app does not start (a classic script
cannot use `import ... from`): ask for the dynamic `import()`. Small differences of wording or layout are fine;
compare with the checkpoint to see one way to do it.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 09: the finder Vue app"
git tag lesson-09
```
Reference: [`checkpoints/lesson-09`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-09).
Learn more: [9.7.3 Requests](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests) (calling capabilities from a Vue app).

## Exercise

Add a select **Author** under the search field. Choosing an author shows only that author's books (with `list_books`
and its optional `author_id`); choosing "Every author" shows them all again. Still no server code.

<details><summary>Show the solution</summary>

In `public/finder/index.php`, add a second field in `.cq-fields`, after the Search field:
```html
<div>
    <label for="author">Author</label>
    <select id="author" class="form-select" v-model="authorId" @change="filter">
        <option value="">Every author</option>
        <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
    </select>
</div>
```
In `public/finder/scripts/main.js`, add `authors: []` and `authorId: ''` to `data()`, load the authors in
`mounted()`, and add a method:
```js
  async mounted () {
    this.api = await import(env.home + 'public/js/capabilities.js')
    this.authors = await this.api.listAuthors()
    this.books = await this.api.listBooks()
  },
```
```js
    filter () {
      this.query = ''
      this.run(async () => {
        this.books = await this.api.listBooks(this.authorId ? { author_id: this.authorId } : {})
      })
    },
```
`list_authors` and `list_books` already existed (lesson 04): the server learns nothing new, the app just uses more of
it. To stay identical to the checkpoint, undo it afterwards with `git checkout public/finder`.
</details>

---
Previous: [Lesson 08 - AI agents](08-AI-Agents) · Next: [Lesson 10 - Phones and tablets](10-Phones-and-Tablets)
