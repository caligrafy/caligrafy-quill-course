import { createRouter, createWebHashHistory } from 'vue-router'
import BooksPage from '../components/pages/BooksPage.vue'
import BookPage from '../components/pages/BookPage.vue'

// Hash addresses (#/books/3): they work in development and in the built app, from any folder
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: BooksPage },
    { path: '/books/:id', name: 'book', component: BookPage, props: true }
  ]
})

export default router
