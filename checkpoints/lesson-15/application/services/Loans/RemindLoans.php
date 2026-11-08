<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Notify;
use Caligrafy\Service;

// remind_loans: tells the administrators which books are due today or late; a scheduled job runs it every morning
#[Capability(
    name: 'remind_loans',
    description: 'Notifies every administrator of the loans due today or late, by push and email. Returns how many loans are due today, how many are late, and how many administrators got the message.',
    scope: 'loans:write',
    level: 1
)]
class RemindLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        // nobody is signed in when a job runs: the capability finds the people it works for itself
        $today = date('Y-m-d');
        $due = Loan::whereNull('returned_at')->where('due_on', $today)->count();
        $late = Loan::whereNull('returned_at')->where('due_on', '<', $today)->count();
        if ($due + $late === 0) {
            return array('due' => 0, 'late' => 0, 'notified' => 0);
        }
        $books = $due + $late;
        $message = array(
            'title' => $books === 1 ? '1 book to bring back' : "$books books to bring back",
            'body' => "$due due today, $late late. Open the loans to see who has them.",
            'url' => '/loans',
            'tag' => 'loans-due'
        );
        $notified = 0;
        foreach (Database::table('users')->where('permissions', '>=', 1)->pluck('id') as $admin) {
            $sent = Notify::user($admin, $message);   // every channel this person turned on: push, email
            if ($sent['push'] > 0 || $sent['email']) {
                $notified++;
            }
        }
        return array('due' => $due, 'late' => $late, 'notified' => $notified);
    }
}
