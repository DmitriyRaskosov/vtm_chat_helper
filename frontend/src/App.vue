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

const showScrollButtons = computed(() => (
    route.name === 'characters'
    || route.name === 'character'
    || route.name === 'world'
));

function scrollTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollBottom() {
    const root = document.documentElement;
    window.scrollTo({ top: root.scrollHeight, behavior: 'smooth' });
}
</script>
