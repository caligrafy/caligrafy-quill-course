<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;

// reading_report: a read that takes a while: async, so Snap runs it in the background as a task (Track: Task card)
#[Capability(
    name: 'reading_report',
    description: 'Builds the reading report of the Bookshelf: for each author, how many books and how many are out. Takes a little while.',
    scope: 'books:read',
    readOnly: true,
    async: true
)]
class ReadingReport extends Service
{
    public $rules = array('pause' => 'integer|min_numeric,0|max_numeric,30');
    public $descriptions = array('pause' => 'Optional: seconds to take, to show a slow report (8 by default)');

    public function handle(array $input, Context $context)
    {
        sleep((int) ($input['pause'] ?? 8));   // the slow part of a real report
        $books = Database::table('books')->select('id', 'author_id')->get();
        $out = array_flip(array_map('intval', Database::table('loans')->whereNull('returned_at')->pluck('book_id')));
        $report = array();
        foreach (Database::table('authors')->select('id', 'name')->orderBy('name')->get() as $author) {
            $author = (array) $author;
            $own = array_filter($books, function ($b) use ($author) { return (int) ((array) $b)['author_id'] === (int) $author['id']; });
            $report[] = array('name' => $author['name'], 'books' => count($own),
                'out_now' => count(array_filter($own, function ($b) use ($out) { return isset($out[(int) ((array) $b)['id']]); })));
        }
        return $report;
    }
}
