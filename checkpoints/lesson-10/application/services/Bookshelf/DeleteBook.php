<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// delete_book: destructive, so AI agents ask before calling it
#[Capability(
    name: 'delete_book',
    description: 'Deletes a book of the catalog, by its id. This cannot be undone.',
    scope: 'books:write',
    destructive: true,
    public: true   // for now: lesson 11 closes it
)]
class DeleteBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book to delete');

    public function handle(array $input, Context $context)
    {
        $books = new Book('books');
        if (!$books->find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        $books->delete($input['id']);
        return array('deleted' => (int) $input['id']);
    }
}
