/*
 * store.js - Showcase state with Pinia (replaces the Vuex store of the original course)
 *   import { useProjectStore } from '@/common/store'
 *   const store = useProjectStore()
 */
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
    state: () => ({
        projects: [],
        current: null,
        errors: {},
        loading: false
    }),

    getters: {
        count: (state) => state.projects.length
    },

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
                this.current = response.data;
            } catch (error) {
                if (error.response?.status !== 404) throw error;   // 404: current stays null
            }
        },
        async create(project) {
            this.errors = {};
            try {
                const response = await api.post('projects', project);   // 201 Created
                this.projects.unshift(response.data);
                return true;
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors;
                    return false;
                }
                throw error;
            }
        },
        async update(id, changes) {
            const response = await api.put('projects/' + id, changes);
            this.current = response.data;
        },
        async remove(id) {
            await api.delete('projects/' + id);
            this.projects = this.projects.filter((project) => project.id !== id);
        }
    }
});
