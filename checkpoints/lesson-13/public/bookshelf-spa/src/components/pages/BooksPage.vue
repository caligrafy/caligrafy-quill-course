<!-- The catalog: the list filtered as you type, then the form in a grey band (one column, on every screen) -->
<template>
  <main>
    <section class="cq-hero cq-hero--page">
      <h1 class="cq-display">Bookshelf</h1>
      <p class="cq-lead">The same catalog as the web pages, the REST API and AI agents.</p>
    </section>

    <!-- the list, filtered as you type (search_books) -->
    <section class="cq-band cq-band--tight">
      <div class="cq-inner cq-inner--narrow">
        <div class="cq-field">
          <label for="query">Search</label>
          <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time..." autocomplete="off">
        </div>
        <ul class="cq-rows">
          <li class="cq-row" v-for="book in books" :key="book.id">
            <div class="cq-row-main">
              <p class="cq-row-title"><router-link :to="{ name: 'book', params: { id: book.id } }">{{ book.title }}</router-link></p>
              <p class="cq-row-meta">{{ book.published_year }}</p>
            </div>
          </li>
        </ul>
        <p class="cq-empty-state" v-if="!books.length">No book here: add one.</p>
      </div>
    </section>

    <!-- the form (create_book): the messages come from its rules -->
    <section class="cq-band cq-band--surface">
      <div class="cq-inner cq-inner--narrow cq-stack">
        <h2 class="cq-heading">Add a book</h2>
        <form @submit.prevent="add" novalidate>
          <div class="cq-form-grid">
            <div class="cq-field">
              <label for="title">* Title</label>
              <input id="title" class="form-control" type="text" v-model="form.title" maxlength="200">
            </div>
            <div class="cq-field">
              <label for="author_id">* Author</label>
              <select id="author_id" class="form-select" v-model="form.author_id">
                <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
              </select>
            </div>
            <div class="cq-field">
              <label for="published_year">* Year</label>
              <input id="published_year" class="form-control" type="number" v-model="form.published_year" placeholder="1969">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Add book</button>
          <p class="cq-field-error" role="alert">{{ error }}</p>
        </form>
      </div>
    </section>
  </main>
</template>

<script>
import { listBooks, searchBooks, listAuthors, createBook, CapabilityError } from '../../capabilities.js'

export default {
  name: 'BooksPage',
  data () {
    return { books: [], authors: [], query: '', form: { title: '', author_id: '', published_year: '' }, error: '', timer: null }
  },
  async mounted () {
    this.authors = await listAuthors()
    this.form.author_id = this.authors.length ? this.authors[0].id : ''
    this.books = await listBooks()
  },
  methods: {
    search () {
      clearTimeout(this.timer)
      this.timer = setTimeout(async () => {
        this.books = this.query.trim().length < 2 ? await listBooks() : await searchBooks({ query: this.query })
      }, 250)
    },
    async add () {
      this.error = ''
      try {
        await createBook(this.form)
        this.form.title = ''
        this.form.published_year = ''
        this.books = await listBooks()
      } catch (e) {
        this.error = e instanceof CapabilityError ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong. Try again.'
      }
    }
  }
}
</script>
