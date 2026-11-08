<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_loans: the books that are out, who has them, and whether they are late
#[Capability(
    name: 'list_loans',
    description: 'Lists the books that are lent and not returned yet, the soonest due first: id, book_id, borrower, due_on, overdue (true when the due date is past) and the book (id, title, published_year...).',
    scope: 'loans:read',
    readOnly: true
)]
class ListLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        // two queries in all: the open loans, then the books of all of them (with); arrays(): rows we can add to
        $loans = Loan::with('book')->whereNull('returned_at')->orderBy('due_on')->arrays()->get();
        $today = date('Y-m-d');
        foreach ($loans as $i => $loan) {
            $loans[$i]['overdue'] = $loan['due_on'] < $today;
        }
        return $loans;
    }
}
