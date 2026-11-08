<?php
/**
 * web.php
 * Web Routes are defined in this file
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {
    
    // API ACCESS - add comments if you don't want to allow API access on routes
    Auth::activateAPI();
    
    
    // AUTHENTICATION - Remove comment if you have an authentication implemented
    Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::redirect('/', '/books');   // the home of Bookshelf is its catalog
    Route::redirect('/catalog', '/books', 301, ['keepQuery' => true]);   // the catalog's old address: the filter of its address goes along

    // SIGN IN, SIGN OUT: /login, /logout and /notAuthorized; after signing in, /login?return=<page>, else the catalog
    Route::auth(['home' => '/books', 'register' => false]);   // no /register: people join with the form of /join

    // ROUTING TO JAVASCRIPT APPS
    Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);   // public/<appName>/index.php, or ?app=<file>

    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // BOOKSHELF PAGES: rendered on the server with the data of the capabilities; each form calls one capability
    Route::page('/books', 'Bookshelf/books', ['query' => ['author_id'], 'data' => ['books' => 'list_books', 'authors' => 'list_authors'], 'with' => ['install' => true]]);   // install: offer to add it to the home screen
    Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book', 'authors' => 'list_authors'], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
    Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);
    Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);   // the same capability, as a file
    Route::page('/people', 'Bookshelf/people', ['level' => 1, 'comeBack' => true, 'data' => ['people' => 'list_users']]);   // administrators only

    // JOIN THE CLUB: the only way to create an account; people already signed in go to the catalog
    Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
    Route::form('/join', 'register_user', [
        'visitorsOnly' => '/books',
        'same' => ['passcode_again' => ['passcode', 'The two passwords are not the same']],
        'accepted' => ['rules' => 'Accept the rules of the club to join'],
        'signsIn' => 'id',
        'success' => '/books',
        'flash' => 'Welcome to the club, {username}'
    ]);

    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');

    // LOANS: one more feature, every experience: a page and its forms, REST, the JS clients and AI agents
    Route::page('/loans', 'Loans/index', ['signedIn' => true, 'comeBack' => true, 'data' => ['loans' => 'list_loans', 'books' => 'list_books']]);
    Route::form('/loans', 'lend_book', ['success' => '/loans', 'flash' => 'Lent to {borrower} until {due_on}']);
    Route::form('/loans/{book_id}/return', 'return_book', ['success' => '/loans', 'flash' => 'Returned by {borrower}']);
    Route::form('/books/{book_id}/borrow', 'lend_book', ['inputs' => function () { return ['borrower' => Auth::user()->username ?? '', 'days' => 14]; }, 'success' => '/loans', 'flash' => 'It is yours until {due_on}']);   // the server says who borrows
    Route::form('/loans/remind', 'send_reminders', ['success' => '/loans', 'flash' => 'The reminders are on their way (job {job})']);   // administrators
    Route::capability('GET', '/api/loans', 'list_loans');
    Route::capability('POST', '/api/loans', 'lend_book');
    Route::capability('POST', '/api/books/{book_id}/return', 'return_book');

    // WHAT A PAGE OR A FORM CANNOT SAY: each answered by a capability declared webOnly: true
    Route::action('/surprise', 'surprise_me');                 // a request that decides where to go
    Route::action('/feed', 'books_feed');                      // an answer that is XML
    Route::action('/loans/{book_id}/renew', 'renew_loan', ['signedIn' => true, 'comeBack' => true]);   // a link that changes data

    // APPENDIX: routes, controllers and forms written by hand
    Route::get('/about', function () {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function () {
        $name = request()->fetch('name');        // the {name} part of the address
        echo 'Hello ' . sanitize($name) . '!';   // sanitize: never print what a visitor typed as is
    });
    Route::get('/classic/feed', 'Bookshelf\\FeedController@books');   // the older way of lesson 16's /feed: a controller that calls a capability
    Route::get('/authors/new', 'Bookshelf\\AuthorController@createForm');   // before /authors/{id}, or {id} would take "new"
    Route::post('/authors', 'Bookshelf\\AuthorController@create');
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@show');   // under the hood: the classic way
    Route::get('/v1/authors', 'Bookshelf\\AuthorController@index');

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}



