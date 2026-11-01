<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>Bookshelf finder</title>

        <!-- The same look as the Caligrafy pages: Bootstrap 5, the tokens, the structure (layout.css) and the look (look.css) -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/tokens.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/layout.css'; ?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/look.css'; ?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>
    </head>

    <body class="cq-site">
        <!-- Vue controls everything inside #app -->
        <div id="app" v-cloak>
            <!-- the top bar of the Caligrafy pages -->
            <nav class="cq-nav" aria-label="Main">
                <a class="cq-nav-brand" href="<?php echo session('home'); ?>"><img class="cq-nav-mark" src="<?php echo session('public').'images/resources/caligrafy-mark.svg'; ?>" alt="" width="28" height="28"><span class="semibold">Cali</span>grafy</a>
            </nav>

            <!-- the title band -->
            <section class="cq-hero cq-hero--page">
                <h1 class="cq-display">Find a book</h1>
                <p class="cq-lead">Type a few words of a title or a summary: the search_books capability answers as you type.</p>
            </section>

            <!-- the search field and the books found -->
            <section class="cq-band cq-band--tight">
                <div class="cq-inner cq-inner--narrow">
                    <form @submit.prevent>
                        <div class="cq-field">
                            <label for="query">Search</label>
                            <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time, cities..." autocomplete="off">
                        </div>
                    </form>
                    <p class="cq-field-error" role="alert">{{ error }}</p>

                    <ul class="cq-rows">
                        <li class="cq-row" v-for="book in books" :key="book.id">
                            <div class="cq-row-main">
                                <p class="cq-row-title">{{ book.title }}</p>
                                <p class="cq-row-meta">{{ book.published_year }}</p>
                            </div>
                            <div class="cq-row-actions">
                                <button type="button" class="btn btn-secondary" @click="open(book.id)">Open</button>
                            </div>
                        </li>
                    </ul>
                    <p class="cq-empty-state" v-if="!books.length">No book matches "{{ query }}".</p>
                    <p class="cq-auth-note">{{ books.length }} book(s)</p>
                </div>
            </section>

            <!-- the book opened: get_book, in a grey band under the list -->
            <section class="cq-band cq-band--surface">
                <div class="cq-inner cq-inner--narrow" v-if="selected">
                    <h2 class="cq-heading">{{ selected.book.title }}</h2>
                    <p class="cq-lead">by {{ selected.author.name }}, {{ selected.book.published_year }}</p>
                    <p class="cq-lead">{{ selected.book.summary || 'No summary yet.' }}</p>
                    <p class="cq-row-meta">{{ selected.author.bio }}</p>
                </div>
                <p class="cq-empty-state" v-else>Open a book to read about it.</p>
            </section>
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
