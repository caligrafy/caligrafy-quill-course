<!-- The list of books: route '/' -->
<template>
  <div>
    <h2 class="medium-text semibold horizontal-center">Bookshelf</h2>
    <p class="horizontal-center">{{ books.length }} books</p>
    <p class="error horizontal-center" v-if="message">{{ message }}</p>
    <div class="spacer-2"></div>
    <router-link v-for="book in books" :key="book.id" :to="{ name: 'book', params: { id: book.id } }">
      <div class="card color0 dropshadow equally-spaced-1">
        <h3 class="medium-text semibold textcolor5 reset-margin">{{ book.title }}</h3>
        <p class="default-text textcolor5">{{ book.published_year }}</p>
      </div>
    </router-link>
  </div>
</template>

<script>
import { callApi, apiConfig } from '../../common/app.js'

export default {
  name: 'BooksPage',
  data() {
    return { books: [], message: '' }
  },
  mounted() {
    callApi.get('/api/books', apiConfig)
      .then((response) => { this.books = response.data.books })
      .catch(() => { this.message = 'The books could not be loaded. Is Caligrafy running?' })
  }
}
</script>
