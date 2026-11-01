<!-- One book (get_book), and a Delete button (delete_book) -->
<template>
  <main v-if="found">
    <section class="cq-hero cq-hero--page">
      <h1 class="cq-display">{{ found.book.title }}</h1>
      <p class="cq-lead">by {{ found.author.name }}, {{ found.book.published_year }}</p>
    </section>
    <section class="cq-band cq-band--tight">
      <div class="cq-inner cq-inner--narrow">
        <p>{{ found.book.summary || 'No summary yet.' }}</p>
        <div class="cq-buttons">
          <button class="btn btn-secondary" type="button" @click="remove">Delete</button>
        </div>
        <p class="cq-auth-note"><router-link :to="{ name: 'home' }">All books</router-link></p>
      </div>
    </section>
  </main>

  <main v-else>
    <section class="cq-hero">
      <h1 class="cq-display">{{ error ? 'Not found' : 'Loading...' }}</h1>
      <p class="cq-lead">{{ error }}</p>
      <div class="cq-cta"><router-link class="btn btn-primary" :to="{ name: 'home' }">All books</router-link></div>
    </section>
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
