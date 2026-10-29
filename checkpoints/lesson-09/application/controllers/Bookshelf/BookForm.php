<?php

namespace Bookshelf;

use Caligrafy\Validator;

// The rules of a valid book, in one place: the web forms (lesson 07) and the API (lesson 09) both use them
class BookForm {

    // filters clean the input before it is checked
    const FILTERS = array(
        'title' => 'trim',
        'published_year' => 'trim',
        'summary' => 'trim',
        'author_id' => 'trim'
    );

    // rules say what a valid value is
    const RULES = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1|max_numeric,2100',
        'summary' => 'max_len,2000',
        'author_id' => 'required|integer'
    );

    // Returns array($data, $errors): the cleaned data, and one message per invalid field (empty when all is valid)
    public static function check(Validator $validator, array $input)
    {
        $data = array();
        foreach (array_keys(self::RULES) as $field) {
            $data[$field] = $input[$field] ?? '';   // keep only the fields of a book
        }
        $data = $validator->filter($data, self::FILTERS);
        $valid = $validator->validate($data, self::RULES);
        $errors = $valid === true ? array() : $validator->get_errors_array();
        return array($data, $errors);
    }

    // The checked data as the columns of the books table, with numbers as integers
    public static function columns(array $data)
    {
        return array(
            'title' => $data['title'],
            'published_year' => (int) $data['published_year'],
            'summary' => $data['summary'],
            'author_id' => (int) $data['author_id']
        );
    }
}
