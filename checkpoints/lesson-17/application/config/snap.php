<?php
// Snap Cards and the assistant (Route::snap()): hints for the Bookshelf's cards, keyed by capability name.
// Every card works without them; they give a card its name, the columns of a list, the words the assistant says,
// the numbers of Home with their actions, what Home invites to browse, and when an action applies.
// The full list of keys: the wiki page "Snap Cards and the Assistant".
return array(
    'about' => 'a library app: the books of a catalog, their authors, and the books lent to people',

    'cards' => array(
        'list_books' => array('title' => 'The catalog', 'columns' => array('title', 'author.name', 'published_year'), 'full' => '/books', 'empty' => 'No book yet.',
            'insight' => array('label' => 'New this month', 'icon' => 'badge-plus', 'since' => array('field' => 'created_at', 'days' => 30),
                'action' => array('capability' => 'list_books', 'text' => 'Browse the catalog')),
            'discover' => array('open' => 'get_book', 'key' => 'id', 'title' => 'title', 'meta' => array('author.name', 'published_year'),
                'new' => 'created_at', 'related' => 'author.id', 'opened' => array('get_book' => 'id', 'lend_book' => 'book_id'),
                'unavailable' => array('capability' => 'list_loans', 'field' => 'book_id'), 'available_label' => 'On the shelf', 'title_section' => 'On the shelf for you')),
        'search_books' => array('title' => 'Find a book', 'columns' => array('title', 'author.name', 'published_year')),
        'get_book' => array('title' => 'Open a book', 'full' => '/books/{id}'),
        'create_book' => array('title' => 'Add a book', 'done' => 'Done! {heading} is on the shelf.', 'submit' => 'Add the book', 'full' => '/books'),
        'update_book' => array('title' => 'Change a book', 'full' => '/books/{id}'),
        'delete_book' => array('title' => 'Delete a book'),
        'list_authors' => array('title' => 'Authors', 'columns' => array('name', 'bio')),
        'list_loans' => array('title' => 'Books that are out', 'columns' => array('book.title', 'borrower', 'due_on'), 'full' => '/loans',
            'insight' => array('label' => 'Out now', 'icon' => 'arrow-left-right', 'ring' => array('of' => 'list_books', 'label' => 'On the shelf', 'invert' => true),
                'action' => array('capability' => 'return_book', 'text' => 'Record a return')),
            'alert' => array('when' => 'overdue', 'tag' => 'Overdue', 'title' => 'Overdue books', 'message' => '{count} book{s} overdue', 'reason' => 'Their due dates have passed.',
                'label' => 'book.title', 'action' => 'remind_loans', 'yes' => 'Send reminders'),
            'open' => array('capability' => 'get_book', 'input' => 'id', 'from' => 'book_id'), 'empty' => 'Every book is on the shelf.',
            'empty_next' => array('lend_book')),   // nothing out: lending one is what would fill it
        'lend_book' => array('title' => 'Lend a book', 'done' => 'Done! {heading} is lent. Enjoy the read.', 'full' => '/loans', 'next' => array('list_loans'),
            'applies' => array('not_in' => array('capability' => 'list_loans', 'field' => 'book_id')), /* a book that is out cannot be lent */
            'undo' => array('capability' => 'return_book', 'map' => array('book_id' => 'book_id'), 'label' => 'Undo the loan')),
        'return_book' => array('title' => 'Record a return', 'done' => 'Welcome back, {heading}!', 'next' => array('list_loans'),
            'applies' => array('in' => array('capability' => 'list_loans', 'field' => 'book_id')), /* only a book that is out comes back */
            'choices' => array('book_id' => array('capability' => 'list_loans', 'value' => 'book_id', 'label' => 'book.title'))),
        'list_users' => array('title' => 'People', 'columns' => array('username', 'permissions')),
        'snap_routine_save' => array('title' => 'Set up a routine', 'submit' => 'Set it up', 'consequence' => 'You can stop it any time in What I\'ve noticed.', 'labels' => array('capability' => 'What', 'weekday' => 'Every', 'hour' => 'Around', 'level' => 'How'), 'hide' => array('inputs'),
            'values' => array('weekday' => array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'),
                'hour' => array_combine(range(0, 23), array_map(function ($h) { return $h === 0 ? 'midnight' : ($h < 12 ? "$h am" : ($h === 12 ? 'noon' : ($h - 12) . ' pm')); }, range(0, 23))),
                'level' => array('suggest' => 'Remind me', 'confirm' => 'Prepare it for me to confirm', 'auto' => 'Do it and tell me'))),
        'reading_report' => array('title' => 'Reading report', 'noun' => 'authors',
            'working' => array('Counting the books of each author…', 'Checking which ones are out…', 'Putting the report together…'), 'next' => array('list_authors', 'list_loans'), 'columns' => array('name', 'books', 'out_now'), 'labels' => array('out_now' => 'Out now')),
        'remind_loans' => array('title' => 'Remind about due books',
            'working' => array('Finding the books that are due…', 'Writing the reminders…', 'Sending them…'), 'submit' => 'Send the reminders'),
        'send_reminders' => array('title' => 'Send reminders (queued by the app)'),
    ),
);
