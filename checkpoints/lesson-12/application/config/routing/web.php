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
    Route::page('/', 'Bookshelf/books', ['with' => ['install' => true]]);
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // SIGN IN, SIGN OUT, REGISTER: /login, /logout, /register and /notAuthorized; after signing in, the catalog
    Route::auth(['home' => '/books']);

    // User Administration Routes - Uncomment only if AUTHENTICATION activated above
	// Route::get('/admin', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users', 'Caligrafy\\Controllers\\AdminController');
	// Route::get('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@editUserForm');
	// Route::put('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@updateUser');
	// Route::delete('/users/{id}/edit', 'Caligrafy\\Controllers\\AdminController@deleteUser');

    // GPT AI controller
    // Route::get('/ai', 'Caligrafy\\Controllers\\AIController');
    // Route::post('/ai', 'Caligrafy\\Controllers\\AIController@query');

    // ROUTING TO JAVASCRIPT APPS
    // Make sure you create a JS app or a Vue app in Caligrafer first
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController'); // you can specify any path as long as it ends with appName

    
    // BOT PAGE ROUTES
    Route::get('/bot/{appName}', 'Caligrafy\\Controllers\\ClientController');  // A botSkillId needs to be provided in the URL. For Example: ?botSkillId=62196ce3-0b22-4aea-b2f2-d39730c03797
    
    // BOT DATA ROUTES
    // routes need to start with reserved path __bots__ 
    Route::post('/__bots__/{appName}/communicate', 'Caligrafy\\Controllers\\WatsonController@communicate');
    Route::post('/__bots__/{appName}/{appId}', 'Caligrafy\\Controllers\\WatsonController@connect');
    Route::delete('/__bots__/{appName}', 'Caligrafy\\Controllers\\WatsonController@end');   

    /* ML Routes
     *
     * If you don't specify an up, it will go to the face, body detector.
     * Specify an app as a URI Parameter ?app=<name of app>
     * 
     * There are 3 possible apps: featureextractor, neuralnetwork and posedetector
    */
    Route::get('/ml/{appName}', 'Caligrafy\\Controllers\\ClientController'); 
    

    // BUILD ONCE: every capability, for the JS client and AI agents
    Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps
    Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)
    Route::notifications();                 // the pages notification emails link to (confirm, unsubscribe)

    // BOOKSHELF PAGES: the catalog (public/js/books.js), the page of one book (data: get_book and list_authors) and its form
    Route::page('/books', 'Bookshelf/books', ['with' => ['install' => true]]);
    Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['found' => 'get_book', 'authors' => ['list_authors', []]], 'notFound' => 'Bookshelf/notfound']);
    Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);   // the edit form: no controller

    // BOOKSHELF API: the same capabilities, for other applications (JSON)
    Route::capability('GET', '/api/books', 'list_books');
    Route::capability('GET', '/api/books/{id}', 'get_book');
    Route::capability('POST', '/api/books', 'create_book');
    Route::capability('PUT', '/api/books/{id}', 'update_book');
    Route::capability('DELETE', '/api/books/{id}', 'delete_book');
    Route::capability('GET', '/api/authors', 'list_authors');
    Route::download('/authors.csv', 'list_authors');   // the same capability, as a file for a spreadsheet

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling: HTTP status code (404, 401, 403, 500...) with the error text, or a JSON error for API calls
    Errors::handle($e);
}



