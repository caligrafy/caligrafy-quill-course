<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// return_book: closes the open loan of a book
#[Capability(
    name: 'return_book',
    description: 'Records that a lent book came back, by the id of the book. Returns the closed loan with its book.',
    scope: 'loans:write'
)]
class ReturnBook extends Service
{
    public $rules = array('book_id' => 'required|integer');
    public $descriptions = array('book_id' => 'The id of the book that came back');

    public function handle(array $input, Context $context)
    {
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if (!$open) {
            throw ServiceError::notFound("Book #{$input['book_id']} is not lent");
        }
        Loan::where('id', $open->id)->update(array('returned_at' => date('Y-m-d H:i:s')));
        return Loan::with('book')->find($open->id);
    }
}
