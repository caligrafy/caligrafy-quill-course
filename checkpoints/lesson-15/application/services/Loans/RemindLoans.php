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
        $today = date('Y-m-d');
        $loans = Database::toArray(Database::execute('SELECT due_on FROM loans WHERE returned_at IS NULL AND due_on <= ?', array($today)));
        $late = count(array_filter($loans, function ($loan) use ($today) { return $loan['due_on'] < $today; }));
        $due = count($loans) - $late;
        if (!$loans) {
            return array('due' => 0, 'late' => 0, 'notified' => 0);
        }
        $message = array(
            'title' => count($loans) === 1 ? '1 book to bring back' : count($loans) . ' books to bring back',
            'body' => "$due due today, $late late. Open the loans to see who has them.",
            'url' => '/loans',
            'tag' => 'loans-due'
        );
        $notified = 0;
        foreach (Database::toArray(Database::execute('SELECT id FROM users WHERE permissions >= 1')) as $admin) {
            $sent = Notify::user($admin['id'], $message);
            if ($sent['push'] > 0 || $sent['email']) {
                $notified++;
            }
        }
        return array('due' => $due, 'late' => $late, 'notified' => $notified);
    }
}
