<!-- The form to add a book: route '/books/new' -->
<template>
  <div class="wrapper width-max-600">
    <div class="card color0 dropshadow equally-spaced-1">
      <h2 class="medium-text semibold reset-margin">Add a book</h2>
      <div class="spacer-1"></div>
      <p class="error" v-if="store.message">{{ store.message }}</p>
      <form @submit.prevent="save">
        <div class="mb-3">
          <label class="default-text textcolor5" for="title">* title</label>
          <input class="form-control" id="title" type="text" v-model="form.title">
          <p class="error" v-if="store.errors.title">{{ store.errors.title }}</p>
        </div>
        <div class="mb-3">
          <label class="default-text textcolor5" for="author_id">* author</label>
          <select class="form-select" id="author_id" v-model="form.author_id">
            <option value="">Choose an author</option>
            <option v-for="author in store.authors" :key="author.id" :value="author.id">{{ author.name }}</option>
          </select>
          <p class="error" v-if="store.errors.author_id">{{ store.errors.author_id }}</p>
        </div>
        <div class="mb-3">
          <label class="default-text textcolor5" for="published_year">* year</label>
          <input class="form-control" id="published_year" type="number" v-model="form.published_year">
          <p class="error" v-if="store.errors.published_year">{{ store.errors.published_year }}</p>
        </div>
        <div class="mb-3">
          <label class="default-text textcolor5" for="summary">summary</label>
          <textarea class="form-control" id="summary" rows="3" v-model="form.summary"></textarea>
          <p class="error" v-if="store.errors.summary">{{ store.errors.summary }}</p>
        </div>
        <input class="btn btn-primary" type="submit" value="Save">
      </form>
    </div>
    <div class="spacer-2"></div>
    <p class="horizontal-center">
      <router-link class="btn btn-secondary" :to="{ name: 'home' }">Cancel</router-link>
    </p>
  </div>
</template>

<script>
import { useBookStore } from '../../common/store.js'

export default {
  name: 'NewBookPage',
  data() {
    return {
      store: useBookStore(),
      form: { title: '', author_id: '', published_year: '', summary: '' }
    }
  },
  mounted() {
    this.store.errors = {}
    this.store.message = ''
    this.store.fetchAuthors()
  },
  methods: {
    async save() {
      const book = await this.store.createBook(this.form)
      if (book) {
        this.$router.push({ name: 'book', params: { id: book.id } })
      }
    }
  }
}
</script>
