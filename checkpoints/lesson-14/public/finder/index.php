<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bookshelf finder</title>

        <!-- The same look as the Caligrafy pages: Bootstrap 5, the black and white theme and the page components -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/tokens.css'; ?>">
    </head>

    <body>
        <!-- Vue controls everything inside #app -->
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>

            <main class="cq-page cq-page--wide">
                <div class="cq-card">
                    <div class="cq-card-head">
                        <h2 class="cq-title">Find a book</h2>
                        <p class="cq-note">Type a few words of a title or a summary: the search_books capability answers as you type.</p>
                    </div>

                    <form class="cq-form" @submit.prevent>
                        <div class="cq-fields">
                            <div>
                                <label for="query">Search</label>
                                <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time, cities..." autocomplete="off">
                            </div>
                        </div>
                    </form>
                    <p class="cq-error" role="alert">{{ error }}</p>

                    <div class="cq-section cq-split">
                        <!-- the books found -->
                        <div>
                            <ul class="cq-list">
                                <li class="cq-item" v-for="book in books" :key="book.id">
                                    <div class="cq-item-body">
                                        <p class="cq-item-title">{{ book.title }}</p>
                                        <p class="cq-item-meta">{{ book.published_year }}</p>
                                    </div>
                                    <div class="cq-actions">
                                        <button type="button" class="btn btn-secondary" @click="open(book.id)">Open</button>
                                    </div>
                                </li>
                            </ul>
                            <p class="cq-empty" v-if="!books.length">No book matches "{{ query }}".</p>
                        </div>

                        <!-- the book opened: get_book -->
                        <div v-if="selected">
                            <h3 class="cq-title">{{ selected.book.title }}</h3>
                            <p class="cq-note">by {{ selected.author.name }}, {{ selected.book.published_year }}</p>
                            <p>{{ selected.book.summary || 'No summary yet.' }}</p>
                            <p class="cq-item-meta">{{ selected.author.bio }}</p>
                        </div>
                        <p class="cq-empty" v-else>Open a book to read about it.</p>
                    </div>

                    <div class="cq-foot">{{ books.length }} book(s)</div>
                </div>
            </main>
        </div>

        <!-- Vue 3 (pinned by Caligrafy), then app.js, which defines loadEnvironment() and loadVue() -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <!-- $env holds what the server gives the app: env.home is the address of the application -->
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <!-- loads scripts/main.js of this folder -->
        <script>loadVue({ scripts: ['main'] });</script>
    </body>
</html>
