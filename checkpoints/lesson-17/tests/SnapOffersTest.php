<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Snap\Offers;

/**
 * Snap Cards (lesson 17): Offer: Suggestion and Offer: Routine. Opt-in, explained, dismissible, capped, and a routine is
 * itself a change that asks for its Confirm; a delete is never done on a rhythm
 */
final class SnapOffersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Caligrafy\Snap\Cards::forget();
        Offers::$now = strtotime('2026-11-17 14:00:00');   // a Tuesday afternoon
    }

    protected function tearDown(): void
    {
        Offers::$now = null;
        parent::tearDown();
    }

    private function admin() { return $this->user(1, 1); }
    private function reader() { return $this->user(2, 0); }

    private function noticing($as, $on = true) { $this->call('snap_noticing_set', array('on' => $on), $as); }

    // the same request on several past days, at an hour
    private function did($userId, $capability, array $inputs, array $days)
    {
        foreach ($days as $day) {
            Database::table('caligrafy_snap_events')->insert(array('user_id' => $userId, 'capability' => $capability, 'inputs' => json_encode(array_map('strval', $inputs)),
                'fingerprint' => Offers::fingerprint($capability, $inputs), 'at' => $day));
        }
    }

    private function offers($as) { return $this->call('snap_offers', array(), $as); }

    public function testNothingIsNoticedUntilThePersonTurnsItOn(): void
    {
        $this->action('snap_run', array('capability' => 'list_loans', 'inputs' => array()), $this->admin());
        $this->assertSame(0, (int) Database::table('caligrafy_snap_events')->where('user_id', 1)->count());
        $this->noticing($this->admin());
        $this->action('snap_run', array('capability' => 'list_loans', 'inputs' => array()), $this->admin());
        $this->assertSame(1, (int) Database::table('caligrafy_snap_events')->where('user_id', 1)->where('capability', 'list_loans')->count());
        $this->noticing($this->admin(), false);
        $this->assertSame(0, (int) Database::table('caligrafy_snap_events')->where('user_id', 1)->count(), 'off forgets');
        $this->did(1, 'list_loans', array(), array('2026-10-26 09:05:00', '2026-11-02 09:20:00', '2026-11-09 08:50:00'));
        $this->assertSame(array(), $this->call('snap_noticed', array(), $this->admin())['patterns'], 'off shows no pattern, whatever the table holds');
        $this->assertSame(array(), $this->offers($this->admin()));
    }

    public function testAWeeklyPatternIsOfferedAsARoutineWithItsReason(): void
    {
        $this->noticing($this->admin());
        $this->did(1, 'list_loans', array(), array('2026-10-26 09:05:00', '2026-11-02 09:20:00', '2026-11-09 08:50:00', '2026-11-16 09:10:00'));
        $offers = $this->offers($this->admin());
        $this->assertCount(1, $offers);
        $this->assertSame(array('offer', 'routine'), array($offers[0]['family'], $offers[0]['type']));
        $this->assertSame('Do this for you every Monday around 9 am?', $offers[0]['question']);
        $this->assertSame('You did this 4 Mondays in a row, around 9 am.', $offers[0]['reason']);
        $this->assertSame(array('suggest', 'confirm', 'auto'), array_keys($offers[0]['levels']));
        $this->assertSame(1, $offers[0]['weekday']);
    }

    public function testAtTheUsualMomentItIsASuggestionUntilItIsDone(): void
    {
        $this->noticing($this->admin());
        $this->did(1, 'list_loans', array(), array('2026-10-26 09:05:00', '2026-11-02 09:20:00', '2026-11-09 08:50:00'));
        Offers::$now = strtotime('2026-11-16 09:30:00');   // a Monday morning
        $offers = $this->offers($this->admin());
        $this->assertSame(array('offer', 'suggestion'), array($offers[0]['family'], $offers[0]['type']));
        $this->assertSame('It is Monday morning: open books that are out?', $offers[0]['question']);
        $this->did(1, 'list_loans', array(), array('2026-11-16 09:31:00'));
        $this->assertNotSame('suggestion', $this->offers($this->admin())[0]['type'] ?? null, 'done today: no suggestion');
    }

    public function testWeakPatternsSayNothingAndOffersAreCapped(): void
    {
        $this->noticing($this->admin());
        $this->did(1, 'list_loans', array(), array('2026-11-09 09:00:00', '2026-11-16 09:00:00'));
        $this->assertSame(array(), $this->offers($this->admin()), 'two days is not a pattern');
        foreach (array(1, 2, 3) as $book) {
            $this->did(1, 'get_book', array('id' => $book), array('2026-10-27 10:00:00', '2026-11-03 10:00:00', '2026-11-10 10:00:00'));
        }
        $this->assertCount(2, $this->offers($this->admin()), 'at most two at a time');
    }

    public function testNotNowAndNeverAreKept(): void
    {
        $this->noticing($this->admin());
        $this->did(1, 'list_loans', array(), array('2026-10-26 09:05:00', '2026-11-02 09:20:00', '2026-11-09 08:50:00'));
        $fingerprint = $this->offers($this->admin())[0]['fingerprint'];
        $this->call('snap_dismiss', array('fingerprint' => $fingerprint, 'kind' => 'today'), $this->admin());
        $this->assertSame(array(), $this->offers($this->admin()));
        Offers::$now = strtotime('2026-11-18 14:00:00');
        $this->assertCount(1, $this->offers($this->admin()), 'Not now lasts until tomorrow');
        $this->call('snap_dismiss', array('fingerprint' => $fingerprint, 'kind' => 'never'), $this->admin());
        Offers::$now = strtotime('2026-12-18 14:00:00');
        $this->did(1, 'list_loans', array(), array('2026-11-23 09:00:00', '2026-11-30 09:00:00', '2026-12-07 09:00:00', '2026-12-14 09:00:00'));
        $this->assertSame(array(), $this->offers($this->admin()), 'never is never');
    }

    public function testSettingUpARoutineIsAChangeThatAsksFirst(): void
    {
        $this->noticing($this->admin());
        $this->did(1, 'list_loans', array(), array('2026-10-26 09:05:00', '2026-11-02 09:20:00', '2026-11-09 08:50:00'));
        $inputs = array('capability' => 'list_loans', 'inputs' => '{}', 'weekday' => 1, 'hour' => 9, 'level' => 'auto');
        $confirm = $this->action('snap_run', array('capability' => 'snap_routine_save', 'inputs' => $inputs), $this->admin())->describe()['data'];
        $this->assertSame('confirm', $confirm['type']);
        $this->assertContains(array('label' => 'What', 'icon' => 'zap', 'after' => 'Books that are out'), $confirm['changes'], 'by its title, not its name');
        $this->assertSame(0, (int) Database::table('caligrafy_snap_routines')->count(), 'nothing set up before the Confirm');
        $done = $this->action('snap_run', array('capability' => 'snap_routine_save', 'inputs' => $inputs, 'confirm' => $confirm['token']), $this->admin())->describe()['data'];
        $this->assertSame('Done.', $done['done']);
        $routine = (array) Database::table('caligrafy_snap_routines')->first();
        $this->assertSame(array('list_loans', 1, 9, 'auto', '2026-11-23 09:00:00'), array($routine['capability'], (int) $routine['weekday'], (int) $routine['hour'], $routine['level'], $routine['next_at']));
        $this->assertSame(1, (int) Database::table('caligrafy_audit')->where('capability', 'snap_routine')->count(), 'the consent to automate is audited');
        $this->assertSame(array(), $this->offers($this->admin()), 'a pattern with its routine is not offered again');
        $this->assertCapabilityFails('invalid', 'snap_routine_save', array('capability' => 'delete_book', 'inputs' => '{"id":1}', 'hour' => 9, 'level' => 'auto'), $this->admin());
    }

    public function testAnAutoRoutineRunsAsATaskOfItsOwnerWhenDue(): void
    {
        $this->call('snap_routine_save', array('capability' => 'reading_report', 'inputs' => '{"pause":0}', 'weekday' => 2, 'hour' => 13, 'level' => 'auto'), $this->reader());
        Database::table('caligrafy_snap_routines')->where('id', '>', 0)->update(array('next_at' => '2026-11-17 13:00:00'));
        $ran = Offers::runDue();
        $this->assertCount(1, $ran);
        $task = (array) Database::table('caligrafy_snap_tasks')->where('id', $ran[0]['task'])->first();
        $this->assertSame(array(2, 'reading_report'), array((int) $task['user_id'], $task['capability']));
        $this->assertSame('2026-11-24 13:00:00', Database::table('caligrafy_snap_routines')->value('next_at'), 'next week');
        $this->assertSame(array(), Offers::runDue(), 'once');
    }

    public function testAConfirmLevelRoutineIsOfferedWhenDueAndItsYesAsksForTheConfirm(): void
    {
        $this->call('snap_routine_save', array('capability' => 'lend_book', 'inputs' => '{"book_id":2,"borrower":"Sam"}', 'weekday' => 2, 'hour' => 13, 'level' => 'confirm'), $this->admin());
        Database::table('caligrafy_snap_routines')->where('id', '>', 0)->update(array('next_at' => '2026-11-17 13:00:00'));
        $offer = $this->offers($this->admin())[0];
        $this->assertSame(array('suggestion', 'confirm'), array($offer['type'], $offer['level']));
        $this->assertStringStartsWith('Your routine: every Tuesday around 1 pm', $offer['reason']);
        $next = $this->action('snap_run', array('capability' => $offer['target']['capability'], 'inputs' => (array) $offer['target']['input']), $this->admin())->describe()['data'];
        $this->assertSame('confirm', $next['type'], 'the routine prepared it; the person still confirms');
    }
}
