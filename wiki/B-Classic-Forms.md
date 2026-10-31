# Appendix B - Classic forms and controller APIs

**In this appendix** you write a classic form, "Add an author", handled entirely by a controller: CSRF protection,
filters and rules, a 422 with the messages, saving with the controller's database helpers and a redirect. Then you
see what that costs (no other experience gets it), and in the exercise you turn it into a `create_author` capability.

**Before you start:** [Appendix A](A-Routes-and-Controllers) is done (tag `appendix-a`), the containers run
(`docker compose ps`), and you have the account `reader1` of lesson 11 (if you are before lesson 11, see the note in
step 4).

**Time:** 45 minutes.

---

## New concepts

**A classic form** sends the page to the server: `form(method='POST', action=".../authors")` posts the fields
(`name=...&bio=...`) to `POST /authors`, and the server answers with a whole new page. The catalog of lesson 06 does
it differently: JavaScript calls a capability and updates the page in place.

**Reading the posted fields.** In a controller, `$this->request->parameters()` returns the posted fields as an array.
Careful: `$this->request->fetch('name')` reads **route variables** only (`{name}` of the path), not posted fields.
This course met that bug while writing this appendix: the name was always empty.

**CSRF (Cross-Site Request Forgery).** Without protection, another website could hold a hidden form that posts to
your Bookshelf with the browser of a visitor who is signed in. Caligrafy refuses every browser `POST`, `PUT` and
`DELETE` that does not carry the secret token of the visitor's session, with status **403**. In Pug, `=csrf()` writes
that token as a hidden field. **Every form needs `=csrf()`.** (The JS client sends the same token in a header, which
is why you never wrote it for the catalog.) A form that must send `PUT` or `DELETE` also adds `=methodField('PUT')`:
HTML forms only know GET and POST (you saw both in `book.pug`, lesson 06).

**Filters and rules in a controller.** `$this->validator` is the GUMP validator, the same library behind `$rules` in
a capability:
- `$this->validator->filter($data, $filters)` cleans the values (`trim`);
- `$this->validator->validate($data, $rules)` returns `true`, or something else when a rule fails;
- `$this->validator->get_errors_array()` then gives one message per field: `array('name' => 'The Name field is required')`.
Call `filter()` then `validate()`. (`check()` also exists, but with rules **and** filters it returns an array even
when everything is valid: a classic bug.)

**The controller's database helpers** (the "controller API"):

| Call | What it does |
|---|---|
| `$this->associate('Author', 'authors')` | the model and table the next calls work on |
| `$this->all($args)`, `$this->find($id)` | read rows (Appendix A) |
| `$this->save($data)` | inserts a row when the route has no `{id}`, updates the row `{id}` when it has one; returns the model |
| `$this->delete($id)` | deletes a row (by default the `{id}` of the route) |

**After a valid POST, redirect.** The controller saves, then `redirect('/authors/4')`: the browser loads that page
with GET, so pressing Reload does not send the form twice. `redirect()` only sends a header: write `exit;` after it.
When the form is invalid, the controller shows the form again with status **422** and the messages.

---

## Manual track

### Step 1 - The controller actions

`AuthorController` gets two actions, `createForm()` and `create()`, and two private helpers.

**File `application/controllers/Bookshelf/AuthorController.php`** (replace its whole content):

```php
<?php

namespace Bookshelf;

use Caligrafy\Controller;

// The classic way: a controller reads the models itself and renders a view. No capability is involved,
// so only this page has these features: REST clients and AI agents do not see them
class AuthorController extends Controller
{
    // GET /authors/{id}: an author and their books
    public function show()
    {
        $this->associate('Author', 'authors');   // the model and the table this controller works on
        $author = $this->find();                 // finds the row whose id is {id} of the route
        if (!$author) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no such author.', 'authorized' => authorized()));
        }
        return view('Bookshelf/author', array('author' => $author, 'books' => $author->books(), 'authorized' => authorized()));
    }

    // GET /v1/authors: JSON for programs (Accept: application/json and a token with the scope *)
    public function index()
    {
        $this->associate('Author', 'authors');
        return api(array('authors' => $this->all(array('order' => 'ORDER BY name'))));
    }

    // GET /authors/new: the empty form (signed-in users only)
    public function createForm()
    {
        $this->signedInOnly();
        return $this->form(array('name' => '', 'bio' => ''), array());
    }

    // POST /authors: the form is sent here. The rules live in this controller only
    public function create()
    {
        $this->signedInOnly();
        $input = $this->request->parameters();   // the posted fields
        $data = $this->validator->filter(array(
            'name' => $input['name'] ?? '',
            'bio' => $input['bio'] ?? ''
        ), array('name' => 'trim', 'bio' => 'trim'));
        $valid = $this->validator->validate($data, array('name' => 'required|max_len,150', 'bio' => 'max_len,2000'));
        if ($valid !== true) {
            http_response_code(422);
            return $this->form($data, $this->validator->get_errors_array());
        }
        $this->associate('Author', 'authors');
        $author = $this->save($data);   // no {id} in this route: save() inserts a new row
        redirect('/authors/' . $author->id);
        exit;
    }

    private function form(array $values, array $errors)
    {
        return view('Bookshelf/authorform', array('values' => $values, 'errors' => $errors, 'authorized' => authorized()));
    }

    // Sends visitors who are not signed in to the sign-in page
    private function signedInOnly()
    {
        if (!authorized()) {
            redirect('/login');
            exit;
        }
    }
}
```

- `signedInOnly()`: a visitor is sent to the sign-in page. `authorized()` is true when someone is signed in.
- `create()` keeps only `name` and `bio` from the posted fields (a visitor could post anything), trims them, checks
  them, and on failure shows the form again with what was typed and the messages.
- `save($data)`: the route `POST /authors` has no `{id}`, so it inserts. It returns the new `Author`, whose `id`
  builds the address of the redirect.
- `form()` is `private`: only public methods can be actions.

### Step 2 - The form

**File `application/views/Bookshelf/authorform.pug`**:

```pug
extends ../default/master/layoutblocks

//- Add an author: a classic form posted to Bookshelf\AuthorController@create

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title Add an author
                p.cq-note A classic form: the page is sent to the server, which answers with a new page.

            form.cq-form(method='POST', action="{$home}authors", novalidate)
                =csrf()
                .cq-fields
                    div
                        label(for='name') * Name
                        input#name.form-control(type='text', name='name', value=$values['name'], maxlength='150')
                    div
                        label(for='bio') Bio
                        textarea#bio.form-control(name='bio', rows='3', maxlength='2000')= $values['bio']
                button.btn.btn-primary.cq-submit(type='submit') Add author

            if $errors
                p.cq-error(role='alert')= implode(' ', $errors)

            .cq-foot
                a(href="{$home}books") All books
```

- `=csrf()` right under `form(...)` writes the hidden `_token` field.
- `value=$values['name']` and the textarea content keep what the visitor typed when the form comes back with errors.
- `novalidate` turns off the browser's own checks, so you see the server's messages (the ones every client gets).

### Step 3 - The routes

In `application/config/routing/web.php`, rename the comment of the Appendix A block:
```php
    // APPENDIX: routes, controllers and forms written by hand
```
and add these two lines right **above** `Route::get('/authors/{id}', ...)` (the full file is in the checkpoint):
```php
    Route::get('/authors/new', 'Bookshelf\\AuthorController@createForm');   // before /authors/{id}, or {id} would take "new"
    Route::post('/authors', 'Bookshelf\\AuthorController@create');
```

### Step 4 - Try it

Signed out, from the `bookshelf` folder:
```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' http://localhost:8080/authors/new
```
Expected: `302 http://localhost:8080/login` (302 is a redirect).

Without the CSRF token, as another website would post:
```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST -d 'name=Forged' http://localhost:8080/authors
```
Expected: `403`. Nothing was saved.

In the browser, sign in as `reader1` and open http://localhost:8080/authors/new: the card "Add an author" with Name,
Bio and "Add author".
- Click "Add author" with an empty name: the same form, with "The Name field is required" (status 422).
- Type `N. K. Jemisin` and a bio (for example "American author of the Broken Earth trilogy."), click "Add author":
  you land on http://localhost:8080/authors/4, her page (no books yet).
- Open http://localhost:8080/books: the author tabs (one per author, from `list_authors`) now include N. K. Jemisin. The data is shared by everyone; only
  the **form** is private to this page.

> Before lesson 11 nobody can sign in, so `/authors/new` always redirects to `/login`. Remove the two `$this->signedInOnly();` lines to try the form, and put them back afterwards.

> **If it doesn't work**
>
> | What you see | Cause | Fix |
> |---|---|---|
> | `[ERROR 3000] Security Error - Cross Site Forgery` (403) when you submit your own form | `=csrf()` is missing, or your session expired (the form was open while the containers restarted) | Add `=csrf()` under `form(...)`; reload the form and submit again |
> | `/authors/new` shows "There is no such author." | `/authors/new` is declared below `/authors/{id}` | Move it above |
> | "The Name field is required" although you typed a name | The controller reads the field with `$this->request->fetch('name')` (route variables only) | Use `$this->request->parameters()` |
> | A blank page after a valid submit | `exit;` is missing after `redirect()`, or code after it failed | Keep `redirect(...); exit;` |
> | Saving changed an existing author | The route that receives the form has an `{id}`, so `save()` updated that row | Post to `/authors` (no `{id}`) to create |

---

## With Claude

The plugin's `/caligrafy-quill:new-resource` command scaffolds a whole classic resource (table, model, controller with
validated actions, routes). Authors already exist, so here ask directly:
```
The classic way, without capabilities: add to Bookshelf\AuthorController a createForm() action for GET /authors/new
and a create() action for POST /authors. Both are for signed-in users only (redirect('/login') and exit otherwise).
create() reads the posted fields with $this->request->parameters(), keeps only name and bio, trims them with
$this->validator->filter(), validates with $this->validator->validate() (name required, max 150; bio max 2000), and
on failure shows the form again with status 422 and $this->validator->get_errors_array(). On success:
associate('Author', 'authors'), save(), redirect to /authors/<id> and exit. The view
application/views/Bookshelf/authorform.pug uses the page components, =csrf(), keeps the typed values and shows the
errors in .cq-error. In web.php, add GET /authors/new above GET /authors/{id}, and POST /authors.
```

**Check** before you accept:
- [ ] The form has `=csrf()`.
- [ ] `/authors/new` is declared **above** `/authors/{id}`.
- [ ] The posted fields come from `parameters()`, not `fetch()` or `$_POST`.
- [ ] `filter()` then `validate()` (not `check()` with both rules and filters).
- [ ] `http_response_code(422)` before the form is shown again; `exit;` after every `redirect()`.
- [ ] The checks of step 4 give the same answers (302, 403, the 422 message, the redirect to the new author).

**If Claude's result differs:** if it puts the rules in a separate class or uses `check()` correctly, that works too.
If it offers a `create_author` capability, keep that for the exercise.

---

## Compare with capabilities

| | The form of this appendix | A `create_author` capability |
|---|---|---|
| The web page | yes | yes, through `$this->call('create_author')` |
| REST | no JSON route: `POST /authors` is made for a browser form and answers with a page or a redirect | `Route::capability('POST', '/api/authors', 'create_author')` |
| The JS client | no `createAuthor()` | yes, after `client` |
| AI agents | no tool: an agent cannot add an author | a `create_author` tool, with its description and schema |
| Rules, scopes, audit | in this controller only, no scope, nothing audited | `$rules` once, scope `books:write`, every change and refusal audited |

The classic form is fine for something only one page will ever do. For anything else, the exercise below is the
better design.

---

## Checkpoint

```bash
git add -A
git commit -m "Appendix B: a classic form"
git tag appendix-b
```
Reference: [`checkpoints/appendix-b`](https://github.com/caligrafy/caligrafy-quill-course/tree/main/checkpoints/appendix-b).

## Exercise

Turn the form into a capability: write `create_author` (scope `books:write`, the same rules), make the form's
`create()` call it, give it a REST route `POST /api/authors`, regenerate the clients, and check that an agent with the
"Claude Code" token can add an author. (This goes beyond the checkpoint. Do it after lesson 13; earlier, skip the SPA
client and expect fewer capabilities.)

<details><summary>Show the solution</summary>

```bash
docker compose exec dev-box php caligrafer.php service Bookshelf/CreateAuthor --scope books:write
```

`application/services/Bookshelf/CreateAuthor.php` (like the other Bookshelf services: no namespace):
```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// create_author: adds an author, with the same rules whoever calls it
#[Capability(
    name: 'create_author',
    description: 'Adds an author to the Bookshelf catalog and returns it with its id. Use it before create_book when list_authors does not have the author.',
    scope: 'books:write'
)]
class CreateAuthor extends Service
{
    public $rules = array('name' => 'required|max_len,150', 'bio' => 'max_len,2000');
    public $filters = array('name' => 'trim', 'bio' => 'trim');
    public $descriptions = array(
        'name' => 'The name of the author, for example N. K. Jemisin',
        'bio' => 'Optional: one or two sentences about the author'
    );

    public function handle(array $input, Context $context)
    {
        return (new Author('authors'))->create($input);
    }
}
```

In `AuthorController.php`, add `use Caligrafy\ServiceError;` below `use Caligrafy\Controller;` and replace `create()`:
```php
    // POST /authors: the form calls the create_author capability, like every other experience
    public function create()
    {
        $this->signedInOnly();
        try {
            $author = $this->call('create_author');   // the inputs are the posted fields name and bio
            redirect('/authors/' . $author['id']);
            exit;
        } catch (ServiceError $e) {
            http_response_code($e->status());
            $input = $this->request->parameters();
            $values = array('name' => $input['name'] ?? '', 'bio' => $input['bio'] ?? '');
            return $this->form($values, $e->fields ?: array($e->getMessage()));
        }
    }
```

In `web.php`, in the `// BOOKSHELF API` block:
```php
    Route::capability('POST', '/api/authors', 'create_author');
```

Then:
```bash
docker compose exec dev-box php caligrafer.php client public/js/capabilities.js
docker compose exec dev-box php caligrafer.php client public/bookshelf-spa/src/capabilities.js
docker compose exec dev-box php caligrafer.php capabilities
```
The client now has `createAuthor()` (11 capabilities), and `capabilities` lists `create_author  books:write  writes`.
The form works as before (an empty name: "The Name field is required", 422). `POST /api/authors` answers `401` to
a visitor, `422` with `"fields":{"name":"The Name field is required"}` for `{"name": ""}`, and `201` with the new
author for the "Claude Code" token. Reconnect Claude Code with that token if needed and ask "Add the author Ted
Chiang, then list the authors": it calls the `create_author` tool. `audit --limit 5` shows the calls, with the web
form as channel `web`, REST as `rest` and the agent as `mcp`. One capability, every experience.
</details>

---
Previous: [Appendix A - Routes and controllers by hand](A-Routes-and-Controllers) · Next: [Back to the course home](Home)
