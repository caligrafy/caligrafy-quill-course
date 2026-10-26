# 03 Models and relationships

**Goal:** a `Project` model, a full CRUD controller, and the owner relationship to `User`.

## Manual
`application/models/Showcase/Project.php` - one public property per column, no namespace (models are global classes):
```php
<?php
use Caligrafy\Model;

class Project extends Model {
    public $id;
    public $title;
    public $description;
    public $user_id;
    public $created_at;
    public $modified_at;

    // projects.user_id -> users.id
    public function owner()
    {
        return $this->hasOne('User', 'users');
    }
}
```
The controller binds the model per action with `associate('Project', 'projects')` and uses the Controller helpers
`all()`, `find()` (reads `{id}`), `save()` (insert, or update when `{id}` is in the route) and `delete()`:
```php
private function context()
{
    $this->associate('Project', 'projects');
}

public function index()
{
    $this->context();
    $projects = $this->all(['order' => 'ORDER BY created_at DESC']) ?: [];
    return api(['projects' => $projects]);
}

public function read()
{
    $this->context();
    $project = $this->find();
    if (!$project) {
        http_response_code(404);
        return api(['error' => 'Project not found']);
    }
    return api($project);
}
```
Inside `namespace Showcase` a model must be written `new \Project()` (leading backslash), otherwise PHP looks for
`Showcase\Project`. Clauses passed to `all()` are raw SQL: never concatenate user input, use `'bindings'`.
Many-to-many (the original course linked projects and users with `belongsToMany`) still exists and creates the pivot
table for you; Showcase uses the simpler one-owner relationship.
Full controller: [ProjectController.php](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-1-server/application/controllers/Showcase/ProjectController.php).

## With Claude
```
/caligrafy-quill:new-resource Project title:string description:text user:fk
```
The skill writes the SQL script, the model, the controller (filter + validate, 201/404/422 status codes) and the five
REST routes. Then ask: *"Move it to the Showcase namespace: controllers/Showcase/ProjectController.php,
namespace Showcase, routes 'Showcase\\ProjectController@...', keep the model global."*

**Check:** `\Project` inside the namespace; the routes point at `Showcase\\ProjectController`; `create_db.sql` has no
`DROP TABLE`; `user_id` is the foreign key; `find()` misses return 404, not 200 with `null`.

**Prefer** Claude for the scaffolding once you have typed one resource by hand; compare its controller with yours.
