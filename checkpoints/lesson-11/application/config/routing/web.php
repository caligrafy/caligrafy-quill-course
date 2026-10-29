<?php
/**
 * web.php - every URL of the Bookshelf application is declared in this file
 */

use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {

    // API ACCESS: lets programs that send the API key call the controller routes (lesson 09)
    Auth::activateAPI();

    // AUTHENTICATION: users are rows of the users table, read with the User model (lesson 08)
    Auth::authentication('User', 'users');

    // ROUTES. The first route must stay '/': a URL that matches no route shows the first route.
    Route::get('/', 'Bookshelf\\BookController');
    Route::get('/helloworld', function() { echo "Hello World"; });

    // Lesson 02: our first routes
    Route::get('/about', function() {
        echo 'Bookshelf: a small catalog of the books we love.';
    });
    Route::get('/hello/{name}', function() {
        $name = request()->fetch('name');
        echo 'Hello ' . sanitize($name) . '!';
    });

    // Books and authors
    Route::get('/books', 'Bookshelf\\BookController');            // no @action: index()
    Route::get('/books/new', 'Bookshelf\\BookController@createForm');   // before /books/{id}
    Route::get('/books/{id}', 'Bookshelf\\BookController@read');
    Route::get('/books/{id}/edit', 'Bookshelf\\BookController@editForm');
    Route::post('/books', 'Bookshelf\\BookController@create');
    Route::put('/books/{id}', 'Bookshelf\\BookController@update');
    Route::delete('/books/{id}', 'Bookshelf\\BookController@remove');
    Route::get('/authors/{id}', 'Bookshelf\\AuthorController@read');

    // Sign in, sign out, register (controllers shipped with the framework)
    Route::get('/home', 'Bookshelf\\BookController');          // where the login and logout pages go
    Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
    Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');
    Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');

    // REST API (lesson 09): JSON only, called with the API key
    Route::get('/api/books', 'Bookshelf\\Api\\BookApiController');
    Route::get('/api/authors', 'Bookshelf\\Api\\BookApiController@authors');
    Route::get('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@read');
    Route::post('/api/books', 'Bookshelf\\Api\\BookApiController@create');
    Route::put('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@update');
    Route::delete('/api/books/{id}', 'Bookshelf\\Api\\BookApiController@remove');

    // The Vue catalog (lesson 10): public/catalog, shown by a controller that passes signedIn to the app
    Route::get('/catalog', 'Bookshelf\\CatalogController');

    // Client apps (lesson 10): public/<appName> is shown at /apps/<appName>
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');

    // MUST STAY LAST: finds the route that matches the request and runs it
    Route::run();
}
catch (Exception $e) {

    // Errors get their HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}
