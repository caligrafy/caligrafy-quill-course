<?php

use Caligrafy\Model;

// One Loan object = one row of the loans table: a book lent to someone, returned or not
class Loan extends Model
{
    public $id;
    public $book_id;
    public $borrower;
    public $due_on;
    public $returned_at;
    public $created_at;
    public $modified_at;
}
