<?php

use Caligrafy\Testing\TestCase;

// The rules of the Bookshelf capabilities: the same for the pages, the REST API, the Vue apps and AI agents.
// call() goes through the capability as every experience does: who calls, the rules, then handle().
// Each test runs in a transaction that is rolled back at its end: the books it adds are gone afterwards
final class BookRulesTest extends TestCase
{
    public function testABookNeedsATitleAYearAndAnAuthor(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'create_book', [], $this->user(1));
        $this->assertSame(['title', 'published_year', 'author_id'], array_keys($e->fields));
    }

    public function testTheYearMustBeAWholeNumber(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'create_book', ['title' => 'Kindred', 'published_year' => 'soon', 'author_id' => 2], $this->user(1));
        $this->assertArrayHasKey('published_year', $e->fields);
    }

    public function testAValidBookIsTrimmedAndKeepsOnlyItsInputs(): void
    {
        // an author of its own: the test does not depend on the sample data (the transaction removes it afterwards)
        $author = Caligrafy\Database::table('authors')->insert(['name' => 'Octavia E. Butler']);   // the id of the new row
        $book = $this->call('create_book', ['title' => '  Kindred ', 'published_year' => 1979, 'author_id' => $author, 'price' => 9], $this->user(1));
        $this->assertSame('Kindred', $book['title']);
        $this->assertArrayNotHasKey('price', $book);   // only the inputs of $rules reach handle()
    }

    public function testAVisitorCannotAddABook(): void
    {
        $this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Kindred', 'published_year' => 1979, 'author_id' => 2], 'guest');
    }

    public function testUpdatingNeedsTheIdOfTheBook(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'update_book', ['title' => 'Kindred'], $this->user(1));
        $this->assertArrayHasKey('id', $e->fields);
    }

    public function testASearchNeedsAtLeastTwoCharacters(): void
    {
        $e = $this->assertCapabilityFails('invalid', 'search_books', ['query' => 'a'], 'guest');
        $this->assertArrayHasKey('query', $e->fields);
    }
}
