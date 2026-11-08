<?php

use Caligrafy\Database;
use Caligrafy\Testing\TestCase;

// The rules and permissions of the Bookshelf capabilities: the same for the pages, REST, the Vue apps and AI agents.
// call() goes through the capability as every experience does: who calls, the rules, then handle().
// Each test runs in a transaction that is rolled back at its end: what it adds is gone afterwards
final class BookRulesTest extends TestCase
{
    // an author of the test's own: the tests do not depend on the sample data
    private function author(): int
    {
        return Database::table('authors')->insert(['name' => 'Test Author']);   // the id of the new row
    }

    public function testABookNeedsATitleAYearAndAnAuthor(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'create_book', [], $this->user(1));
        $this->assertSame(['title', 'published_year', 'author_id'], array_keys($e->fields));
    }

    public function testTheYearIsAWholeNumberAndNotInTheFuture(): void
    {
        $author = $this->author();
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Soon', 'published_year' => 'soon', 'author_id' => $author], $this->user(1));
        $this->assertArrayHasKey('published_year', $e->fields);
        $next = (int) date('Y') + 1;
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Soon', 'published_year' => $next, 'author_id' => $author], $this->user(1));
        $this->assertArrayHasKey('published_year', $e->fields);
    }

    public function testAValidBookIsTrimmedAndKeepsOnlyItsInputs(): void
    {
        $book = $this->call('create_book', ['title' => '  Kindred ', 'published_year' => 1979, 'author_id' => $this->author(), 'price' => 9], $this->user(1));
        $this->assertSame('Kindred', $book['title']);
        $this->assertSame('Test Author', $book['author']['name']);
        $this->assertArrayNotHasKey('price', $book);   // only the inputs of $rules reach handle()
    }

    public function testTwoBooksNeverShareAnIsbn(): void
    {
        $author = $this->author();
        $this->call('create_book', ['title' => 'One', 'published_year' => 2000, 'author_id' => $author, 'isbn' => '978-0-00-000000-1'], $this->user(1));
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Two', 'published_year' => 2000, 'author_id' => $author, 'isbn' => '978-0-00-000000-1'], $this->user(1));
        $this->assertArrayHasKey('isbn', $e->fields);
    }

    public function testAVisitorCannotAddABook(): void
    {
        $this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Kindred', 'published_year' => 1979, 'author_id' => $this->author()], 'guest');
    }

    public function testOnlyAnAdministratorDeletesABook(): void
    {
        $book = $this->call('create_book', ['title' => 'Gone soon', 'published_year' => 2000, 'author_id' => $this->author()], $this->user(1));
        $this->assertCapabilityFails('forbidden', 'delete_book', ['id' => $book['id']], $this->user(1, 0));   // a reader
        $this->assertSame(['deleted' => $book['id']], $this->call('delete_book', ['id' => $book['id']], $this->user(1, 1)));
    }

    public function testASearchNeedsAtLeastTwoCharacters(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'search_books', ['query' => 'a'], 'guest');
        $this->assertArrayHasKey('query', $e->fields);
    }
}
