# 09 Pinia and the Caligrafy API

**Goal:** one Pinia store owns the projects and every API call; pages only call its actions.
Pinia replaces the Vuex store of the original course (no mutations, actions can be async, full TypeScript support).

## Manual
`src/common/store.js` ([full file](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-2-vue/public/showcase-spa/src/common/store.js)):
```js
import { defineStore } from 'pinia'
import axios from 'axios'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_ROUTE || '/',
    headers: {
        'Authorization': 'Bearer ' + import.meta.env.VITE_API_KEY,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
});

export const useProjectStore = defineStore('projects', {
    state: () => ({ projects: [], current: null, errors: {}, loading: false }),
    getters: { count: (state) => state.projects.length },
    actions: {
        async fetchAll() {
            this.loading = true;
            try {
                const response = await api.get('projects');
                this.projects = response.data.projects ?? [];
            } finally {
                this.loading = false;
            }
        },
        async fetchOne(id) {
            this.current = null;
            try {
                const response = await api.get('projects/' + id);
                this.current = response.data.project ?? null;   // view() answers {"project": ...}
            } catch (error) {
                if (error.response?.status !== 404) throw error;
            }
        },
        async create(project) {
            this.errors = {};
            try {
                const response = await api.post('projects', project);   // 201
                this.projects.unshift(response.data);
                return true;
            } catch (error) {
                if (error.response?.status === 422) { this.errors = error.response.data.errors; return false; }
                throw error;
            }
        },
        async update(id, changes) { this.current = (await api.put('projects/' + id, changes)).data; },
        async remove(id) {
            await api.delete('projects/' + id);
            this.projects = this.projects.filter((project) => project.id !== id);
        }
    }
});
```
In a page: `const store = useProjectStore()`, `onMounted(() => store.fetchAll())`, `v-for="project in store.projects"`,
`store.errors.title`. The status codes of lesson 06 drive the UI: 201 adds, 422 shows errors, 404 shows "Project not found".
Note the two JSON shapes: routes answered by `view()` wrap the data (`{"projects": [...]}`, `{"project": {...}}`),
routes answered by `api($saved)` return the model itself.

## With Claude
Ask: *"Create a Pinia store useProjectStore in src/common/store.js that wraps the Showcase API (fetchAll, fetchOne,
create with 422 errors, update, remove) using VITE_API_KEY, and use it in ProjectsPage and ProjectPage."*

**Check:** one axios instance with the Bearer key from `import.meta.env` (never hard-coded); `fetchOne` reads
`response.data.project` (Claude may assume the bare model: test the project page!); errors are per field; no Vuex.

**Prefer** Manual for the first store (state/getters/actions is the mental model of Part 2), Claude for new actions.
