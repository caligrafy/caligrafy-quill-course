# 10 Review, test, doctor and upgrade

**Goal:** keep Showcase healthy: review it, diagnose it, and upgrade it when Caligrafy Quill moves on.

## Manual
- **Lint** every PHP file: `find application -name '*.php' -print0 | xargs -0 -n1 php -l`
- **Smoke test** the API with the curl list of lesson 06 and the SPA with `npm run build`.
- **Doctor** (read-only): `php caligrafer.php doctor` (Docker: `docker-compose exec dev-box php caligrafer.php doctor`)
  checks `.env`, keys (masked), the database connection and services, with a fix per line.
- **Upgrade**: pull the new framework, then `php caligrafer.php upgrade` (a dry run) lists which `application/`
  files differ from the new templates and which you customized; `php caligrafer.php upgrade --apply` updates them
  and keeps a backup. Read the list before applying: your `web.php` and views are customized on purpose.
- **Framework tests**: the framework's PHPUnit suite lives in `qa/unit` (`vendor/bin/phpunit`). Run it after an
  upgrade to make sure the core you build on still passes.

## With Claude
- *"Use the caligrafy-reviewer agent on application/ and public/showcase-spa/src."* The reviewer checks routes order,
  CSRF on forms, guards, status codes, raw SQL in `all()` clauses, exposed keys and Bootstrap 5 / Vue 3 usage.
- *"Run php caligrafer.php doctor in the container and explain each warning."*
- *"Run php caligrafer.php upgrade (dry run) and tell me which files I customized before we apply anything."*
- The plugin's PHP lint hook runs `php -l` after Claude edits a PHP file.

**Check:** Claude ran `upgrade` as a dry run and asked before `--apply`; doctor output shows masked keys only; review
findings point at real lines (open them) before you change code.

**Prefer** Claude for reviews and upgrade triage (broad, repetitive reading), Manual for the final decision.

**The course is done:** compare with `checkpoints/part-2-vue` (tag `part-2`). Next: the framework wiki covers
metadata/social sharing, payments, AI and machine learning apps.
