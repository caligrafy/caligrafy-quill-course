# 02 Routes, requests and controllers

**Goal:** `/projects` handled by a namespaced controller `Showcase\ProjectController`.

## Manual
All routes live in `application/config/routing/web.php`, inside the `try`, after `Route::get('/', ...)` (unknown
URLs fall back to the **first** route, so keep `/` first) and before `Route::run()` (keep it last):
```php
Route::get('/projects', 'Showcase\\ProjectController');                    // index()
Route::get('/projects/new', 'Showcase\\ProjectController@createForm');     // before /projects/{id}
Route::get('/projects/{id}', 'Showcase\\ProjectController@read');
Route::get('/projects/{id}/edit', 'Showcase\\ProjectController@editForm');
Route::post('/projects', 'Showcase\\ProjectController@create');
Route::put('/projects/{id}', 'Showcase\\ProjectController@update');
Route::delete('/projects/{id}', 'Showcase\\ProjectController@remove');
```
Controller `application/controllers/Showcase/ProjectController.php` - the folder matches the namespace:
```php
<?php
namespace Showcase;

use Caligrafy\Controller;

class ProjectController extends Controller {
    public function index()
    {
        return api(['message' => 'projects will come here']);
    }
}
```
Every controller needs `index()` (used when the route names no method). Request data: `$this->request->fetch('id')`
for a route parameter, `$this->request->parameters()` for the body/query. Test: http://localhost:8080/projects.

What changed since the original course: controllers can be namespaced (`'Showcase\\ProjectController@read'`), the
framework's own controllers are `Caligrafy\Controllers\...`, and errors go through `Errors::handle($e)`.

## With Claude
Ask: *"Add a namespaced controller Showcase\ProjectController with an index() returning a JSON message, and its
GET /projects route."* The `caligrafy` skill knows the routing rules. Lesson 03 scaffolds the full resource.

**Check:** the routes are before `Route::run()` and after `/`; `/projects/new` comes before `/projects/{id}`;
the namespace matches the folder; the route string uses `\\` in PHP single quotes.

**Prefer** Manual: it is a handful of lines and the routing rules are the foundation of everything else.
