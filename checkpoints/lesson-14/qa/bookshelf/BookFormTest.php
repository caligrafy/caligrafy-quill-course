<?php

use Bookshelf\BookForm;
use Caligrafy\Validator;
use PHPUnit\Framework\TestCase;

// Each public function whose name starts with "test" is one test
final class BookFormTest extends TestCase
{
    public function testAValidBookHasNoErrors(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array(
            'title' => '  Kindred ',
            'published_year' => '1979',
            'summary' => '',
            'author_id' => '2'
        ));
        $this->assertSame(array(), $errors);
        $this->assertSame('Kindred', $data['title']);   // the trim filter removed the spaces
    }

    public function testTheTitleIsRequired(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array('published_year' => '1979', 'author_id' => '2'));
        $this->assertArrayHasKey('title', $errors);
    }

    public function testTheYearMustBeANumber(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array('title' => 'Kindred', 'published_year' => 'soon', 'author_id' => '2'));
        $this->assertArrayHasKey('published_year', $errors);
    }

    public function testOtherFieldsAreIgnored(): void
    {
        list($data, $errors) = BookForm::check(new Validator(), array('title' => 'Kindred', 'published_year' => '1979', 'author_id' => '2', 'id' => '99'));
        $this->assertArrayNotHasKey('id', $data);
    }

    public function testColumnsAreIntegers(): void
    {
        $columns = BookForm::columns(array('title' => 'Kindred', 'published_year' => '1979', 'summary' => '', 'author_id' => '2'));
        $this->assertSame(1979, $columns['published_year']);
        $this->assertSame(2, $columns['author_id']);
    }
}
