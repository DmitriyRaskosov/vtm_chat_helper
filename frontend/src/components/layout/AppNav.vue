<template>
    <div class="top">
        <h1>
            <RouterLink to="/chat" :class="{ 'nav-current': current === 'chat' }">Чат</RouterLink>
            <span class="nav-sep">·</span>
            <RouterLink to="/characters" :class="{ 'nav-current': current === 'characters' }">Персонажи</RouterLink>
            <template v-if="isStoryteller">
                <span class="nav-sep">·</span>
                <RouterLink to="/world" :class="{ 'nav-current': current === 'world' }">Мир</RouterLink>
            </template>
        </h1>
        <span class="muted">
            {{ auth.user.value?.name }}
            <template v-if="isStoryteller"> · рассказчик</template>
            ·
            <button class="link" type="button" @click="logout">Выйти</button>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuth } from '../../auth';

defineProps({
    current: {
        type: String,
        default: null,
        validator: (value) => value == null || ['chat', 'characters', 'world'].includes(value),
    },
});

const auth = useAuth();
const router = useRouter();
const isStoryteller = computed(() => auth.user.value?.is_storyteller === true);

async function logout() {
    await auth.logout();
    await router.push('/login');
}
</script>
