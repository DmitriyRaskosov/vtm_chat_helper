<template>
    <div class="wrap" :class="{ wide: isStoryteller && route.name === 'chat' }">
        <RouterView />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { useAuth } from './auth';

const route = useRoute();
const auth = useAuth();
const isStoryteller = computed(() => auth.user.value?.is_storyteller === true);
</script>
