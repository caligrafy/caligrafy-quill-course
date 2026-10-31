<!-- One book (get_book), and a Delete button (delete_book) -->
<template>
  <main class="cq-page">
    <div class="cq-card" v-if="found">
      <div class="cq-card-head">
        <h2 class="cq-title">{{ found.book.title }}</h2>
        <p class="cq-note">by {{ found.author.name }}, {{ found.book.published_year }}</p>
      </div>
      <div class="cq-section">
        <p>{{ found.book.summary || 'No summary yet.' }}</p>
        <button class="btn btn-secondary" type="button" @click="remove">Delete</button>
      </div>
      <div class="cq-foot"><router-link :to="{ name: 'home' }">All books</router-link></div>
    </div>

    <div class="cq-card" v-else>
      <div class="cq-card-head">
        <h2 class="cq-title">{{ error ? 'Not found' : 'Loading...' }}</h2>
        <p class="cq-note">{{ error }}</p>
      </div>
      <div class="cq-foot"><router-link :to="{ name: 'home' }">All books</router-link></div>
    </div>
  </main>
</template>

<script>
import { getBook, deleteBook } from '../../capabilities.js'

export default {
  name: 'BookPage',
  props: ['id'],
  data () {
    return { found: null, error: '' }
  },
  async mounted () {
    try {
      this.found = await getBook({ id: this.id })
    } catch (e) {
      this.error = e.message   // for example "There is no book #42"
    }
  },
  methods: {
    async remove () {
      if (!confirm('Delete "' + this.found.book.title + '"?')) return
      await deleteBook({ id: this.id })
      this.$router.push({ name: 'home' })
    }
  }
}
</script>
