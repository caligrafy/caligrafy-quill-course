<?php

namespace Bookshelf;

use Caligrafy\Controller;

class CatalogController extends Controller {

    // GET /catalog : the Vue app of public/catalog, told whether someone is signed in
    public function index()
    {
        return view('Client/app', array(
            'appName' => 'catalog',          // the folder public/catalog
            'app' => 'index',                // the file public/catalog/index.php
            'signedIn' => authorized()       // arrives in the app as env.signedIn
        ));
    }
}
