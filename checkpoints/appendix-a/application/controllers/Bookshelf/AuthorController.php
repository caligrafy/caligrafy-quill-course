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
}
