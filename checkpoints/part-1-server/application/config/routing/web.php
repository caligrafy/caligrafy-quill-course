<?php
/**
 * web.php
 * Showcase routes (Caligrafy Quill course, part 1)
 */
use Caligrafy\Auth;
use Caligrafy\Route;
use Caligrafy\Errors;
use \Exception as Exception;

try {
    // API ACCESS: JSON requests need "Authorization: Bearer <API_KEY>"
    Auth::activateAPI();

    // AUTHENTICATION (users table: application/models/Auth/create_db.sql)
    Auth::authentication('User', 'users');

    // Keep '/' first: unmatched URLs fall back to the first route
    Route::get('/', function() { return view('default/index', array('authorized' => authorized())); });

    // Showcase projects: pages and JSON API
    Route::get('/projects', 'Showcase\\ProjectController');
    Route::get('/projects/new', 'Showcase\\ProjectController@createForm');   // before /projects/{id}
    Route::get('/projects/{id}', 'Showcase\\ProjectController@read');
    Route::get('/projects/{id}/edit', 'Showcase\\ProjectController@editForm');
    Route::post('/projects', 'Showcase\\ProjectController@create');
    Route::put('/projects/{id}', 'Showcase\\ProjectController@update');
    Route::delete('/projects/{id}', 'Showcase\\ProjectController@remove');

    // Auth routes
    Route::get('/home', 'Caligrafy\\Controllers\\AuthController@home');
    Route::get('/login', 'Caligrafy\\Controllers\\AuthController');
    Route::get('/logout', 'Caligrafy\\Controllers\\AuthController@logout');
    Route::get('/notAuthorized', 'Caligrafy\\Controllers\\AuthController@notAuthorized');
    Route::get('/register', 'Caligrafy\\Controllers\\AuthController@registerForm');
    Route::post('/login', 'Caligrafy\\Controllers\\AuthController@login');
    Route::post('/register', 'Caligrafy\\Controllers\\AuthController@register');

    // Client apps (part 2)
    Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');

    // MUST stay last
    Route::run();
}
catch (Exception $e) {
    Errors::handle($e);
}
