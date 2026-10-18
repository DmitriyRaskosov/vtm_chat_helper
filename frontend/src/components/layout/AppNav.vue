<template>
    <div class="top">
        <h1>
            <ChronicleSwitcher />
            <RouterLink to="/chat" :class="{ 'nav-current': current === 'chat' }">Чат</RouterLink>
            <span class="nav-sep">·</span>
            <RouterLink to="/characters" :class="{ 'nav-current': current === 'characters' }">Персонажи</RouterLink>
            <span class="nav-sep">·</span>
            <RouterLink to="/chronicles" :class="{ 'nav-current': current === 'chronicles' }">Хроники</RouterLink>
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
import { RouterLink, useRouter } from 'vue-router';
import ChronicleSwitcher from './ChronicleSwitcher.vue';
import { isStoryteller, useAuth } from '../../auth';

defineProps({
    current: {
        type: String,
        default: null,
        validator: (value) => value == null || ['chat', 'characters', 'chronicles'].includes(value),
    },
});

const auth = useAuth();
const router = useRouter();

async function logout() {
    await auth.logout();
    await router.push('/login');
}
</script>
