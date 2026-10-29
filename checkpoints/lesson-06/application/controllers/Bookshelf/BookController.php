<?php

namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // GET /books (and GET /)
    public function index()
    {
        $this->associate('Book', 'books');
        $books = $this->all(array('order' => 'ORDER BY title'));
        return view('Bookshelf/books', array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return view('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return view('Bookshelf/book', array('book' => $book, 'author' => $book->author()));
    }
}
