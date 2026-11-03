<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// search_books: finds books by words of their title or summary. Anyone may read it
#[Capability(
    name: 'search_books',
    description: 'Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". Returns id, title, year and summary of each book found.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class SearchBooks extends Service
{
    public $rules = array('query' => 'required|min_len,2|max_len,100');
    public $filters = array('query' => 'trim');
    public $descriptions = array('query' => 'The words to look for');

    public function handle(array $input, Context $context)
    {
        // % matches any text: '%magic%' finds "magic" anywhere in the title or the summary
        return (new Book('books'))->search('title,summary', '%' . $input['query'] . '%');
    }
}
