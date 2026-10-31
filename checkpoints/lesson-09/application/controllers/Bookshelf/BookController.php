<?php

namespace Bookshelf;

use Caligrafy\Controller;
use Caligrafy\ServiceError;

// The page of one book: rendered on the server from the get_book and update_book capabilities
class BookController extends Controller
{
    // GET /books/{id}
    public function show()
    {
        try {
            $found = $this->call('get_book');   // the input is {id}, from the route
            return $this->page($found, $found['book']);
        } catch (ServiceError $e) {
            return $this->notFound($e);
        }
    }

    // PUT /books/{id}: the edit form of the page is sent here
    public function update()
    {
        try {
            $book = $this->call('update_book');   // the inputs are {id} and the fields of the form
            redirect('/books/' . $book['id']);
            exit;
        } catch (ServiceError $e) {
            if ($e->kind !== 'invalid') {
                return $this->notFound($e);
            }
            // show the page again: the form keeps what was typed, with a message per field
            http_response_code(422);
            $found = $this->call('get_book');
            return $this->page($found, array_merge($found['book'], $this->request->parameters), $e->fields);
        }
    }

    private function page(array $found, array $form, array $errors = array())
    {
        return view('Bookshelf/book', array(
            'book' => $found['book'],
            'author' => $found['author'],
            'form' => $form,
            'authors' => $this->call('list_authors', array()),
            'errors' => $errors
        ));
    }

    private function notFound(ServiceError $e)
    {
        http_response_code($e->status());
        return view('Bookshelf/notfound', array('message' => $e->getMessage()));
    }
}
