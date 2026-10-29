# Lesson 05 - Models and relationships

**In this lesson** you write the models `Book` and `Author`, and `BookController` reads the books from MySQL instead
of its array. A new `AuthorController` shows an author with all their books. You learn the two relationships
Caligrafy offers for a foreign key: `hasOne()` (a book has one author) and `hasMany()` (an author has many books).

**Before you start:** lesson 04 is done (tag `lesson-04`): the tables `authors` and `books` exist and hold the
sample data.

---

## New concepts

**Model.** A PHP class that represents one table. One **object** of the class is one **row**: a `Book` object has
the properties `id`, `title`, `published_year`... with the values of one row of `books`. Your model `extends
Caligrafy\Model`, which knows how to read and write rows. The rule: **one public property per column**, with
exactly the column names. A property that is not a column is ignored when saving; a column without a property is
not read into the object.

**Models are global classes.** Models do not need a namespace: their names (`Book`, `Author`) are the names of
your tables and are unlikely to collide. We put them in `application/models/Bookshelf/` next to the SQL scripts.

**`associate('Book', 'books')`.** A controller works with one model at a time. `associate()` tells it which model
class and which table: after it, the controller methods `all()`, `find()`, `save()` and `delete()` work on that
table. Call it at the start of every action, and again when you switch to another table.
`'Book'` is a **string**, so PHP looks for the class `Book` in the global namespace, even from a controller in
`namespace Bookshelf` (writing `new Book()` there would look for `Bookshelf\Book`: you would need `use Book;`).

**`all($args)`.** Returns all the rows as an array of model objects. `$args` can add SQL clauses:
`array('order' => 'ORDER BY title')`. The clauses are pasted into the SQL as they are, so **never put a value that
comes from the user into them**: use a `?` placeholder and `'bindings'` instead:
`array('condition' => 'WHERE author_id = ?', 'bindings' => array($id))`. The value is then sent separately to
MySQL and cannot change the query (no SQL injection).

**`find()`.** Returns the row whose `id` is the `{id}` of the URL, as a model object, or `null` when there is none.
`find(3)` finds the row 3.

**Relationships.** A method of the model that returns related rows:

| On | Method | Returns | SQL that Caligrafy runs (names come from the convention `<model>_id`) |
|---|---|---|---|
| `Book` | `$this->hasOne('Author', 'authors')` | one `Author` (or `null`) | the author whose `id` is this book's `author_id` |
| `Author` | `$this->hasMany('Book', 'books')` | an array of `Book` | `SELECT * FROM books WHERE author_id = <this author's id>` |

For a foreign key `author_id` on `books`, `hasOne` reads it from the book side and `hasMany` from the author side.
(Caligrafy also has `belongsTo()`, but it does **not** mean "the inverse of hasMany": it is another name for
`hasMany`. Use `hasOne` on the side that holds the foreign key.)

**JSON of a model.** `api()` turns model objects into JSON with all their public properties, in the order of the
class, starting with the property `table` (the table name, which every model has). Numbers stay numbers
(`"id":3`), dates are text (`"created_at":"2026-10-29 20:32:40"`).

---

## Manual track

### Step 1 - The `Author` model

**File `application/models/Bookshelf/Author.php`**:

```php
<?php

use Caligrafy\Model;

// One Author object = one row of the authors table
class Author extends Model {

    // one public property per column of the table
    public $id;
    public $name;
    public $bio;
    public $created_at;
    public $modified_at;

    // An author has many books: SELECT * FROM books WHERE author_id = <this author's id>
    public function books()
    {
        return $this->hasMany('Book', 'books');
    }
}
```

### Step 2 - The `Book` model

**File `application/models/Bookshelf/Book.php`**:

```php
<?php

use Caligrafy\Model;

// One Book object = one row of the books table
class Book extends Model {

    // one public property per column of the table
    public $id;
    public $title;
    public $published_year;
    public $summary;
    public $author_id;
    public $created_at;
    public $modified_at;

    // A book has one author: the row of authors whose id is this book's author_id
    public function author()
    {
        return $this->hasOne('Author', 'authors');
    }
}
```

### Step 3 - `BookController` reads MySQL

**File `application/controllers/Bookshelf/BookController.php`** - replace its whole content with:

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // GET /books
    public function index()
    {
        // work with the Book model and the books table
        $this->associate('Book', 'books');
        $books = $this->all(array('order' => 'ORDER BY title'));
        return api(array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();   // uses {id} from the URL
        if (!$book) {
            http_response_code(404);
            return api(array('error' => array('code' => 404, 'message' => 'Book not found')));
        }
        return api(array('book' => $book, 'author' => $book->author()));
    }
}
```

### Step 4 - `AuthorController`

**File `application/controllers/Bookshelf/AuthorController.php`**:

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
            return api(array('error' => array('code' => 404, 'message' => 'Author not found')));
        }
        return api(array('author' => $author, 'books' => $author->books()));
    }
}
```

### Step 5 - The route of the author page

In `web.php`, add one line after the two book routes, so that the lesson 03 block becomes:

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
    Route::get('/', function() { return view('default/index'); });
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

### Step 6 - Try it

```bash
curl http://localhost:8080/books/3
```
Expected (one line; shown here on two lines):
```json
{"book":{"table":"books","id":3,"title":"Kindred","published_year":1979,"summary":"A young woman is pulled back in time to a plantation in Maryland.","author_id":2,"created_at":"2026-10-29 20:32:40","modified_at":"2026-10-29 20:32:40"},
 "author":{"table":"authors","id":2,"name":"Octavia E. Butler","bio":"American science fiction author, the first to receive a MacArthur fellowship.","created_at":"2026-10-29 20:32:40","modified_at":"2026-10-29 20:32:40"}}
```
(`created_at` and `modified_at` are the date and time when you ran the seed.)

```bash
curl http://localhost:8080/authors/1
```
Expected: `{"author":{"table":"authors","id":1,"name":"Ursula K. Le Guin",...},"books":[{"table":"books","id":1,"title":"A Wizard of Earthsea",...},{"table":"books","id":2,"title":"The Left Hand of Darkness",...}]}`

```bash
curl http://localhost:8080/books
```
Expected: the 5 books, sorted by title: A Wizard of Earthsea, Invisible Cities, Kindred, Parable of the Sower,
The Left Hand of Darkness.

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/authors/42
```
Expected: `404`.

### Step 7 - Prove that the data comes from MySQL

Change a title in the database:
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "UPDATE books SET title = 'Kindred (1979)' WHERE id = 3"
curl http://localhost:8080/books/3
```
The JSON now says `"title":"Kindred (1979)"`. Put it back:
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "UPDATE books SET title = 'Kindred' WHERE id = 3"
```

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 2003] Database Query Error` | No connection: `DB_ACTIVATE` is not `true`, `DB_HOST` is not `mysql`, or the table does not exist | Check `.env` (lesson 01, step 6) and `docker compose exec dev-box php caligrafer.php doctor --only database`; check the tables exist (lesson 04) |
> | `"author":null` | The book's `author_id` points to no author, or the method `author()` uses another table name | Check the data with the SELECT of lesson 04; `hasOne('Author', 'authors')` |
> | `"books":[]` for an author with books | The foreign key is not called `author_id`, or the class is not called `Author` | `hasMany()` looks for `<lower case class name>_id` = `author_id` |
> | A JSON property is `null` although the column has a value | The property name differs from the column name (`$year` instead of `$published_year`) | One public property per column, same names |
> | `Class "Bookshelf\Book" not found` | You wrote `new Book()` in a namespaced controller | Use `$this->associate('Book', 'books')` (a string), or add `use Book;` under `use Caligrafy\Controller;` |
> | `[ERROR 500] Cannot redeclare class Book (previously declared in ...)` | A second file declares `class Book` somewhere in `application/` (a copy, a backup) | Keep one class per name in `application/` |

---

## With Claude

The plugin's `/caligrafy-quill:new-resource` command scaffolds a SQL script, a model, a controller with CRUD actions
and REST routes in one go. Here the tables already exist, so ask for the models and the read actions only:

```
The tables authors and books already exist (see application/models/Bookshelf/create_db.sql; books.author_id
references authors.id). Create the global models Author and Book in application/models/Bookshelf/ with one
public property per column, Book::author() with hasOne('Author', 'authors') and Author::books() with
hasMany('Book', 'books'). Change Bookshelf\BookController so that index() returns
api(['books' => all ordered by title]) and read() returns api(['book' => ..., 'author' => $book->author()])
or a 404 JSON error, and add Bookshelf\AuthorController with read() returning the author and their books,
with its route GET /authors/{id}. Do not create views and do not change the SQL.
```

**What Claude should create:** the two models, the two controllers and one route, close to steps 1 to 5.

**Check** before you accept:
- [ ] Each model has one public property per column, with the exact column names (compare with `create_db.sql`).
- [ ] `Book::author()` uses `hasOne`, `Author::books()` uses `hasMany` - not `belongsTo`.
- [ ] Every action calls `associate()` before `all()` / `find()`.
- [ ] No action is named `find`, `all`, `delete`...; the route is `'Bookshelf\\AuthorController@read'`.
- [ ] Every `all()` clause with a value uses `?` and `'bindings'` (here there is none).
- [ ] The curl commands of step 6 give the expected results.

**If Claude's result differs:** if you used `/caligrafy-quill:new-resource Book ...` instead of the prompt, Claude
also writes a new `create_db.sql` (with its own table definition), a controller named `BookController`
**without** a namespace and `create/update/remove` actions. Keep your SQL from lesson 04, and either ask Claude to
put the controller in `namespace Bookshelf` with the routes `'Bookshelf\\BookController@...'`, or delete the
extra actions: lesson 07 writes them step by step. A global `BookController` next to your `Bookshelf\BookController`
works (different full names), but it is confusing: keep one.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 05: Book and Author models"
git tag lesson-05
```
Reference: [`checkpoints/lesson-05`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-05).

## Exercise

Add an action `recent()` to `BookController` and a route `GET /books/recent` that returns the books published
after 1975, newest first. Use `all()` with a `?` placeholder.

<details><summary>Show the solution</summary>

```php
    // GET /books/recent
    public function recent()
    {
        $this->associate('Book', 'books');
        $books = $this->all(array(
            'condition' => 'WHERE published_year > ?',
            'order' => 'ORDER BY published_year DESC',
            'bindings' => array(1975)
        ));
        return api(array('books' => $books));
    }
```
Route, **before** `/books/{id}` (otherwise `recent` is taken as an id):
```php
    Route::get('/books/recent', 'Bookshelf\\BookController@recent');
```
Result: Parable of the Sower (1993), then Kindred (1979).
</details>

---
Previous: [Lesson 04 - The database](04-The-Database) · Next: [Lesson 06 - Views and the layout](06-Views-and-the-Layout)
