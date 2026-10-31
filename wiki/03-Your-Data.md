# Lesson 03 - Your data: tables and models

**In this lesson** you create the two tables of Bookshelf, `authors` and `books`, fill them with five books, and
write their two **models**, `Author` and `Book`. Nothing is visible on a page yet: this is the data every experience
of Bookshelf will share, through the capabilities of lesson 04.

**Before you start:** tag `lesson-02` (the Tasks example is removed); the containers run (`docker compose ps` shows
three containers `Up`).

**Time:** 30 to 45 minutes.

---

## New concepts

### Database, table, row, column

MySQL keeps data in **tables**, inside a **database** (yours is called `caligrafy`: quickstart created it). A table is
like a spreadsheet: each **column** has a name and a type (`title`: text of at most 200 characters), each **row** is
one record (one book).

### The Caligrafy conventions

| Convention | Example | Why |
|---|---|---|
| Table names: plural, lower case | `books`, `authors` | one table holds many rows |
| Primary key: a column `id`, a number MySQL chooses (`AUTO_INCREMENT`: 1, 2, 3...) | `books.id` | the framework finds a row by its `id` |
| Foreign key: `<model in lower case>_id` | `books.author_id` holds the `id` of a row of `authors` | the relationships of the models rely on this name |
| `created_at` and `modified_at` | filled by MySQL itself | you always know when a row was added or changed |

A **foreign key** links two tables: instead of copying the author's name into every book, a book stores the `id` of
its author. The **constraint** `FOREIGN KEY (author_id) REFERENCES authors (id)` makes MySQL refuse a book whose
author does not exist, and refuse to delete an author who still has books.

### SQL scripts, not migrations

Some frameworks create tables from PHP code ("migrations"). Caligrafy uses plain **SQL scripts** next to the models,
by convention `application/models/<Folder>/create_db.sql`, that you run with
`docker compose exec dev-box php caligrafer.php sql <file>`. Two habits make a script safe to run again:
`CREATE TABLE IF NOT EXISTS` (never `DROP TABLE` in it), and `INSERT IGNORE` for sample data (skip a row whose `id`
already exists).

### Models

A **model** is a PHP class that represents one table: one **object** of the class is one **row**. Your models
`extends Caligrafy\Model`, which knows how to read and write rows. The rule: **one public property per column**, with
exactly the column names. Models need no namespace: they are global classes, named like the thing they hold
(`Book`, `Author`).

A **relationship** is a method of the model that returns related rows:

| On | Method | Returns |
|---|---|---|
| `Book` | `author()`: `$this->hasOne('Author', 'authors')` | the one author whose `id` is this book's `author_id` |
| `Author` | `books()`: `$this->hasMany('Book', 'books')` | every book whose `author_id` is this author's `id` |

### Why this matters for build once

The tables and models are written once, below every experience. In lesson 04 the capabilities use them
(`$book->author()`), and every experience (pages, REST, agents, apps) gets the same data through the capabilities.
No experience talks to the tables directly.

---

## Manual track

### Step 1 - Write the table script

Create the folder `application/models/Bookshelf` and, in it, the file `create_db.sql`.

**File `application/models/Bookshelf/create_db.sql`**:

```sql
/* Bookshelf tables. Safe to run more than once: it never drops anything.
 * Run it with:
 * docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
 */

CREATE TABLE IF NOT EXISTS authors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    bio TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    published_year SMALLINT UNSIGNED NOT NULL,
    summary TEXT NULL,
    author_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_books_author_id (author_id),
    CONSTRAINT fk_books_author_id FOREIGN KEY (author_id) REFERENCES authors (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

What each part means:
- `INT UNSIGNED`: a whole number, never negative. `author_id` must have **exactly** the type of `authors.id`,
  otherwise MySQL refuses the foreign key.
- `VARCHAR(150)`: text of at most 150 characters; `TEXT`: long text; `SMALLINT UNSIGNED`: 0 to 65535, enough for a year.
- `NOT NULL`: the value is required; `NULL`: it may be empty (a book may have no summary yet).
- `DEFAULT CURRENT_TIMESTAMP` fills the date when the row is added; `ON UPDATE CURRENT_TIMESTAMP` refreshes
  `modified_at` whenever the row changes.
- `authors` comes first because `books` refers to it.
- `fk_books_author_id`: constraint names must be unique in the whole database, hence `fk_<table>_<column>`.
- `ON DELETE RESTRICT`: deleting an author who still has books is refused.
- `utf8mb4`: stores every character, accents and emoji included.

### Step 2 - Write the sample data script

**File `application/models/Bookshelf/seed.sql`**:

```sql
/* Sample data. INSERT IGNORE skips rows whose id already exists, so running it twice changes nothing.
 * Run it after create_db.sql with:
 * docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/seed.sql
 */

INSERT IGNORE INTO authors (id, name, bio) VALUES
    (1, 'Ursula K. Le Guin', 'American author of science fiction and fantasy, known for the Earthsea books.'),
    (2, 'Octavia E. Butler', 'American science fiction author, the first to receive a MacArthur fellowship.'),
    (3, 'Italo Calvino', 'Italian writer of playful, inventive novels and short stories.');

INSERT IGNORE INTO books (id, title, published_year, summary, author_id) VALUES
    (1, 'A Wizard of Earthsea', 1968, 'A gifted boy learns magic and must face the shadow he set loose.', 1),
    (2, 'The Left Hand of Darkness', 1969, 'An envoy visits a planet whose people have no fixed sex.', 1),
    (3, 'Kindred', 1979, 'A young woman is pulled back in time to a plantation in Maryland.', 2),
    (4, 'Parable of the Sower', 1993, 'In a collapsing California, a teenager starts a new belief system.', 2),
    (5, 'Invisible Cities', 1972, 'Marco Polo describes imaginary cities to the emperor Kublai Khan.', 3);
```

The ids are fixed (1 to 5), so that book 3 is *Kindred* on every computer: the next lessons rely on it.

### Step 3 - Run both scripts

```bash
docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/seed.sql
```
Expected, one line each:
```
 application/models/Bookshelf/create_db.sql run on caligrafy
 application/models/Bookshelf/seed.sql run on caligrafy
```
`caligrafer.php sql` runs the script on the database of `.env` (`caligrafy`). Run the seed a second time: it prints
the same line and changes nothing (`INSERT IGNORE`).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `service "dev-box" is not running` | The containers are stopped | `docker compose up -d dev-box` |
> | An error that names `No such file` or the path | The file is not where the command says, or has another name | Check `ls application/models/Bookshelf` (exactly `create_db.sql` and `seed.sql`) |
> | `You have an error in your SQL syntax` (ERROR 1064) | A typo: a missing comma between columns, or a comma after the last line before `)` | Compare with step 1, or copy the file from the checkpoint |
> | `Referencing column 'author_id' and referenced column 'id' ... are incompatible` (ERROR 3780) | `author_id` and `authors.id` have different types (`INT` and `INT UNSIGNED`) | Use `INT UNSIGNED` for both, as in step 1 |
> | `Table 'caligrafy.authors' doesn't exist` (ERROR 1146) while seeding | `seed.sql` was run before `create_db.sql` | Run `create_db.sql`, then `seed.sql` |
> | `Permission denied` or `EACCES` when you save or create a file (Linux, or WSL) | The container writes the files it creates as root | In the `bookshelf` folder run `sudo chown -R "$USER": .`, then retry (again after a `caligrafer` command that creates files) |

### Step 4 - Check the data

Ask MySQL for every book with its author's name (a `JOIN` follows the foreign key):
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "SELECT b.title, a.name FROM books b JOIN authors a ON a.id = b.author_id"
```
Expected: the password warning (harmless), then 5 rows (MySQL draws them in a table with borders):

| title | name |
|---|---|
| A Wizard of Earthsea | Ursula K. Le Guin |
| The Left Hand of Darkness | Ursula K. Le Guin |
| Kindred | Octavia E. Butler |
| Parable of the Sower | Octavia E. Butler |
| Invisible Cities | Italo Calvino |

Or look in **phpMyAdmin**: open http://localhost:8077, sign in with user `root` and password `root`, click the
database `caligrafy` in the left column, then `books`: the 5 rows. The tab **Structure** shows the columns and their
types. phpMyAdmin is handy to look; change the structure only through the SQL scripts, so that they stay the truth
about your database.

You also see `caligrafy_tokens` and `caligrafy_audit` (the framework tables of lesson 02): leave them alone.

### Step 5 - See the foreign key protect your data

Try to delete Ursula K. Le Guin, who has two books:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "DELETE FROM authors WHERE id = 1"
```
Expected (after the password warning):
```
ERROR 1451 (23000) at line 1: Cannot delete or update a parent row: a foreign key constraint fails (`caligrafy`.`books`, CONSTRAINT `fk_books_author_id` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE)
```
Nothing was deleted (`ON DELETE RESTRICT`).

### Step 6 - Write the models

**File `application/models/Bookshelf/Author.php`** (one property per column of `authors`, and the books of the author):

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

**File `application/models/Bookshelf/Book.php`** (one property per column of `books`, and the author of the book):

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

- `use Caligrafy\Model;` lets you write `Model` instead of the full name `Caligrafy\Model`.
- `'Author', 'authors'`: the model class and its table. The relationship finds the link by the convention
  `author_id`, which is why the column must have exactly that name.
- `hasOne` goes on the side that **holds** the foreign key (`books.author_id`); `hasMany` on the other side.

Check that PHP reads both files without a syntax error:
```bash
docker compose exec dev-box php -l application/models/Bookshelf/Author.php
docker compose exec dev-box php -l application/models/Bookshelf/Book.php
```
Expected: `No syntax errors detected in application/models/Bookshelf/Author.php`, and the same for `Book.php`.
Lesson 04 uses the models for real.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `PHP Parse error: syntax error, unexpected ...` | A missing `;`, `{` or `}` | The error names the line; compare with the file above |
> | Later, a property stays empty (lesson 04) | The property name differs from the column name (`year` instead of `published_year`) | One public property per column, exactly the column names |
> | Later, `$book->author()` returns nothing | The foreign key column is not named `author_id` | Keep the convention `<model>_id` |

---

## With Claude

The plugin command `/caligrafy-quill:db` runs SQL scripts in the `mysql` container and lists tables. It reads a
script first and asks before running anything that drops or deletes data.

In the `bookshelf` folder, start `claude` and ask:
```
Write the Bookshelf data layer, following the Caligrafy conventions, in application/models/Bookshelf/:
1. create_db.sql for MySQL 8.4: authors (id INT UNSIGNED AUTO_INCREMENT primary key, name VARCHAR(150) required,
   bio TEXT optional) and books (id INT UNSIGNED AUTO_INCREMENT primary key, title VARCHAR(200) required,
   published_year SMALLINT UNSIGNED required, summary TEXT optional, author_id INT UNSIGNED required with the
   index idx_books_author_id and the constraint fk_books_author_id to authors(id), ON UPDATE CASCADE ON DELETE
   RESTRICT). Both with created_at and modified_at DATETIME filled by MySQL, InnoDB, utf8mb4,
   CREATE TABLE IF NOT EXISTS and no DROP.
2. seed.sql with INSERT IGNORE and fixed ids: authors 1 Ursula K. Le Guin, 2 Octavia E. Butler, 3 Italo Calvino,
   each with a one-sentence bio; books 1 A Wizard of Earthsea (1968, author 1), 2 The Left Hand of Darkness
   (1969, 1), 3 Kindred (1979, 2), 4 Parable of the Sower (1993, 2), 5 Invisible Cities (1972, 3), each with a
   one-sentence summary.
3. The models Author.php and Book.php: global classes (no namespace) that extend Caligrafy\Model, one public
   property per column, Book::author() with hasOne('Author', 'authors') and Author::books() with
   hasMany('Book', 'books').
Then run both scripts with /caligrafy-quill:db and list the tables.
```
or run the scripts yourself through the plugin:
```
/caligrafy-quill:db application/models/Bookshelf/create_db.sql
/caligrafy-quill:db application/models/Bookshelf/seed.sql
/caligrafy-quill:db tables
```

**What Claude should do:** write the four files, run `create_db.sql` then `seed.sql` in the `mysql` container, and
list the tables `authors` (3 rows) and `books` (5 rows), next to `caligrafy_tokens` and `caligrafy_audit`.

**Check** before you accept:
- [ ] Primary keys are `id`; the foreign key column is `author_id` (not `authors_id` or `writer`).
- [ ] `author_id` and `authors.id` are both `INT UNSIGNED`; no `DROP TABLE` in `create_db.sql`.
- [ ] The seed uses `INSERT IGNORE` and the fixed ids 1 to 5, with book 3 = Kindred (later lessons rely on these ids).
- [ ] The models have no `namespace` line, extend `Caligrafy\Model`, and have exactly one public property per column.
- [ ] `Book` uses `hasOne('Author', 'authors')`, `Author` uses `hasMany('Book', 'books')`.
- [ ] The query of step 4 returns the 5 books with their authors.
- [ ] Claude dropped or emptied nothing, and did not print `.env`.

**If Claude's result differs:** Claude's comments and summaries may differ from the files of this page: that is fine
if the Check list passes, but the next lessons show outputs for exactly these 5 books, so compare with the
checkpoint (`diff -ru`, lesson 00) or copy its files. If Claude wants to run a script that contains `DROP TABLE`,
say no and ask it to remove the `DROP` lines.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 03: Bookshelf tables and models"
git tag lesson-03
```
Reference: [`checkpoints/lesson-03`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-03).
The data itself is not in git: it lives in the `mysql` container. Anyone who clones your project recreates it by
running the two scripts of step 3.

## Quiz

1. Why is the column named `author_id`, and not `writer`?
2. You run `create_db.sql` and `seed.sql` a second time. What happens?
3. Which side gets `hasOne`, and which side `hasMany`?
4. A page, the REST API and an AI agent all need the list of books. Which of them read the `books` table directly?

<details><summary>Show the solution</summary>

1. The relationships find the link by the convention `<model in lower case>_id`: for the model `Author`, `author_id`.
2. Nothing changes: `CREATE TABLE IF NOT EXISTS` skips existing tables, and `INSERT IGNORE` skips rows whose `id`
   already exists.
3. `hasOne` on `Book`, the side that holds the foreign key (`books.author_id`); `hasMany` on `Author`.
4. None of them. They all call the same capability (lesson 04), and only the capability uses the models and the tables.
</details>

---
Previous: [Lesson 02 - Build once, seen three ways](02-Build-Once-Seen-Three-Ways) · Next: [Lesson 04 - Your first capabilities](04-Your-First-Capabilities)
