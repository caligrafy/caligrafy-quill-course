<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// lend_book: records that someone borrowed a book, for a number of days
#[Capability(
    name: 'lend_book',
    description: 'Lends a book of the catalog to someone for a number of days (14 by default) and returns the loan. Refused when the book is already lent.',
    scope: 'loans:write'
)]
class LendBook extends Service
{
    public $rules = array('book_id' => 'required|integer', 'borrower' => 'required|max_len,100', 'days' => 'integer|min_numeric,1|max_numeric,60');
    public $filters = array('borrower' => 'trim');
    public $descriptions = array(
        'book_id' => 'The id of the book (list_books, search_books)',
        'borrower' => 'The name of the person who borrows it',
        'days' => 'For how many days: 1 to 60, 14 by default'
    );

    public function handle(array $input, Context $context)
    {
        if (!Book::find($input['book_id'])) {
            throw ServiceError::notFound("There is no book #{$input['book_id']}");
        }
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if ($open) {
            throw ServiceError::conflict("This book is already lent to {$open->borrower}");
        }
        $days = (int) ($input['days'] ?? 14);
        $id = Loan::insert(array(
            'book_id' => $input['book_id'],
            'borrower' => $input['borrower'],
            'due_on' => date('Y-m-d', strtotime("+$days days"))
        ));
        return Loan::find($id);
    }
}
