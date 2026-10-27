<template>
    <nav class="sidebar">
        <RouterLink :to="chatLink" class="nav-icon" :class="{ active: current === 'chat' }" title="Чат">📖</RouterLink>
        <RouterLink :to="charactersLink" class="nav-icon" :class="{ active: current === 'characters' }" title="Персонажи">👤</RouterLink>
        <div class="sidebar-anchor">
            <button
                type="button"
                class="nav-icon nav-icon-btn"
                :class="{ active: current === 'chronicles' || chroniclesOpen }"
                title="Хроники"
                @click="chroniclesOpen = !chroniclesOpen"
            >
                🌍
            </button>
            <div v-if="chroniclesOpen" class="sidebar-popover">
                <ChronicleSwitcher />
                <RouterLink class="sidebar-popover-link" to="/chronicles" @click="chroniclesOpen = false">
                    Все хроники
                </RouterLink>
            </div>
        </div>
        <div class="sidebar-spacer"></div>
        <RouterLink
            v-if="showWorkshopNav"
            :to="workshopLink"
            class="nav-icon"
            :class="{ active: current === 'workshop' }"
            title="Мастерская"
        >
            ⚙️
        </RouterLink>
        <button
            type="button"
            class="nav-icon nav-icon-btn"
            title="Сменить тему"
            @click="toggleTheme"
        >
            🌓
        </button>
        <button
            type="button"
            class="nav-icon nav-icon-btn"
            :class="{ active: !soundsMuted }"
            :title="soundsMuted ? 'Звуки выключены' : 'Звуки включены'"
            @click="toggleSoundsMuted"
        >
            {{ soundsMuted ? '🔕' : '🔔' }}
        </button>
        <button
            type="button"
            class="nav-icon nav-icon-btn"
            :title="logoutTitle"
            @click="logout"
        >
            ↪
        </button>
    </nav>
</template>

<script setup>
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ChronicleSwitcher from './ChronicleSwitcher.vue';
import { currentChronicleRole, hasAnyStorytellerRole, isStoryteller, useAuth } from '../../auth';
import { resolveChronicleId, useCurrentChronicle } from '../../composables/useCurrentChronicle';
import { useSoundSettings } from '../../composables/useSoundSettings';
import { useTheme } from '../../composables/useTheme';

const { soundsMuted, toggleSoundsMuted } = useSoundSettings();
const { toggleTheme } = useTheme();

defineProps({
    current: {
        type: String,
        default: null,
        validator: (value) => value == null || ['chat', 'characters', 'chronicles', 'workshop'].includes(value),
    },
});

const auth = useAuth();
const router = useRouter();
const route = useRoute();
const { currentId } = useCurrentChronicle();
const chroniclesOpen = ref(false);

const showWorkshopNav = computed(() =>
    hasAnyStorytellerRole.value
    || isStoryteller.value
    || route.name === 'chronicles',
);

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

const workshopLink = computed(() => (
    activeChronicleId.value
        ? `/c/${activeChronicleId.value}/workshop`
        : '/chronicles'
));

const logoutTitle = computed(() => {
    const name = auth.user.value?.name ?? '';
    const role = currentChronicleRole.value === 'storyteller'
        ? 'рассказчик'
        : (currentChronicleRole.value === 'player' ? 'игрок' : '');

    return ['Выйти', name, role].filter(Boolean).join(' · ');
});

async function logout() {
    await auth.logout();
    await router.push('/login');
}
</script>
