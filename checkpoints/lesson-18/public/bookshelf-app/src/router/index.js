import { createRouter, createWebHashHistory } from 'vue-router'
import CatalogPage from '../components/pages/CatalogPage.vue'
import BookPage from '../components/pages/BookPage.vue'
import LoansPage from '../components/pages/LoansPage.vue'
import SignInPage from '../components/pages/SignInPage.vue'
import { signedIn } from '../mobile/session.js'

const router = createRouter({
  // hash history (#/page): the app's files are served by the phone itself, with no server
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: CatalogPage, meta: { signedIn: true } },
    { path: '/books/:id', name: 'book', component: BookPage, meta: { signedIn: true } },
    { path: '/loans', name: 'loans', component: LoansPage, meta: { signedIn: true } },
    { path: '/sign-in', name: 'sign-in', component: SignInPage },
  ]
})

// the pages that need someone signed in send visitors to the sign-in page, and back once they signed in
router.beforeEach(to => {
  if (to.meta.signedIn && !signedIn()) return { name: 'sign-in', query: { back: to.fullPath } }
  if (to.name === 'sign-in' && signedIn()) return { name: 'home' }
})

export default router
