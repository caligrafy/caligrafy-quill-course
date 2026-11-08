# Lesson 03 - Your data: tables and models

**In this lesson** you write the first version of the two tables of Bookshelf, `authors` and `books`, with five sample
books, in one SQL script, `create_db.sql`, and run it. Then you write their two **models**, `Author` and `Book`.
Nothing is visible on a page yet: this is the data every experience of Bookshelf will share, through the capabilities
of lesson 04.

**Before you start:** tag `lesson-02` (the Tasks example is removed); the containers run (`docker compose ps` shows
three containers `Up`).

**Time:** 30 to 45 minutes.

---

## New concepts

### Database, table, row, column

MySQL keeps data in **tables**, inside a **database** (yours is called `caligrafy`: quickstart created it). A table is
like a spreadsheet: each **column** has a name and a type (`title`: text of at most 200 characters), each **row** is one
record (one book).

### The Caligrafy conventions

| Convention | Example | Why |
|---|---|---|
| Table names: plural, lower case | `books`, `authors` | the model `Book` finds its table `books` by itself |
| Primary key: a column `id`, a number MySQL chooses (`AUTO_INCREMENT`: 1, 2, 3...) | `books.id` | the framework finds a row by its `id` |
| Foreign key: `<singular of the table>_id` | `books.author_id` holds the `id` of a row of `authors` | the query builder follows this name to load related rows (`with('author')`, lesson 04) |
| `created_at` and `modified_at` | filled by MySQL itself | you always know when a row was added or changed |

A **foreign key** links two tables: instead of copying the author's name into every book, a book stores the `id` of
its author. The **constraint** `FOREIGN KEY (author_id) REFERENCES authors (id)` makes MySQL refuse a book whose author
does not exist, and refuse to delete an author who still has books.

### create_db.sql: the first version of the tables

The tables of a part of your application start in one SQL script next to its models,
`application/models/<Folder>/create_db.sql`, run with `docker compose exec dev-box php caligrafer.php sql <file>`. It is
written so that it can run again without harm:

- `CREATE TABLE IF NOT EXISTS`: a table that exists is skipped (never a `DROP TABLE`);
- `INSERT IGNORE` for the sample rows, with fixed ids: a row whose `id` exists is skipped.

The deploy step runs it on the server too (lesson 14). Once the tables hold data, you **never edit** this script to
change them: a change goes in a **migration**, a numbered file that runs once everywhere (lesson 05).

### Models

A **model** is a PHP class that represents one table: one object of the class is one row. Your models
`extends Caligrafy\Model`. The rule: **one public property per column**, with exactly the column names: the data of a
row comes out through these properties, so a column without its property never reaches a page, REST or an agent.
Models need no namespace: they are global classes, named like one row (`Book`, `Author`).

The model class also starts queries, with the **query builder** of lesson 04: `Book::find(3)` reads book 3,
`Book::where('author_id', 2)->get()` the books of author 2, and `Book::with('author')` adds each book's author. The
class finds its table by its name: `Book` reads `books` (the class name in lower case, plus an `s`). Never write a
method named `find()` in a model: `Book::find()` would then fail.

### Why this matters for build once

The tables and models are written once, below every experience. In lesson 04 the capabilities use them, and every
experience (pages, REST, agents, apps) gets the same data through the capabilities. No experience reads the tables
directly.

---

## Steps

### Step 1 - Write the tables and the sample books

Create the folder `application/models/Bookshelf` and, in it, the file `create_db.sql`:

**File `application/models/Bookshelf/create_db.sql`** (the first version of the tables, and five sample books):

```sql
/* Bookshelf: the first version of its tables, and sample books.
 * Run it with: docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
 * Safe to run again: CREATE TABLE IF NOT EXISTS skips a table that exists, INSERT IGNORE a row whose id exists.
 * Never edit it once it ran on a database with data: change a table with a migration (lesson 05).
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

-- Sample data, with fixed ids so that book 3 is Kindred everywhere
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

What each part means:
- `INT UNSIGNED`: a whole number, never negative. `author_id` must have **exactly** the type of `authors.id`, otherwise
  MySQL refuses the foreign key.
- `VARCHAR(150)`: text of at most 150 characters; `TEXT`: long text; `SMALLINT UNSIGNED`: 0 to 65535, enough for a year.
- `NOT NULL`: the value is required; `NULL`: it may be empty (a book may have no summary yet).
- `DEFAULT CURRENT_TIMESTAMP` fills the date when the row is added; `ON UPDATE CURRENT_TIMESTAMP` refreshes
  `modified_at` whenever the row changes.
- `authors` comes first because `books` refers to it.
- `fk_books_author_id`: constraint names must be unique in the whole database, hence `fk_<table>_<column>`.
- `ON DELETE RESTRICT`: deleting an author who still has books is refused.
- `utf8mb4`: stores every character, accents and emoji included.
- The sample rows have fixed ids (1 to 5), so that book 3 is *Kindred* on every computer: the next lessons rely on it.

### Step 2 - Run it

```bash
docker compose exec dev-box php caligrafer.php sql application/models/Bookshelf/create_db.sql
```
Expected:
```text
 application/models/Bookshelf/create_db.sql run on caligrafy
```
Run the same command a second time: it prints the same line and changes nothing.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `service "dev-box" is not running` | The containers are stopped | `docker compose up -d dev-box` |
> | `... could not be run: No such file: ...` | The file is not where the command says, or has another name | `ls application/models/Bookshelf` must show `create_db.sql` |
> | `... could not be run: SQLSTATE[42000]: ... 1064 You have an error in your SQL syntax` | A typo: a missing comma between columns, or a comma after the last line before `)` | Compare with step 1, or copy the file from the checkpoint |
> | `... could not be run: ... 3780 Referencing column 'author_id' and referenced column 'id' ... are incompatible` | `author_id` and `authors.id` have different types (`INT` and `INT UNSIGNED`) | Use `INT UNSIGNED` for both |
> | `Permission denied` or `EACCES` when you save or create a file (Linux, or WSL) | The container writes the files it creates as root | In the `bookshelf` folder run `sudo chown -R "$USER": .`, then retry |

### Step 3 - Check the data

Ask MySQL for every book with its author's name (a `JOIN` follows the foreign key):
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "SELECT b.id, b.title, a.name FROM books b JOIN authors a ON a.id = b.author_id ORDER BY b.id"
```
Expected: the password warning (harmless), then 5 rows, drawn in a table with borders:

| id | title | name |
|---|---|---|
| 1 | A Wizard of Earthsea | Ursula K. Le Guin |
| 2 | The Left Hand of Darkness | Ursula K. Le Guin |
| 3 | Kindred | Octavia E. Butler |
| 4 | Parable of the Sower | Octavia E. Butler |
| 5 | Invisible Cities | Italo Calvino |

Or look in **phpMyAdmin**: open http://localhost:8077, sign in with user `root` and password `root`, click the
database `caligrafy` in the left column, then `books`. The tab **Structure** shows the columns and their types.
phpMyAdmin is handy to look; change the structure only with `create_db.sql` (new tables) and migrations (changes), so
that they stay the truth about your database. The `caligrafy_*` tables are the framework's (tokens, audit log...):
leave them alone.

### Step 4 - See the foreign key protect your data

Try to delete Ursula K. Le Guin, who has two books:
```bash
docker compose exec mysql mysql -uroot -proot caligrafy -e "DELETE FROM authors WHERE id = 1"
```
Expected (after the password warning):
```text
ERROR 1451 (23000) at line 1: Cannot delete or update a parent row: a foreign key constraint fails (`caligrafy`.`books`, CONSTRAINT `fk_books_author_id` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE)
```
Nothing was deleted (`ON DELETE RESTRICT`).

### Step 5 - Write the models

**File `application/models/Bookshelf/Author.php`** (one property per column of `authors`):

```php
<?php

use Caligrafy\Model;

// One Author = one row of the authors table (Author::query() reads authors: the class name in lower case, plus s)
class Author extends Model {

    // one public property per column of the table
    public $id;
    public $name;
    public $bio;
    public $created_at;
    public $modified_at;
}
```

**File `application/models/Bookshelf/Book.php`** (one property per column of `books`):

```php
<?php

use Caligrafy\Model;

// One Book = one row of the books table. Book::with('author') adds the row of authors whose id is author_id
class Book extends Model {

    // one public property per column of the table
    public $id;
    public $title;
    public $published_year;
    public $summary;
    public $author_id;
    public $created_at;
    public $modified_at;
}
```

- `use Caligrafy\Model;` lets you write `Model` instead of the full name `Caligrafy\Model`.
- The models hold no SQL and no relationship methods: `Book::with('author')` (lesson 04) finds the author through the
  column `author_id` and the table `authors`, by the conventions above.

Check that PHP reads both files without a syntax error:
```bash
docker compose exec dev-box php -l application/models/Bookshelf/Author.php
docker compose exec dev-box php -l application/models/Bookshelf/Book.php
```
Expected:
```text
No syntax errors detected in application/models/Bookshelf/Author.php
No syntax errors detected in application/models/Bookshelf/Book.php
```
Lesson 04 uses the models for real.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `PHP Parse error: syntax error, unexpected ...` | A missing `;`, `{` or `}` | The error names the line; compare with the File block |
> | Later, a value is missing from the answers (lesson 04) | The property name differs from the column name (`year` instead of `published_year`) | One public property per column, exactly the column names |

---

## Check

- [ ] `application/models/Bookshelf/` holds `create_db.sql`, `Author.php` and `Book.php`, identical to the File blocks.
- [ ] The query of step 3 returns the 5 books with their authors, and book 3 is *Kindred*.
- [ ] Deleting author 1 is refused (step 4).
- [ ] `php -l` finds no syntax error in the two models.

## With Claude

The plugin command `/caligrafy-quill:db` runs SQL scripts in the `mysql` container and lists tables; it reads a script
first and asks before running anything that drops or deletes data. In the `bookshelf` folder, start `claude` and ask:
```text
Write the Bookshelf data layer in application/models/Bookshelf/, following the Caligrafy conventions:
1. create_db.sql for MySQL 8.4 with CREATE TABLE IF NOT EXISTS and no DROP: authors (id INT UNSIGNED AUTO_INCREMENT
   primary key, name VARCHAR(150) required, bio TEXT optional) and books (id INT UNSIGNED AUTO_INCREMENT primary key,
   title VARCHAR(200) required, published_year SMALLINT UNSIGNED required, summary TEXT optional, author_id INT
   UNSIGNED required with the index idx_books_author_id and the constraint fk_books_author_id to authors(id), ON
   UPDATE CASCADE ON DELETE RESTRICT); both with created_at and modified_at filled by MySQL, InnoDB, utf8mb4. At the
   end, INSERT IGNORE sample rows with fixed ids: authors 1 Ursula K. Le Guin, 2 Octavia E. Butler, 3 Italo Calvino;
   books 1 A Wizard of Earthsea (1968, author 1), 2 The Left Hand of Darkness (1969, 1), 3 Kindred (1979, 2),
   4 Parable of the Sower (1993, 2), 5 Invisible Cities (1972, 3).
2. Author.php and Book.php: global classes (no namespace) extending Caligrafy\Model, one public property per column,
   no other method.
Then run create_db.sql with /caligrafy-quill:db and list the tables.
```

**Check** before you accept:
- [ ] Primary keys are `id`; the foreign key column is `author_id`; `author_id` and `authors.id` are both `INT UNSIGNED`.
- [ ] The script uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE` with the ids 1 to 5, and no `DROP`.
- [ ] The models have no `namespace` line and exactly one public property per column, and no `find()` method.
- [ ] The query of step 3 returns the 5 books. Claude's comments may differ from the File blocks: compare with the
      checkpoint (`diff -ru`, lesson 00) or copy its files, because the next lessons show outputs for exactly these rows.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 03: Bookshelf tables and models"
git tag lesson-03
```
Reference: [`checkpoints/lesson-03`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-03).
The data itself is not in git: it lives in the `mysql` container. Anyone who clones your project recreates it with the
`sql` command of step 2.

## Quiz

1. Why is the column named `author_id`, and not `writer`?
2. You run `create_db.sql` a second time. What happens?
3. Next month Bookshelf needs a new column on `books`, which already holds data. Where does the change go?
4. A page, the REST API and an AI agent all need the list of books. Which of them read the `books` table directly?

<details><summary>Show the solution</summary>

1. The query builder finds the link by the convention `<singular of the table>_id`: for the table `authors`,
   `author_id`.
2. Nothing changes: `CREATE TABLE IF NOT EXISTS` skips existing tables, and `INSERT IGNORE` skips rows whose `id`
   already exists.
3. In a migration (lesson 05), never in `create_db.sql`: a migration runs once, in order, on every database that does
   not have it yet.
4. None of them. They all call the same capability (lesson 04), and only the capability uses the models and the tables.
</details>

---
Previous: [Lesson 02 - Build once, seen three ways](02-Build-Once-Seen-Three-Ways) · Next: [Lesson 04 - Your first capabilities](04-Your-First-Capabilities)
