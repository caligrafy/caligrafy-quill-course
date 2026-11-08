<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title, 20 books per page, each with its author. Anyone may read it
#[Capability(
    name: 'list_books',
    description: 'Lists the books of the Bookshelf catalog, sorted by title, 20 per page, each with its author (id, name, bio). Give author_id to list only the books of one author, and page for the next pages. Returns data (the books), total, page, per_page and pages.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer', 'page' => 'integer');
    public $descriptions = array(
        'author_id' => 'Optional: only the books of this author',
        'page' => 'Optional: the page, 1 by default (20 books per page)'
    );

    public function handle(array $input, Context $context)
    {
        // two queries in all: the books of the page, then all their authors at once
        $query = Book::with('author')->orderBy('title');
        if (!empty($input['author_id'])) {
            $query->where('author_id', $input['author_id']);   // the value is bound, never pasted into the SQL
        }
        return $query->paginate(20, $input['page'] ?? 1);
    }
}
