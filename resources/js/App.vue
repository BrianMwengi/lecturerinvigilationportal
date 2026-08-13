<script setup>
import { ref } from 'vue';
import SearchGate from './Components/SearchGate.vue';
import Dashboard from './Components/Dashboard.vue';

const searchResult = ref(null);
const searchErrorMessage = ref('');
const isSearching = ref(false);

async function handleSearchSubmitted(invigilatorFullName) {
    isSearching.value = true;
    searchErrorMessage.value = '';

    try {
        const response = await fetch('/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ name: invigilatorFullName }),
        });

        if (!response.ok) {
            const errorBody = await response.json();
            searchErrorMessage.value = errorBody.message ?? 'Something went wrong.';
            return;
        }

        searchResult.value = await response.json();
    } catch {
        searchErrorMessage.value = 'Unable to reach the server. Please try again.';
    } finally {
        isSearching.value = false;
    }
}

function resetToSearchGate() {
    searchResult.value = null;
    searchErrorMessage.value = '';
}
</script>

<template>
    <Dashboard
        v-if="searchResult"
        :invigilator-name="searchResult.invigilator_name"
        :verified="searchResult.verified"
        :schedule="searchResult.schedule"
        @search-again="resetToSearchGate"
    />
    <SearchGate
        v-else
        :is-searching="isSearching"
        :error-message="searchErrorMessage"
        @search-submitted="handleSearchSubmitted"
    />
</template>