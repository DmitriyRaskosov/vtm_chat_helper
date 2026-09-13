<template>
    <div class="wrap" :class="{ wide: isWide }">
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
const isWide = computed(() => (
    (isStoryteller.value && route.name === 'chat')
    || route.name === 'characters'
    || route.name === 'character'
    || route.name === 'world'
));
</script>
