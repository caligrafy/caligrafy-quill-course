<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Snap\Tasks;

/**
 * Snap Cards (lesson 17): Track: Task. Slow work runs in the background as the person who asked (never with the queue's
 * full access), and its card says where it is, then shows the card it ended with
 */
final class SnapTasksTest extends TestCase
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
        $input = array('capability' => $capability, 'inputs' => $inputs) + ($confirm ? array('confirm' => $confirm) : array());
        $answer = $this->action('snap_run', $input, $as)->describe();
        return array($answer['data'], $answer['status']);
    }

    public function testASlowReadStartsATaskAtOnce(): void
    {
        [$card, $status] = $this->send('reading_report', array('pause' => 0), $this->reader());
        $this->assertSame(202, $status);
        $this->assertSame(array('track', 'task', 'queued', true), array($card['family'], $card['type'], $card['status'], $card['live']));
        $this->assertSame('Reading report', $card['title']);
        $job = Database::table('caligrafy_jobs')->where('id', Database::table('caligrafy_snap_tasks')->where('id', $card['id'])->value('job_id'))->first();
        $this->assertSame('snap_task_run', ((array) $job)['capability'], 'a job of Caligrafy\'s queue');

        Tasks::run($card['id']);   // what the worker does
        $done = $this->call('snap_task', array('id' => $card['id']), $this->reader());
        $this->assertSame(array('done', false), array($done['status'], $done['live']));
        $this->assertSame(array('show', 'list'), array($done['result']['family'], $done['result']['type']));
        $this->assertSame(array('Name', 'Books', 'Out now'), array_column($done['result']['columns'], 'label'));
        $this->assertTrue($done['steps'][2]['done']);
        $this->assertSame(1, (int) Database::table('caligrafy_notifications')->where('user_id', 2)->count() >= 1 ? 1 : 0, 'the person is notified');
    }

    public function testAChangeRunsAsATaskOnlyAfterItsConfirm(): void
    {
        [$confirm, $status] = $this->send('remind_loans', array(), $this->admin());
        $this->assertSame(array(200, 'confirm'), array($status, $confirm['type']));
        $this->assertSame(0, (int) Database::table('caligrafy_snap_tasks')->where('capability', 'remind_loans')->count(), 'nothing started before the Confirm');
        [$task, $status] = $this->send('remind_loans', array(), $this->admin(), $confirm['token']);
        $this->assertSame(array(202, 'track', 'queued'), array($status, $task['family'], $task['status']));
    }

    public function testTheWorkerRunsAsThePersonNotWithTheQueuesAccess(): void
    {
        [$confirm] = $this->send('remind_loans', array(), $this->admin());
        [$task] = $this->send('remind_loans', array(), $this->admin(), $confirm['token']);
        Database::table('users')->where('id', 1)->update(array('permissions' => 0));   // no longer an administrator
        Tasks::run($task['id']);
        $card = $this->call('snap_task', array('id' => $task['id']), $this->user(1, 0));
        $this->assertSame('failed', $card['status'], 'remind_loans needs level 1: the worker had only the person\'s rights');
        $this->assertSame('notice', $card['result']['variant']);
    }

    public function testRefusedInputsEndAsAnAskCardToAnswer(): void
    {
        [$task] = $this->send('reading_report', array('pause' => 99), $this->reader());
        Tasks::run($task['id']);
        $card = $this->call('snap_task', array('id' => $task['id']), $this->reader());
        $this->assertSame(array('needs_input', 'ask', 'form'), array($card['status'], $card['result']['family'], $card['result']['type']));
        $this->assertArrayHasKey('pause', (array) $card['result']['errors']);
    }

    public function testATaskIsItsOwnersOnly(): void
    {
        [$task] = $this->send('reading_report', array('pause' => 0), $this->reader());
        $this->assertCapabilityFails('not_found', 'snap_task', array('id' => $task['id']), $this->admin());
        $this->assertCount(0, array_filter($this->call('snap_tasks', array(), $this->admin()), function ($t) use ($task) { return $t['id'] === $task['id']; }));
        $this->assertSame($task['id'], $this->call('snap_tasks', array(), $this->reader())[0]['id'], 'newest first');
    }

    public function testTheGalleryAndThePlannerNeverRunASlowCapabilityInline(): void
    {
        $started = microtime(true);
        $cards = array_column($this->call('snap_catalog', array(), $this->reader()), null, 'capability');
        $this->assertLessThan(5, microtime(true) - $started, 'the 8-second report was not run');
        $this->assertSame('ask', $cards['reading_report']['family']);
    }
}
