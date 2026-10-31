/*
 * store.js - the Bookshelf state, shared by every page (Pinia)
 *   import { useBookStore } from '@/common/store'
 *   const store = useBookStore()
 */
import { defineStore } from 'pinia'
import { callApi, apiConfig } from './app.js'

// framework and API errors answer {"error": {"code": ..., "message": "..."}}
function errorMessage(error) {
  const data = error.response ? error.response.data : null
  return data && data.error ? data.error.message : 'The server could not be reached. Is Caligrafy running?'
}

export const useBookStore = defineStore('books', {
  // the shared data
  state: () => ({
    books: [],
    authors: [],
    current: null,      // the book of the book page
    currentAuthor: null,
    errors: {},         // one message per invalid field, from a 422 answer
    message: '',        // the last error message
    loading: false
  }),

  // values computed from the state
  getters: {
    count: (state) => state.books.length
  },

  // functions that change the state; they call the API
  actions: {
    async fetchBooks() {
      this.loading = true
      this.message = ''
      try {
        const response = await callApi.get('/api/books', apiConfig)
        this.books = response.data.books
      } catch (error) {
        this.message = errorMessage(error)
      } finally {
        this.loading = false
      }
    },

    async fetchAuthors() {
      try {
        const response = await callApi.get('/api/authors', apiConfig)
        this.authors = response.data.authors
      } catch (error) {
        this.message = errorMessage(error)
      }
    },

    async fetchBook(id) {
      this.current = null
      this.currentAuthor = null
      this.message = ''
      try {
        const response = await callApi.get('/api/books/' + id, apiConfig)
        this.current = response.data.book
        this.currentAuthor = response.data.author
      } catch (error) {
        this.message = errorMessage(error)
      }
    },

    // returns the new book, or null when the API refused it
    async createBook(book) {
      this.errors = {}
      this.message = ''
      try {
        const response = await callApi.post('/api/books', book, apiConfig)   // 201 Created
        this.books.push(response.data.book)
        return response.data.book
      } catch (error) {
        if (error.response && error.response.status === 422) {
          this.errors = error.response.data.errors
        } else {
          this.message = errorMessage(error)
        }
        return null
      }
    },

    // returns true when the book was deleted
    async deleteBook(id) {
      this.message = ''
      try {
        await callApi.delete('/api/books/' + id, apiConfig)
        this.books = this.books.filter((book) => book.id !== id)
        return true
      } catch (error) {
        this.message = errorMessage(error)
        return false
      }
    }
  }
})
