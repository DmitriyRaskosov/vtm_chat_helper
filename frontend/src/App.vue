<template>
    <div class="wrap" :class="wrapClass">
        <RouterView />
        <div v-if="showScrollButtons" class="scroll-float">
            <button type="button" class="secondary" @click="scrollTop">Наверх</button>
            <button type="button" class="secondary" @click="scrollBottom">Вниз</button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
const route = useRoute();
const shellRoutes = [
    'chronicle-chat',
    'characters',
    'character',
    'character-diary',
    'chronicles',
    'workshop',
];
const wrapClass = computed(() => ({
    'app-frame': shellRoutes.includes(route.name),
    'app-frame-chat': route.name === 'chronicle-chat',
}));

const showScrollButtons = computed(() => (
    route.name === 'characters'
    || route.name === 'character'
));

function scrollTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollBottom() {
    const root = document.documentElement;
    window.scrollTo({ top: root.scrollHeight, behavior: 'smooth' });
}
</script>
