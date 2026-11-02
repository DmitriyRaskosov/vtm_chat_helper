<template>
    <div class="with-sidebar">
        <AppNav current="profile" />
        <div class="page-main">
            <header class="top">
                <h1>Личный кабинет</h1>
            </header>

            <p v-if="loading" class="muted">Загрузка персонажей…</p>
            <section v-else-if="storytellerDenied" class="card profile-empty">
                <p>Вы вошли как рассказчик. У рассказчика нет Личного кабинета. Для управления персонажами перейдите в Мастерскую</p>
                <RouterLink class="profile-workshop-link" :to="workshopPath">Мастерская</RouterLink>
            </section>
            <p v-else-if="error" class="error">{{ error }}</p>
            <p v-else-if="characters.length === 0" class="muted">
                У вас пока нет персонажей. Попросите вашего рассказчика создать персонажа и привязать его к вашему аккаунту.
            </p>

            <div v-else class="profile-grid">
                <article
                    v-for="row in characters"
                    :key="row.id"
                    class="card profile-card"
                    @click="openCharacter(row)"
                >
                    <button
                        type="button"
                        class="profile-avatar"
                        :disabled="!row.avatar_path"
                        @click.stop="openAvatar(row)"
                    >
                        <img v-if="row.avatar_path" :src="avatarUrl(row)" :alt="row.canonical_name" />
                        <span v-else class="muted">Нет аватара</span>
                    </button>
                    <RouterLink class="profile-name" :to="characterPath(row)" @click.stop>
                        {{ row.canonical_name }}
                    </RouterLink>
                    <div class="profile-actions" @click.stop>
                        <input
                            :ref="(el) => setAvatarInput(row.id, el)"
                            type="file"
                            accept="image/jpeg,image/png"
                            class="profile-file"
                            @change="onAvatarSelected(row, $event)"
                        />
                        <input
                            :ref="(el) => setSheetInput(row.id, el)"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            class="profile-file"
                            @change="onSheetSelected(row, $event)"
                        />
                        <button type="button" class="secondary" @click="avatarInputs[row.id]?.click()">
                            Загрузить аватар
                        </button>
                        <button type="button" class="secondary" @click="sheetInputs[row.id]?.click()">
                            Загрузить чарлист
                        </button>
                        <button type="button" class="secondary" @click="openSheet(row)">
                            Чарлист
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <AvatarModal v-model="avatarOpen" :src="avatarSrc" />

        <div v-if="sheetCharacter" class="diary-backdrop" @click.self="sheetCharacter = null">
            <div class="diary-modal card profile-sheet-modal">
                <header class="diary-modal-header">
                    <h2>{{ sheetCharacter.canonical_name }}</h2>
                    <button type="button" class="link" @click="sheetCharacter = null">Закрыть</button>
                </header>
                <SheetViewer :character-id="sheetCharacter.id" can-upload />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineAsyncComponent, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import AvatarModal from '../components/profile/AvatarModal.vue';
import { currentChronicleId, useAuth } from '../auth';
import { useProfile } from '../composables/useProfile';

const SheetViewer = defineAsyncComponent(() => import('../components/profile/SheetViewer.vue'));

const router = useRouter();
const auth = useAuth();
const {
    characters,
    loading,
    error,
    storytellerDenied,
    loadCharacters,
    uploadAvatar,
    uploadSheet,
} = useProfile();

const avatarOpen = ref(false);
const avatarSrc = ref('');
const sheetCharacter = ref(null);
const avatarInputs = reactive({});
const sheetInputs = reactive({});

onMounted(() => {
    loadCharacters().catch(() => {});
});

const workshopPath = computed(() => {
    const memberships = auth.user.value?.memberships ?? [];
    const storytellerMembership = memberships.find((membership) => (
        membership.role === 'storyteller'
        && (membership.left_at === null || membership.left_at === undefined)
    ));
    const chronicleId = currentChronicleId.value ?? storytellerMembership?.chronicle_id;

    return chronicleId ? `/c/${chronicleId}/workshop` : '/chronicles';
});

function characterPath(row) {
    return `/c/${row.chronicle_id}/characters/${row.id}`;
}

function openCharacter(row) {
    router.push(characterPath(row));
}

function avatarUrl(row) {
    return `/storage/${row.avatar_path}`;
}

function openAvatar(row) {
    if (!row.avatar_path) {
        return;
    }

    avatarSrc.value = avatarUrl(row);
    avatarOpen.value = true;
}

function openSheet(row) {
    sheetCharacter.value = row;
}

function setAvatarInput(id, el) {
    avatarInputs[id] = el;
}

function setSheetInput(id, el) {
    sheetInputs[id] = el;
}

async function onAvatarSelected(row, event) {
    const input = event.target;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    try {
        await uploadAvatar(row.id, file);
    } catch {
        // error уже в useProfile
    }
}

async function onSheetSelected(row, event) {
    const input = event.target;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    try {
        await uploadSheet(row.id, file);
    } catch {
        // error уже в useProfile
    }
}
</script>

<style scoped>
.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.profile-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    cursor: pointer;
    border-color: var(--gold);
}

.profile-avatar {
    margin: 0;
    padding: 0;
    width: 100%;
    aspect-ratio: 1;
    background: var(--bg-light);
    border: 1px solid var(--border);
    color: var(--text);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-name {
    font-family: var(--font-lore), serif;
    color: var(--gold);
    text-decoration: none;
    font-size: 1.15rem;
}

.profile-actions {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.profile-actions button {
    margin-top: 0;
}

.profile-file {
    display: none;
}

.profile-sheet-modal {
    max-width: 52rem;
}

.profile-empty p {
    margin-top: 0;
}

.profile-workshop-link {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.55rem 1rem;
    background: var(--accent);
    color: var(--on-accent);
    text-decoration: none;
}
</style>
