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
            return $this->page('Bookshelf/notfound', array('message' => 'There is no author with this number.'));
        }
        return $this->page('Bookshelf/author', array('author' => $author, 'books' => $author->books()));
    }

    // Shows a view; 'authorized' tells the layout and the views whether someone is signed in
    private function page($viewName, $viewData)
    {
        $viewData['authorized'] = authorized();
        return view($viewName, $viewData);
    }
}
