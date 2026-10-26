# 00 Setup: Docker, Caligrafy Quill and Claude Code

**Goal:** a running Caligrafy Quill project called `showcase` on http://localhost:8080 with a MySQL database `showcase`.

## Manual
```bash
git clone git@github.com:caligrafy/caligrafy-quill.git showcase
cd showcase
.bin/caligrafer server start
```
`server start` runs `composer install` and `php caligrafer.php initialize` when needed, then `docker-compose up` for
`dev-box` (PHP 8.5 + Apache, your folder mounted, so edits are live), `mysql` (8.4, root/root) and `phpmyadmin`.
`initialize` creates `application/` (your code) and `.env`, prints your `APP_KEY`/`API_KEY`, and **removes the
framework's `.git`**: the project is now yours, so run `git init` and commit.

Edit `.env` (never commit it; `.gitignore` already excludes it):
```ini
APP_ROOT=
DB_ACTIVATE=true
DB_HOST=mysql
DB_DATABASE=showcase
DB_USERNAME=root
DB_PASSWORD=root
```
Create the database (phpMyAdmin on http://localhost:8077, or):
```bash
docker-compose exec -T mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS showcase"
```
Open http://localhost:8080: you see the Caligrafy welcome page (centered title, black and white cards).
Ports taken? Put a `docker-compose.override.yml` next to the compose file (for example `"8081:80"`) instead of
editing `docker-compose.yaml`.

## With Claude
Open Claude Code in the `showcase` folder and trust it: `.claude/settings.json` enables the `caligrafy-quill` plugin
shipped in `claude-plugin/`. Then:
```
/caligrafy-quill:server start
```
Claude checks Docker, free ports (proposing an override file when one is taken), the `.env` Docker values, warns
before `initialize` removes `.git`, starts the containers and curls the home page.
Then: `/caligrafy-quill:db query "CREATE DATABASE IF NOT EXISTS showcase"`.

**Check:** the home page answers 200; `.env` has `DB_HOST=mysql` and empty `APP_ROOT`; Claude did not print your
keys; nothing in `.env` was committed. If something fails later, `php caligrafer.php doctor` (lesson 10) checks
`.env`, keys (masked), the database and services.

**Prefer** the manual track the first time (you need to know what `initialize` does); use the skill afterwards,
especially to diagnose port conflicts. Use `/caligrafy-quill:server stop`, not `down`: the MySQL service has no
named volume, so `down` loses the data.
