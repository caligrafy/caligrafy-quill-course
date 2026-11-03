<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_authors: every author, sorted by name. Anyone may read it
#[Capability(
    name: 'list_authors',
    description: 'Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListAuthors extends Service
{
    public function handle(array $input, Context $context)
    {
        return (new Author('authors'))->all(array('order' => 'ORDER BY name'));
    }
}
