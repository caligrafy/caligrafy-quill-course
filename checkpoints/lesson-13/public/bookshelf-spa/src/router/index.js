import { createRouter, createWebHashHistory } from 'vue-router'
import BooksPage from '../components/pages/BooksPage.vue'
import BookPage from '../components/pages/BookPage.vue'
import NewBookPage from '../components/pages/NewBookPage.vue'

// Hash history: the page URLs look like index.html#/books/3, so they work from any folder, without server rules
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: BooksPage },
    { path: '/books/new', name: 'new-book', component: NewBookPage },
    { path: '/books/:id', name: 'book', component: BookPage, props: true }
  ]
})

export default router
