<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// update_book: changes only the inputs that are given
#[Capability(
    name: 'update_book',
    description: 'Changes the title, year, summary, ISBN or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book with its author.',
    scope: 'books:write',
    public: true   // for now: lesson 11 closes it
)]
class UpdateBook extends Service
{
    public $rules = array(
        'id' => 'required|integer',
        'title' => 'max_len,200',
        'published_year' => 'integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'isbn' => 'alpha_numeric_dash|max_len,20|unique,books.isbn,id,id',   // unique, except on this book itself
        'author_id' => 'integer|exists,authors.id'
    );
    public $filters = array('title' => 'trim', 'summary' => 'trim', 'isbn' => 'trim');
    public $descriptions = array(
        'id' => 'The id of the book to change',
        'title' => 'A new title',
        'published_year' => 'A new year of publication',
        'summary' => 'A new summary',
        'isbn' => 'A new ISBN (empty removes it)',
        'author_id' => 'The id of a new author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        if (!Book::find($input['id'])) {
            throw ServiceError::notFound("There is no book #{$input['id']}");
        }
        // keep only the inputs that were given: a missing input never erases a column
        $changes = array();
        foreach (array('title', 'published_year', 'summary', 'isbn', 'author_id') as $field) {
            if (isset($input[$field])) {
                $changes[$field] = $input[$field];
            }
        }
        // a book always has a title, a year and an author: given empty, they are refused like a missing required input
        $errors = array();
        foreach (array('title' => 'Title', 'published_year' => 'Published Year', 'author_id' => 'Author Id') as $field => $label) {
            if (($changes[$field] ?? null) === '') {
                $errors[$field] = "The $label field is required";
            }
        }
        if ($errors) {
            throw ServiceError::invalid($errors);
        }
        if (($changes['isbn'] ?? null) === '') {
            $changes['isbn'] = null;
        }
        if ($changes) {
            Book::where('id', $input['id'])->update($changes);   // only this book: an update needs a where
        }
        return Book::with('author')->find($input['id']);
    }
}
