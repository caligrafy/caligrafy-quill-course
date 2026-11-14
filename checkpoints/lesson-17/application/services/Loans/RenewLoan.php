<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Flash;
use Caligrafy\Service;
use Caligrafy\Web;

// renew_loan: a link that changes data: 14 more days for a lent book. Opening it twice gives the same date
#[Capability(
    name: 'renew_loan',
    description: 'Gives a lent book 14 more days, from today, by the id of the book.',
    scope: 'loans:write',
    webOnly: true   // only Route::action may change data on a GET
)]
class RenewLoan extends Service
{
    public $rules = array('book_id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if (!$open) {
            return Web::view('Bookshelf/notfound', array('message' => "Book #{$input['book_id']} is not lent"), 404);
        }
        $due = date('Y-m-d', strtotime('+14 days'));
        Loan::where('id', $open->id)->update(array('due_on' => $due));
        Flash::message('success', "{$open->borrower} can keep it until $due");
        return Web::redirect('/loans');
    }
}
