<?php

use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Snap\Conversation;
use Caligrafy\Snap\Planner;

/**
 * The task space (lesson 17): one Home card per capability, every Home card opens in the task space,
 * suggestions that anticipate what comes next (and run at once), Home's suggestions general, and lines that name what
 * the card is about (so the card carries no title or instruction of its own)
 */
final class SnapTaskSpaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
        Planner::$transport = null;
    }

    private function admin() { return $this->user(1, 1); }
    private function reader() { return $this->user(2, 0); }
    private function ask(array $input, $as) { return $this->action('snap_ask', $input, $as)->describe()['data']; }

    private function task($status, $finished = null)
    {
        return Database::table('caligrafy_snap_tasks')->insert(array('user_id' => 1, 'capability' => 'reading_report', 'inputs' => '{}', 'status' => $status,
            'started_at' => date('Y-m-d H:i:s'), 'finished_at' => $finished));
    }

    public function testHomeShowsOneCardPerCapabilityTheLatest(): void
    {
        $this->task('done', date('Y-m-d H:i:s'));
        $this->task('done', date('Y-m-d H:i:s'));
        $latest = $this->task('running');
        $cards = array();
        foreach ($this->call('snap_home', array(), $this->admin())['sections'] as $section) {
            foreach ($section['cards'] as $card) {
                $cards[] = $card;
            }
        }
        $reports = array_values(array_filter($cards, function ($c) { return $c['type'] === 'task' && $c['of'] === 'reading_report'; }));
        $this->assertCount(1, $reports, 'one Reading report, not one per run');
        $this->assertSame((int) $latest, $reports[0]['id'], 'the latest run is the card');
        $this->assertSame('task:' . $latest, $reports[0]['key']);
        $this->assertSame(count($cards), count(array_unique(array_column($cards, 'key'))), 'every card has its own key');
    }

    public function testEveryHomeCardOpensInTheTaskSpace(): void
    {
        Database::table('loans')->insert(array('book_id' => 2, 'borrower' => 'Sam', 'due_on' => '2026-10-15'));
        \Caligrafy\Snap\Cards::forget();
        $alert = $this->ask(array('open' => 'alert:list_loans'), $this->admin());
        $this->assertSame(array('Overdue books', 'alert', '1 book overdue'), array($alert['text'], $alert['cards'][0]['variant'], $alert['cards'][0]['say']));
        $texts = array_column($alert['suggestions'], 'text');
        $this->assertNotContains('Send reminders', $texts, 'its next steps are its buttons, never repeated as suggestions');
        $this->assertSame(array('Back to Home', 'What else can I do?'), array_slice($texts, -2), 'and never a dead end');
        $this->assertNotEmpty($alert['turn'], 'kept as a turn: back and forth and history work');

        $shortcut = $this->call('snap_shortcut_save', array('title' => 'My catalog', 'capability' => 'list_books', 'inputs' => array()), $this->admin());
        $opened = $this->ask(array('open' => 'shortcut:' . $shortcut['id']), $this->admin());
        $this->assertSame(array('My catalog', 'list', 'list_books'), array($opened['text'], $opened['cards'][0]['type'], $opened['cards'][0]['capability']),
            'a shortcut opens what it saved, in the task space, not inline on Home');
    }

    public function testSuggestionsAnticipateWhatComesNextAndRunAtOnce(): void
    {
        $list = $this->ask(array('text' => 'the catalog'), $this->admin());
        $this->assertSame(array('Add a book', 'Find a book', 'Back to Home', 'What else can I do?'), array_column($list['suggestions'], 'text'));
        $this->assertSame('create_book', $list['suggestions'][0]['run']['capability']);
        $form = $this->ask(array('text' => 'Add a book', 'run' => $list['suggestions'][0]['run'], 'conversation' => $list['conversation']), $this->admin());
        $this->assertSame(array('form', 'create_book', $list['conversation']), array($form['cards'][0]['type'], $form['cards'][0]['capability'], $form['conversation']));
        $this->assertSame('fast', $form['via'], 'no model');

        $done = array('type' => 'answer', 'capability' => 'create_book', 'done' => 'Done.', 'actions' => array());
        $this->assertSame(array('Add another book', 'See the catalog', 'Back to Home', 'What else can I do?'),
            array_column(Conversation::suggestions(array($done), new Context('web', $this->admin())), 'text'), 'after adding: another one, or the list');
        $lent = array('type' => 'answer', 'capability' => 'lend_book', 'done' => 'Done.', 'actions' => array());
        $this->assertSame('See books that are out', Conversation::suggestions(array($lent), new Context('web', $this->admin()))[0]['text'], 'the hint next');
        try {
            $this->action('snap_ask', array('text' => 'x', 'run' => array('capability' => 'snap_forget')), $this->admin());
            $this->fail('a suggestion never runs the assistant\'s own capabilities');
        } catch (\Caligrafy\ServiceError $e) {
            $this->assertSame('invalid', $e->kind);
        }
    }

    public function testAnEmptyAnswerSuggestsWaysOut(): void
    {
        $texts = function (array $card) { return array_column(Conversation::suggestions(array($card), new Context('web', $this->admin())), 'text'); };
        $run = function ($capability, array $inputs) { return $this->action('snap_run', array('capability' => $capability, 'inputs' => $inputs), $this->admin())->describe()['data']; };

        $search = $run('search_books', array('query' => 'zebra unicorn'));
        $this->assertSame('I found nothing for "zebra unicorn". Here is what you can do instead.', $search['say']);
        $this->assertSame(array('Search for "zebra"', 'Search for "unicorn"', 'Add "zebra unicorn"', 'See the catalog'), array_slice($texts($search), 0, 4), 'broader, add it, or everything');
        $add = array_column($search['next'], null, 'text')['Add "zebra unicorn"'];
        $this->assertSame(array('title' => 'zebra unicorn'), (array) $add['run']['input'], 'what was looked for, prefilled');

        $author = Database::table('authors')->insert(array('name' => 'Nobody Yet', 'bio' => ''));
        $filtered = $run('list_books', array('author_id' => $author));
        $this->assertContains('See the catalog', $texts($filtered), 'without the filter');
        $fits = array_column($filtered['next'], null, 'text')['Add a book'];
        $this->assertSame(array('author_id' => (string) $author), array_map('strval', (array) $fits['run']['input']), 'one that fits the filter');

        $out = $run('list_loans', array());
        $this->assertSame('Lend a book', $texts($out)[0], 'nothing out: what fills it');

        $gone = $run('get_book', array('id' => 99999));
        $this->assertStringEndsWith('Here is what you can do instead.', $gone['say']);
        $this->assertContains('See the catalog', $texts($gone));
        $this->assertContains('Find a book', $texts($gone));
    }

    public function testTheConversationKeepsItsContext(): void
    {
        $kindred = Database::table('books')->where('title', 'Kindred')->first()['id'];
        $first = $this->ask(array('text' => 'the catalog'), $this->admin());
        $focus = array('focus' => array('capability' => 'get_book', 'title' => 'Kindred', 'input' => array('id' => $kindred)),
            'screen' => array(array('capability' => 'get_book', 'type' => 'answer', 'title' => 'Open a book', 'about' => 'Kindred', 'input' => array('id' => $kindred))),
            'selections' => array('opened Kindred'));

        // a short follow-up on what the conversation is about: at once, without a model
        $delete = $this->ask(array('text' => 'delete it', 'conversation' => $first['conversation'], 'context' => $focus), $this->admin());
        $this->assertSame(array('confirm', 'delete_book', 'fast'), array($delete['cards'][0]['type'], $delete['cards'][0]['capability'], $delete['via']));
        $this->assertSame((string) $kindred, (string) ((array) $delete['cards'][0]['inputs'])['id'], 'the book on screen');

        // anything longer goes to the model, with the conversation's memory
        $sent = array();
        Planner::$transport = function (array $r) use (&$sent, $kindred) {
            $sent[] = $r;
            return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1', 'name' => 'show_cards',
                'input' => array('caption' => 'Lending it to Sam.', 'cards' => array(array('capability' => 'lend_book', 'values' => array('book_id' => $kindred, 'borrower' => 'Sam')))))));
        };
        $lend = $this->ask(array('text' => 'lend it to Sam', 'conversation' => $first['conversation'], 'context' => $focus), $this->admin());
        $asked = $sent[0]['messages'][0]['content'];
        $this->assertStringContainsString('They asked "the catalog"', $asked, 'the earlier turns');
        $this->assertStringContainsString('What they did since: opened Kindred', $asked, 'the selections');
        $this->assertStringContainsString('The conversation is about: Kindred (get_book id=' . $kindred . ')', $asked, 'what "it" is');
        $this->assertStringContainsString('They now ask: lend it to Sam', $asked);
        $this->assertSame('confirm', $lend['cards'][0]['type']);
        Planner::$transport = null;

        // what the page sends is checked: a capability this person may not use, or a made-up one, is dropped
        $memory = Conversation::memory(new Context('web', $this->user(2, 0)), '', array('focus' => array('capability' => 'delete_user', 'title' => 'x', 'input' => array('id' => 1)),
            'screen' => array(array('capability' => 'drop_tables', 'title' => 'x'))));
        $this->assertNull($memory['focus']);
        $this->assertSame('', $memory['text']);
    }

    public function testHomeSuggestionsAreGeneralNeverOneRequestAgain(): void
    {
        $card = json_encode(array(array('type' => 'confirm', 'capability' => 'create_book')));
        foreach (range(1, 3) as $i) {
            Database::table('caligrafy_snap_turns')->insert(array('user_id' => 2, 'conversation' => str_repeat((string) $i, 16), 'text' => 'add Kindred by Octavia Butler', 'cards' => $card, 'suggestions' => '[]', 'via' => 'model'));
        }
        $texts = array_column($this->call('snap_home', array(), $this->reader())['suggestions'], 'text');
        $this->assertContains('Add a book', $texts, 'what this person does often, said in general');
        $this->assertNotContains('Add Kindred by Octavia Butler', $texts);
        $this->assertNotContains('add Kindred by Octavia Butler', $texts);
    }

    public function testTheLineNamesWhatTheCardIsAbout(): void
    {
        $form = $this->call('snap_card', array('capability' => 'create_book'), $this->admin());
        $this->assertStringStartsWith('To add a book, I just need ', \Caligrafy\Snap\Say::on($form)['say']);
        $list = $this->ask(array('text' => 'the catalog'), $this->admin())['cards'][0];
        $this->assertMatchesRegularExpression('/^Here you go: the catalog, \d+ books\.$/', $list['say']);
    }
}
