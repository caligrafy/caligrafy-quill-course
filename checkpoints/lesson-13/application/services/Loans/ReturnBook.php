<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// return_book: closes the open loan of a book
#[Capability(
    name: 'return_book',
    description: 'Records that a lent book came back, by the id of the book. Returns the closed loan.',
    scope: 'loans:write'
)]
class ReturnBook extends Service
{
    public $rules = array('book_id' => 'required|integer');
    public $descriptions = array('book_id' => 'The id of the book that came back');

    public function handle(array $input, Context $context)
    {
        $loans = new Loan('loans');
        $open = $loans->all(array('condition' => 'WHERE book_id = ? AND returned_at IS NULL', 'bindings' => array($input['book_id'])));
        if (!$open) {
            throw ServiceError::notFound("Book #{$input['book_id']} is not lent");
        }
        return $loans->update(array('returned_at' => date('Y-m-d H:i:s')), $open[0]->id);
    }
}
