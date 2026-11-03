<?php

namespace Bookshelf;

use Caligrafy\Controller;
use Caligrafy\ServiceError;

// The edit form of the book page (PUT /books/{id}). Showing a page is Route::page's job; saving a form, then
// redirecting or showing the page again with the messages, is logic of its own: a controller. It only calls capabilities.
class BookController extends Controller
{
    // PUT /books/{id}: the edit form of the page is sent here
    public function update()
    {
        try {
            $book = $this->call('update_book');   // the inputs are {id} and the fields of the form
            redirect('/books/' . $book['id']);
            exit;
        } catch (ServiceError $e) {
            if ($e->kind === 'not_found') {
                http_response_code($e->status());
                return view('Bookshelf/notfound', array('message' => $e->getMessage(), 'authorized' => authorized()));
            }
            // show the page again: the form keeps what was typed, with the messages of the capability
            http_response_code($e->status());
            $found = $this->call('get_book');
            return view('Bookshelf/book', array(
                'found' => $found,
                'authors' => $this->call('list_authors', array()),
                'form' => array_merge($found['book'], $this->request->parameters),
                'errors' => $e->fields ?: array($e->getMessage()),   // "Sign in or send a personal access token"
                'authorized' => authorized()
            ));
        }
    }
}
