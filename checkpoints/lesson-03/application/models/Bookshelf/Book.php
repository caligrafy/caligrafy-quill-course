<?php

use Caligrafy\Model;

// One Book = one row of the books table. Book::with('author') adds the row of authors whose id is author_id
class Book extends Model {

    // one public property per column of the table
    public $id;
    public $title;
    public $published_year;
    public $summary;
    public $author_id;
    public $created_at;
    public $modified_at;
}
