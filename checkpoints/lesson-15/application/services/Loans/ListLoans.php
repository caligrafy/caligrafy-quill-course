<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// list_loans: the books that are out, with who has them and whether they are late
#[Capability(
    name: 'list_loans',
    description: 'Lists the books that are lent and not returned yet: book id and title, borrower, due date, and overdue (true when the due date is past).',
    scope: 'loans:read',
    readOnly: true
)]
class ListLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        // with('book'): the book of every loan in one more query, not one query per loan
        $loans = array();
        foreach (Loan::with('book')->whereNull('returned_at')->orderBy('due_on')->get() as $loan) {
            $loans[] = array(
                'book_id' => $loan->book_id,
                'title' => $loan->book['title'] ?? '',
                'borrower' => $loan->borrower,
                'due_on' => $loan->due_on,
                'overdue' => $loan->due_on < date('Y-m-d')
            );
        }
        return $loans;
    }
}
