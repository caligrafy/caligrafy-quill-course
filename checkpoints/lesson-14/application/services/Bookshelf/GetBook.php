<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// get_book: one book and its author, or "not found"
#[Capability(
    name: 'get_book',
    description: 'Returns one book of the catalog (id, title, year, summary) and its author (id, name, bio), by the id of the book.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class GetBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book');

    public function handle(array $input, Context $context)
    {
        $book = (new Book('books'))->find($input['id']);
        if (!$book) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        return array('book' => $book, 'author' => $book->author());
    }
}
