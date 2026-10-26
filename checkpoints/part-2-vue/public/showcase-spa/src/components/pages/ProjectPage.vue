<template>
    <div class="width-max-800 horizontal-center">
        <div class="card color0 equally-spaced-1 dropshadow" v-if="store.current">
            <template v-if="!editing">
                <h2 class="medium-text semibold">{{ store.current.title }}</h2>
                <p class="default-text">{{ store.current.description }}</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary" @click="startEdit">edit</button>
                    <button class="btn btn-outline-dark" @click="remove">delete</button>
                </div>
            </template>
            <form v-else @submit.prevent="save">
                <div class="mb-3">
                    <input class="form-control input-default" v-model="draft.title" maxlength="150">
                </div>
                <div class="mb-3">
                    <textarea class="form-control input-default" rows="4" v-model="draft.description"></textarea>
                </div>
                <input class="btn btn-secondary w-100" type="submit" value="save">
            </form>
        </div>
        <h2 class="medium-text semibold horizontal-center" v-else>Project not found</h2>
        <div class="spacer-2"></div>
        <router-link :to="{ name: 'projects' }">back to my projects</router-link>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/common/store'

const props = defineProps({ id: { type: String, required: true } })
const store = useProjectStore()
const router = useRouter()
const editing = ref(false)
const draft = reactive({ title: '', description: '' })

watch(() => props.id, (id) => store.fetchOne(id), { immediate: true })

function startEdit() {
    draft.title = store.current.title
    draft.description = store.current.description
    editing.value = true
}

async function save() {
    await store.update(props.id, { ...draft })
    editing.value = false
}

async function remove() {
    await store.remove(Number(props.id))
    router.push({ name: 'projects' })
}
</script>
