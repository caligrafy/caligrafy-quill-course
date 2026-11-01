// The finder: a Vue app on the same capabilities as the pages, REST and AI agents
const app = Vue.createApp({
  data () {
    return {
      query: '',
      books: [],
      selected: null,   // { book, author } from get_book
      error: '',
      timer: null
    }
  },
  async mounted () {
    // the generated JS client: one function per capability (php caligrafer.php client public/js/capabilities.js)
    this.api = await import(env.home + 'public/js/capabilities.js')
    this.books = await this.api.listBooks()
  },
  methods: {
    // called on every key: wait until the typing pauses, then ask search_books
    search () {
      clearTimeout(this.timer)
      this.timer = setTimeout(() => this.run(async () => {
        this.books = this.query.trim().length < 2
          ? await this.api.listBooks()
          : await this.api.searchBooks({ query: this.query })
      }), 250)
    },
    open (id) {
      this.run(async () => { this.selected = await this.api.getBook({ id }) })
    },
    // every call: clear the error, run it, show its message if it is refused
    async run (action) {
      this.error = ''
      try {
        await action()
      } catch (e) {
        this.error = e.name === 'CapabilityError' ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong. Try again.'
      }
    }
  }
})

app.mount('#app')
