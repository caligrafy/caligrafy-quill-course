<?php

use Caligrafy\ServiceError;
use PHPUnit\Framework\TestCase;

// The rules of the Bookshelf capabilities: the same for the pages, the REST API, the Vue apps and AI agents.
// validate() checks the inputs against $rules, without the database: what handle() would receive, or a refusal
class BookRulesTest extends TestCase
{
    private function refusal($service, array $input)
    {
        try {
            $service->validate($input);
        } catch (ServiceError $e) {
            return $e;
        }
        $this->fail('The input was accepted');
    }

    public function testABookNeedsATitleAYearAndAnAuthor()
    {
        $e = $this->refusal(new CreateBook(), array());
        $this->assertSame('invalid', $e->kind);
        $this->assertSame(array('title', 'published_year', 'author_id'), array_keys($e->fields));
    }

    public function testTheYearMustBeAWholeNumber()
    {
        $e = $this->refusal(new CreateBook(), array('title' => 'Kindred', 'published_year' => 'soon', 'author_id' => 2));
        $this->assertArrayHasKey('published_year', $e->fields);
    }

    public function testAValidBookIsTrimmedAndKeepsOnlyItsInputs()
    {
        $input = (new CreateBook())->validate(array('title' => '  Kindred ', 'published_year' => 1979, 'author_id' => 2, 'price' => 9));
        $this->assertSame('Kindred', $input['title']);
        $this->assertArrayNotHasKey('price', $input);   // only the inputs of $rules reach handle()
    }

    public function testUpdatingNeedsTheIdOfTheBook()
    {
        $e = $this->refusal(new UpdateBook(), array('title' => 'Kindred'));
        $this->assertArrayHasKey('id', $e->fields);
    }

    public function testASearchNeedsAtLeastTwoCharacters()
    {
        $e = $this->refusal(new SearchBooks(), array('query' => 'a'));
        $this->assertArrayHasKey('query', $e->fields);
    }
}
