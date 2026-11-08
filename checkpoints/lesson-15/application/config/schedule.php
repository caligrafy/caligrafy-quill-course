<?php
// Scheduled jobs: capabilities on a timer, with no page open (php caligrafer.php schedule list)
return array(
    // every morning at 8, New York time: the administrators hear about the books due today and the late ones
    array('name' => 'remind-loans', 'capability' => 'remind_loans', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York'),
    // every minute: the background jobs of the queue (Queue::push), such as the reminders of the button
    array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55),
);
