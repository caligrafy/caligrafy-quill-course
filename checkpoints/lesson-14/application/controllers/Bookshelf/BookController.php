<?php

namespace Bookshelf;

use Caligrafy\Controller;
use Caligrafy\ServiceError;

// The Bookshelf pages: the catalog, and the page of one book rendered from the get_book and update_book capabilities
class BookController extends Controller
{
    // GET /, /books and /home: the catalog (public/js/books.js does the rest)
    public function catalog()
    {
        // install: offer to add it to the home screen; authorized: the header shows Sign out
        return view('Bookshelf/books', array('install' => true, 'authorized' => authorized()));
    }

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
            if ($e->kind === 'not_found') {
                return $this->notFound($e);
            }
            // show the page again: the form keeps what was typed, with the messages of the capability
            http_response_code($e->status());
            $found = $this->call('get_book');
            $errors = $e->fields ?: array($e->getMessage());   // "Sign in or send a personal access token"
            return $this->page($found, array_merge($found['book'], $this->request->parameters), $errors);
        }
    }

    private function page(array $found, array $form, array $errors = array())
    {
        return view('Bookshelf/book', array(
            'book' => $found['book'],
            'author' => $found['author'],
            'form' => $form,
            'authors' => $this->call('list_authors', array()),
            'errors' => $errors,
            'authorized' => authorized()
        ));
    }

    private function notFound(ServiceError $e)
    {
        http_response_code($e->status());
        return view('Bookshelf/notfound', array('message' => $e->getMessage(), 'authorized' => authorized()));
    }
}
