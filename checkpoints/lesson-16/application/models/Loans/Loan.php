<?php

use Caligrafy\Model;

// One Loan = one row of the loans table: a book lent to someone, returned or not.
// Loan::with('book') adds the row of books whose id is book_id
class Loan extends Model {

    // one public property per column of the table
    public $id;
    public $book_id;
    public $borrower;
    public $due_on;
    public $returned_at;
    public $created_at;
    public $modified_at;
}
