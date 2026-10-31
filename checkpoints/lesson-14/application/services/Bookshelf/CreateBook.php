<?php

namespace Bookshelf;

use Author;
use Book;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// create_book: the same rules as the web form and the API (BookForm), written once
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the catalog and returns it with its id. The author must already exist (see get_book for author ids).',
    scope: 'books:write'
)]
class CreateBook extends Service
{
    public $rules = BookForm::RULES;
    public $filters = BookForm::FILTERS;
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published',
        'summary' => 'A short summary (optional)',
        'author_id' => 'The id of its author'
    );

    public function handle(array $input, Context $context)
    {
        if (!(new Author('authors'))->find($input['author_id'])) {
            throw ServiceError::invalid(array('author_id' => 'There is no author with this id'));
        }
        return (new Book('books'))->create(BookForm::columns($input + array('summary' => '')));
    }
}
