<?php

namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // GET /books
    public function index()
    {
        // work with the Book model and the books table
        $this->associate('Book', 'books');
        $books = $this->all(array('order' => 'ORDER BY title'));
        return api(array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();   // uses {id} from the URL
        if (!$book) {
            http_response_code(404);
            return api(array('error' => array('code' => 404, 'message' => 'Book not found')));
        }
        return api(array('book' => $book, 'author' => $book->author()));
    }
}
