<!-- One book: route '/books/:id'; the router gives the id as a prop -->
<template>
  <div>
    <p class="error horizontal-center" v-if="message">{{ message }}</p>
    <div class="card color0 dropshadow equally-spaced-1" v-if="book">
      <h2 class="medium-text semibold reset-margin">{{ book.title }}</h2>
      <div class="spacer-1"></div>
      <p class="default-text textcolor5">by {{ author.name }}, {{ book.published_year }}</p>
      <p>{{ book.summary }}</p>
    </div>
    <div class="spacer-2"></div>
    <p class="horizontal-center">
      <router-link class="btn btn-secondary" :to="{ name: 'home' }">All books</router-link>
    </p>
  </div>
</template>

<script>
import { callApi, apiConfig } from '../../common/app.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data() {
    return { book: null, author: null, message: '' }
  },
  mounted() {
    callApi.get('/api/books/' + this.id, apiConfig)
      .then((response) => {
        this.book = response.data.book
        this.author = response.data.author
      })
      .catch((error) => {
        this.message = error.response && error.response.status === 404 ? 'There is no book with this number.' : 'The book could not be loaded.'
      })
  }
}
</script>
