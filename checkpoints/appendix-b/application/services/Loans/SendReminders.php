<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Queue;
use Caligrafy\Service;

// send_reminders: the "Send the reminders now" button; remind_loans runs a moment later, in the queue
#[Capability(
    name: 'send_reminders',
    description: 'Sends the reminders of the loans due today or late to the administrators now, in the background (remind_loans in the queue). Returns the number of the job.',
    scope: 'loans:write',
    level: 1
)]
class SendReminders extends Service
{
    public function handle(array $input, Context $context)
    {
        // the page answers at once; the queue runs remind_loans, with 3 tries if a delivery fails
        return array('job' => Queue::push('remind_loans'));
    }
}
