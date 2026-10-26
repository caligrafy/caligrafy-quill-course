# 08 Vite SPA and Vue Router

**Goal:** a single page application (Vite 8, Vue 3.5, Vue Router 5) in `public/showcase-spa` with a list page and a
project page.

## Manual
```bash
php caligrafer.php create showcase-spa    # copies the Vite template and runs npm install (Node 20.19+ / 22.12+)
cd public/showcase-spa
cp .env.example .env                      # VITE_API_KEY=<your API_KEY>; add .env to .gitignore
npm run dev                               # http://localhost:5173
```
The SPA lives in `public/` so it can import the Caligrafy theme (`../../css/styles.css`, `theme.css`) and keep the
black and white look. Changed files ([checkpoint](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-2-vue/public/showcase-spa)):

`vite.config.js`: relative `base: './'` and a dev proxy so `/projects` reaches Caligrafy without CORS:
```js
base: './',
server: { proxy: { '/projects': 'http://localhost:8080' } }
```
`src/router/index.js`:
```js
import { createRouter, createWebHashHistory } from 'vue-router'
import ProjectsPage from '../components/pages/ProjectsPage.vue'

const router = createRouter({
  history: createWebHashHistory(),          // #/projects/1: deep links work without rewrite rules
  routes: [
    { path: '/', name: 'projects', component: ProjectsPage },
    { path: '/projects/:id', name: 'project', component: () => import('../components/pages/ProjectPage.vue'), props: true }
  ]
})
export default router
```
Pages: [`ProjectsPage.vue`](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-2-vue/public/showcase-spa/src/components/pages/ProjectsPage.vue) and
[`ProjectPage.vue`](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-2-vue/public/showcase-spa/src/components/pages/ProjectPage.vue) use `<script setup>` and
`<router-link :to="{ name: 'project', params: { id: project.id } }">`. They read their data from the store of lesson 09.

Build and serve from Caligrafy:
```bash
npm run build      # -> public/showcase-spa/dist, open http://localhost:8080/public/showcase-spa/dist/index.html
```
Changes from the original myvue app: Vite 8 instead of Vue CLI/webpack, `eslint.config.js` (flat config),
Vue Router 5 `createRouter`, `<script setup>`.

## With Claude
```
/caligrafy-quill:new-app create showcase-spa
```
then: *"Add a projects list page and a project page (route /projects/:id, lazy loaded) with hash history, a Vite dev
proxy for /projects to http://localhost:8080 and base './'."*

**Check:** `npm run build` succeeds; `.env` is git-ignored and only `.env.example` is committed; the pages
keep the Caligrafy classes; `ProjectPage` reloads when the `id` changes (a `watch`, not only `onMounted`).

**Prefer** Claude for the scaffolding and config, Manual for the router: you will edit routes all the time.
