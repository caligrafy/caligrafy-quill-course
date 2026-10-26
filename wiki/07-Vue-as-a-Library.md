# 07 Vue as a library

**Goal:** the projects list and an "add" form rendered by Vue 3.5 in the browser, with no build step, served by
Caligrafy at http://localhost:8080/apps/showcase and talking to the Part 1 API.

## Manual
```bash
php caligrafer.php app showcase      # Docker: docker-compose exec dev-box php caligrafer.php app showcase
```
This copies the client app template to `public/showcase` (`index.php` + `scripts/main.js`). The generic route
`Route::get('/apps/{appName}', 'Caligrafy\\Controllers\\ClientController');` (already in `web.php`) serves it; for a
dedicated URL add `Route::get('/showcase', function() { return view('Client/app', ['appName' => 'showcase', 'app' => 'index']); });`
(the path must end with the folder name).

Replace the `#app` div of `public/showcase/index.php` with the markup ([full file](https://github.com/caligrafy/caligrafy-quill-course/blob/main/checkpoints/part-2-vue/public/showcase/index.php)), and write
`public/showcase/scripts/main.js`:
```js
const app = Vue.createApp({
    data () {
        return {
            projects: [],
            form: { title: '', description: '' },
            errors: {},
            env: env,                                  // injected by Caligrafy
            config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' } }
        }
    },
    methods: {
        async load() {
            const response = await axios.get(this.env.home + 'projects', this.config);
            this.projects = response.data.projects ?? [];
        },
        async add() {
            try {
                await axios.post(this.env.home + 'projects', this.form, this.config);   // 201 Created
                this.form = { title: '', description: '' };
                this.errors = {};
                await this.load();
            } catch (error) {
                if (error.response?.status === 422) this.errors = error.response.data.errors;
            }
        },
        async remove(project) {
            await axios.delete(this.env.home + 'projects/' + project.id, this.config);
            await this.load();
        }
    },
    mounted () { this.load(); }
});
app.mount('#app');
```
Vue 2 -> Vue 3 changes from the original course: `Vue.createApp({...}).mount('#app')` instead of
`new Vue({ el })`, `data` is always a function, filters are gone (use methods or computed).
**Security:** client app pages expose the `API_KEY` to the browser (G27): anyone who can open the page can call the
API. Protect the page route (for example with a controller that checks `authorized()`) before going to production.

## With Claude
```
/caligrafy-quill:new-app app showcase
```
then: *"In public/showcase show the projects from GET /projects as cards in the Caligrafy black and white style, with
a form that POSTs a new project and shows the 422 errors per field, and a delete button."*

**Check:** the requests send `Accept: application/json` and the Bearer key; 422 errors are displayed, not swallowed;
Vue 3 syntax only (no `new Vue`, no filters); Bootstrap 5 classes; Claude mentioned the G27 exposure.

**Prefer** Manual to understand how Caligrafy hands `env` and `apiKey` to the page; Claude for the markup.
