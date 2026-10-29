# Lesson 06 - Views and the layout

**In this lesson** Bookshelf gets real pages: a list of books, a page per book and a page per author, in the
Caligrafy black and white look. You write them in **Pug**, the template language of Caligrafy, and they all reuse
the default layout (header, footer, stylesheets). The home page `/` becomes the list of books.

**Before you start:** lesson 05 is done (tag `lesson-05`): `curl http://localhost:8080/books/3` returns Kindred
from MySQL.

---

## New concepts

**View.** The HTML page the visitor sees. In Caligrafy, views are `.pug` files in `application/views/`. A
controller shows one with `view('Bookshelf/book', array('book' => $book))`: the first argument is the path of the
file inside `application/views/`, without `.pug`; the second gives the variables of the page (`$book` in the view).

**Pug.** A short way to write HTML: no closing tags, the **indentation** says what is inside what.

| Pug | HTML |
|---|---|
| `h2 Bookshelf` | `<h2>Bookshelf</h2>` |
| `.card.color0` | `<div class="card color0"></div>` (a `.` without a tag name means `div`) |
| `a.btn(href="/books") All books` | `<a class="btn" href="/books">All books</a>` |
| `p= $book->title` | `<p>` + the value of `$book->title`, **escaped** + `</p>` |
| `p by #{$author->name}` | text with a value in it, escaped |
| `each $book in $books` + indented lines | repeats the indented lines for each book |
| `if $authorized` + indented lines | shows the indented lines only when the condition is true |
| `\| some text` | a line of plain text |

The values are **PHP**: `$book->title`, `$values['title'] ?? ''`. **Escaped** means that `<` becomes `&lt;`: a
title like `<script>` is shown as text, never run. Pug escapes `=` and `#{}` for you (`!=` would not: do not use it
for data that users typed). Indent with **spaces only** (4 per level in this course): a tab, or a line indented one
space too far, changes the meaning or breaks the page.

**Why Pug?** Views stay short and easy to read, and a forgotten closing tag cannot break the page structure.

**Layout.** Every page of a site shares the same frame: `<head>` with the stylesheets, the header, the footer. In
Caligrafy this frame is `application/views/default/master/layoutblocks.pug` (it extends `layout.pug`). A view
starts with `extends ../default/master/layoutblocks` (the path is relative to the view file) and fills the
**block** called `content`. The layout:
- loads Bootstrap 5.3.8, Caligrafy's `styles.css` and `public/css/theme.css`, which gives Bootstrap the Caligrafy
  black and white look;
- puts `class='caligrafy-layout'` on `<body>` and shows the header with the `Caligrafy` title (the signed-in
  version of the header, with a *Sign out* link, appears when the view receives `authorized` = true: lesson 08);
- defines `$home`, the base URL of the site (`http://localhost:8080/`). **Build every link from `$home`**:
  `href="{$home}books"`. Then the links still work if the site is later installed in a sub-folder.

**The Caligrafy classes used in this course**

| Class | Effect |
|---|---|
| `content-section` | a full-width section of the page |
| `bounded-xlarge`, `bounded-large` | a section with side margins |
| `wrapper width-max-600`, `wrapper width-max-800` | a centered column at most 600 / 800 px wide (cards are at most 600px wide: use a 600 column for cards) |
| `card color0 dropshadow equally-spaced-1` | a white card with a soft shadow and even padding (cards are at most 600px wide; use `card-unbounded` for wider ones) |
| `medium-text`, `large-text`, `default-text`, `small-text` | text sizes |
| `semibold` | semi-bold text |
| `textcolor5` | the grey text color of Caligrafy |
| `horizontal-center` | centers the content |
| `spacer-1` ... `spacer-4` | an empty vertical space, small to large |
| `reset-margin` | removes the margins of a heading |
| `btn btn-primary` / `btn btn-secondary` | a black button / a white button with black text |
| `error` | red error text (lesson 07) |

Bootstrap 5 classes work too (`mb-3`, `row`, `col-md-6`, `form-control`), but keep the Caligrafy colors: no
`btn-success` or `bg-primary`.

---

## Manual track

### Step 1 - The list of books

Create the folder `application/views/Bookshelf` and, in it:

**File `application/views/Bookshelf/books.pug`**:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            h2.medium-text.semibold.horizontal-center Bookshelf
            p.horizontal-center A small catalog of the books we love
            .spacer-2
            each $book in $books
                a(href="{$home}books/{$book->id}")
                    .card.color0.dropshadow.equally-spaced-1
                        h3.medium-text.semibold.textcolor5.reset-margin= $book->title
                        p.default-text.textcolor5= $book->published_year
            .spacer-2
```
`block content` is not indented; everything in the block is indented by 4 spaces more at each level.
`"{$home}books/{$book->id}"` is a PHP string: `{$home}` and `{$book->id}` are replaced by their values, for
example `http://localhost:8080/books/3`.

### Step 2 - The page of a book

**File `application/views/Bookshelf/book.pug`**:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            .card.color0.dropshadow.equally-spaced-1
                h2.medium-text.semibold.reset-margin= $book->title
                .spacer-1
                p.default-text.textcolor5
                    | by 
                    a(href="{$home}authors/{$author->id}")= $author->name
                    | , #{$book->published_year}
                p= $book->summary
            .spacer-2
            .horizontal-center
                a.btn.btn-secondary(href="{$home}books") All books
            .spacer-2
```
Note the space after `| by `: the text and the link are separate lines in Pug, so the space must be written.

### Step 3 - The page of an author

**File `application/views/Bookshelf/author.pug`**:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            .card.color0.dropshadow.equally-spaced-1
                h2.medium-text.semibold.reset-margin= $author->name
                .spacer-1
                p= $author->bio
                h3.default-text.semibold Books
                ul
                    each $book in $books
                        li
                            a(href="{$home}books/{$book->id}")= $book->title
            .spacer-2
            .horizontal-center
                a.btn.btn-secondary(href="{$home}books") All books
            .spacer-2
```

### Step 4 - A "not found" page

**File `application/views/Bookshelf/notfound.pug`**:

```pug
extends ../default/master/layoutblocks

block content
    .content-section.bounded-xlarge
        .wrapper.width-max-600
            .spacer-4
            .card.color0.dropshadow.equally-spaced-1
                h2.medium-text.semibold.reset-margin Not found
                .spacer-1
                p= $message
            .spacer-2
            .horizontal-center
                a.btn.btn-secondary(href="{$home}books") All books
            .spacer-2
```

### Step 5 - The controllers show the views

Replace `api(...)` by `view(...)`.

**File `application/controllers/Bookshelf/BookController.php`** - replace its whole content with:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // GET /books (and GET /)
    public function index()
    {
        $this->associate('Book', 'books');
        $books = $this->all(array('order' => 'ORDER BY title'));
        return view('Bookshelf/books', array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return view('Bookshelf/book', array('book' => $book, 'author' => $book->author()));
    }
}
```

**File `application/controllers/Bookshelf/AuthorController.php`** - replace its whole content with:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

class AuthorController extends Controller {

    // GET /authors/{id}
    public function read()
    {
        $this->associate('Author', 'authors');
        $author = $this->find();
        if (!$author) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no author with this number.'));
        }
        return view('Bookshelf/author', array('author' => $author, 'books' => $author->books()));
    }
}
```

### Step 6 - The home page shows the books

In `web.php`, the first route now shows the list of books. It stays the **first** route.

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
    Route::get('/books/{id}', 'Bookshelf\\BookController@read');
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@read');

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

### Step 7 - Look at the pages

| URL | You should see |
|---|---|
| http://localhost:8080/ | the header `Caligrafy`, the title **Bookshelf**, and 5 white cards: A Wizard of Earthsea (1968), Invisible Cities, Kindred, Parable of the Sower, The Left Hand of Darkness |
| click **Kindred** | a card "Kindred", "by Octavia E. Butler, 1979", the summary, and a white **All books** button |
| click **Octavia E. Butler** | her bio and the list Kindred, Parable of the Sower |
| http://localhost:8080/books/42 | the card "Not found - There is no book with this number." |

Check the status codes:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/books/3
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/books/42
```
Expected: `200`, then `404`.

### Step 8 - Escaping, for real

Give a book a title that contains HTML, and look at it:
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "UPDATE books SET title = '<i>Kindred</i>' WHERE id = 3"
```
Open http://localhost:8080/books/3: the title shows `<i>Kindred</i>` as text, not in italics. Pug escaped it.
Put the title back:
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "UPDATE books SET title = 'Kindred' WHERE id = 3"
```

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Failed to compile: Source file /var/www/html/application/views/Bookshelf/authors.pug not found` (500) | The view path in `view()` does not match the file (`Bookshelf/author` = `application/views/Bookshelf/author.pug`, case-sensitive) | Check the folder and file names |
> | `Failed to lex: Inconsistent indentation. Expecting either 12 or 16 spaces/tabs.` with the line number (500) | A line indented by a different number of spaces, or a tab mixed with spaces | Re-indent the file with spaces only, 4 per level |
> | The page has no header and no styles | The first line `extends ../default/master/layoutblocks` is missing or wrong, or the content is not inside `block content` | Compare with step 1 |
> | All the text of a card is inside the link but the page shows `{$home}` literally | Single quotes around the attribute: `href='{$home}books'` | Use double quotes: `href="{$home}books"` |
> | A dark error page with `Undefined variable $books on line 13` | The controller did not pass it in the `view()` array (a typo in the key) | Compare the keys of the array with the variables of the view |
> | Your change does not show | PHP's file cache (2 seconds, lesson 02) | Wait 2 seconds and reload |
> | `[ERROR 1004] Access Denied` (401) | You called the page with `curl -H "Accept: application/json"` without the API key | Normal: see lesson 09 |

---

## With Claude

```
Create Pug views for Bookshelf in application/views/Bookshelf/: books.pug (the list of $books, each a link to
{$home}books/{id}), book.pug ($book and $author, with a link to the author page {$home}authors/{id}),
author.pug ($author and their $books) and notfound.pug ($message). They extend
../default/master/layoutblocks and fill block content, use the Caligrafy black and white classes
(content-section bounded-xlarge, wrapper width-max-600, card color0 dropshadow equally-spaced-1, medium-text
semibold, textcolor5, btn btn-secondary, spacer-N), no Bootstrap colors, links built from $home.
Then make Bookshelf\BookController and Bookshelf\AuthorController return these views instead of api(),
with HTTP 404 and notfound.pug when nothing is found, and make the '/' route use Bookshelf\BookController.
```

**What Claude should create:** four `.pug` files and the two controllers of steps 1 to 6. The layout and the
classes come from the plugin's `caligrafy` skill (pages *Views* and the gotcha about Bootstrap 5).

**Check** before you accept:
- [ ] Every view starts with `extends ../default/master/layoutblocks` and has `block content`.
- [ ] Values are printed with `=` or `#{}`, never `!=`.
- [ ] Links use `{$home}`, inside double quotes.
- [ ] No Bootstrap color classes (`btn-success`, `bg-primary`, `text-white`...).
- [ ] `/` is still the first route, `Route::run()` still last; the pages of step 7 look as described.

**If Claude's result differs:** Claude may give the views other names (`index.pug`, `show.pug`) or another
folder: fine, as long as `view()` uses the same path. If Claude adds a `navigation` block or its own CSS file,
ask it to remove them: the default layout already has the header. If a page fails to render, ask Claude to run
`curl -s http://localhost:8080/books/3 | head -40` and read the Phug error with you.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 06: Pug views"
git tag lesson-06
```
Reference: [`checkpoints/lesson-06`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-06).

## Exercise

On the page of a book, under the summary, show "Added on" followed by the date of `created_at`, in small grey
text. Which Pug line do you add?

<details><summary>Show the solution</summary>

In `book.pug`, after `p= $book->summary`, at the same indentation:
```pug
                p.small-text.textcolor5 Added on #{$book->created_at}
```
</details>

---
Previous: [Lesson 05 - Models and relationships](05-Models-and-Relationships) · Next: [Lesson 07 - Forms, CSRF and validation](07-Forms-CSRF-and-Validation)
