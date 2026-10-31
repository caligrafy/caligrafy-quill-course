<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// create_book: adds a book, with the same rules whoever calls it
#[Capability(
    name: 'create_book',
    description: 'Adds a book to the Bookshelf catalog and returns it with its id. The author must already exist: use list_authors to find author_id.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
)]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,2100',
        'summary' => 'max_len,2000',
        'author_id' => 'required|integer'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim');
    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published, for example 1969',
        'summary' => 'Optional: one or two sentences about the book',
        'author_id' => 'The id of its author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        if (!(new Author('authors'))->find($input['author_id'])) {
            throw ServiceError::invalid(array('author_id' => 'There is no such author'));
        }
        return (new Book('books'))->create($input);
    }
}
