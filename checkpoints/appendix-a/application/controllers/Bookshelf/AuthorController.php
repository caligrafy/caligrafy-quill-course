<?php

namespace Bookshelf;

use Caligrafy\Controller;

// Under the hood, the classic way: the controller reads the tables itself and renders a view. No capability is
// involved, so only this page has these features: REST clients, the JS clients and AI agents do not see them
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
}
