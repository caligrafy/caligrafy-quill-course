# Lesson 14 - Put it online

**In this lesson** Bookshelf leaves your computer. You run the **deploy step** that prepares a database, build the
**production image** and run it on your computer exactly as a server does, put the code on GitHub, and deploy it with
**Caligrafy Cloud** to `https://bookshelf-<you>.caligrafy.com` from the command line. Then a migration makes your
account the first administrator online.

> **With Claude:** `/caligrafy-quill:ship bookshelf-sam` commits, creates the GitHub repository, pushes, signs in to
> Caligrafy Cloud, creates the app and deploys it; you only approve in the browser and say yes before credits are
> spent. The steps below are what it does.

**Before you start:** lesson 13 is done (tag `lesson-13`), `docker compose ps` shows the 3 containers running. You
need a [GitHub](https://github.com/) account (free) and an email address you can read.

**Time:** 45 minutes, plus a few minutes for each build.

---

## New concepts

**Online is not your computer.** On your computer, `docker compose` runs containers made for development: the code is
mounted from your folder, so a change shows at once. A server runs a **production image**: a frozen copy of the
project with what it needs and nothing more.

| | Your computer (lessons 01 to 13) | Online |
|---|---|---|
| The code | your folder, mounted in the container | copied into the image when it is built |
| Built from | `docker/Dockerfile` and `docker-compose.yaml` | `Dockerfile` at the root of the project |
| The tables | `php caligrafer.php sql` and `migrate`, by hand | `php caligrafer.php deploy`, at every start |
| `.env` | yours, `APP_ENV=local` | the server's, `APP_ENV=production`, `APP_DEBUG=false`, its own database and keys |
| A change | save a file | commit, push, deploy again |
| Browsers | you reload | a deploy starts from a fresh copy of your repository, so `app.js?v=` and `main.js?v=` get a new number and browsers fetch the new scripts (lesson 09) |

**The production image** (`Dockerfile`): PHP 8.5 with Apache, the libraries of `composer.lock` without the
development ones, and your project. `Dockerfile.dockerignore` keeps out what must never be in it: `.env` and every
`.env.*`, `.git`, `vendor/` (installed fresh), `tests/`, `qa/`, `backups/`, `node_modules/`, `.caligrafy/`. The
settings are not in the image: the server gives the container its `.env` as a file at `/var/www/html/.env`. Sessions
live in `/var/lib/php/sessions`, a folder the host keeps between deploys, so a new version does not sign everyone out.

**The deploy step** (`php caligrafer.php deploy`) runs each time the image starts, before the web server:
1. it waits for the database (`--wait 60` seconds at most: a new server starts its database with the app);
2. it compiles `.env` to `.env.php` (the same as `php caligrafer.php env cache`): pages read it without parsing `.env`,
   and read `.env` again as soon as it differs from what was compiled;
3. it creates the framework tables (tokens, audit log, job runs, jobs, cache, migrations, scripts);
4. it runs every `application/models/<folder>/create_db.sql` that is **new or changed** since its last run, in the
   order of the folder names (`Auth`, `Bookshelf`, `Loans`), and records it in `caligrafy_scripts`;
5. it runs the migrations of `application/migrations` that never ran on this database, in the order of their numbers.

A script that drops, deletes or truncates is never run a second time, even when it changed. Bookshelf's
`create_db.sql` has the sample authors and books (`INSERT IGNORE`), so the online catalog starts with them; people's
accounts start empty.

**Caligrafy Cloud** (https://deploy.caligrafy.com) is a hosting service for Caligrafy projects: it builds the
production image from your GitHub repository, gives it a MySQL database and a `.env`, runs its scheduled jobs, and
serves it at `https://<name>.caligrafy.com` with HTTPS. Every new app is free for its first 30 minutes online, from its
first deploy; `cloud extend` keeps it online a week, a month or a year (2, 5 or 50 credits; 1 credit = 1 USD). The
command line is `php caligrafer.php cloud`. It signs in without a password or a token to copy: `cloud login` shows a
code, you approve it in the browser, and the command keeps the token it receives in `.caligrafy/cloud.json`
(git-ignored, never in the image).

**A deploy key** is an SSH key that GitHub accepts for **one** repository, read-only. For a private repository,
`cloud create` gives you its public half: you add it to the repository, and Caligrafy Cloud can read that repository
and nothing else of your account.

---

## Steps

The app is named `bookshelf-sam` and the GitHub account `sam`: put your own name everywhere. An app name is 3 to 30
lowercase letters, digits or single dashes, starting with a letter, and must be free on Caligrafy Cloud.

### Step 1 - The deploy step, on your computer

```bash
docker compose exec dev-box php caligrafer.php version
docker compose exec dev-box php caligrafer.php deploy
```
Expected: `Caligrafy Quill 2.21.8`, then (the first time):
```text
 Caligrafy deploy
 caligrafy-schedule {"version":1,"jobs":[]}
  OK    Settings: .env compiled to .env.php (read without parsing on every request)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations, scripts
  OK    application/models/Auth/create_db.sql: run
  OK    application/models/Bookshelf/create_db.sql: run
  OK    application/models/Loans/create_db.sql: run
  OK    Migrations: up to date
```
Your database already had everything: the scripts change nothing (`IF NOT EXISTS`, `INSERT IGNORE`), and the deploy
step now remembers them. Run it again: each script says `up to date`. The `caligrafy-schedule` line is for the host
(lesson 15). `.env.php` is git-ignored, like `.env`.

### Step 2 - The production image, on your computer

Build the image the way a host does (the first build takes a few minutes):
```bash
docker build -t bookshelf-prod .
```
Expected, near the end: `naming to docker.io/library/bookshelf-prod done`. Run it against a database of its own, the one of
your dev server, with a production `.env`. Write `.env.prod` next to `.env` (git-ignored by `/.env.*`): a copy of
`.env` with these lines changed:
```text
APP_ENV=production
APP_DEBUG=false
DB_DATABASE=bookshelf_online
```
Create the empty database, then start the image on port 8090 (any free port), on the network of the dev server
(`bookshelf_default`: the folder name, then `_default`) so that `DB_HOST=mysql` still works:
```bash
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE bookshelf_online"
docker run -d --name bookshelf-prod -p 8090:80 --network bookshelf_default -v "$PWD/.env.prod:/var/www/html/.env" bookshelf-prod
docker logs bookshelf-prod
```
Expected, at the top of the logs:
```text
 Caligrafy deploy
 caligrafy-schedule {"version":1,"jobs":[]}
  OK    Settings: .env compiled to .env.php (read without parsing on every request)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations, scripts
  OK    application/models/Auth/create_db.sql: run
  OK    application/models/Bookshelf/create_db.sql: run
  OK    application/models/Loans/create_db.sql: run
  OK    Migrations: 1 run: 20261105000100_add_books_isbn.sql
```
then the start of Apache. On an empty database the deploy step created every table, the sample catalog, and ran the
migration of lesson 05. Check it with the smoke test of lesson 12:
```bash
bash qa/bookshelf/smoke.sh http://localhost:8090
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8090/tests/BookRulesTest.php
```
Expected: `All checks passed.`, then `404`: the tests are not in the image. The pages, the finder (`/apps/finder`) and
the built SPA (`/public/bookshelf-spa/dist/index.html`) all answer: `dist/` is committed (lesson 10). This is what a
server runs; on your own server, run this image with its `.env` the same way. Stop it when you are done:
`docker rm -f bookshelf-prod`.

### Step 3 - Put the code on GitHub

Caligrafy Cloud builds from GitHub. Commit, and check that no secret goes with it:
```bash
git add -A
git status --short | grep -E "\.env|\.caligrafy" || echo "no .env and no token: good"
git commit -m "Lesson 14: ready to go online"
```
Expected: `no .env and no token: good` (if `git status` is empty, there is nothing to commit, which is fine). On GitHub,
create a repository: **New repository**, name `bookshelf`, **Private**, no README, license or `.gitignore` (your
project has them). Then:
```bash
git remote add origin https://github.com/sam/bookshelf.git
git push -u origin main
git push origin --tags
```
The first push asks you to sign in to GitHub. Refresh the repository page: your files are there, `Dockerfile` at the
root, no `.env`.

### Step 4 - Sign in to Caligrafy Cloud

Create your account at https://deploy.caligrafy.com/signup and confirm your email. Then, from the `bookshelf` folder:
```bash
docker compose exec dev-box php caligrafer.php cloud login
```
It prints a code and an address:
```text
 To sign in, approve this code in your browser: <code>
 https://deploy.caligrafy.com/cli?code=<code>
 (sign up or sign in there first if needed). Waiting for the approval...
```
Open the address (the container cannot open your browser), check that the code is the same, approve. The command ends
with ` Signed in as <your email> (0 credits). The token is in .caligrafy/cloud.json (git-ignored).`
`cloud account` prints your email and credits.

### Step 5 - Create the app and deploy it

```bash
docker compose exec dev-box php caligrafer.php cloud create bookshelf-sam
```
It reads the repository from `git remote get-url origin` (or give `--repo https://github.com/sam/bookshelf`, and
`--branch` when it is not `main`). It answers ` Created https://bookshelf-sam.caligrafy.com from
https://github.com/sam/bookshelf (main).`, and, because the repository is private, the deploy key (a line that starts
with `ssh-ed25519`), saved in `.caligrafy/bookshelf-sam-deploy-key.pub`. Add it on GitHub: the repository >
**Settings** > **Deploy keys** > **Add deploy key**, title `Caligrafy Cloud`, the line in **Key**, **Allow write access**
off. With the [GitHub CLI](https://cli.github.com/) it is one command:
```bash
gh repo deploy-key add .caligrafy/bookshelf-sam-deploy-key.pub -R sam/bookshelf --title "Caligrafy Cloud"
```
Then deploy:
```bash
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf-sam
```
It prints ` Deploying https://bookshelf-sam.caligrafy.com (a few minutes)...`, checks every 5 seconds, and ends with
` Online: https://bookshelf-sam.caligrafy.com (30 minutes left, free trial)`. If the build fails, it prints the end of
the build log instead. `cloud status bookshelf-sam` and `cloud logs bookshelf-sam` show the state and the logs at any
time; the logs start with the deploy step of step 2.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `remote origin already exists` | A remote was added before | `git remote set-url origin https://github.com/sam/bookshelf.git` |
> | `Usage: php caligrafer.php cloud create <name> [--repo ...]` | No GitHub `origin` remote | Add it (step 3), or give `--repo` |
> | The name is refused as taken or reserved | Someone has it, or it is kept for the service | Choose another one, such as `bookshelf-sam-2` |
> | `The deployment failed` and `Permission denied (publickey)` in the log | The deploy key is not on the repository | Add it, then `cloud deploy` again |
> | `The deployment failed` with a composer or PHP error | The build itself failed | Build the image on your computer (step 2): the same error shows there |
> | `The sign-in was expired` | The code was not approved in time | Run `cloud login` again |

### Step 6 - Bookshelf on the internet

Open https://bookshelf-sam.caligrafy.com/books: the catalog with its sample books, over HTTPS. Run the smoke test
against it:
```bash
bash qa/bookshelf/smoke.sh https://bookshelf-sam.caligrafy.com
```
Expected: `All checks passed.`, `.env is never served` included. On a phone, open the address: the install offer of
lesson 10 works now (HTTPS). The online database is new: no account, no token, no loan. Create a token online for an
agent with the dashboard of the app, never by copying one from your computer.

### Step 7 - The first administrator online

Join on https://bookshelf-sam.caligrafy.com/join with the address `reader1@example.com` (in your own app, your own
address, in the migration too). Online there is no `docker compose exec mysql`:
data that already exists is changed by a **migration**, which the deploy step runs once. Write it:
```bash
docker compose exec dev-box php caligrafer.php migrate make make_reader1_admin
```
Expected: ` application/migrations/20261108000056_make_reader1_admin.sql written: add its SQL, then php caligrafer.php migrate`
(your number is the date and time). Add the `UPDATE` under its header:

**File `application/migrations/20261108000056_make_reader1_admin.sql`** (use the name of your file):

```sql
-- make_reader1_admin
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.


-- The first administrator of Bookshelf online. The account must exist first (create it on the site), then commit,
-- push and deploy. It only gives a level: it never creates an account and never holds a password.
UPDATE users SET permissions = 1 WHERE username = 'reader1@example.com';
```

Run it on your computer first (reader1 is already an administrator there: nothing changes), then deploy:
```bash
docker compose exec dev-box php caligrafer.php migrate
git add -A
git commit -m "The first administrator online"
git push
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf-sam
```
Expected locally: ` OK   20261108000056_make_reader1_admin.sql` and ` 1 migration(s) run on caligrafy`. Online, the logs
(`cloud logs bookshelf-sam`) show `OK    Migrations: 1 run: 20261108000056_make_reader1_admin.sql`. Sign in online as
reader1: **Delete this book** on every book, and `/people` lists you as `Administrator`. The next administrators need
no migration (lesson 11).

### Step 8 - Time and settings

- `cloud apps` lists your apps with their state and time left; `cloud extend bookshelf-sam week` keeps it online
  (2 credits; `month` 5, `year` 50); `cloud credits 5` gives a Stripe address to buy credits. When the time is over the
  app is paused.
- A setting of your own (for example `OPEN_AI_KEY`) never goes in git:
  `cloud secret set bookshelf-sam OPEN_AI_KEY=sk-...` stores it, and the next `cloud deploy` applies it.
- `cloud delete bookshelf-sam --yes` deletes the app, its database and its files, for good.

---

## Check

- [ ] `deploy` on your computer says `up to date` for the three scripts the second time.
- [ ] The production image runs against an empty database: the logs show every script `run` and
      `Migrations: 1 run`, the smoke test passes on port 8090, and `/tests/...` answers 404.
- [ ] GitHub has your code and no `.env`; `.caligrafy/` is not committed.
- [ ] Online: the smoke test passes; reader1 is an administrator after the migration.
- [ ] The migration file is identical to the File block (with your number).

## With Claude

```text
/caligrafy-quill:ship bookshelf-sam
```
The `ship` skill commits, creates the GitHub repository and pushes (SSH first, then HTTPS, then a browser approval),
signs in to Caligrafy Cloud with a browser approval, creates the app, adds its deploy key, deploys and checks it. You
approve in the browser and say yes before credits are spent. For your own server instead,
`/caligrafy-quill:deploy` checks that the project is ready (the image, the deploy step, the settings).

**Check** before you accept:
- [ ] `git log origin/main -1` is your last commit, and GitHub shows no `.env` and no `.caligrafy/`.
- [ ] `cloud status bookshelf-sam` says `running`, and the smoke test passes against the address.
- [ ] Claude printed no token and no line of `.env`.

For the first administrator, ask:
```text
Write a migration that makes reader1@example.com an administrator (UPDATE users SET permissions = 1 WHERE username =
'reader1@example.com'),
run it locally, commit, push and redeploy bookshelf-sam.
```
and check that it is a new file of `application/migrations/`, not an edit of a `create_db.sql`.

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 14: online"
git tag lesson-14
git push origin main --tags
```
Reference: [`checkpoints/lesson-14`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-14).
The checkpoint has your migration under the name of the course's run: keep yours.
Learn more: [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online) and
[Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

## Quiz

1. Which files are never in the production image, and where do its settings come from?
2. You add a column to `loans` for the online app. Do you edit `application/models/Loans/create_db.sql`?
3. Why does the online catalog have the sample books but no accounts?
4. What does `env cache` do, and what happens when you then change `.env`?

<details><summary>Answers</summary>

1. `.env` and every `.env.*`, `.git`, `vendor/`, `tests/`, `qa/`, `backups/`, `node_modules/`, `.caligrafy/`
   (`Dockerfile.dockerignore`). The host gives the container its `.env` as a file.
2. No: it ran already, online too. Write a migration (`migrate make`); the deploy step runs it once.
3. `Bookshelf/create_db.sql` inserts the sample authors and books (`INSERT IGNORE`), and the deploy step runs it on the
   new database. Accounts are created by people, on the site.
4. It compiles `.env` to `.env.php`, which pages read without parsing. When `.env` changes, it no longer matches what
   was compiled, and pages read `.env` again until the next `env cache` or deploy.
</details>

---
Previous: [Lesson 13 - Capstone: one more feature, everywhere](13-Capstone-Loans) · Next: [Lesson 15 - Reminders](15-Reminders)
