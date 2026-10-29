<?php

namespace Bookshelf;

use Caligrafy\Controller;

class BookController extends Controller {

    // GET /books (and GET /)
    public function index()
    {
        $this->associate('Book', 'books');
        $books = $this->all(array('order' => 'ORDER BY title'));
        return $this->page('Bookshelf/books', array('books' => $books));
    }

    // GET /books/{id}
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return $this->page('Bookshelf/book', array('book' => $book, 'author' => $book->author()));
    }

    // GET /books/new : the empty form
    public function createForm()
    {
        $this->requireLogin();
        return $this->showForm('Add a book', 'books', 'POST', array());
    }

    // POST /books : the form of createForm() is sent here
    public function create()
    {
        $this->requireLogin();
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return $this->showForm('Add a book', 'books', 'POST', $data, $errors);
        }
        $this->associate('Book', 'books');
        $book = $this->save(BookForm::columns($data));   // no {id} in this route: save() inserts a new row
        redirect('/books/' . $book->id);
        exit;
    }

    // GET /books/{id}/edit : the form filled with the book
    public function editForm()
    {
        $this->requireLogin();
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        return $this->showForm('Edit the book', 'books/' . $book->id, 'PUT', (array) $book);
    }

    // PUT /books/{id} : the form of editForm() is sent here
    public function update()
    {
        $this->requireLogin();
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            http_response_code(404);
            return $this->page('Bookshelf/notfound', array('message' => 'There is no book with this number.'));
        }
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return $this->showForm('Edit the book', 'books/' . $book->id, 'PUT', $data, $errors);
        }
        $this->associate('Book', 'books');
        $this->save(BookForm::columns($data));   // {id} is in this route: save() updates that row
        redirect('/books/' . $book->id);
        exit;
    }

    // DELETE /books/{id}
    public function remove()
    {
        $this->requireLogin();
        $this->associate('Book', 'books');
        if ($this->find()) {
            $this->delete();
        }
        redirect('/books');
        exit;
    }

    // Sends visitors who are not signed in to the login page
    private function requireLogin()
    {
        if (!authorized()) {
            redirect('/login');
            exit;
        }
    }

    // Shows a view; 'authorized' tells the layout and the views whether someone is signed in
    private function page($viewName, $viewData)
    {
        $viewData['authorized'] = authorized();
        return view($viewName, $viewData);
    }

    // Filters and validates the posted fields, and checks that the author exists
    private function checkBook()
    {
        list($data, $errors) = BookForm::check($this->validator, $this->request->parameters());
        if (!isset($errors['author_id'])) {
            $this->associate('Author', 'authors');
            if (!$this->find($data['author_id'])) {
                $errors['author_id'] = 'Choose an author from the list.';
            }
        }
        return array($data, $errors);
    }

    // Shows the book form; $values fills the fields, $errors holds one message per invalid field
    private function showForm($heading, $action, $method, $values, $errors = array())
    {
        $this->associate('Author', 'authors');
        $authors = $this->all(array('order' => 'ORDER BY name'));
        return $this->page('Bookshelf/bookform', array(
            'heading' => $heading,
            'action' => $action,
            'method' => $method,
            'values' => $values,
            'errors' => $errors,
            'authors' => $authors
        ));
    }
}
