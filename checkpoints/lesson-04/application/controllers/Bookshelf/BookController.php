<?php

// The namespace groups our classes under the name Bookshelf.
// The folder (controllers/Bookshelf) matches it, so the class is easy to find.
namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // Temporary data: lesson 05 replaces it with the database
    private $books = array(
        1 => array('id' => 1, 'title' => 'A Wizard of Earthsea', 'published_year' => 1968),
        2 => array('id' => 2, 'title' => 'Kindred', 'published_year' => 1979),
        3 => array('id' => 3, 'title' => 'Invisible Cities', 'published_year' => 1972)
    );

    // GET /books
    public function index()
    {
        return api(array('books' => array_values($this->books)));
    }

    // GET /books/{id}
    public function read()
    {
        $id = (int) $this->request->fetch('id');
        if (!isset($this->books[$id])) {
            http_response_code(404);
            return api(array('error' => array('code' => 404, 'message' => 'Book not found')));
        }
        return api(array('book' => $this->books[$id]));
    }
}
