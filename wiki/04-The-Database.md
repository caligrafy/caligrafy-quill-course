# Lesson 04 - The database

**In this lesson** you create the two tables of Bookshelf, `authors` and `books`, in the MySQL database
`bookshelf` you created in lesson 01, and you fill them with sample data. You write the SQL yourself in two script
files, run them in the MySQL container, and look at the result in phpMyAdmin.

**Before you start:** lesson 03 is done (tag `lesson-03`), the containers run (`docker compose ps`), and `.env`
contains `DB_ACTIVATE=true`, `DB_HOST=mysql` and `DB_DATABASE=bookshelf` (lesson 01, step 6).

---

## New concepts

**Database, table, row, column.** MySQL stores data in **tables**. A table is like a spreadsheet: each **column**
has a name and a type (`title`, text of at most 200 characters), each **row** is one record (one book).

**Primary key `id`.** Every Caligrafy table has a column `id`: a number, unique in the table, that MySQL chooses
for you (`AUTO_INCREMENT`: 1, 2, 3...). The framework finds rows by it: `find(3)` is the row whose `id` is 3, and
`/books/3` is the URL of that book.

**Foreign key `author_id`.** A book has an author. Instead of copying the author's name into every book, the
`books` table has a column `author_id` that holds the `id` of a row of `authors`. The **constraint**
`FOREIGN KEY (author_id) REFERENCES authors (id)` makes MySQL refuse a book whose author does not exist, and
refuse to delete an author who still has books. Caligrafy's naming rule: the foreign key to the model `Author` is
called `author_id` (the model name in lower case + `_id`). Lesson 05 relies on this name.

**No migrations: SQL scripts.** Some frameworks create tables from PHP code ("migrations"). Caligrafy does not: you
write the SQL in a `.sql` file next to your models, by convention `application/models/<Folder>/create_db.sql`, and
run it yourself. The framework ships one for its users table (`application/models/Auth/create_db.sql`, lesson 08).
Two rules make a script safe to run again: `CREATE TABLE IF NOT EXISTS` (never `DROP TABLE` in it), and, for sample
data, `INSERT IGNORE` (skip rows whose `id` already exists).

**Why the `.sql` files are not visible on the web.** The project's `.htaccess` file refuses (403) any URL that
points into `application/`, and any `.sql` file. You can check it at the end of the lesson.

**The `mysql` container.** MySQL runs in the `mysql` container. `docker compose exec -T mysql mysql -uroot -proot
bookshelf < file.sql` means: in the container `mysql`, run the MySQL client (`mysql`) as user `root` with password
`root` on the database `bookshelf`, and feed it the file. `-T` lets the file be passed from your computer into the
container.

---

## Manual track

### Step 1 - Write the table script

Create the folder `application/models/Bookshelf` and, in it, the file `create_db.sql`.

**File `application/models/Bookshelf/create_db.sql`**:

```sql
/* Bookshelf tables. Safe to run more than once: it never drops anything.
 * Run it with:
 * docker compose exec -T mysql mysql -uroot -proot bookshelf < application/models/Bookshelf/create_db.sql
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
- `INT UNSIGNED`: a whole number, never negative. `author_id` must have **exactly** the type of `authors.id`
  (`INT UNSIGNED`), otherwise MySQL refuses the foreign key.
- `VARCHAR(150)`: text of at most 150 characters; `TEXT`: long text; `SMALLINT UNSIGNED`: 0 to 65535, enough for a year.
- `NOT NULL`: the value is required; `NULL`: it may be empty.
- `created_at` and `modified_at`: MySQL fills them by itself (`DEFAULT CURRENT_TIMESTAMP`, and
  `ON UPDATE CURRENT_TIMESTAMP` refreshes `modified_at` whenever the row changes).
- `authors` is created first because `books` refers to it.
- The constraint has a unique name, `fk_books_author_id` (`fk_<table>_<column>`): MySQL requires constraint names to
  be unique in the whole database.
- `ON DELETE RESTRICT`: deleting an author who still has books is refused.
- `utf8mb4`: stores every character, including accents and emoji.

### Step 2 - Write the sample data script

**File `application/models/Bookshelf/seed.sql`**:

```sql
/* Sample data. INSERT IGNORE skips rows whose id already exists, so running it twice changes nothing.
 * Run it after create_db.sql with:
 * docker compose exec -T mysql mysql -uroot -proot bookshelf < application/models/Bookshelf/seed.sql
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

### Step 3 - Run both scripts

From the `bookshelf` folder:
```bash
docker compose exec -T mysql mysql -uroot -proot bookshelf < application/models/Bookshelf/create_db.sql
docker compose exec -T mysql mysql -uroot -proot bookshelf < application/models/Bookshelf/seed.sql
```
Each command prints only one line:
```
mysql: [Warning] Using a password on the command line interface can be insecure.
```
That warning is normal on your own computer (the password `root` is the one of the development container).
No other output means success.

### Step 4 - Check the data

```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "SELECT id, title, published_year, author_id FROM books"
```
Expected (after the warning line; when the output does not go to a terminal, MySQL prints the same rows separated by
tabs, without the borders):
```
+----+---------------------------+----------------+-----------+
| id | title                     | published_year | author_id |
+----+---------------------------+----------------+-----------+
|  1 | A Wizard of Earthsea      |           1968 |         1 |
|  2 | The Left Hand of Darkness |           1969 |         1 |
|  3 | Kindred                   |           1979 |         2 |
|  4 | Parable of the Sower      |           1993 |         2 |
|  5 | Invisible Cities          |           1972 |         3 |
+----+---------------------------+----------------+-----------+
```

Run the seed script a second time: nothing changes (still 5 books), thanks to `INSERT IGNORE`.

### Step 5 - Look at it in phpMyAdmin

Open http://localhost:8077, sign in with user `root` and password `root`. In the left column click `bookshelf`, then
`books`: you see the 5 rows. Click the tab **Structure** to see the columns and their types. phpMyAdmin is handy
to look at data; the course always changes the structure through the SQL scripts, so that the scripts stay the
truth about your database.

### Step 6 - See the foreign key protect your data

Try to delete Ursula K. Le Guin, who has two books:
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "DELETE FROM authors WHERE id = 1"
```
Expected:
```
ERROR 1451 (23000) at line 1: Cannot delete or update a parent row: a foreign key constraint fails (...)
```
Nothing was deleted. And a book with an author that does not exist is refused too:
```bash
docker compose exec mysql mysql -uroot -proot bookshelf -e "INSERT INTO books (title, published_year, author_id) VALUES ('Ghost', 2000, 99)"
```
Expected: `ERROR 1452 (23000) at line 1: Cannot add or update a child row: a foreign key constraint fails (...)`.
Nothing was added, but MySQL still **used up** the next id (6): the next book you create will get the id 7. MySQL
never reuses an id, which is what you want (an old link `/books/6` can never show another book).

### Step 7 - Check that Caligrafy reaches the database

```bash
docker compose exec dev-box php caligrafer.php doctor --only database
```
Expected (the server version may differ):
```
Caligrafy doctor (read-only): Caligrafy Quill 2.1.2

 Database
  OK    Database: connected to mysql://mysql:3306/bookshelf (server 8.4.13)
  SKIP  Framework tables: caligrafy_tokens, caligrafy_audit not installed (needed for tokens, the audit log and MCP)
        -> php caligrafer.php platform install

 1 OK, 0 WARN, 0 FAIL, 1 SKIP
```
(`doctor` checks; it never changes anything.)

Finally check that the scripts are not downloadable:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/application/models/Bookshelf/create_db.sql
```
Expected: `403` (Forbidden).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `ERROR 1049 (42000): Unknown database 'bookshelf'` | The database was not created in lesson 01 | `docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS bookshelf DEFAULT CHARACTER SET utf8mb4"` then run the scripts again |
> | `ERROR 2002 (HY000): Can't connect to local MySQL server through socket` | MySQL is still starting (the first start takes about 20 seconds) | Wait 20 seconds and run the command again |
> | `service "mysql" is not running` | The containers are stopped | `docker compose up -d dev-box` (it also starts `mysql`) |
> | `ERROR 3780 ... Referencing column 'author_id' and referenced column 'id' ... are incompatible` | `author_id` and `authors.id` have different types (for example `INT` and `INT UNSIGNED`) | Use `INT UNSIGNED` for both, as in step 1 |
> | `ERROR 1826 ... Duplicate foreign key constraint name` | Two constraints have the same name in the database | Name them `fk_<table>_<column>` |
> | `ERROR 1064 ... You have an error in your SQL syntax` | A typo: a missing comma between columns, or a comma after the last line before `)` | Compare with step 1; the error names the line |
> | Your data disappeared after `docker compose down` | The `mysql` container stores its data inside itself; `down` deletes the container | Use `docker compose stop` to stop, never `down`. Re-run both scripts to recreate the data |
> | `doctor` says `FAIL Database` with `getaddrinfo for mysql failed` | You ran `php caligrafer.php doctor` on your computer: the name `mysql` only exists inside Docker | Run it with `docker compose exec dev-box ...` as in step 7 |

---

## With Claude

The plugin command `/caligrafy-quill:db` runs SQL scripts in the `mysql` container. It reads the script first and
asks before running anything that drops or deletes data.

1. Ask Claude to write the scripts:
   ```
   Write application/models/Bookshelf/create_db.sql for MySQL 8.4 with two tables following the Caligrafy
   conventions: authors (name varchar 150 required, bio text optional) and books (title varchar 200 required,
   published_year smallint unsigned required, summary text optional, author_id required foreign key to
   authors.id with ON DELETE RESTRICT). Both with id INT UNSIGNED AUTO_INCREMENT primary key, created_at and
   modified_at timestamps, utf8mb4, CREATE TABLE IF NOT EXISTS and no DROP. Then write
   application/models/Bookshelf/seed.sql with INSERT IGNORE of 3 authors and 5 books with fixed ids.
   ```
2. Run them with the plugin:
   ```
   /caligrafy-quill:db application/models/Bookshelf/create_db.sql
   /caligrafy-quill:db application/models/Bookshelf/seed.sql
   /caligrafy-quill:db tables
   ```

**What Claude should do:** write two files close to steps 1 and 2 (its sample books will be different: that is
fine), then run each script with `docker compose exec -T mysql ...` (or `docker-compose`), and list the tables
`authors` and `books` with their row counts.

**Check** before you accept:
- [ ] Primary keys are `id`; the foreign key column is `author_id` (not `authors_id` or `writer`): lesson 05 needs it.
- [ ] `author_id` has exactly the type of `authors.id` (both `INT UNSIGNED`).
- [ ] No `DROP TABLE` in `create_db.sql`; the constraint name is unique (`fk_books_author_id`).
- [ ] The seed uses fixed ids (1, 2, 3...), so that `/books/1` exists. The course pages show the books of step 2:
      if you want the same screens, use the seed of step 2.
- [ ] Claude did not print the content of `.env`.

**If Claude's result differs:** if Claude asks whether to run a script that contains `DROP TABLE`, say no and ask
it to remove the `DROP` lines. If the `db` skill does not find the `mysql` container, check `docker compose ps`.
If Claude suggests `php caligrafer.php doctor --only db` (the plugin's `db` skill mentions it), use
`--only database`: `db` is not a check name and `doctor` answers
`Unknown check(s): db. Available: project, database, openai, groq, stripe, coinbase, watson, mail`.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 04: authors and books tables"
git tag lesson-04
```
Reference: [`checkpoints/lesson-04`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-04).
The data itself is not in git: it lives in the `mysql` container. Anyone who clones your project recreates it by
running the two scripts.

## Quiz

1. Why is the column named `author_id` and not `writer`?
2. You run `create_db.sql` a second time. What happens to the books?
3. Why must `author_id` be `INT UNSIGNED`?

<details><summary>Show the solution</summary>

1. Caligrafy's relationships (lesson 05) look for the foreign key `<model in lower case>_id`: for the model
   `Author`, that is `author_id`.
2. Nothing: `CREATE TABLE IF NOT EXISTS` skips tables that exist, and the script has no `DROP` or `DELETE`.
3. A foreign key column must have the same type as the column it refers to, `authors.id`, which is `INT UNSIGNED`.
</details>

---
Previous: [Lesson 03 - Controllers](03-Controllers) · Next: [Lesson 05 - Models and relationships](05-Models-and-Relationships)
