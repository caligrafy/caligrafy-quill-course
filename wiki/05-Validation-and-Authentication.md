# 05 Validation and authentication

**Goal:** validated input with readable errors, and only signed-in users can change projects in the browser.

## Manual
Filter, then validate (GUMP rules); `validate()` returns `true` or an array of messages per field:
```php
private $filters = ['title' => 'trim|sanitize_string', 'description' => 'trim|sanitize_string'];
private $rules = ['title' => 'required|max_len,150', 'description' => 'required|min_len,10'];

public function create()
{
    if (!$this->canWrite()) {
        return redirect('/login');
    }
    $this->context();
    $data = $this->filter(array_merge(['title' => '', 'description' => ''], $this->request->parameters() ?? []), $this->filters);
    $valid = $this->validate($data, $this->rules);
    if ($valid !== true) {
        http_response_code(422);
        return view('Showcase/form', ['project' => (object) $data, 'errors' => $valid, 'authorized' => authorized()]);
    }
    $project = new \Project();
    $project->title = $data['title'];
    $project->description = $data['description'];
    $user = user();
    $project->user_id = $user->id ?? null;
    $saved = $this->save($project);
    if ($this->request->wantsJson()) {
        http_response_code(201);
        return api($saved);
    }
    return redirect('/projects');
}

// Browser writes need a signed-in user; API writes are authorized by the API key
private function canWrite()
{
    return $this->request->wantsJson() || authorized();
}
```
Show errors in the form: `if isset($errors['title'])` / `.text-danger.small-text #{$errors['title']}`.

Authentication: in `web.php` uncomment `Auth::authentication('User', 'users');` and the auth routes, which now
point at the framework controller `Caligrafy\\Controllers\\AuthController` (`/login`, `/register`, `/logout`,
`/home`, `/notAuthorized`). Pass `'authorized' => authorized()` to every view so the layout shows the signed-in
navigation. Passwords are stored with `password_hash()`. Register at http://localhost:8080/register.

## With Claude
Ask: *"Turn on Caligrafy authentication (users table already created), protect the project create/edit/delete pages
for signed-in users while API calls stay authorized by the API key, and show validation errors under each field."*

**Check:** `Auth::authentication` is active and the auth routes use `Caligrafy\\Controllers\\AuthController`; invalid
posts answer **422** and re-show the form with messages; a signed-out browser is redirected to `/login`; nothing
stores plain passwords; forms still carry `=csrf()`.

**Prefer** Manual: security code is the code you must understand best. Ask the `caligrafy-reviewer` agent afterwards
(lesson 10) to look for missing guards.
