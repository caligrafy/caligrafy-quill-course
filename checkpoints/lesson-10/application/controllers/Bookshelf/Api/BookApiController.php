<?php

// A sub-namespace for the API: the file lives in controllers/Bookshelf/Api
namespace Bookshelf\Api;

use Caligrafy\Controller;
use Bookshelf\BookForm;

class BookApiController extends Controller {

    // GET /api/books -> 200 {"books": [...]}
    public function index()
    {
        $this->associate('Book', 'books');
        return api(array('books' => $this->all(array('order' => 'ORDER BY title'))));
    }

    // GET /api/books/{id} -> 200 {"book": {...}, "author": {...}} or 404
    public function read()
    {
        $this->associate('Book', 'books');
        $book = $this->find();
        if (!$book) {
            return $this->error(404, 'Book not found');
        }
        return api(array('book' => $book, 'author' => $book->author()));
    }

    // GET /api/authors -> 200 {"authors": [...]}
    public function authors()
    {
        $this->associate('Author', 'authors');
        return api(array('authors' => $this->all(array('order' => 'ORDER BY name'))));
    }

    // POST /api/books -> 201 {"book": {...}}, 401 when not signed in, 422 {"errors": {...}} when invalid
    public function create()
    {
        if (!authorized()) {
            return $this->error(401, 'Sign in first');
        }
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return api(array('errors' => $errors));
        }
        $this->associate('Book', 'books');
        $book = $this->save(BookForm::columns($data));
        http_response_code(201);
        return api(array('book' => $book));
    }

    // PUT /api/books/{id} -> 200 {"book": {...}}, 401, 404 or 422
    public function update()
    {
        if (!authorized()) {
            return $this->error(401, 'Sign in first');
        }
        $this->associate('Book', 'books');
        if (!$this->find()) {
            return $this->error(404, 'Book not found');
        }
        list($data, $errors) = $this->checkBook();
        if ($errors) {
            http_response_code(422);
            return api(array('errors' => $errors));
        }
        $this->associate('Book', 'books');
        return api(array('book' => $this->save(BookForm::columns($data))));
    }

    // DELETE /api/books/{id} -> 200 {"deleted": true}, 401 or 404
    public function remove()
    {
        if (!authorized()) {
            return $this->error(401, 'Sign in first');
        }
        $this->associate('Book', 'books');
        if (!$this->find()) {
            return $this->error(404, 'Book not found');
        }
        $this->delete();
        return api(array('deleted' => true));
    }

    // Same shape as the errors of the framework: {"error": {"code": ..., "message": "..."}}
    private function error($status, $message)
    {
        http_response_code($status);
        return api(array('error' => array('code' => $status, 'message' => $message)));
    }

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
}
