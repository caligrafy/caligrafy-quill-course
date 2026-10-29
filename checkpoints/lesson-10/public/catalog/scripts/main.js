// The Bookshelf catalog: a Vue 3 app that reads and changes the books through the Caligrafy REST API
const app = Vue.createApp({
    data() {
        return {
            env: env,                     // set by loadEnvironment() in index.php
            signedIn: env.signedIn === true,
            books: [],
            authors: [],
            form: { title: '', author_id: '', published_year: '', summary: '' },
            errors: {},
            message: '',
            // every API call sends the API key and asks for JSON
            config: {
                headers: {
                    'Authorization': 'Bearer ' + apiKey,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            }
        };
    },

    methods: {
        // GET /api/books
        loadBooks() {
            axios.get(this.env.home + 'api/books', this.config)
                .then(response => { this.books = response.data.books; })
                .catch(error => { this.showError(error); });
        },

        // GET /api/authors (for the select of the form)
        loadAuthors() {
            axios.get(this.env.home + 'api/authors', this.config)
                .then(response => { this.authors = response.data.authors; })
                .catch(error => { this.showError(error); });
        },

        // POST /api/books: 201 with the new book, or 422 with one message per invalid field
        add() {
            this.errors = {};
            this.message = '';
            axios.post(this.env.home + 'api/books', this.form, this.config)
                .then(() => {
                    this.form = { title: '', author_id: '', published_year: '', summary: '' };
                    this.loadBooks();
                })
                .catch(error => {
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    } else {
                        this.showError(error);
                    }
                });
        },

        // DELETE /api/books/{id}
        remove(book) {
            if (!confirm('Delete "' + book.title + '"?')) {
                return;
            }
            axios.delete(this.env.home + 'api/books/' + book.id, this.config)
                .then(() => { this.loadBooks(); })
                .catch(error => { this.showError(error); });
        },

        // framework and API errors answer {"error": {"code": ..., "message": "..."}}
        showError(error) {
            const data = error.response ? error.response.data : null;
            this.message = data && data.error ? data.error.message : 'The server could not be reached.';
        }
    },

    // runs once, when the app is on the page
    mounted() {
        this.loadBooks();
        this.loadAuthors();
    }
});

app.mount('#app');
