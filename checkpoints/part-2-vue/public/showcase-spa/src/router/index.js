import { createRouter, createWebHashHistory } from 'vue-router'
import ProjectsPage from '../components/pages/ProjectsPage.vue'

// Hash history (#/projects/1): deep links work without server rewrite rules
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'projects', component: ProjectsPage },
    // route-level code splitting: loaded when visited
    { path: '/projects/:id', name: 'project', component: () => import('../components/pages/ProjectPage.vue'), props: true }
  ]
})

export default router
