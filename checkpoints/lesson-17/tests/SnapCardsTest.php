<?php

use Caligrafy\Testing\TestCase;

/**
 * Snap Cards (lesson 17): Ask (Form, Choice) and Show (Answer, List) cards come from what capabilities declare,
 * and only for what the person may use
 */
final class SnapCardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
    }

    private function admin() { return $this->user(1, 1); }
    private function reader() { return $this->user(2, 0); }

    private function catalog($as)
    {
        $cards = array();
        foreach ($this->call('snap_catalog', array(), $as) as $card) {
            $cards[$card['capability']] = $card;
        }
        return $cards;
    }

    public function testEveryCardNamesItsFamilyAndType(): void
    {
        foreach ($this->catalog($this->admin()) as $card) {
            $this->assertContains($card['family'], array('ask', 'show'));
            $this->assertContains($card['type'], $card['family'] === 'ask' ? array('form', 'choice') : array('answer', 'list'));
            $this->assertSame(1, $card['snap']);
            $this->assertStringStartsWith('/snap/c/', $card['url']);
        }
    }

    public function testAVisitorGetsOnlyWhatAVisitorMayUse(): void
    {
        $cards = $this->catalog('guest');
        $this->assertArrayHasKey('list_books', $cards);
        $this->assertArrayHasKey('register_user', $cards);
        foreach (array('create_book', 'delete_book', 'lend_book', 'list_users', 'list_loans') as $hidden) {
            $this->assertArrayNotHasKey($hidden, $cards, "$hidden is not for visitors");
        }
        foreach (array_keys($cards) as $name) {
            $this->assertDoesNotMatchRegularExpression('/^(snap_|notify_|queue_work)/', $name, 'plumbing is never a card');
        }
        $this->assertCapabilityFails('not_found', 'snap_card', array('capability' => 'create_book'), 'guest');
    }

    public function testAReaderDoesNotGetTheAdministratorsCards(): void
    {
        $cards = $this->catalog($this->reader());
        $this->assertArrayHasKey('create_book', $cards);
        $this->assertArrayNotHasKey('list_users', $cards);
        $this->assertArrayHasKey('list_users', $this->catalog($this->admin()));
    }

    public function testAFormComesFromTheRules(): void
    {
        $card = $this->call('snap_card', array('capability' => 'create_book'), $this->admin());
        $this->assertSame(array('ask', 'form'), array($card['family'], $card['type']));
        $this->assertSame('write', $card['risk']);
        $fields = array_column($card['fields'], null, 'name');
        $this->assertSame(array('required' => true, 'widget' => 'text', 'maxlength' => 200), array_intersect_key($fields['title'], array('required' => 1, 'widget' => 1, 'maxlength' => 1)));
        $this->assertSame('number', $fields['published_year']['widget']);
        $this->assertSame(1450, $fields['published_year']['min']);
        $this->assertSame((int) date('Y'), $fields['published_year']['max'], 'current_year becomes this year');
        $this->assertFalse($fields['summary']['required']);
        $this->assertSame('textarea', $fields['summary']['widget'], 'a long text is a text area');
        $this->assertSame('one or two sentences about the book', $fields['summary']['help'], 'the "Optional:" meant for agents is left out');
        // exists,authors.id: the authors, from list_authors
        $this->assertSame('choice', $fields['author_id']['widget']);
        $this->assertSame('Author', $fields['author_id']['label']);
        $this->assertSame('list_authors', $fields['author_id']['source']);
        $this->assertGreaterThanOrEqual(1, count($fields['author_id']['options']));
        $this->assertSame('', $fields['author_id']['help'], 'an id is for agents: a person picks the author by name');
    }

    public function testOnePickMissingIsAChoice(): void
    {
        $card = $this->call('snap_card', array('capability' => 'get_book'), $this->reader());
        $this->assertSame(array('ask', 'choice'), array($card['family'], $card['type']));
        $this->assertSame('id', $card['field']);
        $this->assertSame('Book', $card['label']);
        $titles = array_column($card['options'], 'label');
        $this->assertContains('A Wizard of Earthsea', $titles);
        $this->assertNotEmpty($card['options'][0]['meta'], 'the author helps tell books apart');
    }

    public function testAChangeStartsFromTheCurrentRecord(): void
    {
        $card = $this->call('snap_card', array('capability' => 'update_book', 'values' => array('id' => 1)), $this->admin());
        $fields = array_column($card['fields'], null, 'name');
        $this->assertSame('A Wizard of Earthsea', $fields['title']['value']);
        $this->assertSame(1968, $fields['published_year']['value']);
        $this->assertSame('/books/1', $card['full']);
    }

    public function testAListShowsShortColumnsAndOpensRows(): void
    {
        $card = $this->catalog($this->reader())['list_books'];
        $this->assertSame(array('show', 'list'), array($card['family'], $card['type']));
        $this->assertSame(array('Title', 'Author', 'Published year'), array_column($card['columns'], 'label'));
        $this->assertSame(array('capability' => 'get_book', 'input' => 'id'), $card['open']);
        $this->assertSame(count($card['rows']), count($card['keys']));
        $this->assertMatchesRegularExpression('/^\d+ books?$/', $card['glance']);
        $this->assertSame('/books', $card['full']);
    }

    public function testAnAnswerShowsTheRecordAndWhatItLeadsTo(): void
    {
        $card = $this->call('snap_open', array('capability' => 'get_book', 'in' => '{"id":1}'), $this->admin());
        $this->assertSame(array('show', 'answer', 'record'), array($card['family'], $card['type'], $card['variant']));
        $this->assertSame('A Wizard of Earthsea', $card['heading']);
        $this->assertContains('Author', array_column($card['fields'], 'label'));
        $actions = array_column($card['actions'], 'risk', 'capability');
        $this->assertSame('write', $actions['update_book']);
        $this->assertSame('destroy', $actions['delete_book']);
        $this->assertArrayHasKey('lend_book', $actions, 'the other capabilities of a book, with its id');
        $this->assertSame('/snap/c/get_book?in=' . rawurlencode('{"id":1}'), $card['url']);
        $this->assertSame('/books/1', $card['full']);
    }

    public function testACardAtAnAddressNeverChangesData(): void
    {
        $before = count($this->call('list_books', array('page' => 1), $this->admin())['data']);
        $card = $this->call('snap_open', array('capability' => 'create_book', 'in' => json_encode(array('title' => 'Snap', 'published_year' => 2020, 'author_id' => 1))), $this->admin());
        $this->assertSame(array('ask', 'form'), array($card['family'], $card['type']), 'a write at an address comes back as its card');
        $this->assertSame('Snap', array_column($card['fields'], 'value', 'name')['title'], 'prefilled');
        $this->assertSame($before, count($this->call('list_books', array('page' => 1), $this->admin())['data']));
        $read = $this->call('snap_open', array('capability' => 'get_book', 'in' => '{"id":1}'), $this->reader());
        $this->assertSame(array('show', 'answer'), array($read['family'], $read['type']), 'a read with its inputs runs');
    }

    public function testSendingACardAnswersWithTheNextCard(): void
    {
        $refused = $this->action('snap_run', array('capability' => 'create_book', 'inputs' => array('title' => 'Too late', 'published_year' => 3000, 'author_id' => 1)), $this->admin());
        $card = $refused->describe()['data'];
        $this->assertSame(422, $refused->describe()['status']);
        $this->assertSame(array('ask', 'form'), array($card['family'], $card['type']));
        $this->assertArrayHasKey('published_year', (array) $card['errors']);

        $read = $this->action('snap_run', array('capability' => 'get_book', 'inputs' => array('id' => 1)), $this->reader());
        $card = $read->describe()['data'];
        $this->assertSame(array('show', 'answer'), array($card['family'], $card['type']), 'a read runs at once');
        $this->assertSame('A Wizard of Earthsea', $card['heading']);

        // a refusal the capability decides when it runs ("not lent") comes after the Confirm, as its own card
        $confirm = $this->action('snap_run', array('capability' => 'return_book', 'inputs' => array('book_id' => 99999)), $this->admin())->describe()['data'];
        $this->assertSame('confirm', $confirm['type']);
        $missing = $this->action('snap_run', array('capability' => 'return_book', 'inputs' => array('book_id' => 99999), 'confirm' => $confirm['token']), $this->admin());
        $this->assertSame(404, $missing->describe()['status']);
        $this->assertSame('notice', $missing->describe()['data']['variant']);
    }

    public function testAReturnOffersOnlyTheBooksThatAreOut(): void
    {
        $this->call('lend_book', array('book_id' => 2, 'borrower' => 'Sam'), $this->admin());
        $card = $this->call('snap_card', array('capability' => 'return_book'), $this->admin());
        $this->assertSame('choice', $card['type']);
        $this->assertSame(array(2), array_map('intval', array_column($card['options'], 'value')), 'from list_loans (the hint), not every book');
    }
}
