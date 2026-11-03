# Lesson 14 - Put it online

**In this lesson** Bookshelf leaves your computer: you check that the project has the production image and the deploy
step (Caligrafy Quill 2.4.0 and later), put the code on GitHub, and deploy it with **Caligrafy Cloud** to
`https://bookshelf-<you>.caligrafy.com`: the catalog, the REST API, sign-in and the phone app, on the internet. You
use the dashboard first, then the same service from the command line.

> **With Claude:** `/caligrafy-quill:ship bookshelf-sam` does all of this; you only approve in the browser. The Manual
> track shows each step it takes.

**Before you start:** lesson 13 is done (tag `lesson-13`), the containers run (`docker compose ps`). You need a
[GitHub](https://github.com/) account (free) and an email address you can read.

**Time:** 45 minutes, plus a few minutes of waiting for each build.

---

## New concepts

**Online is not your computer.** On your computer, `docker compose` runs three containers made for development: the
PHP code is mounted from your folder, so a change shows at once, and phpMyAdmin is there to look at the data. A server
runs something else: a **production image**, a frozen copy of your project with everything it needs and nothing more.

| | Your computer (lessons 01 to 13) | Online (this lesson) |
|---|---|---|
| The code | your folder, mounted in the container | copied into the image when it is built |
| Built from | `docker/Dockerfile` + `docker-compose.yaml` | `Dockerfile` at the root of the project |
| The database | the `mysql` container, filled with `php caligrafer.php sql` | a MySQL database of the host, filled by `php caligrafer.php deploy` |
| `.env` | yours, with `APP_ENV=local` | written by the host: `APP_ENV=production`, `APP_DEBUG=false`, its own database and keys |
| You change it | save a file | commit, push, deploy again |

**The production image** (`Dockerfile`, new in 2.4.0). PHP 8.5 with Apache and the extensions Caligrafy needs, the PHP
libraries of `composer.json` without the development ones (`composer install --no-dev`), and your project.
`Dockerfile.dockerignore` keeps out what must never be in it: `.env`, `.git`, `vendor/` (installed fresh), the tests
(`qa/`), backups, `node_modules`. People's sessions are kept in `/var/lib/php/sessions`, a folder the host keeps
between deploys, so a new version does not sign everybody out.

**The deploy step** (`php caligrafer.php deploy`, new in 2.4.0) runs each time the image starts, before the web
server:
1. it waits for the database (a new server starts its database at the same time as the app);
2. it creates the framework tables (tokens, audit log);
3. it runs every `application/models/<folder>/create_db.sql` that is **new or changed** since its last run, in the
   order of the folder names, and records each one in a table named `caligrafy_scripts`.

A script that drops, deletes or truncates is never run a second time, even if it changed: that would destroy data. And
`seed.sql` is never run: sample data is not production data. So the database online starts with your tables and no
rows. This lesson shows how data you need online gets in: a script of its own.

**Caligrafy Cloud** (https://deploy.caligrafy.com) is a hosting service made for Caligrafy projects. It builds the
production image from your GitHub repository, gives it a MySQL database and a `.env`, and serves it at
`https://<name>.caligrafy.com` with HTTPS.

| | |
|---|---|
| Account | sign up with an email, confirm it, sign in |
| Free | the first 30 minutes online of **each new app**, from its first deploy |
| Credits | 1 credit = 1 USD, bought in packs of 5, 10, 25 or 50 |
| Keep an app online | a week: 2 credits, a month: 5, a year: 50 (the time is added to what is left) |
| When the time is over | the app is **paused**: its address shows a "paused" page. Its database and files are kept 7 days, then deleted |
| The code | your GitHub repository: public, or private with a read-only **deploy key** |
| Settings | the database and the keys are set for you. Your own settings (for example `OPEN_AI_KEY`) are stored encrypted and applied at the next deploy |

**A deploy key** is an SSH key that GitHub accepts for **one** repository, read-only. Caligrafy Cloud creates one for
each app with a private repository and gives you the public half (on the app page, and in the file
`.caligrafy/<name>-deploy-key.pub` when you create the app from the command line): you add it to the repository on
GitHub, and Caligrafy Cloud can read that repository and nothing else of your account.

**Build once, again.** Caligrafy Cloud is itself a Caligrafy application: the dashboard, the `php caligrafer.php cloud`
commands and AI agents all call the same capabilities (`create_app`, `deploy_app`, `get_app`...) with the same rules.
A **personal access token** lets the command line and agents act for you, as tokens did for Bookshelf in lesson 11.
You do not create or copy it: `cloud login` shows a code, you approve it in the browser, and the command saves the
token. The dashboard lists your tokens and revokes them.

---

## Manual track

In this lesson the app is named `bookshelf-sam` and the GitHub account is `sam`: replace `sam` with your own name
everywhere. An app name is 3 to 30 lowercase letters, digits or single dashes, starting with a letter, and it must be
free on Caligrafy Cloud.

### Step 1 - Check that the project is on Caligrafy Quill 2.4.0 or later

The production image and the deploy step come with Caligrafy Quill 2.4.0; the release of lesson 01, 2.16.2, has them. Check it:
```bash
docker compose exec dev-box php caligrafer.php version
```
Expected: `Caligrafy Quill 2.16.2`. Then `ls Dockerfile` shows the file, and you can go to step 2.

**Only if it says an older version** (you started the course before 2.16.2 was released): 2.4.0 has the image and the
deploy step, and 2.7.0 adds the browser sign-in of step 7 and the `ship` skill. (2.11.0 also brings Caligrafy's look
of lesson 06, and 2.12.0 the side by side of lessons 09 and 10: the pages you wrote on `layoutblocks.pug` keep
working as they are.) Update the project to the course's
release in one command. Commit your work first (`git add -A && git commit -m "Before the update"`): the update stops
when git has uncommitted changes, so that you can review it and undo it.

Since 2.9.0 the project has the command. An older project gets it once:
```bash
curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php
```
Then, with 2.9.0 or later:
```bash
docker compose exec dev-box php caligrafer.php update 2.16.2
```
or, before 2.9.0:
```bash
docker compose exec dev-box php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run('2.16.2'));"
```
- `framework/` and `claude-plugin/` belong to the framework: the new ones replace them. `claude-plugin/` brings the
  `/caligrafy-quill:deploy` and `/caligrafy-quill:ship` skills.
- The framework files you never changed (`caligrafer.php`, `public/css/tokens.css`...) are replaced, and the new ones
  (`Dockerfile`, `Dockerfile.dockerignore`) are added. `.gitignore` gets its new line, `/.caligrafy/` (step 7), and
  `.env` the new settings with their defaults.
- A file you changed is kept, with the new version next to it as `<file>.caligrafy-new`: merge what you need, then
  delete it. Everything replaced is copied first to `backups/`.
- It ends with `upgrade --apply` for `application/` (lesson 12, step 5). `web.php` is yours: never replaced.

With Claude Code, `/caligrafy-quill:update 2.16.2` does the same and merges the `.caligrafy-new` files with you.

Check the version and the new command:
```bash
docker compose exec dev-box php caligrafer.php doctor
docker compose exec dev-box php caligrafer.php help
```
Expected: the first line of `doctor` is `Caligrafy doctor (read-only): Caligrafy Quill 2.16.2`, and `help` lists
`deploy` and `cloud`. Run the unit tests and the smoke test of lesson 12: they still pass. Commit the update.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `git has uncommitted changes` | The update keeps your work reviewable | Commit first, then run it again |
> | `Could not open input file: framework/core/Updater.php` | The project is older than 2.9.0 and the `curl` line was not run | Run the `curl` line from the `bookshelf` folder |
> | `doctor` still says the old version | The update stopped before the end | Read its last lines, fix what they say, run it again |

### Step 2 - Data for the online database

Online, `deploy` creates your tables but runs no `seed.sql`, and Caligrafy Cloud has no database console. Without
authors, nobody can add a book (`create_book` needs an `author_id`). Give the online database the three authors and five
books with a script that `deploy` runs: a `create_db.sql` in a folder of its own.

**File `application/models/Online/create_db.sql`** (the sample catalog, for the online database):

```sql
/* The data Bookshelf starts with online. php caligrafer.php deploy runs it once when the app starts (Caligrafy Quill
 * 2.4.0+), after Auth, Bookshelf and Loans: the folders run in alphabetical order. INSERT IGNORE skips rows whose id
 * already exists, so running it again changes nothing. Never put a password or a token in a script.
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

- The folder name decides the order: `Auth`, `Bookshelf`, `Loans`, `Online`. The tables exist before the rows go in.
- It is the content of `seed.sql`: the same rows. On your computer the rows are already there, so nothing changes if you
  run it: `docker compose exec dev-box php caligrafer.php sql application/models/Online/create_db.sql` answers without
  an error.
- Only `INSERT IGNORE`: a script that deletes (`DELETE FROM`, `DROP`, `TRUNCATE`) runs once and never again.

Check what `deploy` would do, on your computer:
```bash
docker compose exec dev-box php caligrafer.php deploy
```
Expected: `Caligrafy deploy`, then `OK` lines for the framework tables and each script: `run` the first time,
`up to date` when you run it again.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `FAIL  Database: not reachable` | `deploy` was run on your computer instead of the container | Use `docker compose exec dev-box ...` |
> | `Table '...authors' doesn't exist` | The folder name sorts before `Bookshelf` | Name it `Online` |

### Step 3 - Put the code on GitHub

First, the card a shared link shows (on Facebook, LinkedIn, X, Slack, iMessage). Every page prints it from
`metadata()`; add it at the end of **`application/config/config.php`**:
```php
metadata(array(
    'site_name' => 'Bookshelf',
    'title' => 'Bookshelf: the books of the club',
    'description' => 'The catalog of the club, its loans and who has which book.',
));
```
Its address is the app's own (`APP_URL`, which Caligrafy Cloud sets). An `image` of 1200x630 makes the card large:
see [Metadata and social cards](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards).

Caligrafy Cloud builds from GitHub. Commit first:
```bash
git add -A
git status --short | grep -E "\.env$|\.caligrafy" || echo "no .env and no token: good"
git commit -m "Lesson 14: the online data"
```
On GitHub, create a new repository: **New repository**, name `bookshelf`, **Private**, and do not add a README,
a license or a `.gitignore` (your project has them). GitHub then shows the commands for "an existing repository".
They are:
```bash
git remote add origin https://github.com/sam/bookshelf.git
git push -u origin main
git push origin --tags
```
The first push asks you to sign in to GitHub (a browser window, or your GitHub credentials). Refresh the page of the
repository: your files are there, with `Dockerfile` at the root, and no `.env`.

The phone app of lesson 10 is served from `public/bookshelf-spa/dist/`, and `dist/` is not committed (the SPA's
`.gitignore`): online, the pages, REST, agents and the finder work; the built phone app does not, until you do the
exercise of this lesson.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `remote origin already exists` | A remote was added before | `git remote set-url origin https://github.com/sam/bookshelf.git` |
> | `Authentication failed` / `Permission denied` | GitHub no longer accepts your account password for git | Sign in through the browser window, or create a token on GitHub (Settings, Developer settings) and use it as the password |
> | `.env` in the list of the `grep` | `.gitignore` was changed | Copy `.gitignore` again from the release (step 1), then `git rm --cached .env` |

### Step 4 - Your Caligrafy Cloud account

1. Open https://deploy.caligrafy.com/signup. Enter your email (it is your username), a password of 10 to 72
   characters twice, accept the terms of use, then **Create account**. The form tells you what to fix as you type.
2. Open the email "Confirm your email for Caligrafy" (look in the spam folder too) and click **Confirm my email**.
3. Sign in at https://deploy.caligrafy.com/login with your email and password. Before the link is opened, sign-in says
   **Confirm your email first** and offers **Send the confirmation link again**.

The same account signs in to caligrafy.com. Forgot your password? **Forgot your password?** on the sign-in page emails
you a 6-digit code (10 minutes), then you choose a new password.

The dashboard shows your email and `0 credits`. You need no credits yet: every new app is free for its first 30
minutes online.

### Step 5 - Create the app and deploy it

On the dashboard, in **New app**:
- **Name**: `bookshelf-sam`
- **GitHub repository**: `https://github.com/sam/bookshelf`
- **Branch**: `main`

Click **Create the app**. The app page opens: status `created`, and because the repository is private, a **deploy
key** (a line that starts with `ssh-ed25519`).

Add it on GitHub: the repository `bookshelf` > **Settings** > **Deploy keys** > **Add deploy key**. Title
`Caligrafy Cloud`, paste the line in **Key**, leave **Allow write access** off, **Add key**. GitHub may ask for your
password once.

From the command line (step 7), `cloud create` saves the same key in `.caligrafy/<name>-deploy-key.pub`, and
`php caligrafer.php cloud key <name>` prints it again. With the [GitHub CLI](https://cli.github.com/), one command adds
it, read-only:
```bash
gh repo deploy-key add .caligrafy/bookshelf-sam-deploy-key.pub -R sam/bookshelf --title "Caligrafy Cloud"
```

Back on the app page, click **Deploy**. The status becomes `deploying`, and a line shows the build moving: getting the
code, installing PHP, `composer install`, starting. A first build takes about 3 to 5 minutes. Then the status is
`running` with the time left of its free 30 minutes, and the deploy step has created your tables and the sample catalog.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `bookshelf-sam.caligrafy.com is taken: choose another name` | Someone has that name | Choose another one, for example `bookshelf-sam-2` |
> | `This name is reserved` | Names such as `www`, `api`, `admin` are kept for the service | Choose another one |
> | **Confirm your email first** when you sign in | The confirmation link was not opened | Open it, or **Send the confirmation link again** under the message |
> | **Too many tries** when you sign in | 10 wrong passwords in 15 minutes | Wait 15 minutes, or use **Forgot your password?** |
> | `No Dockerfile at the root of ...` (public repository), or a build that fails because the Dockerfile is missing (private repository) | The update of step 1 was not committed or pushed | Commit, `git push`, then **Deploy** again |
> | The status becomes `failed`, the build log ends with `Permission denied (publickey)` | The deploy key is not on the repository | Add it (above), then **Deploy** again |
> | `failed` with a PHP or composer error | The build itself failed | **Show the logs**: the end of the build log says which file or package |

### Step 6 - Bookshelf on the internet

Open https://bookshelf-sam.caligrafy.com/books: the catalog with its five books, over HTTPS. Then:
```bash
curl -s https://bookshelf-sam.caligrafy.com/api/books | head -c 300
bash qa/bookshelf/smoke.sh https://bookshelf-sam.caligrafy.com
```
The first command prints the start of `{"data":[...` with the five books: the REST API of lesson 07, online. The smoke
test of lesson 12 runs against the server: the same 8 checks pass, including `.env is never served`. And the card of
step 3:
```bash
curl -s https://bookshelf-sam.caligrafy.com/books | grep -o 'og:[a-z_]*" content="[^"]*'
```
Expected: `og:type" content="website`, `og:site_name" content="Bookshelf`, `og:title" content="Bookshelf: the books of
the club`, its description, and `og:url" content="https://bookshelf-sam.caligrafy.com/books`.

Now people. Click **Sign in**, then the link to register, and create the account `reader1` with a password of your
choice: it is a new database, so the accounts of your computer do not exist online. Signed in, add a book: it works.
**Delete** is not there: online, `reader1` has permissions 0. The next step makes it an administrator.

The settings online are the ones of lesson 12's checklist, set by Caligrafy Cloud: `APP_ENV=production`,
`APP_DEBUG=false`, `LEGACY_API_KEY=false`, HTTPS, a database and keys of its own.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | A "paused, being deployed, or does not exist" page | The app is still starting, its time is over, or the name is misspelled | Check the status on the app page |
> | The catalog is empty | The `Online` script did not run | **Show the logs**: the lines after `Caligrafy deploy` list each script. Check that `application/models/Online/create_db.sql` was pushed |
> | `FAIL  Database: not reachable after 60 s` in the logs | The database was still starting | **Deploy** again |
> | A `FAIL` line in the smoke test | One experience answers differently online | The same application as on your computer: compare with lesson 12, step 3, and read `cloud logs` (step 7) |
> | `[ERROR 1004] Access Denied: the legacy API key is turned off (LEGACY_API_KEY=false...)` from a page of yours | The page sends `'Authorization': 'Bearer ' + apiKey`: that key is public, so it is off online | Send `headers: apiHeaders()` (the session and its CSRF token, from `public/js/services/app.js`), or use the JS client of `public/js/capabilities.js`. Programs and agents use a personal access token |

### Step 7 - The command line

Everything the dashboard does, `php caligrafer.php cloud` does from your project, with a token. You sign in once, in
the browser: there is no token to create or copy.

1. In your terminal:
```bash
docker compose exec dev-box php caligrafer.php cloud login
```
2. It prints a code such as `BCDF-GH23` and the address `https://deploy.caligrafy.com/cli?code=BCDF-GH23`, then waits.
   From the Docker server it cannot open your browser: open the address yourself. (Run outside Docker,
   `php caligrafer.php cloud login` opens it for you.)
3. Sign in with your Caligrafy Cloud account, check that the page shows the same code, and click **Approve**.

Back in the terminal, the command saves the token in `.caligrafy/cloud.json` (git-ignored) and prints
`Signed in as <your email> ...`.

The dashboard lists your tokens in **Command line and AI agents**: revoke one there when a computer is no longer
yours. (`cloud login --token cq_...` and `cloud login --paste` still take a token you already have.)

`.caligrafy/` is in the new `.gitignore` (step 1): the token never reaches GitHub. Check it:
```bash
git status --short
```
`.caligrafy` is not in the list. Now the commands:
```bash
docker compose exec dev-box php caligrafer.php cloud account
docker compose exec dev-box php caligrafer.php cloud apps
docker compose exec dev-box php caligrafer.php cloud status bookshelf-sam
docker compose exec dev-box php caligrafer.php cloud logs bookshelf-sam
```
Expected: your email and credits; one line `bookshelf-sam  running  https://bookshelf-sam.caligrafy.com (... minutes left, free trial)`;
the same line for `status`; and the last lines the running app printed (the requests of step 6).

All the commands (`docker compose exec dev-box php caligrafer.php cloud help` lists them):

| Command | What it does |
|---|---|
| `cloud create <name> --repo https://github.com/<you>/<repo>` (`--branch <b>`) | creates an app (the dashboard's **Create the app**); for a private repository, saves its deploy key in `.caligrafy/<name>-deploy-key.pub` |
| `cloud key <name>` | prints the deploy key again |
| `cloud deploy <name>` (`--no-wait`) | builds and starts it, and follows the build until it is online |
| `cloud apps` · `status <name>` · `logs <name>` · `account` | read |
| `cloud secret set <name> KEY=value` · `secret list <name>` · `secret delete <name> KEY` | your own settings, applied at the next deploy |
| `cloud extend <name> week\|month\|year` | 2, 5 or 50 credits |
| `cloud credits 5\|10\|25\|50` | prints a Stripe payment page for credits |
| `cloud delete <name> --yes` | the app, its database and its files, for good |
| `cloud logout` | removes `.caligrafy/cloud.json` |

In the container, `create` needs `--repo`: the container has no git to read the address of `origin`.

`cloud` exits with 1 when Caligrafy Cloud refuses something. In a script that checks the result, run it whole: a
`| tail` after it hides that exit code.

### Step 8 - Make reader1 an administrator online

On your computer you did it in MySQL (lesson 11, step 8). Online there is no console: a script does it, the same way as
the catalog.

**File `application/models/Team/create_db.sql`** (the administrators of the online Bookshelf):

```sql
/* The administrators of Bookshelf online: php caligrafer.php deploy runs it when it is new or changed. The account
 * must exist first (register it on the site), then add its username here, commit, push and deploy.
 * It only gives permissions: it never creates an account and never contains a password.
 */

UPDATE users SET permissions = 1 WHERE username = 'reader1';
```

Commit, push and deploy from the command line:
```bash
git add -A
git commit -m "reader1 is an administrator online"
git push
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf-sam
```
Expected: `Deploying https://bookshelf-sam.caligrafy.com (a few minutes)...`, then after the build
`Online: https://bookshelf-sam.caligrafy.com (... minutes left, free trial)`. The logs show the deploy step:
```bash
docker compose exec dev-box php caligrafer.php cloud logs bookshelf-sam
```
Among the first lines: `application/models/Online/create_db.sql: up to date` and
`application/models/Team/create_db.sql: run`. The scripts that did not change are not run again; the new one is.
Sign out and in again as `reader1`: **Delete** is there.

This script is for the **first** administrator. After it, an administrator manages the others without a deploy:
the user capabilities (`list_users`, `update_user`, lesson 11) work online too. Uncomment the
`// User Administration Routes` of `web.php`, deploy, and an administrator changes permissions on `/users`. Adding
usernames to the script
(`WHERE username IN ('reader1', 'sam')`) also works: it changed, so the next deploy runs it again.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Sign in first: php caligrafer.php cloud login` | No token saved | Step 7 |
> | `Sign in, or use a token of your account` | The token was revoked on the dashboard, or deleted | `cloud login` again and approve the code in the browser |
> | `cloud login` ends without `Signed in` | The code was denied, or not approved in time | Run `cloud login` again and approve the new code |
> | The deploy shows the old code | The commit was not pushed | `git push`, then `cloud deploy` again |
> | Still no **Delete** | You did not sign in again, or `reader1` was registered after the deploy | Sign out and in. If the account is newer than the deploy, change the script (add a comment line) and deploy again |
> | `Caligrafy Cloud could not be reached` | No internet in the container, or a company proxy | Run the same command outside Docker: `php caligrafer.php cloud ...` needs only PHP |

### Step 9 - Time, settings and the end of the trial

Every new app is free for its first 30 minutes online, counted from its first deploy. When they are over, the app is
paused: its address shows the "paused" page, you get an email, and its database and files are kept 7 days, then deleted.

To keep it online (this costs money: 2 USD for a week):
```bash
docker compose exec dev-box php caligrafer.php cloud credits 5
```
It prints a Stripe payment page: pay there, and the credits appear on the dashboard a few seconds later. A deploy
refused for lack of time says how many credits you have. Then:
```bash
docker compose exec dev-box php caligrafer.php cloud extend bookshelf-sam week
```
Expected: `https://bookshelf-sam.caligrafy.com is online until <date and time>. Credits left: 3.` If the app was
paused, it adds `It is starting again.` and it is back with its data a few minutes later. The same buttons are on the
app page: **One week: 2 credits**...

Your own settings go in the same way, for example the OpenAI key of an AI feature (never in git):
```bash
docker compose exec dev-box php caligrafer.php cloud secret set bookshelf-sam OPEN_AI_KEY=sk-...
docker compose exec dev-box php caligrafer.php cloud secret list bookshelf-sam
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf-sam
```
`secret list` shows the names, never the values. The database settings and the keys are set by Caligrafy Cloud: you
cannot change them, and you do not need to.

When you no longer need the app:
```bash
docker compose exec dev-box php caligrafer.php cloud delete bookshelf-sam --yes
```
It deletes the app, its database and its files at once, and frees the name. Without `--yes` it only tells you what
would happen.

> **Elsewhere.** The same `Dockerfile` runs on any host where you can give the container a MySQL database and a `.env`
> **file** at `/var/www/html/.env`: Caligrafy reads `.env` as a file, not plain environment variables. That is the
> case of [Coolify](https://coolify.io) (a file storage) or any server with Docker:
> `docker run -v $(pwd)/.env.production:/var/www/html/.env -p 80:80 <image>`. In that `.env`: `APP_ROOT=` (empty),
> the `DB_*` settings of the database, your `APP_KEY` and `API_KEY`, `APP_ENV=production`, `APP_DEBUG=false`. The
> image already runs the deploy step at start. Hosts that only offer environment variables (Railway, Render,
> DigitalOcean App Platform) are not supported yet.

---

## With Claude

Start Claude Code in the `bookshelf` folder (`claude`). The update of step 1 brought the plugin's skills (plugin 1.28.1
with Caligrafy Quill 2.16.2).

### Ship with the plugin

```
/caligrafy-quill:ship bookshelf-sam
```

**What Claude should do:** commit, create the GitHub repository (private; `--public` for a public one) and push: with
your SSH key first, then HTTPS with the GitHub CLI, then a browser approval of the GitHub CLI that it opens for you.
Then sign in to Caligrafy Cloud with the browser approval of step 7, create the app, add its deploy key, deploy and
check the address. You approve the sign-ins in the browser; it asks you before it spends credits.

**Check:**
- [ ] You never copied a token or a key: you only approved codes in the browser.
- [ ] It asked you before `extend` or `credits`.
- [ ] The repository on GitHub has a deploy key `Caligrafy Cloud`, read-only.
- [ ] `cloud status <name>` (the name it reports; `bookshelf-<you>` if `bookshelf` is taken) says `running`, and the
  catalog opens at the address.

### Deploy with the plugin

```
/caligrafy-quill:deploy bookshelf-sam
```

**What Claude should do:** check that the project is ready before it deploys: Caligrafy Quill 2.4.0 or later with a `Dockerfile`
at the root, everything committed and pushed to GitHub, a `create_db.sql` for every table, no `.env` or token in git,
and data the online database needs (step 2). Then drive `php caligrafer.php cloud ...`: `account`, `create` (with
`--repo`), the deploy key to add for a private repository, `deploy`, `status`. To sign in, it runs `cloud login`, and
**you** approve the code in the browser: a token never goes in the chat.

**Check:**
- [ ] Claude did not print or commit `.env`, and never asked you for a token in the chat.
- [ ] It did not create an app or spend credits without asking you (`create`, `extend`, `credits`).
- [ ] It told you to add the deploy key on GitHub with write access off.
- [ ] `cloud status bookshelf-sam` says `running`, and the catalog opens at the address.
- [ ] It did not run `cloud delete` without your OK.

### Make reader1 an administrator

```
Online, reader1 must be an administrator. Caligrafy Cloud has no database console: add a create_db.sql in a new
folder application/models/Team that gives reader1 permissions 1, check that the folder sorts after Auth, commit,
push and deploy with php caligrafer.php cloud deploy bookshelf-sam. Then show me the deploy lines of the logs.
```

**Check:**
- [ ] The script only has an `UPDATE users SET permissions = 1 WHERE username = 'reader1'`: no account created, no password.
- [ ] No `DELETE`, `DROP` or `TRUNCATE`: those would run once and never again.
- [ ] The logs show `application/models/Team/create_db.sql: run`.

### Agents and Caligrafy Cloud

A personal access token also works for AI agents: Caligrafy Cloud is an MCP server at https://deploy.caligrafy.com/mcp,
with tools such as `list_apps`, `get_app`, `deploy_app` and `app_logs`. Add it to Claude Code like Bookshelf in lesson 11
(a separate token is better: create one named "Claude Code" on the dashboard):
```bash
claude mcp add --transport http caligrafy-cloud https://deploy.caligrafy.com/mcp --header "Authorization: Bearer cq_..."
```
Then ask "Is bookshelf-sam running, and how much time is left?". Claude answers from `get_app`, the capability the
dashboard and `cloud status` call.

**If Claude's result differs:** if Claude wants to put the token in a file of the project or in `.env`, refuse: it
belongs in `.caligrafy/cloud.json` (git-ignored) or in Claude's MCP settings. If it suggests running `seed.sql` online,
there is no console: the data goes through a `create_db.sql` script, as in step 2.

### Your next idea, in one conversation

You have now done every step by hand: data, capabilities, every experience, people and trust, tests, online. The
plugin can do them all at once for your next idea. In a new folder (`git clone https://github.com/caligrafy/caligrafy-quill.git myidea`,
then `cd myidea` and `claude`):
```
/caligrafy-quill:idea <your idea, in a sentence or two>
```
Claude proposes a plan and asks you once, for that plan. Then it builds it, checks it, and ships it with
`/caligrafy-quill:ship`, like this lesson did: you only approve the sign-ins in the browser. Because you
followed the course, you can read every file it writes and change it. See the
[Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude).

---

## Checkpoint

```bash
git add -A
git commit -m "Lesson 14: Bookshelf online"
git tag lesson-14
git push origin main --tags
```
Bookshelf is online at `https://bookshelf-sam.caligrafy.com` (with your name): the catalog and the book pages, the
REST API (`/api/books`), sign-in with `reader1` as an administrator, and `/mcp` for agents. It is paused after its free
30 minutes unless you extend it.
Reference: [`checkpoints/lesson-14`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/lesson-14).

You have finished the course: Bookshelf has ten capabilities, five experiences, people with permissions, tokens with
scopes, tests, an audit log, and an address on the internet. The
[Caligrafy Quill wiki](https://github.com/caligrafy/caligrafy-quill/wiki) documents the modules the course did not
cover (file uploads, payments, social cards, the AI and machine learning modules). The appendix shows what is under
the hood: routes, controllers and forms written by hand.

## Quiz

1. You change the title of a book on your computer. Does it change online? Why?
2. You add a column to `books` in `application/models/Bookshelf/create_db.sql` with `ALTER TABLE`, push and deploy.
   What does the deploy step do with that script?
3. Why is `seed.sql` never run online, and how did the catalog get there?
4. A friend sees your app page and the deploy key on it. What can they do with it?

<details><summary>Show the solution</summary>

1. No. The online database is another database, filled by the deploy step. Your computer and the server share the
   code (through git and a deploy), not the data.
2. The script changed, so `deploy` runs it again. It must be safe to run on a database that already has the tables:
   `CREATE TABLE IF NOT EXISTS` is, and so is `ALTER TABLE ... ADD COLUMN`: since 2.13.5 the deploy step skips a
   column, an index or a foreign key that is already there, so the line can stay in the script. A script that drops or
   deletes is never run again at all.
3. Sample data is not production data: running a seed on every deploy could bring back rows that people deleted. The
   catalog came from `application/models/Online/create_db.sql`, a script made for the online database, with
   `INSERT IGNORE` so it can run again safely.
4. Nothing: it is the **public** half of the key. The private half stays in Caligrafy Cloud, and GitHub accepts it
   for that one repository, read-only.
</details>

## Exercise

Put the phone app of lesson 10 online too. It is served from `public/bookshelf-spa/dist/`, which is not committed.

<details><summary>Show the solution</summary>

The image has no Node.js, so it cannot build the app: commit the built files instead. In
`public/bookshelf-spa/.gitignore`, remove the line `dist`. Then from `public/bookshelf-spa`:
```bash
npm run build
```
and from the `bookshelf` folder:
```bash
git add -A
git commit -m "The built phone app"
git push
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf-sam
```
Open `https://bookshelf-sam.caligrafy.com/public/bookshelf-spa/dist/index.html` on your phone: the same app as on your
computer, with HTTPS, so it can be installed on the home screen (lesson 10). Run `npm run build` and commit again after
each change of the app.
</details>

---
Previous: [Lesson 13 - Capstone: one more feature, everywhere](13-Capstone-Loans) · Next: [Appendix A - Routes and controllers by hand](A-Routes-and-Controllers) · [Back to the course home](Home)
