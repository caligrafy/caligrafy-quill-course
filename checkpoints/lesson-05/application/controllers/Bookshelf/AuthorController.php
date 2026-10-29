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
            return api(array('error' => array('code' => 404, 'message' => 'Author not found')));
        }
        return api(array('author' => $author, 'books' => $author->books()));
    }
}
