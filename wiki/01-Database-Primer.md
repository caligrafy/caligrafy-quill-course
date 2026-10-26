# 01 Database primer

**Goal:** the `users` and `projects` tables with sample data. Caligrafy never migrates: you write the SQL.
Conventions: every table has an `id` primary key; foreign keys are `<model>_id` (here `user_id`).

## Manual
Create `application/models/Showcase/create_db.sql`:
```sql
CREATE TABLE IF NOT EXISTS projects (
    id int NOT NULL AUTO_INCREMENT,
    title varchar(150) NOT NULL,
    description text NOT NULL,
    user_id int NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_projects_user_id (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```
and `application/models/Showcase/seed.sql` ([checkpoint](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-1-server/application/models/Showcase/seed.sql)).
Run the users script that ships with the framework, then yours:
```bash
for f in application/models/Auth/create_db.sql application/models/Showcase/create_db.sql application/models/Showcase/seed.sql; do
  docker-compose exec -T mysql mysql -uroot -proot showcase < $f
done
docker-compose exec -T mysql mysql -uroot -proot showcase -e "SELECT id, title FROM projects"
```
Changes from the original course: `utf8mb4`, `CURRENT_TIMESTAMP` defaults, no `DROP TABLE` in the create
script (re-running it is safe), and the users table now stores `password_hash()` passcodes (`varchar(255)`).

## With Claude
```
/caligrafy-quill:db application/models/Auth/create_db.sql
/caligrafy-quill:db application/models/Showcase/create_db.sql
/caligrafy-quill:db tables
```
Or ask: *"Write application/models/Showcase/create_db.sql for a projects table (title, description, optional
user_id owner, timestamps) following the Caligrafy conventions, plus a seed.sql with 3 sample projects, and run both."*

**Check:** `id` primary key and `user_id` (not `users_id`); no `DROP TABLE` in the create script; the skill
asks before destructive SQL; `tables` lists `users` and `projects`.

**Prefer** Manual for the first schema (you must be able to read SQL); the `db` skill for running scripts and queries.
