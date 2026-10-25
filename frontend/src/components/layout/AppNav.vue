<template>
    <div class="top">
        <h1>
            <ChronicleSwitcher />
            <RouterLink :to="chatLink" :class="{ 'nav-current': current === 'chat' }">Чат</RouterLink>
            <span class="nav-sep">·</span>
            <RouterLink :to="charactersLink" :class="{ 'nav-current': current === 'characters' }">Персонажи</RouterLink>
            <span class="nav-sep">·</span>
            <RouterLink to="/chronicles" :class="{ 'nav-current': current === 'chronicles' }">Хроники</RouterLink>
        </h1>
        <span class="muted nav-user">
            <label class="toggle nav-sounds-toggle" title="Звуковые оповещения в чате">
                <input v-model="soundsMuted" type="checkbox" />
                Без звуков
            </label>
            {{ auth.user.value?.name }}
            <template v-if="currentChronicleRole === 'storyteller'"> · рассказчик</template>
            <template v-else-if="currentChronicleRole === 'player'"> · игрок</template>
            ·
            <button class="link" type="button" @click="logout">Выйти</button>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ChronicleSwitcher from './ChronicleSwitcher.vue';
import { currentChronicleRole, useAuth } from '../../auth';
import { resolveChronicleId, useCurrentChronicle } from '../../composables/useCurrentChronicle';
import { useSoundSettings } from '../../composables/useSoundSettings';

const { soundsMuted } = useSoundSettings();

defineProps({
    current: {
        type: String,
        default: null,
        validator: (value) => value == null || ['chat', 'characters', 'chronicles'].includes(value),
    },
});

const auth = useAuth();
const router = useRouter();
const route = useRoute();
const { currentId } = useCurrentChronicle();

const activeChronicleId = computed(() => {
    const fromRoute = resolveChronicleId(route.params.chronicleId);

    if (fromRoute !== null) {
        return fromRoute;
    }

    return currentId.value;
});

const chatLink = computed(() => (
    activeChronicleId.value
        ? `/c/${activeChronicleId.value}/chat`
        : '/chronicles'
));

const charactersLink = computed(() => (
    activeChronicleId.value
        ? `/c/${activeChronicleId.value}/characters`
        : '/chronicles'
));

async function logout() {
    await auth.logout();
    await router.push('/login');
}
</script>
