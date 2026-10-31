<!-- The catalog: the list and the form side by side on tablets and computers, one under the other on phones -->
<template>
  <main class="cq-page cq-page--wide">
    <div class="cq-card">
      <div class="cq-card-head">
        <h2 class="cq-title">Bookshelf</h2>
        <p class="cq-note">The same catalog as the web pages, the REST API and AI agents.</p>
      </div>

      <div class="cq-section cq-split">
        <!-- the list, filtered as you type (search_books) -->
        <div>
          <label for="query">Search</label>
          <input id="query" class="form-control" type="search" v-model="query" @input="search" placeholder="magic, time..." autocomplete="off">
          <ul class="cq-list">
            <li class="cq-item" v-for="book in books" :key="book.id">
              <div class="cq-item-body">
                <p class="cq-item-title"><router-link :to="{ name: 'book', params: { id: book.id } }">{{ book.title }}</router-link></p>
                <p class="cq-item-meta">{{ book.published_year }}</p>
              </div>
            </li>
          </ul>
          <p class="cq-empty" v-if="!books.length">No book here: add one.</p>
        </div>

        <!-- the form (create_book): the messages come from its rules -->
        <form class="cq-form" @submit.prevent="add" novalidate>
          <div class="cq-fields">
            <div>
              <label for="title">* Title</label>
              <input id="title" class="form-control" type="text" v-model="form.title" maxlength="200">
            </div>
            <div>
              <label for="author_id">* Author</label>
              <select id="author_id" class="form-select" v-model="form.author_id">
                <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
              </select>
            </div>
            <div>
              <label for="published_year">* Year</label>
              <input id="published_year" class="form-control" type="number" v-model="form.published_year" placeholder="1969">
            </div>
          </div>
          <button class="btn btn-primary cq-submit" type="submit">Add book</button>
          <p class="cq-error" role="alert">{{ error }}</p>
        </form>
      </div>
    </div>
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
