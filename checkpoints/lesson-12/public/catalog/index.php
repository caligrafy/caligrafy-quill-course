<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bookshelf catalog</title>

        <!-- The same stylesheets as the Caligrafy pages: Caligrafy styles, Bootstrap 5, the black and white theme -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css'; ?>">
    </head>

    <body>
        <!-- Vue controls everything inside #app -->
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>

            <div class="content-section bounded-xlarge">
                <div class="wrapper width-max-600">
                    <div class="spacer-4"></div>
                    <h2 class="medium-text semibold horizontal-center">Bookshelf catalog</h2>
                    <p class="horizontal-center">{{ books.length }} books, loaded from the API</p>
                    <p class="error horizontal-center" v-if="message">{{ message }}</p>
                    <div class="spacer-2"></div>

                    <!-- the list: one card per book -->
                    <div class="card color0 dropshadow equally-spaced-1" v-for="book in books" :key="book.id">
                        <h3 class="medium-text semibold textcolor5 reset-margin">{{ book.title }}</h3>
                        <p class="default-text textcolor5">{{ book.published_year }}</p>
                        <button class="btn btn-secondary" v-if="signedIn" @click="remove(book)">Delete</button>
                    </div>

                    <div class="spacer-2"></div>

                    <!-- the form: only for signed-in users, because the API refuses changes from visitors -->
                    <div class="card color0 dropshadow equally-spaced-1" v-if="signedIn">
                        <h3 class="medium-text semibold reset-margin">Add a book</h3>
                        <div class="spacer-1"></div>
                        <form @submit.prevent="add">
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="title">* title</label>
                                <input class="form-control" id="title" type="text" v-model="form.title">
                                <p class="error" v-if="errors.title">{{ errors.title }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="author_id">* author</label>
                                <select class="form-select" id="author_id" v-model="form.author_id">
                                    <option value="">Choose an author</option>
                                    <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                                </select>
                                <p class="error" v-if="errors.author_id">{{ errors.author_id }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="published_year">* year</label>
                                <input class="form-control" id="published_year" type="number" v-model="form.published_year">
                                <p class="error" v-if="errors.published_year">{{ errors.published_year }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="default-text textcolor5" for="summary">summary</label>
                                <textarea class="form-control" id="summary" rows="3" v-model="form.summary"></textarea>
                                <p class="error" v-if="errors.summary">{{ errors.summary }}</p>
                            </div>
                            <input class="btn btn-primary" type="submit" value="Add">
                        </form>
                    </div>
                    <p class="horizontal-center" v-else>
                        <a class="btn btn-secondary" :href="env.home + 'login'">Sign in to add books</a>
                    </p>
                    <div class="spacer-2"></div>
                </div>
            </div>

            <footer>
                <p class="small-text semi-bold horizontal-center">Copyright &copy; {{ new Date().getFullYear() }}</p>
            </footer>
        </div>

        <!-- Vue 3 and axios (pinned by Caligrafy), then app.js, which defines loadEnvironment() and loadVue() -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <!-- $env holds the data the server gives the app (home URL, API key, CSRF token, signedIn...) -->
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <!-- loads scripts/main.js of this folder -->
        <script>loadVue({ scripts: ['main'] });</script>
        <script src="<?php echo scripts('bootstrap_script'); ?>"></script>
    </body>
</html>
