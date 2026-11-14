<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

// books_feed: the first page of the catalog as an RSS feed: XML, which is neither a page, a form, JSON nor a CSV file
#[Capability(
    name: 'books_feed',
    description: 'Answers the first page of the catalog as an RSS feed, for feed readers.',
    scope: 'books:read',
    readOnly: true,
    public: true,
    webOnly: true
)]
class BooksFeed extends Service
{
    public function handle(array $input, Context $context)
    {
        $page = $this->capability('list_books', array('page' => 1), $context);
        $home = session('home');
        $items = '';
        foreach ($page['data'] as $book) {
            $items .= '<item><title>' . htmlspecialchars($book['title'], ENT_XML1) . '</title>'
                . '<link>' . $home . 'books/' . $book['id'] . '</link>'
                . '<description>' . htmlspecialchars($book['author']['name'] . ', ' . $book['published_year'], ENT_XML1) . '</description></item>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel><title>Bookshelf</title><link>' . $home . 'books</link>'
            . '<description>The books we love</description>' . $items . '</channel></rss>';
        return Web::text($xml)->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
