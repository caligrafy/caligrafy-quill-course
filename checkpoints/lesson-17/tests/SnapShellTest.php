<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Snap\Planner;

/**
 * Snap Cards (lesson 17): the shell. The fast path answers without a model; Go: Shortcut cards are a person's own;
 * the planner (Claude, faked here with scripted turns) may only look things up and propose cards, and a change it
 * proposes still arrives as an Ask: Confirm whose token it never sees.
 */
final class SnapShellTest extends TestCase
{
    private $requests = array();

    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
        $this->requests = array();
        Planner::$transport = null;
    }

    protected function tearDown(): void
    {
        Planner::$transport = null;
        parent::tearDown();
    }

    private function admin() { return $this->user(1, 1); }
    private function reader() { return $this->user(2, 0); }

    private function ask($text, $as)
    {
        return $this->action('snap_ask', array('text' => $text), $as)->describe()['data'];
    }

    // a scripted model: each turn is a list of blocks ['text' => ...] or ['tool' => name, 'input' => [...]]
    private function model(array $turns)
    {
        $turn = 0;
        Planner::$transport = function (array $request) use (&$turn, $turns) {
            $this->requests[] = $request;
            $blocks = array();
            foreach ($turns[min($turn, count($turns) - 1)] as $i => $b) {
                $blocks[] = isset($b['tool'])
                    ? (object) array('type' => 'tool_use', 'id' => "toolu_{$turn}_$i", 'name' => $b['tool'], 'input' => $b['input'] ?? array())
                    : (object) array('type' => 'text', 'text' => $b['text']);
            }
            $turn++;
            $tool = array_filter($blocks, function ($b) { return $b->type === 'tool_use'; });
            return (object) array('stopReason' => $tool ? 'tool_use' : 'end_turn', 'content' => $blocks);
        };
    }

    public function testTheMenuAndCardNamesNeedNoModel(): void
    {
        $menu = $this->ask('What can I do?', $this->reader());
        $this->assertSame('fast', $menu['via']);
        $this->assertSame(array('go', 'shortcut', 'menu'), array($menu['cards'][0]['family'], $menu['cards'][0]['type'], $menu['cards'][0]['variant']), 'the menu is a card');
        $byName = array_column($menu['cards'][0]['items'], null, 'capability');
        $this->assertSame('show', $byName['list_books']['family']);
        $this->assertSame('ask', $byName['create_book']['family']);
        $this->assertArrayNotHasKey('list_users', $byName, 'only what this person may use');

        $catalog = $this->ask('the catalog', $this->reader());
        $this->assertSame(array('fast', 'show', 'list'), array($catalog['via'], $catalog['cards'][0]['family'], $catalog['cards'][0]['type']));
        $lend = $this->ask('Lend a book', $this->reader());
        $this->assertSame('form', $lend['cards'][0]['type'], 'a card by its title, as its Ask card');
    }

    public function testWithoutAModelKeyTheShellSaysSoAndShowsTheMenu(): void
    {
        if (Planner::on()) {
            $this->markTestSkipped('ANTHROPIC_API_KEY is set');
        }
        $answer = $this->ask('who has kindred?', $this->reader());
        $this->assertSame('none', $answer['via']);
        $this->assertSame('menu', $answer['cards'][0]['variant']);
    }

    public function testShortcutsArePersonalAndRunThroughTheSameFlow(): void
    {
        $saved = $this->call('snap_shortcut_save', array('title' => 'Butler books', 'capability' => 'list_books', 'inputs' => array('author_id' => 3)), $this->reader());
        $this->assertSame(array('go', 'shortcut', 'read'), array($saved['family'], $saved['type'], $saved['risk']));
        $this->assertCount(1, $this->call('snap_shortcuts', array(), $this->reader()));
        $this->assertCount(0, $this->call('snap_shortcuts', array(), $this->admin()), 'someone else does not see it');
        $ran = $this->ask('butler books', $this->reader());
        $this->assertSame(array('fast', 'list'), array($ran['via'], $ran['cards'][0]['type']), 'its title runs it');
        $this->assertCapabilityFails('not_found', 'snap_shortcut_save', array('title' => 'People', 'capability' => 'list_users'), $this->reader());
        $this->assertCapabilityFails('not_found', 'snap_shortcut_delete', array('id' => $saved['id']), $this->admin());
        $this->call('snap_shortcut_delete', array('id' => $saved['id']), $this->reader());
        $this->assertCount(0, $this->call('snap_shortcuts', array(), $this->reader()));

        $change = $this->call('snap_shortcut_save', array('title' => 'Lend Kindred to Sam', 'capability' => 'lend_book', 'inputs' => array('book_id' => 3, 'borrower' => 'Sam')), $this->reader());
        $this->assertSame('write', $change['risk']);
        $loans = (int) Database::table('loans')->count();
        $ran = $this->ask('Lend Kindred to Sam', $this->reader());
        $this->assertSame('confirm', $ran['cards'][0]['type'], 'a shortcut that changes data still asks first');
        $this->assertSame($loans, (int) Database::table('loans')->count());
    }

    public function testThePlannerLooksUpThenProposesAChangeThatStillNeedsConfirming(): void
    {
        $this->model(array(
            array(array('tool' => 'search_books', 'input' => array('query' => 'Kindred'))),
            array(array('tool' => 'show_cards', 'input' => array('caption' => 'Ready to lend it.', 'cards' => array(array('capability' => 'lend_book', 'values' => array('book_id' => 3, 'borrower' => 'Sam', 'due_on' => '2026-11-28')))))),
        ));
        $loans = (int) Database::table('loans')->count();
        $answer = $this->ask('lend kindred to sam for two weeks', $this->admin());
        $this->assertSame('model', $answer['via']);
        $this->assertSame(2, $answer['steps']);
        $this->assertSame('Ready to lend it.', $answer['caption']);
        $this->assertSame(array('ask', 'confirm'), array($answer['cards'][0]['family'], $answer['cards'][0]['type']));
        $this->assertSame($loans, (int) Database::table('loans')->count(), 'nothing lent: the person confirms');
        $this->assertSame(array('book_id' => 3, 'borrower' => 'Sam'), (array) $answer['cards'][0]['inputs'], 'a value the model made up is dropped');
        // the lookup ran as this person and went back to the model as data
        $second = $this->requests[1]['messages'];
        $result = end($second)['content'][0];
        $this->assertSame('tool_result', $result['type']);
        $this->assertStringContainsString('Kindred', $result['content']);
        // the model is never given a token, and only read capabilities are its tools
        $this->assertStringNotContainsString($answer['cards'][0]['token'], json_encode($this->requests));
        $tools = array_column($this->requests[0]['tools'], 'name');
        $this->assertContains('search_books', $tools);
        $this->assertNotContains('lend_book', $tools);
        $this->assertNotContains('delete_book', $tools);
        $this->assertSame('show_cards', end($tools));
        $this->assertSame('claude-opus-5-5', $this->requests[0]['model']);
        $this->assertSame('default', $this->requests[0]['fallbacks']);
    }

    public function testAModelThatCallsAChangeAsAToolIsRefused(): void
    {
        $this->model(array(
            array(array('tool' => 'delete_book', 'input' => array('id' => 1))),
            array(array('tool' => 'show_cards', 'input' => array('caption' => 'Here.', 'cards' => array(array('capability' => 'delete_book', 'values' => array('id' => 1)))))),
        ));
        $books = (int) Database::table('books')->count();
        $answer = $this->ask('delete the first book', $this->admin());
        $refusal = end($this->requests[1]['messages'])['content'][0];
        $this->assertTrue($refusal['isError']);
        $this->assertSame('Not available.', $refusal['content']);
        $this->assertSame(array('confirm', 'destroy'), array($answer['cards'][0]['type'], $answer['cards'][0]['risk']));
        $this->assertSame($books, (int) Database::table('books')->count(), 'nothing deleted');
    }

    public function testAModelCannotReachWhatThePersonCannot(): void
    {
        $this->model(array(
            array(array('tool' => 'list_users', 'input' => array())),
            array(array('tool' => 'show_cards', 'input' => array('caption' => 'People.', 'cards' => array(array('capability' => 'list_users'), array('capability' => 'list_books'))))),
        ));
        $answer = $this->ask('who are the members?', $this->reader());
        $this->assertTrue(end($this->requests[1]['messages'])['content'][0]['isError'], 'a reader may not list users: neither may the model for them');
        $this->assertSame(array('list_books'), array_column($answer['cards'], 'capability'), 'the card a reader may not see is left out');
    }

    public function testARefusalOrAnAnswerWithoutCardsIsSaidPlainly(): void
    {
        Planner::$transport = function () { return (object) array('stopReason' => 'refusal', 'content' => array()); };
        $this->assertSame("I can't help with that here.", $this->ask('something refused', $this->reader())['caption']);
        $this->model(array(array(array('text' => 'I am not sure.'))));
        $answer = $this->ask('hmm', $this->reader());
        $this->assertSame(array('I am not sure.', array()), array($answer['caption'], $answer['cards']));
    }
}
