<?php

namespace Bookshelf;

use Caligrafy\Controller;

// Under the hood, the classic way: the controller reads and writes the tables itself and renders views. No capability
// is involved, so only these pages have these features: REST clients, the JS clients and AI agents do not see them
class AuthorController extends Controller
{
    // GET /authors/{id}: an author and their books
    public function show()
    {
        $this->associate('Author', 'authors');   // the model and the table the next calls work on
        $author = $this->find();                 // the row whose id is {id} of the route, or nothing
        if (!$author) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no such author.'));
        }
        $this->associate('Book', 'books');
        $books = $this->all(array('condition' => 'WHERE author_id = ?', 'bindings' => array($author->id), 'order' => 'ORDER BY title'));
        return view('Bookshelf/author', array('author' => $author, 'books' => $books));
    }

    // GET /v1/authors: JSON for programs, the classic api() output (Accept: application/json and a token with *)
    public function index()
    {
        $this->associate('Author', 'authors');
        return api(array('authors' => $this->all(array('order' => 'ORDER BY name'))));
    }

    // GET /authors/new: the empty form (people signed in only)
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
        return view('Bookshelf/authorform', array('values' => $values, 'errors' => $errors));
    }

    // sends visitors who are not signed in to the sign-in page
    private function signedInOnly()
    {
        if (!authorized()) {
            redirect('/login');
            exit;
        }
    }
}
