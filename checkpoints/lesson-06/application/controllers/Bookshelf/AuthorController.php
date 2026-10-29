<?php

namespace Bookshelf;

use Caligrafy\Controller;

class AuthorController extends Controller {

    // GET /authors/{id}
    public function read()
    {
        $this->associate('Author', 'authors');
        $author = $this->find();
        if (!$author) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no author with this number.'));
        }
        return view('Bookshelf/author', array('author' => $author, 'books' => $author->books()));
    }
}
