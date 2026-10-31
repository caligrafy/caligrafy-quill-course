<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// update_book: changes only the inputs that are given
#[Capability(
    name: 'update_book',
    description: 'Changes the title, year, summary or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
)]
class UpdateBook extends Service
{
    public $rules = array(
        'id' => 'required|integer',
        'title' => 'max_len,200',
        'published_year' => 'integer|min_numeric,1450|max_numeric,2100',
        'summary' => 'max_len,2000',
        'author_id' => 'integer'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim');
    public $descriptions = array(
        'id' => 'The id of the book to change',
        'title' => 'A new title',
        'published_year' => 'A new year of publication',
        'summary' => 'A new summary',
        'author_id' => 'The id of a new author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        $books = new Book('books');
        if (!$books->find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        if (isset($input['title']) && $input['title'] === '') {
            throw ServiceError::invalid(array('title' => 'The Title field is required'));
        }
        if (isset($input['author_id']) && !(new Author('authors'))->find($input['author_id'])) {
            throw ServiceError::invalid(array('author_id' => 'There is no such author'));
        }

        // keep only the inputs that were given
        $changes = array();
        foreach (array('title', 'published_year', 'summary', 'author_id') as $field) {
            if (isset($input[$field])) {
                $changes[$field] = $input[$field];
            }
        }
        return $changes ? $books->update($changes, $input['id']) : $books->find($input['id']);
    }
}
