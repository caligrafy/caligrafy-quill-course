<?php

namespace Bookshelf;

use Book;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title. Read-only: it changes nothing
#[Capability(
    name: 'list_books',
    description: 'Lists every book of the Bookshelf catalog, sorted by title, with its id, title, year, summary and author id.',
    scope: 'books:read',
    readOnly: true
)]
class ListBooks extends Service
{
    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->all(array('order' => 'ORDER BY title'));
    }
}
