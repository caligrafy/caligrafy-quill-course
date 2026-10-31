<?php

use Caligrafy\Model;

// One Book object = one row of the books table
class Book extends Model {

    // one public property per column of the table
    public $id;
    public $title;
    public $published_year;
    public $summary;
    public $author_id;
    public $created_at;
    public $modified_at;

    // A book has one author: the row of authors whose id is this book's author_id
    public function author()
    {
        return $this->hasOne('Author', 'authors');
    }
}
