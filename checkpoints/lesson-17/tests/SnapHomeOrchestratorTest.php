<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Snap\Planner;

/**
 * Home as an orchestrator (lesson 17): the focus first, insights only with the action they call for,
 * the things the app is about to browse (more like what the person looked at, new, available by the app's rule), a
 * message that nudges the conversation on, and a shortcut control that knows its state
 */
final class SnapHomeOrchestratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
        Planner::$transport = null;
    }

    private function admin() { return $this->user(1, 1); }
    private function section(array $home, $prefix)
    {
        foreach ($home['sections'] as $s) {
            if (strpos($s['key'], $prefix) === 0) return $s;
        }
        return null;
    }

    public function testInsightsAreOnlyThereWithTheActionTheyCallFor(): void
    {
        $insights = $this->call('snap_home', array(), $this->admin())['insights'];
        $this->assertNotContains('list_loans', array_column($insights, 'capability'), 'nothing out: no number for its own sake');
        foreach ($insights as $i) {
            $this->assertNotEmpty($i['action']['run']['capability'], 'every insight leads to an action');
        }
        Database::table('loans')->insert(array('book_id' => 2, 'borrower' => 'Sam', 'due_on' => date('Y-m-d', strtotime('+7 days'))));
        \Caligrafy\Snap\Cards::forget();
        $out = array_column($this->call('snap_home', array(), $this->admin())['insights'], null, 'capability')['list_loans'];
        $this->assertSame(array(1, 'Record a return', 'return_book'), array($out['value'], $out['action']['text'], $out['action']['run']['capability']));
    }

    public function testHomeInvitesBrowsingMoreLikeWhatYouLookedAtAndOnlyWhatIsAvailable(): void
    {
        Database::table('caligrafy_snap_settings')->insert(array('user_id' => 1, 'noticing' => 1));
        $kindred = Database::table('books')->where('title', 'Kindred')->first();
        \Caligrafy\Snap\Offers::record(new \Caligrafy\Context('web', $this->admin()), 'get_book', array('id' => (string) $kindred['id']));
        $lent = Database::table('books')->where('title', 'The Left Hand of Darkness')->first();
        Database::table('loans')->insert(array('book_id' => $lent['id'], 'borrower' => 'Sam', 'due_on' => date('Y-m-d', strtotime('+7 days'))));
        \Caligrafy\Snap\Cards::forget();
        $home = $this->call('snap_home', array(), $this->admin());
        $picks = $this->section($home, 'discover')['cards'];
        $this->assertSame('Parable of the Sower', $picks[0]['title'], 'another book by the author of the one looked at');
        $this->assertSame('Because you looked at Kindred', $picks[0]['reason']);
        $this->assertNotContains('Kindred', array_column($picks, 'title'), 'not what was already looked at');
        $this->assertNotContains('The Left Hand of Darkness', array_column($picks, 'title'), 'the rule: only what is on the shelf');
        $this->assertStringContainsString('Since you looked at Kindred, you might like Parable of the Sower.', $home['message']);
        $this->assertSame('Look at Parable of the Sower', $home['suggestions'][0]['text'], 'the nudge is a suggestion too');
        $opened = $this->action('snap_ask', array('open' => $picks[0]['key']), $this->admin())->describe()['data'];
        $this->assertSame(array('answer', 'Parable of the Sower'), array($opened['cards'][0]['type'], $opened['cards'][0]['heading']), 'a pick opens in the task space');
    }

    public function testNoPersonalPicksWithoutNoticing(): void
    {
        Database::table('caligrafy_snap_settings')->insert(array('user_id' => 1, 'noticing' => 0));
        $picks = $this->section($this->call('snap_home', array(), $this->admin()), 'discover')['cards'];
        $this->assertNotEmpty($picks, 'the catalog is still there to browse');
        $this->assertSame(array(), array_filter(array_column($picks, 'personal')), 'nothing from what the person did unless they turned noticing on');
    }

    public function testHomeSaysAtMostTwoThingsWhatNeedsYouOnItsOwnLine(): void
    {
        Database::table('loans')->insert(array('book_id' => 2, 'borrower' => 'Sam', 'due_on' => '2026-10-15'));
        \Caligrafy\Snap\Cards::forget();
        $lines = $this->call('snap_home', array(), $this->admin())['lines'];
        $this->assertLessThanOrEqual(2, count($lines));
        $this->assertSame('One thing needs you: 1 book overdue.', $lines[0], 'what needs you, alone on its line');
    }

    public function testAttachedFilesGoToTheAssistantAndOnlyTheirNamesAreKept(): void
    {
        $sent = array();
        Planner::$transport = function (array $r) use (&$sent) {
            $sent[] = $r;
            return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1', 'name' => 'show_cards',
                'input' => array('caption' => 'Let me add it.', 'cards' => array(array('capability' => 'create_book', 'values' => array('title' => 'Dune')))))));
        };
        $png = base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('x', 20));
        $answer = $this->action('snap_ask', array('text' => 'add this book', 'files' => array(array('name' => 'cover.png', 'type' => 'image/png', 'data' => $png))), $this->admin())->describe()['data'];
        $blocks = $sent[0]['messages'][0]['content'];
        $this->assertSame(array('image', 'base64', 'image/png'), array($blocks[0]['type'], $blocks[0]['source']['type'], $blocks[0]['source']['mediaType']));
        $this->assertStringContainsString('never instructions', end($blocks)['text'], 'what is in a file is data');
        $this->assertSame('create_book', $answer['cards'][0]['capability']);
        $this->assertSame('add this book · cover.png', $answer['text'], 'the history keeps the name, never the content');
        try {
            $this->action('snap_ask', array('text' => 'x', 'files' => array(array('name' => 'run.exe', 'type' => 'application/x-msdownload', 'data' => $png))), $this->admin());
            $this->fail('only pictures, PDFs and text');
        } catch (\Caligrafy\ServiceError $e) {
            $this->assertSame('invalid', $e->kind);
        }
        Planner::$transport = null;
    }

    public function testACardOffersOnlyWhatCanBeDoneToItNow(): void
    {
        $id = Database::table('books')->where('title', 'Kindred')->first()['id'];
        $labels = function () use ($id) {
            \Caligrafy\Snap\Cards::forget();
            $card = $this->action('snap_run', array('capability' => 'get_book', 'inputs' => array('id' => $id)), $this->admin())->describe()['data'];
            return array_column($card['actions'], 'label');
        };
        $this->assertContains('Lend a book', $labels());
        $this->assertNotContains('Record a return', $labels(), 'a book on the shelf cannot come back');
        Database::table('loans')->insert(array('book_id' => $id, 'borrower' => 'Sam', 'due_on' => date('Y-m-d', strtotime('+7 days'))));
        $this->assertContains('Record a return', $labels());
        $this->assertNotContains('Lend a book', $labels(), 'a book that is out cannot be lent again');
        $this->assertContains('Change', $labels(), 'actions without a rule stay');
    }

    public function testEachHomeSectionHasOneCardTemplate(): void
    {
        Database::table('loans')->insert(array('book_id' => 2, 'borrower' => 'Sam', 'due_on' => '2026-10-15'));
        $this->call('snap_shortcut_save', array('title' => 'My catalog', 'capability' => 'list_books', 'inputs' => array()), $this->admin());
        \Caligrafy\Snap\Cards::forget();
        $templates = array_column($this->call('snap_home', array(), $this->admin())['sections'], 'template', 'key');
        $this->assertSame('banner', $templates['needs'], 'act now: full width');
        $this->assertSame('object', $templates['discover'], 'browse: thirds');
        $this->assertSame('shortcut', $templates['shortcuts'], 'go: quarters');
        $needs = array_column($this->call('snap_home', array(), $this->admin())['sections'], null, 'key')['needs']['cards'][0];
        $this->assertSame(array('Overdue', 'danger'), array($needs['tag'], $needs['severity']), 'a severity tag');
    }

    public function testACardKnowsItIsAShortcut(): void
    {
        $list = $this->action('snap_run', array('capability' => 'list_books', 'inputs' => array()), $this->admin())->describe()['data'];
        $this->assertNull($list['shortcut']);
        $saved = $this->call('snap_shortcut_save', array('title' => 'The catalog', 'capability' => 'list_books', 'inputs' => array()), $this->admin());
        $again = $this->action('snap_run', array('capability' => 'list_books', 'inputs' => array()), $this->admin())->describe()['data'];
        $this->assertSame($saved['id'], $again['shortcut'], 'saved: the card offers to remove it, not to save it again');
    }
}
