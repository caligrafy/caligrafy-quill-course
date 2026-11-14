<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;
use Caligrafy\Web;

// The web-only capabilities: action() calls them as Route::action does; call() is refused, as every other channel is
final class ActionsTest extends TestCase
{
    private function book(): int
    {
        $author = Database::table('authors')->insert(['name' => 'Test Author']);
        return $this->call('create_book', ['title' => 'A test book', 'published_year' => 2000, 'author_id' => $author], $this->user(1))['id'];
    }

    public function testSurpriseGoesToTheCatalog(): void
    {
        $this->book();
        $answer = $this->action('surprise_me', [], 'guest');
        $this->assertInstanceOf(Web::class, $answer);
        $this->assertSame('redirect', $answer->describe()['type']);
        $this->assertMatchesRegularExpression('#^/books/\d+$#', $answer->describe()['target']);
    }

    public function testNoOtherChannelReachesAWebOnlyCapability(): void
    {
        $this->assertCapabilityFails('not_found', 'surprise_me', [], $this->user(1, 1));
    }

    public function testAVisitorCannotRenewALoan(): void
    {
        $this->expectException(\Caligrafy\ServiceError::class);
        $this->action('renew_loan', ['book_id' => 1], 'guest');
    }

    public function testRenewingAndWhatIsNotLent(): void
    {
        $book = $this->book();
        $missing = $this->action('renew_loan', ['book_id' => $book], $this->user(1))->describe();
        $this->assertSame(['view', 404], [$missing['type'], $missing['status']]);

        $this->call('lend_book', ['book_id' => $book, 'borrower' => 'Sam', 'days' => 1], $this->user(1));
        $answer = $this->action('renew_loan', ['book_id' => $book], $this->user(1))->describe();
        $this->assertSame(['redirect', '/loans'], [$answer['type'], $answer['target']]);
        $this->assertSame(date('Y-m-d', strtotime('+14 days')), Database::table('loans')->where('book_id', $book)->value('due_on'));
    }
}
