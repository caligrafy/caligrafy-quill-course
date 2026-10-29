<!-- One book: route '/books/:id'; the router gives the id as a prop -->
<template>
  <div class="wrapper width-max-600">
    <div class="spacer-2"></div>
    <p class="error horizontal-center" v-if="store.message">{{ store.message }}</p>
    <div class="card color0 dropshadow equally-spaced-1" v-if="store.current">
      <h2 class="medium-text semibold reset-margin">{{ store.current.title }}</h2>
      <div class="spacer-1"></div>
      <p class="default-text textcolor5">by {{ store.currentAuthor.name }}, {{ store.current.published_year }}</p>
      <p>{{ store.current.summary }}</p>
      <button class="btn btn-secondary" @click="remove">Delete</button>
    </div>
    <div class="spacer-2"></div>
    <p class="horizontal-center">
      <router-link class="btn btn-secondary" :to="{ name: 'home' }">All books</router-link>
    </p>
  </div>
</template>

<script>
import { useBookStore } from '../../common/store.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data() {
    return { store: useBookStore() }
  },
  mounted() {
    this.store.fetchBook(this.id)
  },
  watch: {
    // the router reuses this page when only the id changes (#/books/3 -> #/books/4): load the new book
    id() {
      this.store.fetchBook(this.id)
    }
  },
  methods: {
    async remove() {
      if (!confirm('Delete "' + this.store.current.title + '"?')) {
        return
      }
      if (await this.store.deleteBook(this.store.current.id)) {
        this.$router.push({ name: 'home' })
      }
    }
  }
}
</script>
