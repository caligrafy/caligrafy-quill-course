<?php

namespace Bookshelf;

use Caligrafy\Controller;

// The right use of a controller: HTTP that no declarative route gives (an RSS feed is XML, not a page, JSON or CSV).
// It holds no rule and reads no model: it calls a capability, with the visitor's rights, and only shapes the answer
class FeedController extends Controller
{
    // GET /feed: the first page of the catalog, for feed readers
    public function books()
    {
        $page = $this->call('list_books', array('page' => 1));   // the same capability as the pages, REST and agents
        $home = session('home');
        $items = '';
        foreach ($page['data'] as $book) {
            $items .= '<item><title>' . htmlspecialchars($book['title'], ENT_XML1) . '</title>'
                . '<link>' . $home . 'books/' . $book['id'] . '</link>'
                . '<description>' . htmlspecialchars($book['author']['name'] . ', ' . $book['published_year'], ENT_XML1) . '</description></item>';
        }
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel><title>Bookshelf</title><link>' . $home . 'books</link>'
            . '<description>The books we love</description>' . $items . '</channel></rss>';
    }
}
