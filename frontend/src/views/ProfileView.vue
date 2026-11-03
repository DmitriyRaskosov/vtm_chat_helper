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
            <template v-else>
                <div class="profile-toolbar">
                    <label class="toggle">
                        <input v-model="showArchived" type="checkbox" />
                        Показывать архивных
                    </label>
                    <button type="button" @click="createOpen = true">Создать персонажа</button>
                </div>
                <p v-if="visibleCharacters.length === 0" class="muted">{{ emptyLabel }}</p>
                <div v-else class="profile-grid">
                    <article
                        v-for="row in visibleCharacters"
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
                            <img
                                v-if="row.avatar_path"
                                :key="avatarImageKey(row)"
                                :src="avatarUrl(row)"
                                :alt="row.canonical_name"
                            />
                            <span v-else class="muted">Нет аватара</span>
                        </button>
                        <RouterLink class="profile-name" :to="characterPath(row)" @click.stop>
                            {{ row.canonical_name }}
                        </RouterLink>
                        <p v-if="isArchived(row)" class="muted">В архиве</p>
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
                            <button
                                v-if="!isArchived(row)"
                                type="button"
                                class="secondary"
                                @click="onArchive(row)"
                            >
                                В архив
                            </button>
                            <button
                                v-if="!isArchived(row) && activeSceneId != null"
                                type="button"
                                class="secondary"
                                :disabled="scenePresenceLoading || sceneBusyId === row.id"
                                @click="toggleScenePresence(row)"
                            >
                                {{ isCharacterOnScene(row.id) ? 'Покинуть сцену' : 'Выйти на сцену' }}
                            </button>
                            <template v-else>
                                <button type="button" class="secondary" @click="onRestore(row)">
                                    Восстановить
                                </button>
                                <button type="button" class="secondary" @click="askDelete(row)">
                                    Удалить навсегда
                                </button>
                            </template>
                        </div>
                    </article>
                </div>
            </template>
        </div>

        <AvatarModal v-model="avatarOpen" :src="avatarSrc" />
        <CreateCharacterModal
            v-if="createOpen"
            v-model="createOpen"
            :chronicle-id="currentChronicleId"
            @created="onCharacterCreated"
        />

        <div v-if="pendingDelete" class="diary-backdrop" @click.self="pendingDelete = null">
            <div class="diary-modal card">
                <h2>Удалить навсегда?</h2>
                <p>{{ pendingDelete.canonical_name }} исчезнет из кабинета. Вернуть его будет нельзя.</p>
                <div class="profile-confirm-actions">
                    <button type="button" @click="confirmDelete">Удалить навсегда</button>
                    <button type="button" class="secondary" @click="pendingDelete = null">Отмена</button>
                </div>
            </div>
        </div>

        <div v-if="sheetCharacter" class="diary-backdrop" @click.self="sheetCharacter = null">
            <div class="diary-modal card profile-sheet-modal">
                <header class="diary-modal-header">
                    <h2>{{ sheetCharacter.canonical_name }}</h2>
                    <button type="button" class="link" @click="sheetCharacter = null">Закрыть</button>
                </header>
                <SheetViewer
                    :key="sheetViewerKey(sheetCharacter.id)"
                    :character-id="sheetCharacter.id"
                    can-upload
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import AvatarModal from '../components/profile/AvatarModal.vue';
import CreateCharacterModal from '../components/profile/CreateCharacterModal.vue';
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
    archiveCharacter,
    restoreCharacter,
    deleteCharacter,
    avatarUrl,
    avatarImageKey,
    sheetViewerKey,
    isCharacterOnScene,
    loadScenePresence,
    enterCharacterOnScene,
    leaveCharacterFromScene,
    activeSceneId,
    scenePresenceLoading,
} = useProfile();

const avatarOpen = ref(false);
const avatarSrc = ref('');
const sheetCharacter = ref(null);
const createOpen = ref(false);
const showArchived = ref(false);
const pendingDelete = ref(null);
const avatarInputs = reactive({});
const sheetInputs = reactive({});
const sceneBusyId = ref(null);

onMounted(() => {
    if (currentChronicleId.value == null) {
        loadCharacters().catch(() => {});
    }
});

watch(currentChronicleId, (id) => {
    loadScenePresence(id).catch(() => {});
    loadCharacters().catch(() => {});
}, { immediate: true });

const workshopPath = computed(() => {
    const memberships = auth.user.value?.memberships ?? [];
    const storytellerMembership = memberships.find((membership) => (
        membership.role === 'storyteller'
        && (membership.left_at === null || membership.left_at === undefined)
    ));
    const chronicleId = currentChronicleId.value ?? storytellerMembership?.chronicle_id;

    return chronicleId ? `/c/${chronicleId}/workshop` : '/chronicles';
});

const visibleCharacters = computed(() => {
    const chronicleId = currentChronicleId.value;
    let rows = characters.value;

    if (chronicleId != null) {
        rows = rows.filter((row) => Number(row.chronicle_id) === Number(chronicleId));
    }

    if (showArchived.value) {
        return rows;
    }

    return rows.filter((row) => row.is_active !== false);
});

const emptyLabel = computed(() => (
    characters.value.length === 0
        ? 'У вас пока нет персонажей.'
        : 'Нет активных персонажей.'
));

function isArchived(row) {
    return row.is_active === false;
}

function characterPath(row) {
    return `/c/${row.chronicle_id}/characters/${row.id}`;
}

function openCharacter(row) {
    router.push(characterPath(row));
}

async function onArchive(row) {
    try {
        await archiveCharacter(row.id);
    } catch {
        // error уже в useProfile
    }
}

async function onRestore(row) {
    try {
        await restoreCharacter(row.id);
    } catch {
        // error уже в useProfile
    }
}

function askDelete(row) {
    pendingDelete.value = row;
}

async function confirmDelete() {
    if (!pendingDelete.value) {
        return;
    }

    try {
        await deleteCharacter(pendingDelete.value.id);
        pendingDelete.value = null;
    } catch {
        // error уже в useProfile
    }
}

async function onCharacterCreated(character) {
    createOpen.value = false;
    await loadCharacters().catch(() => {});
    await loadScenePresence(currentChronicleId.value).catch(() => {});

    const chronicleId = character?.chronicle_id ?? currentChronicleId.value;
    const characterId = character?.id;

    if (!chronicleId || !characterId) {
        return;
    }

    await router.push(`/c/${chronicleId}/characters/${characterId}`);
}

function openAvatar(row) {
    if (!row.avatar_path) {
        return;
    }

    avatarSrc.value = avatarUrl(row);
    avatarOpen.value = true;
}

async function toggleScenePresence(row) {
    sceneBusyId.value = row.id;

    try {
        if (isCharacterOnScene(row.id)) {
            await leaveCharacterFromScene(row.id);
        } else {
            await enterCharacterOnScene(row.id);
        }
    } catch {
        // error уже в useProfile
    } finally {
        sceneBusyId.value = null;
    }
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
        if (avatarOpen.value) {
            const fresh = characters.value.find((character) => Number(character.id) === Number(row.id));
            if (fresh) {
                avatarSrc.value = avatarUrl(fresh);
            }
        }
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

.profile-empty button {
    margin-top: 1rem;
}

.profile-workshop-link {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.55rem 1rem;
    background: var(--accent);
    color: var(--on-accent);
    text-decoration: none;
}

.profile-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.profile-toolbar button {
    margin-top: 0;
}

.profile-confirm-actions {
    display: flex;
    gap: 0.75rem;
}

.profile-confirm-actions button {
    margin-top: 0;
}
</style>
