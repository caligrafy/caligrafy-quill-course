<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_books: the catalog, sorted by title, with the name of each author. Anyone may read it
#[Capability(
    name: 'list_books',
    description: 'Lists the books of the Bookshelf catalog, sorted by title: id, title, year, author id and author name. Give author_id to list only the books of one author.',
    scope: 'books:read',
    readOnly: true,
    public: true
)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer');
    public $descriptions = array('author_id' => 'Optional: only the books of this author');

    public function handle(array $input, Context $context)
    {
        // the name of every author, by id
        $names = array();
        foreach ((new Author('authors'))->all() as $author) {
            $names[$author->id] = $author->name;
        }

        $query = array('order' => 'ORDER BY title');
        if (!empty($input['author_id'])) {
            $query['condition'] = 'WHERE author_id = ?';   // ? is replaced by the value of bindings, safely
            $query['bindings'] = array($input['author_id']);
        }

        $books = array();
        foreach ((new Book('books'))->all($query) as $book) {
            $books[] = array(
                'id' => $book->id,
                'title' => $book->title,
                'published_year' => $book->published_year,
                'author_id' => $book->author_id,
                'author' => $names[$book->author_id] ?? ''
            );
        }
        return $books;
    }
}
