<?php

use Caligrafy\Model;

// One Author = one row of the authors table (Author::query() reads authors: the class name in lower case, plus s)
class Author extends Model {

    // one public property per column of the table
    public $id;
    public $name;
    public $bio;
    public $created_at;
    public $modified_at;
}
