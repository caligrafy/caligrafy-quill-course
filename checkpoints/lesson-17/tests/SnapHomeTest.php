<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Snap\Planner;

/**
 * The assistant (lesson 17): Home and the task space (turns kept, history, rating, a different answer),
 * Needs you with alerts as cards, and the Ask: Form that tells what was said from what is needed and what is optional
 */
final class SnapHomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
        Planner::$transport = null;
    }

    protected function tearDown(): void
    {
        Planner::$transport = null;
        parent::tearDown();
    }

    private function admin() { return $this->user(1, 1); }
    private function reader() { return $this->user(2, 0); }

    private function ask(array $input, $as) { return $this->action('snap_ask', $input, $as)->describe()['data']; }

    public function testEachAnswerIsATurnOfAConversation(): void
    {
        $first = $this->ask(array('text' => 'the catalog'), $this->reader());
        $this->assertSame(16, strlen($first['conversation']));
        $second = $this->ask(array('text' => 'lend a book', 'conversation' => $first['conversation']), $this->reader());
        $this->assertSame($first['conversation'], $second['conversation'], 'the same conversation');
        $turns = $this->call('snap_conversation', array('conversation' => $first['conversation']), $this->reader());
        $this->assertSame(array('the catalog', 'lend a book'), array_column($turns, 'text'));
        $this->assertSame('list', $turns[0]['cards'][0]['type'], 'its cards are kept, to step back to');
        $this->assertSame('the catalog', $this->call('snap_history', array(), $this->reader())[0]['title']);
        $this->assertCapabilityFails('not_found', 'snap_conversation', array('conversation' => $first['conversation']), $this->admin(), 'someone else\'s');
        $this->call('snap_rate', array('turn' => $second['turn'], 'rating' => -1), $this->reader());
        $this->assertSame(-1, $this->call('snap_conversation', array('conversation' => $first['conversation']), $this->reader())[1]['rating']);
        $this->assertCapabilityFails('not_found', 'snap_rate', array('turn' => $second['turn'], 'rating' => 1), $this->admin());
    }

    public function testADifferentAnswerAsksTheModelAgainWithWhatWasShown(): void
    {
        $sent = array();
        Planner::$transport = function (array $r) use (&$sent) {
            $sent[] = $r;
            return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1', 'name' => 'show_cards',
                'input' => array('caption' => 'Here are the authors instead.', 'cards' => array(array('capability' => 'list_authors'))))));
        };
        $first = $this->ask(array('text' => 'the catalog'), $this->reader());
        $again = $this->ask(array('again' => $first['turn']), $this->reader());
        $this->assertSame(array('model', 'the catalog', $first['conversation']), array($again['via'], $again['text'], $again['conversation']));
        $this->assertStringContainsString('different answer than the last one', $sent[0]['messages'][0]['content']);
        $this->assertStringContainsString('The catalog (list)', $sent[0]['messages'][0]['content'], 'what was shown, so it is not shown again');
    }

    public function testSuggestionsFollowTheAnswer(): void
    {
        $form = $this->ask(array('text' => 'add a book'), $this->reader());
        $this->assertSame(array('Add summary', 'Add ISBN', 'Back to Home', 'What else can I do?'), array_column($form['suggestions'], 'text'), 'the optional details, offered in conversation');
        $this->assertSame(array('plus', 'plus', 'house', 'sparkles'), array_column($form['suggestions'], 'icon'));
        $book = $this->ask(array('text' => 'open a book'), $this->reader());
        $texts = array_column($book['suggestions'], 'text');
        $this->assertGreaterThanOrEqual(4, count($texts), 'never a dead end: starting points, Home, and the menu');
        $this->assertSame(array('Back to Home', 'What else can I do?'), array_slice($texts, -2));
    }

    public function testAFormTellsWhatWasSaidFromWhatIsNeededAndWhatIsOptional(): void
    {
        $card = $this->call('snap_card', array('capability' => 'create_book', 'values' => array('title' => 'Dune', 'author_id' => 1)), $this->admin());
        $states = array_column($card['fields'], 'state', 'name');
        $this->assertSame(array('title' => 'said', 'published_year' => 'needed', 'summary' => 'optional', 'isbn' => 'optional', 'author_id' => 'said'), $states);
        $this->assertSame(array('published_year'), $card['needed']);
        $this->assertSame(array('summary', 'isbn'), $card['optional']);
        $shown = array_column($card['fields'], 'shown', 'name');
        $this->assertSame('Dune', $shown['title']);
        $this->assertStringStartsWith((string) Database::table('authors')->where('id', 1)->value('name'), $shown['author_id'], 'a choice by its label');
        $change = $this->call('snap_card', array('capability' => 'update_book', 'values' => array('id' => 3)), $this->admin());
        $this->assertSame('current', array_column($change['fields'], 'state', 'name')['title'], 'a record\'s values are its current state, not something said');
        $refused = $this->action('snap_run', array('capability' => 'create_book', 'inputs' => array('title' => 'Dune', 'published_year' => 3000, 'author_id' => 1)), $this->admin())->describe()['data'];
        $this->assertSame('needed', array_column($refused['fields'], 'state', 'name')['published_year'], 'a refused value is needed again');
    }

    public function testHomeShowsOnlyWhatHasSomethingAndAlertsAreCards(): void
    {
        $home = $this->call('snap_home', array(), $this->user(1, 1, array('username' => 'admin@example.com')));
        $this->assertMatchesRegularExpression('/^(Hello|Good (morning|afternoon|evening)), Admin$/', $home['greeting']);
        $this->assertSame('What else can I do?', end($home['suggestions'])['text']);
        $this->assertNotContains('needs', array_column($home['sections'], 'key'), 'nothing overdue: no Needs you');
        Database::table('loans')->insert(array('book_id' => 2, 'borrower' => 'Sam', 'due_on' => '2026-10-15'));
        \Caligrafy\Snap\Cards::forget();
        $home = $this->call('snap_home', array(), $this->admin());
        $needs = array_column($home['sections'], null, 'key')['needs']['cards'];
        $this->assertSame(array('offer', 'suggestion', 'alert'), array($needs[0]['family'], $needs[0]['type'], $needs[0]['variant']));
        $this->assertSame('1 book overdue', $needs[0]['question']);
        $this->assertSame(array('The Left Hand of Darkness'), $needs[0]['items']);
        $this->assertSame('remind_loans', $needs[0]['target']['capability'], 'the alert carries its next step');
        $this->assertStringStartsWith('One thing needs you: 1 book overdue.', $home['message']);
        $reader = array_column($this->call('snap_home', array(), $this->reader())['sections'], null, 'key')['needs']['cards'][0];
        $this->assertSame('list_loans', $reader['target']['capability'], 'a reader may not send reminders: their alert leads to the list');
        $this->assertSame('read', $reader['risk']);
        $this->assertCapabilityFails('unauthenticated', 'snap_home', array(), 'guest');   // Home is for signed-in people
    }
}
