<!-- The list of books: route '/' -->
<template>
  <div>
    <h2 class="medium-text semibold horizontal-center">Bookshelf</h2>
    <p class="horizontal-center">{{ store.loading ? 'Loading...' : store.count + ' books' }}</p>
    <p class="error horizontal-center" v-if="store.message">{{ store.message }}</p>
    <p class="horizontal-center">
      <router-link class="btn btn-primary" :to="{ name: 'new-book' }">Add a book</router-link>
    </p>
    <div class="spacer-2"></div>
    <router-link v-for="book in store.books" :key="book.id" :to="{ name: 'book', params: { id: book.id } }">
      <div class="card color0 dropshadow equally-spaced-1">
        <h3 class="medium-text semibold textcolor5 reset-margin">{{ book.title }}</h3>
        <p class="default-text textcolor5">{{ book.published_year }}</p>
      </div>
    </router-link>
  </div>
</template>

<script>
import { useBookStore } from '../../common/store.js'

export default {
  name: 'BooksPage',
  data() {
    return { store: useBookStore() }
  },
  mounted() {
    this.store.fetchBooks()
  }
}
</script>
