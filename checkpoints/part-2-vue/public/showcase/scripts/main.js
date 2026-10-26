/*
 * main.js - Showcase as a Vue 3 app loaded as a library (no build step)
 * env and apiKey are injected by Caligrafy (views/Client/app.pug)
 */
const app = Vue.createApp({
    data () {
        return {
            projects: [],
            form: { title: '', description: '' },
            errors: {},
            env: env,
            config: {
                headers: {
                    'Authorization': 'Bearer ' + apiKey,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            }
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
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors;
                } else {
                    console.log(error);
                }
            }
        },
        async remove(project) {
            await axios.delete(this.env.home + 'projects/' + project.id, this.config);
            await this.load();
        }
    },
    mounted () {
        this.load();
    }
});

app.mount('#app');
