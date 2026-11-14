<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;

/**
 * Snap Cards (lesson 17): nothing that changes data runs from a card without the person's Ask: Confirm, and the token
 * of that card works once, for that person, that capability and those inputs only
 */
final class SnapConsentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
    }

    private function admin() { return $this->user(1, 1); }
    private function reader() { return $this->user(2, 0); }

    private function send($capability, array $inputs, $as, $confirm = null)
    {
        $input = array('capability' => $capability, 'inputs' => $inputs);
        if ($confirm !== null) {
            $input['confirm'] = $confirm;
        }
        $answer = $this->action('snap_run', $input, $as)->describe();
        return array($answer['data'], $answer['status']);
    }

    private function books() { return (int) Database::table('books')->count(); }

    public function testAChangeComesBackAsAConfirmAndRunsNothing(): void
    {
        $before = $this->books();
        [$card, $status] = $this->send('create_book', array('title' => 'Snap', 'published_year' => 2001, 'author_id' => 3), $this->admin());
        $this->assertSame(200, $status);
        $this->assertSame(array('ask', 'confirm', 'write'), array($card['family'], $card['type'], $card['risk']));
        $this->assertSame(32, strlen($card['token']));
        $changes = array_column($card['changes'], 'after', 'label');
        $this->assertSame(Database::table('authors')->where('id', 3)->value('name'), $changes['Author'], 'a choice is shown by its label, not its id');
        $this->assertSame('You can undo it right after.', $card['consequence']);
        $this->assertStringNotContainsString($card['token'], $card['url'], 'the token never goes in an address');
        $this->assertSame($before, $this->books(), 'nothing ran');
    }

    public function testTheTokenRunsTheChangeOnceAndOffersUndo(): void
    {
        $inputs = array('title' => 'Snap Once', 'published_year' => 2001, 'author_id' => 3);
        [$confirm] = $this->send('create_book', $inputs, $this->admin());
        [$done, $status] = $this->send('create_book', $inputs, $this->admin(), $confirm['token']);
        $this->assertSame(200, $status);
        $this->assertSame(array('show', 'answer'), array($done['family'], $done['type']));
        $this->assertSame('Done.', $done['done']);
        $this->assertSame('delete_book', $done['undo']['capability'], 'what was created is undone by deleting it');
        $this->assertSame('destroy', $done['undo']['risk']);
        $count = $this->books();
        [$again, $status] = $this->send('create_book', $inputs, $this->admin(), $confirm['token']);
        $this->assertSame(409, $status, 'a token works once');
        $this->assertSame('confirm', $again['type']);
        $this->assertStringContainsString('confirm again', $again['message']);
        $this->assertSame($count, $this->books());
        $this->assertSame(1, (int) Database::table('caligrafy_audit')->where('capability', 'snap_confirm')->where('detail', 'like', '%Snap Once%')->count(), 'the consent is in the audit log');
    }

    public function testATokenIsBoundToItsInputsAndItsPerson(): void
    {
        $inputs = array('title' => 'Bound', 'published_year' => 2001, 'author_id' => 3);
        [$confirm] = $this->send('create_book', $inputs, $this->admin());
        $before = $this->books();
        [, $status] = $this->send('create_book', array('title' => 'Swapped') + $inputs, $this->admin(), $confirm['token']);
        $this->assertSame(409, $status, 'other inputs: refused');
        [$confirm] = $this->send('create_book', $inputs, $this->admin());
        [, $status] = $this->send('create_book', $inputs, $this->reader(), $confirm['token']);
        $this->assertSame(409, $status, 'someone else: refused');
        [, $status] = $this->send('create_book', $inputs, $this->admin(), str_repeat('a', 32));
        $this->assertSame(409, $status, 'a made-up token: refused');
        $this->assertSame($before, $this->books());
    }

    public function testAChangeShowsBeforeAndAfterAndItsUndoRestores(): void
    {
        [$confirm] = $this->send('update_book', array('id' => 3, 'published_year' => 1980), $this->admin());
        $this->assertSame('Kindred · Octavia E. Butler', $confirm['subject']);
        $this->assertSame(array(array('label' => 'Published year', 'icon' => 'calendar', 'before' => '1979', 'after' => '1980')), $confirm['changes'], 'only what changes');
        [$done] = $this->send('update_book', array('id' => 3, 'published_year' => 1980), $this->admin(), $confirm['token']);
        $this->assertSame(1980, (int) Database::table('books')->where('id', 3)->value('published_year'));
        $this->assertSame(array('id' => 3, 'published_year' => 1979), $done['undo']['input']);
        [$undoConfirm] = $this->send('update_book', $done['undo']['input'], $this->admin());
        $this->assertSame('confirm', $undoConfirm['type'], 'undoing is a change too');
        $this->send('update_book', $done['undo']['input'], $this->admin(), $undoConfirm['token']);
        $this->assertSame(1979, (int) Database::table('books')->where('id', 3)->value('published_year'));
    }

    public function testADeleteNeedsItsOwnConfirmAndCannotBeUndone(): void
    {
        [$choice] = $this->send('delete_book', array(), $this->admin());
        $this->assertSame(array('ask', 'choice'), array($choice['family'], $choice['type']), 'which book first');
        [$confirm] = $this->send('delete_book', array('id' => 5), $this->admin());
        $this->assertSame(array('confirm', 'destroy'), array($confirm['type'], $confirm['risk']));
        $this->assertSame('Invisible Cities · Italo Calvino', $confirm['subject']);
        $this->assertSame('This cannot be undone.', $confirm['consequence']);
        $this->assertSame('Delete "Invisible Cities"', $confirm['submit']);
        $this->assertSame(1, (int) Database::table('books')->where('id', 5)->count(), 'still there');
        [$done] = $this->send('delete_book', array('id' => 5), $this->admin(), $confirm['token']);
        $this->assertSame(0, (int) Database::table('books')->where('id', 5)->count());
        $this->assertNull($done['undo']);
    }

    public function testRefusedOrMissingInputsAreAskedBeforeAnyConfirm(): void
    {
        [$card, $status] = $this->send('create_book', array('title' => 'No year', 'author_id' => 3), $this->admin());
        $this->assertSame(200, $status);
        $this->assertSame('form', $card['type'], 'a required input missing: its form, no error yet');
        [$card, $status] = $this->send('create_book', array('title' => 'Bad year', 'published_year' => 3000, 'author_id' => 3), $this->admin());
        $this->assertSame(422, $status, 'the rules answer before anyone is asked to confirm');
        $this->assertArrayHasKey('published_year', (array) $card['errors']);
        $this->assertArrayNotHasKey('token', $card);
        [$card] = $this->send('update_book', array('id' => 3), $this->admin());
        $this->assertSame('form', $card['type'], 'a change with only its id starts as its form');
        [$card] = $this->send('lend_book', array('book_id' => 2), $this->admin());
        $this->assertSame('form', $card['type']);
        $this->assertSame(array(), (array) $card['errors'], 'asked, not refused');
    }

    public function testAHintedUndoReturnsALoan(): void
    {
        [$confirm] = $this->send('lend_book', array('book_id' => 2, 'borrower' => 'Sam'), $this->admin());
        [$done] = $this->send('lend_book', array('book_id' => 2, 'borrower' => 'Sam'), $this->admin(), $confirm['token']);
        $this->assertSame(array('capability' => 'return_book', 'input' => array('book_id' => 2), 'label' => 'Undo the loan', 'risk' => 'write'), $done['undo']);
    }
}
