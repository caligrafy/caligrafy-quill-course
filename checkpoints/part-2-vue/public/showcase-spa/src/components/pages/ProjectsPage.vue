<template>
    <div>
        <show-message header="My Projects" message="A showcase of what I build with Caligrafy"></show-message>
        <div class="spacer-2"></div>
        <p class="horizontal-center small-text" v-if="store.loading">loading...</p>
        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4" v-for="project in store.projects" :key="project.id">
                <router-link :to="{ name: 'project', params: { id: project.id } }">
                    <div class="card animated zoomIn color0 equally-spaced-1 dropshadow">
                        <h2 class="medium-text semibold textcolor5 reset-margin">{{ project.title }}</h2>
                        <p class="textcolor5 default-text block-with-text">{{ project.description }}</p>
                    </div>
                </router-link>
            </div>
        </div>
        <div class="card color0 equally-spaced-1 dropshadow width-max-600 horizontal-center">
            <form @submit.prevent="add">
                <div class="mb-3">
                    <label class="default-text textcolor5" for="title">* title</label>
                    <input id="title" class="form-control input-default" v-model="form.title" maxlength="150">
                    <div class="text-danger small-text" v-if="store.errors.title">{{ store.errors.title }}</div>
                </div>
                <div class="mb-3">
                    <label class="default-text textcolor5" for="description">* description</label>
                    <textarea id="description" class="form-control input-default" rows="3" v-model="form.description"></textarea>
                    <div class="text-danger small-text" v-if="store.errors.description">{{ store.errors.description }}</div>
                </div>
                <input class="btn btn-secondary w-100" type="submit" value="add a project">
            </form>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue'
import { useProjectStore } from '@/common/store'
import ShowMessage from '../global/ShowMessage.vue'

const store = useProjectStore()
const form = reactive({ title: '', description: '' })

async function add() {
    if (await store.create({ ...form })) {
        form.title = ''
        form.description = ''
    }
}

onMounted(() => store.fetchAll())
</script>
