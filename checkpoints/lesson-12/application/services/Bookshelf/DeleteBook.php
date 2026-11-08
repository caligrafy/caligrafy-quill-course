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
    level: 1   // people whose permissions are 1 or more (administrators), and tokens with books:write
)]
class DeleteBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book to delete');

    public function handle(array $input, Context $context)
    {
        // delete() returns how many rows it deleted: 0 when there is no such book
        if (!Book::where('id', $input['id'])->delete()) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return array('deleted' => (int) $input['id']);
    }
}
