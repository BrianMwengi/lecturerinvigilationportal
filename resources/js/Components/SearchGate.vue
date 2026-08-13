<script setup>
import { ref } from 'vue';

defineProps({
    isSearching: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
});

const emit = defineEmits(['search-submitted']);

const invigilatorFullName = ref('');

function submitSearch() {
    if (!invigilatorFullName.value.trim()) return;
    emit('search-submitted', invigilatorFullName.value.trim());
}
</script>

<template>
    <section class="search-gate">
        <h1>Lecturer Invigilation Portal</h1>

        <form @submit.prevent="submitSearch">
            <label for="invigilator-name">Enter Full Name</label>
            <input
                id="invigilator-name"
                v-model="invigilatorFullName"
                type="text"
                placeholder="e.g. Mr. Anthony Wambua"
                autocomplete="off"
            />
            <button type="submit" :disabled="isSearching">
                {{ isSearching ? 'Searching…' : 'Find My Schedule' }}
            </button>
        </form>

        <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>
    </section>
</template>