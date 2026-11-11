<template>
    <nav class="sidebar">
        <RouterLink :to="chatLink" class="nav-icon" :class="{ active: current === 'chat' }" title="Чат">
            <span class="nav-glyph" aria-hidden="true">💬</span>
            <span class="nav-label">Чат</span>
        </RouterLink>
        <RouterLink :to="charactersLink" class="nav-icon" :class="{ active: current === 'characters' }" title="Персонажи">
            <span class="nav-glyph" aria-hidden="true">👤</span>
            <span class="nav-label">Персонажи</span>
        </RouterLink>
        <RouterLink to="/profile" class="nav-icon" :class="{ active: current === 'profile' }" title="Личный кабинет">
            <span class="nav-glyph" aria-hidden="true">🎭</span>
            <span class="nav-label">Кабинет</span>
        </RouterLink>
        <div class="sidebar-anchor">
            <button
                type="button"
                class="nav-icon nav-icon-btn"
                :class="{ active: current === 'chronicles' || chroniclesOpen }"
                title="Хроники"
                @click="chroniclesOpen = !chroniclesOpen"
            >
                <span class="nav-glyph" aria-hidden="true">🌍</span>
                <span class="nav-label">Хроники</span>
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
            <span class="nav-glyph" aria-hidden="true">⚙️</span>
            <span class="nav-label">Мастерская</span>
        </RouterLink>
        <button
            type="button"
            class="nav-icon nav-icon-btn"
            title="Сменить тему"
            @click="toggleTheme"
        >
            <span class="nav-glyph" aria-hidden="true">🌓</span>
            <span class="nav-label">Тема</span>
        </button>
        <button
            type="button"
            class="nav-icon nav-icon-btn"
            :class="{ active: !soundsMuted }"
            :title="soundsMuted ? 'Звуки выключены' : 'Звуки включены'"
            @click="toggleSoundsMuted"
        >
            <span class="nav-glyph" aria-hidden="true">{{ soundsMuted ? '🔕' : '🔔' }}</span>
            <span class="nav-label">Звуки</span>
        </button>
        <button
            type="button"
            class="nav-icon nav-icon-btn"
            :title="logoutTitle"
            @click="logout"
        >
            <span class="nav-glyph" aria-hidden="true">↪</span>
            <span class="nav-label">Выход</span>
        </button>
    </nav>
</template>

<style scoped>
.sidebar {
    width: auto;
    min-width: 5.5rem;
    align-items: stretch;
    padding-right: 0.35rem;
    padding-left: 0.35rem;
}

.nav-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin: 0;
    padding: 0;
    gap: 0.15rem;
    font-size: 1.5rem;
}

.sidebar-anchor {
    display: flex;
    justify-content: center;
    width: 100%;
}

.nav-label {
    font-family: var(--font-ui);
    font-size: 0.62rem;
    line-height: 1.1;
    letter-spacing: 0.02em;
}

.sidebar-popover {
    left: 100%;
}

@media (max-width: 1280px) {
    .sidebar {
        width: 60px;
        min-width: 60px;
        padding-right: 0;
        padding-left: 0;
    }

    .nav-label {
        display: none;
    }
}
</style>

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
        validator: (value) => value == null || ['chat', 'characters', 'chronicles', 'workshop', 'profile'].includes(value),
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
