# Lesson 01 - Create your project

**In this lesson** you create the Bookshelf project from the Caligrafy Quill framework, start it with Docker, set up
its `.env` file and its database, take a tour of its folders, check everything with `doctor`, and make it your own
git repository.

**Before you start:** lesson 00 is done: Git, Docker Desktop (running) and an editor are installed.

**Time:** 20 to 40 minutes. The first start downloads and builds the PHP image: it can take 5 to 20 minutes,
depending on your connection. Later starts take seconds.

---

## New concepts

**A Caligrafy project is a copy of the framework.** You do not install Caligrafy somewhere and point to it: you
**clone** the framework repository into a new folder, and that folder becomes your project. Your code goes in
`application/` and `public/`; the framework stays in `framework/`.

**Composer and `vendor/`.** Caligrafy uses PHP libraries written by others (Pug for the views, GUMP for validation,
PHPUnit for tests...). **Composer** downloads them into the folder `vendor/`, from the list in `composer.json`.
`vendor/` is not in git: you recreate it with `composer install`.

**`initialize`.** The command `php caligrafer.php initialize` prepares a fresh clone:
1. it creates the `.env` file (from `framework/settings/.env.example`) if there is none;
2. it generates your two keys and writes them at the top of `.env`: `APP_KEY` (encrypts sessions) and `API_KEY` (the
   key of your REST API, lesson 09). They are also copied for the Vite template (lesson 11);
3. it sets `APP_ROOT` (see below);
4. it copies `framework/settings/application` to `application/`: the starting point of your code;
5. it **deletes the `.git` folder**, because the history of the framework is not the history of your project.

> **Warning: `initialize` deletes `.git`.** Run it **once**, right after cloning, as in this lesson. Never run it
> again in a project where you have made commits: it would delete your whole git history. If you ever need to
> re-run it, first copy your project to a backup folder.

**`.env`.** A text file of `NAME=value` lines with the settings of **this** installation: the keys, the database
connection, the mode (`APP_ENV`), the API keys of external services. Every line becomes a PHP constant (`DB_HOST`,
`API_KEY`...). It holds secrets, so it is **never** committed: the project's `.gitignore` already excludes it, and
`.htaccess` refuses to serve it on the web.

**Docker Compose.** The file `docker-compose.yaml` of the project describes the containers:

| Service | What it is | Address on your computer |
|---|---|---|
| `dev-box` | PHP 8.5 + Apache, with your project folder mounted inside: a saved change is live at once | http://localhost:8080 |
| `mysql` | MySQL 8.4, user `root`, password `root` | port 3306 |
| `phpmyadmin` | a web page to look at the database | http://localhost:8077 |

Containers talk to each other by service name: from `dev-box`, the database host is `mysql` (not `localhost`). In
the course, every PHP command runs **inside** `dev-box` with `docker compose exec dev-box <command>`, so you do not
need PHP on your computer.

**`APP_ROOT`.** The sub-folder of the site in its URL. Under Docker the site is at the root
(`http://localhost:8080/`), so `APP_ROOT` must be **empty**. `initialize` writes the project folder name there
(for MAMP-style servers where the site is `http://localhost/bookshelf/`), unless you give it one more word: we run
`initialize off`, which leaves `APP_ROOT=` empty.

---

## Manual track

### Step 1 - Clone the framework

Choose the folder where you keep your projects (here `~/code`), and clone Caligrafy Quill into a new folder named
`bookshelf`:
```bash
mkdir -p ~/code
cd ~/code
git clone https://github.com/caligrafy/caligrafy-quill.git bookshelf
cd bookshelf
```
Expected: `Cloning into 'bookshelf'...` then the prompt. `ls` lists, among others,
`caligrafer.php  composer.json  docker  docker-compose.yaml  framework  public  qa  tests`. There is no
`application/` folder and no `.env` yet: `initialize` creates them in step 4.

**From now on, every command of the course runs in this `bookshelf` folder**, unless a lesson says otherwise.

### Step 2 - Install the PHP libraries

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs
```
This runs Composer from its official Docker image, on your project folder (`-v "$PWD":/app`).
`--ignore-platform-reqs` is needed because that image lacks some PHP extensions (`gd`, `bcmath`, `intl`) that the
libraries ask for; the `dev-box` container that runs your site has all of them, and `doctor` checks it in step 8.

Expected: a long list of `- Installing ...`, then `Generating autoload files`. Near the end, the lines
`Package added to be installed/updated with npm: ...` and `Installation failed after 3 tries.` come from an optional
extra (a Node.js Pug tool that Caligrafy does not use): ignore them. Check that `vendor/` exists:
```bash
ls vendor/autoload.php
```
Expected: `vendor/autoload.php`.

(With PHP 8.3+ and Composer on your computer you can run `composer install` instead.)

### Step 3 - Build and start the containers

```bash
docker compose up -d --build dev-box
```
The first time, Docker downloads the base images and builds the PHP image (5 to 20 minutes). It ends with lines
like:
```
 ✔ Container bookshelf-mysql-1       Started
 ✔ Container bookshelf-phpmyadmin-1  Started
 ✔ Container bookshelf-dev-box-1     Started
```
Check:
```bash
docker compose ps
```
Expected: three lines (`dev-box`, `mysql`, `phpmyadmin`), each with a status `Up ...`.

Do not open the site yet: it has no `application/` folder until the next step.

### Step 4 - Initialize the project

```bash
docker compose exec dev-box php caligrafer.php initialize off
```
Expected (on macOS and Windows the first lines are a few `chmod` warnings about `.git` files: they are harmless, the
folder is deleted right after):
```
chmod: changing permissions of '.git/objects/pack/pack-....pack': Permission denied

Preparing and signing the project for usage...

 Application initialized successfully
 APP_KEY=...
 API_KEY=...
```
The two keys are printed once. **Do not share them** (not in a chat, not in a screenshot): they are in `.env`
anyway. `ls -a` now shows `application/` and `.env`, and **no** `.git` folder.

### Step 5 - Look at the welcome page

Open http://localhost:8080 in your browser: the Caligrafy welcome page, black and white, with the title
**Welcome to Caligrafy** and five cards (Docs and Cookbook, Caligrafy Channel...).

From the terminal:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/
```
Expected: `200`.

### Step 6 - Set up `.env` for Docker and the database

Open `.env` in your editor. The first lines are `APP_ROOT=`, `APP_KEY=...`, `API_KEY=...`. Leave the keys alone.
Find the `DB_` lines and change them so that they read exactly:
```ini
DB_ACTIVATE=true
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bookshelf
DB_USERNAME=root
DB_PASSWORD=root
```
- `DB_ACTIVATE=true` turns the database connection on (it is off in a fresh project);
- `DB_HOST=mysql` is the name of the MySQL container (the `.env` value wins over the one in
  `docker-compose.yaml`, so it must be right here);
- `DB_DATABASE=bookshelf` is the database we create now.

Check that `APP_ROOT=` is empty (nothing after `=`). Save.

Create the database:
```bash
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS bookshelf DEFAULT CHARACTER SET utf8mb4"
```
Expected: only `mysql: [Warning] Using a password on the command line interface can be insecure.` (normal for a
development password).

### Step 7 - Tour of the project

| Path | What it is | Do you edit it? |
|---|---|---|
| `application/config/routing/web.php` | the routes (lesson 02) | yes |
| `application/controllers/` | your controllers (lesson 03); `core/` holds the framework's example controllers (sign in...) | yes (not `core/`) |
| `application/models/` | your models and SQL scripts (lessons 04-05); `Auth/` is the users module (lesson 08) | yes |
| `application/views/` | your Pug views (lesson 06); `default/` holds the layout | yes |
| `application/config/config.php` | extra settings (empty) | rarely |
| `public/` | files served as they are: `css/`, `js/`, `images/`, `uploads/`, and your client apps (lessons 10-12) | your apps |
| `framework/` | the framework itself | no |
| `vendor/` | the libraries installed by Composer | no |
| `.env` | the settings and secrets of this installation | yes, never commit it |
| `caligrafer.php` | the Caligrafer command line tool | no |
| `docker-compose.yaml`, `docker/` | the containers | no (see the port box below) |
| `claude-plugin/`, `.claude/` | the Claude Code plugin and the setting that enables it | no |
| `qa/`, `tests/`, `phpunit.xml.dist` | the framework's test suite | no |

### Step 8 - Check the setup with `doctor`

```bash
docker compose exec dev-box php caligrafer.php doctor
```
`doctor` checks and changes nothing. Expected (version numbers and the last 4 characters of the keys differ):
```
Caligrafy doctor (read-only)

 Project
  OK    PHP: 8.5.12
  OK    Extensions: curl, json, mbstring, openssl, pdo
  OK    vendor/: Composer dependencies installed
  OK    .env: present
  OK    APP_KEY: set …Sn8= (44 chars)
  OK    API_KEY: set …vg== (112 chars)
  OK    public/uploads: writable
  OK    Templates: application/ is up to date with the framework templates

 Database
  OK    Database: connected to mysql://mysql:3306/bookshelf (server 8.4.13)

 OpenAI
  SKIP  OpenAI: OPEN_AI_KEY is not set

 Groq
  SKIP  Groq: GROQ_API_KEY is not set

 Stripe
  SKIP  Stripe: PAY_PRIVATE_KEY_TEST is not set (APP_ENV=local)

 Coinbase Business
  SKIP  Coinbase: COINBASE_API_KEY_NAME is not set

 Watson
  SKIP  Watson: WATSON_API_KEY is not set

 Mail
  SKIP  Mail: MAIL_HOST is not set

 9 OK, 0 WARN, 0 FAIL, 6 SKIP
```
`SKIP` means "not configured": these are services this course does not use. `doctor` shows only the end of each key
(`…Sn8=`): it never prints secrets.

### Step 9 - Make the project your own git repository

```bash
git init
git add -A
git status --short | grep -E "^A  \.env$|vendor/" || echo "no .env and no vendor: good"
git commit -m "Bookshelf: new Caligrafy Quill project"
git tag lesson-01
```
Expected: `Initialized empty Git repository in .../bookshelf/.git/`, then `no .env and no vendor: good`, then the
commit summary. From now on, remember the warning of step 4: **never run `initialize` again in this folder.**

### Starting and stopping

| To... | Run (in the `bookshelf` folder) |
|---|---|
| stop the containers at the end of the day | `docker compose stop` |
| start them again | `docker compose up -d dev-box` |
| see their state | `docker compose ps` |

Use `stop`, **not** `down`: the MySQL container keeps the data inside itself, and `down` deletes the containers
with your data (you would re-run the SQL scripts of lessons 04 and 08).

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `Cannot connect to the Docker daemon` | Docker Desktop is not running | Start it, wait for *Engine running* |
> | `Bind for 0.0.0.0:8080 failed: port is already allocated` (or 8077, 3306) | Another program (another Caligrafy project, MAMP, a local MySQL) uses the port | See "Ports already taken" below |
> | `Your requirements could not be resolved...` from Composer | `--ignore-platform-reqs` is missing | Run the command of step 2 exactly |
> | The site shows an error about `application/config/config.php` | Step 4 was not run | Run step 4 |
> | Links and styles point to `http://localhost:8080/bookshelf/...` | You ran `initialize` without `off`, so `APP_ROOT=bookshelf` | Make the line `APP_ROOT=` empty in `.env` |
> | `doctor`: `FAIL Database` ... `Unknown database 'bookshelf'` | The database was not created | The `CREATE DATABASE` command of step 6 |
> | `doctor`: `FAIL Database` ... `getaddrinfo for mysql failed` | You ran `php caligrafer.php doctor` on your computer, outside Docker | Use `docker compose exec dev-box php caligrafer.php doctor` |
> | `docker compose exec` says `service "dev-box" is not running` | The containers are stopped | `docker compose up -d dev-box` |
> | The build stops with `cannot allocate memory` or `Killed` while it downloads packages (`apt-get`) | On a slow or unstable connection the package tool can run out of memory | Docker Desktop > Settings > Resources: give Docker at least 4 GB, do not run other builds at the same time, run step 3 again (it restarts from the failed step); if it keeps failing, try another network |
> | Linux: you cannot edit `application/` files (permission denied) | Files created inside the container belong to root | `sudo chown -R "$USER": .` in the project folder |
>
> **Ports already taken.** Do not edit `docker-compose.yaml`. Create next to it a file
> **`docker-compose.override.yml`** (Docker Compose reads it automatically) with other ports on your side:
> ```yaml
> services:
>   dev-box:
>     ports: !override
>       - "8081:80"
>   phpmyadmin:
>     ports: !override
>       - "8078:80"
>   mysql:
>     ports: !override
>       - "3307:3306"
> ```
> Then `docker compose up -d dev-box` and use http://localhost:8081 (and 8078 for phpMyAdmin) everywhere the course
> says 8080 (and 8077). `!override` needs Docker Compose 2.24+. Add the file to `.gitignore` (it is a personal
> setting): `echo "docker-compose.override.yml" >> .gitignore`.

---

## With Claude

Do steps 1 to 4 by hand: Claude Code works best inside an initialized project, and you must know what `initialize`
does. Then open Claude Code in the `bookshelf` folder:
```bash
claude
```
1. Claude asks *Do you trust the files in this folder?*: answer **Yes**. The project's `.claude/settings.json` then
   enables the `caligrafy-quill` plugin. Type `/plugin` and check that `caligrafy-quill` is listed as installed and
   enabled (if Claude asks to install the plugin from the project's marketplace, accept). Type `/` and look for the
   commands `/caligrafy-quill:server`, `/caligrafy-quill:db`, `/caligrafy-quill:new-resource`,
   `/caligrafy-quill:new-app`.
2. Ask Claude to check the server and `.env`:
   ```
   /caligrafy-quill:server status
   ```
   then:
   ```
   Check .env for Docker without printing any secret value: DB_ACTIVATE must be true, DB_HOST mysql,
   DB_DATABASE bookshelf, APP_ROOT empty. Propose the changes, then create the database bookshelf with
   /caligrafy-quill:db and run docker compose exec dev-box php caligrafer.php doctor.
   ```

**What Claude should do:** report the three containers and their ports; read `.env` and show which `DB_` lines to
change **without** showing `APP_KEY` or `API_KEY`; after your OK, edit `.env`, create the database in the `mysql`
container, run `doctor` in `dev-box`, and explain its result.

**Check** before you accept:
- [ ] Claude never printed the values of `APP_KEY`, `API_KEY` or passwords (doctor masks them).
- [ ] `.env` has `DB_ACTIVATE=true`, `DB_HOST=mysql`, `DB_DATABASE=bookshelf`, `APP_ROOT=` empty.
- [ ] `doctor` shows no `FAIL`.
- [ ] Claude did **not** run `initialize` again, and asked before any command that deletes something.

**If Claude's result differs:** to start the server, the plugin's `server` skill uses `.bin/caligrafer server start`.
That script needs PHP and Composer **on your computer**; without them it falls back to `.bin/server`, which starts the
`prod-box` service (a copy of the code that you must rebuild after every change, under the URL
`/caligrafy-quill`). If you have no local PHP, tell Claude: *"start the dev server with
`docker compose up -d dev-box`"*. If a port is taken, the skill proposes a `docker-compose.override.yml` like the
one of the box above: accept it, and use the new ports.

---

## Checkpoint

You made the tag `lesson-01` in step 9. There is no reference code for this lesson: the files that `initialize`
creates are the same for everyone, and `.env` must never be shared.

## Quiz

1. Why is `.env` not in git?
2. What does `initialize` do to the `.git` folder, and when is that a problem?
3. Inside the `dev-box` container, what is the host name of the database?
4. Why `docker compose stop` and not `docker compose down`?

<details><summary>Show the solution</summary>

1. It contains secrets (`APP_KEY`, `API_KEY`, passwords) and settings that are specific to one installation.
2. It deletes it. That is what you want right after cloning the framework (the framework's history is not yours),
   and a disaster later, when `.git` holds your own commits.
3. `mysql`, the name of the service in `docker-compose.yaml` (`DB_HOST=mysql`).
4. `down` deletes the containers, and the MySQL container keeps the data inside itself: the data would be lost.
</details>

---
Previous: [Lesson 00 - What Caligrafy Quill is](00-Introduction-and-Tools) · Next: [Lesson 02 - Routes](02-Routes)
