<?php

use Caligrafy\Model;

// One Author object = one row of the authors table
class Author extends Model {

    // one public property per column of the table
    public $id;
    public $name;
    public $bio;
    public $created_at;
    public $modified_at;

    // An author has many books: SELECT * FROM books WHERE author_id = <this author's id>
    public function books()
    {
        return $this->hasMany('Book', 'books');
    }
}
