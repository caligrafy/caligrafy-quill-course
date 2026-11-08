<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

// surprise_me: a request that decides where to go: the page of a book chosen at random
#[Capability(
    name: 'surprise_me',
    description: 'Sends the visitor to the page of a book chosen at random.',
    scope: 'books:read',
    readOnly: true,
    public: true,
    webOnly: true   // only Route::action reaches it: its answer is a redirect, which means nothing to a program or an agent
)]
class SurpriseMe extends Service
{
    public function handle(array $input, Context $context)
    {
        // the catalog's own capability, called as the same person: its rules stay in one place
        $page = $this->capability('list_books', array(), $context);
        if (!$page['data']) {
            return Web::view('Bookshelf/notfound', array('message' => 'The shelf is empty.'), 404);
        }
        $book = $page['data'][array_rand($page['data'])];
        return Web::redirect('/books/' . $book['id']);
    }
}
