<?php
/**
 * ProjectController.php
 * One controller serves the Pug pages and the JSON API:
 * view() answers JSON automatically when the request sends "Accept: application/json".
 * Routed as 'Showcase\\ProjectController@action' (namespaced controller).
 */
namespace Showcase;

use Caligrafy\Controller;

class ProjectController extends Controller {

    private $filters = ['title' => 'trim|sanitize_string', 'description' => 'trim|sanitize_string'];
    private $rules = ['title' => 'required|max_len,150', 'description' => 'required|min_len,10'];

    private function context()
    {
        $this->associate('Project', 'projects');
    }

    // Browser writes need a logged-in user; API writes are authorized by the API key (Auth::activateAPI)
    private function canWrite()
    {
        return $this->request->wantsJson() || authorized();
    }

    private function input()
    {
        $data = array_merge(['title' => '', 'description' => ''], $this->request->parameters() ?? []);
        return $this->filter($data, $this->filters);
    }

    // GET /projects
    public function index()
    {
        $this->context();
        $projects = $this->all(['order' => 'ORDER BY created_at DESC']) ?: [];
        return view('Showcase/index', ['projects' => $projects, 'authorized' => authorized()]);
    }

    // GET /projects/{id}
    public function read()
    {
        $this->context();
        $project = $this->find();
        if (!$project) {
            http_response_code(404);
            return view('Showcase/show', ['project' => null, 'error' => 'Project not found', 'authorized' => authorized()]);
        }
        return view('Showcase/show', ['project' => $project, 'authorized' => authorized()]);
    }

    // GET /projects/new
    public function createForm()
    {
        if (!authorized()) {
            return redirect('/login');
        }
        return view('Showcase/form', ['project' => null, 'errors' => [], 'authorized' => true]);
    }

    // POST /projects
    public function create()
    {
        if (!$this->canWrite()) {
            return redirect('/login');
        }
        $this->context();
        $data = $this->input();
        $valid = $this->validate($data, $this->rules);
        if ($valid !== true) {
            http_response_code(422);
            return view('Showcase/form', ['project' => (object) $data, 'errors' => $valid, 'authorized' => authorized()]);
        }
        $project = new \Project();  // models are global classes: note the leading backslash inside the namespace
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

    // GET /projects/{id}/edit
    public function editForm()
    {
        if (!authorized()) {
            return redirect('/login');
        }
        $this->context();
        $project = $this->find();
        if (!$project) {
            return redirect('/projects');
        }
        return view('Showcase/form', ['project' => $project, 'errors' => [], 'authorized' => true]);
    }

    // PUT /projects/{id}
    public function update()
    {
        if (!$this->canWrite()) {
            return redirect('/login');
        }
        $this->context();
        $project = $this->find();
        if (!$project) {
            http_response_code(404);
            return api(['error' => 'Project not found']);
        }
        $data = $this->input();
        $data['title'] = $data['title'] !== '' ? $data['title'] : $project->title;
        $data['description'] = $data['description'] !== '' ? $data['description'] : $project->description;
        $valid = $this->validate($data, $this->rules);
        if ($valid !== true) {
            http_response_code(422);
            return view('Showcase/form', ['project' => $project, 'errors' => $valid, 'authorized' => authorized()]);
        }
        $project->title = $data['title'];
        $project->description = $data['description'];
        $saved = $this->save($project);

        if ($this->request->wantsJson()) {
            return api($saved);
        }
        return redirect('/projects/' . $project->id);
    }

    // DELETE /projects/{id}
    public function remove()
    {
        if (!$this->canWrite()) {
            return redirect('/login');
        }
        $this->context();
        if (!$this->find()) {
            http_response_code(404);
            return api(['error' => 'Project not found']);
        }
        $this->delete();
        if ($this->request->wantsJson()) {
            return api(['deleted' => true]);
        }
        return redirect('/projects');
    }
}
