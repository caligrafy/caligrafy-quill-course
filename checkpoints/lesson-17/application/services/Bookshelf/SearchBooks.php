<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// search_books: the books whose title or summary contains some words. Anyone may read it
#[Capability(
    name: 'search_books',
    description: 'Finds the books of the catalog whose title or summary contains some words, for example "time travel" or "magic". Returns at most 20 books, sorted by title, each with its author (id, name, bio).',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class SearchBooks extends Service
{
    public $rules = array('query' => 'required|min_len,2|max_len,100');
    public $filters = array('query' => 'trim');
    public $descriptions = array('query' => 'The words to look for, for example "time travel"');

    public function handle(array $input, Context $context)
    {
        // LIKE on both columns, the words bound as they are: a % or _ in them is no wildcard
        return Book::with('author')
            ->search(['title', 'summary'], $input['query'])
            ->orderBy('title')
            ->limit(20)
            ->get();
    }
}
