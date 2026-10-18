<template>
    <div class="wrap" :class="{ wide: isWide }">
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
import { isStoryteller } from './auth';

const route = useRoute();
const isWide = computed(() => (
    (isStoryteller.value && route.name === 'chronicle-chat')
    || route.name === 'characters'
    || route.name === 'character'
));

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
