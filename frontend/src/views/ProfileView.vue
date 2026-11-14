<template>
    <div class="with-sidebar">
        <AppNav current="profile" />
        <div class="page-main">
            <header class="top">
                <h1>Личный кабинет</h1>
            </header>

            <nav class="profile-tabs" aria-label="Разделы личного кабинета">
                <button
                    type="button"
                    class="profile-tab"
                    :class="{ 'profile-tab--active': activeTab === 'chronicles' }"
                    @click="activeTab = 'chronicles'"
                >
                    Персонажи в хрониках
                </button>
                <button
                    type="button"
                    class="profile-tab"
                    :class="{ 'profile-tab--active': activeTab === 'library' }"
                    @click="switchToLibrary"
                >
                    Библиотека
                </button>
            </nav>

            <p v-if="librarySuccess" class="profile-success muted">{{ librarySuccess }}</p>

            <template v-if="activeTab === 'chronicles'">
            <p v-if="loading" class="muted">Загрузка персонажей…</p>
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
                <section
                    v-for="group in characterGroups"
                    :key="group.id"
                    class="profile-chronicle"
                >
                    <h3 class="profile-chronicle-title">{{ group.title }}</h3>
                    <div class="profile-grid">
                    <article
                        v-for="row in group.characters"
                        :key="row.id"
                        class="card profile-card"
                        @click="openCharacter(row)"
                    >
                        <button
                            v-if="showStorytellerFlag(row)"
                            type="button"
                            class="storyteller-flag"
                            title="Рассказчик внёс изменения в чарлист"
                            :disabled="isAcknowledging(row)"
                            @click.stop="acknowledgeStorytellerFlag(row)"
                        >
                            !
                        </button>
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
                            <button type="button" class="secondary" @click="openInteractiveSheet(row)">
                                📄 Интерактивный лист
                            </button>
                            <button
                                type="button"
                                class="secondary"
                                :disabled="!row.sheet_image_path"
                                @click="openSheetPhoto(row)"
                            >
                                🖼️ Фото листа
                            </button>
                            <button type="button" class="secondary" @click="openNotes(row)">
                                Заметки
                            </button>
                            <button
                                v-if="!isArchived(row)"
                                type="button"
                                class="secondary"
                                :disabled="librarySaving || librarySavingId === row.id"
                                @click="onSaveToLibrary(row)"
                            >
                                {{ librarySavingId === row.id ? 'Сохранение…' : 'В библиотеку' }}
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
                            <template v-if="isArchived(row)">
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
                </section>
            </template>
            </template>

            <LibraryCharacterList
                v-else
                :templates="libraryTemplates"
                :loading="libraryLoading"
                :saving="librarySaving"
                :error="libraryError"
                :import-handler="onLibraryImport"
                :delete-handler="onLibraryDelete"
            />

            <section v-if="!loading && activeTab === 'chronicles'" class="profile-macros">
                <div class="profile-toolbar">
                    <h2 class="profile-section-title">Макросы кубиков</h2>
                    <button type="button" @click="openCreateMacro">Создать макрос</button>
                </div>
                <p v-if="macrosLoading" class="muted">Загрузка макросов…</p>
                <p v-else-if="macros.length === 0" class="muted">Макросов пока нет.</p>
                <div v-else class="profile-grid">
                    <article v-for="macro in macros" :key="macro.id" class="card profile-macro-card">
                        <h3 class="profile-name">{{ macro.name }}</h3>
                        <p class="muted">Пул {{ macro.pool > 0 ? macro.pool : 'по статам' }} · сложность {{ macro.difficulty }}</p>
                        <div class="profile-actions">
                            <button type="button" class="secondary" @click="openEditMacro(macro)">
                                Редактировать
                            </button>
                            <button
                                type="button"
                                class="secondary"
                                :disabled="macrosSaving"
                                @click="onDeleteMacro(macro)"
                            >
                                Удалить
                            </button>
                        </div>
                    </article>
                </div>
                <p v-if="macrosError && !macroOpen" class="error">{{ macrosError }}</p>
            </section>
        </div>

        <AvatarModal v-model="avatarOpen" :src="avatarSrc" />
        <CreateCharacterModal
            v-if="createOpen"
            v-model="createOpen"
            @created="onCharacterCreated"
        />
        <MacroModal
            v-model="macroOpen"
            :macro="editingMacro"
            :saving="macrosSaving"
            :error="macrosError"
            @save="onSaveMacro"
        />
        <CharacterNotesModal
            v-model:open="notesOpen"
            :chronicle-id="notesChronicleId"
            :character-id="notesCharacterId"
            :character-name="notesCharacterName"
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

        <SheetImageModal
            v-if="sheetPhotoOpen"
            v-model="sheetPhotoOpen"
            :image-path="sheetPhoto?.sheet_image_path ?? ''"
            :title="sheetPhoto?.canonical_name ?? ''"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import AvatarModal from '../components/profile/AvatarModal.vue';
import CreateCharacterModal from '../components/profile/CreateCharacterModal.vue';
import MacroModal from '../components/profile/MacroModal.vue';
import CharacterNotesModal from '../components/profile/CharacterNotesModal.vue';
import LibraryCharacterList from '../components/profile/LibraryCharacterList.vue';
import SheetImageModal from '../components/profile/SheetImageModal.vue';
import { currentChronicleId, useAuth } from '../auth';
import { useDiceMacros } from '../composables/useDiceMacros';
import { useLibraryCharacters } from '../composables/useLibraryCharacters';
import { useProfile } from '../composables/useProfile';

const router = useRouter();
const auth = useAuth();
const {
    characters,
    loading,
    error,
    loadCharacters,
    uploadAvatar,
    uploadSheet,
    archiveCharacter,
    acknowledgeUpdate,
    restoreCharacter,
    deleteCharacter,
    avatarUrl,
    avatarImageKey,
    isCharacterOnScene,
    loadScenePresence,
    enterCharacterOnScene,
    leaveCharacterFromScene,
    activeSceneId,
    scenePresenceLoading,
} = useProfile();

const activeTab = ref('chronicles');
const librarySavingId = ref(null);

const {
    templates: libraryTemplates,
    loading: libraryLoading,
    saving: librarySaving,
    error: libraryError,
    successMessage: librarySuccess,
    fetchLibrary,
    saveToLibrary,
    deleteTemplate,
    importTemplate,
} = useLibraryCharacters();

const {
    macros,
    loading: macrosLoading,
    saving: macrosSaving,
    error: macrosError,
    load: loadMacros,
    createMacro,
    updateMacro,
    deleteMacro,
} = useDiceMacros();

const avatarOpen = ref(false);
const avatarSrc = ref('');
const sheetPhotoOpen = ref(false);
const sheetPhoto = ref(null);
const acknowledgingIds = ref(new Set());
const storytellerChannels = new Map();
const createOpen = ref(false);
const macroOpen = ref(false);
const editingMacro = ref(null);
const showArchived = ref(false);
const pendingDelete = ref(null);
const avatarInputs = reactive({});
const sheetInputs = reactive({});
const sceneBusyId = ref(null);
const notesOpen = ref(false);
const notesCharacterId = ref(null);
const notesCharacterName = ref('');
const notesChronicleId = ref(null);

function switchToLibrary() {
    activeTab.value = 'library';
    fetchLibrary().catch(() => {});
}

async function onSaveToLibrary(row) {
    librarySavingId.value = row.id;

    try {
        await saveToLibrary(row.id);
    } catch {
        // ошибка в composable
    } finally {
        librarySavingId.value = null;
    }
}

async function onLibraryImport(chronicleId, templateId, canonicalName) {
    await importTemplate(chronicleId, templateId, canonicalName);
    await loadCharacters({ includeArchived: showArchived.value }).catch(() => {});
}

async function onLibraryDelete(templateId) {
    await deleteTemplate(templateId);
}

function openNotes(row) {
    notesCharacterId.value = row.id;
    notesCharacterName.value = row.canonical_name ?? '';
    notesChronicleId.value = row.chronicle_id;
    notesOpen.value = true;
}

onMounted(() => {
    if (currentChronicleId.value == null) {
        loadCharacters({ includeArchived: showArchived.value }).catch(() => {});
    }
});

watch(currentChronicleId, (id) => {
    loadScenePresence(id).catch(() => {});
    loadCharacters({ includeArchived: showArchived.value }).catch(() => {});
    loadMacros().catch(() => {});
}, { immediate: true });

watch(showArchived, () => {
    loadCharacters({ includeArchived: showArchived.value }).catch(() => {});
});

const visibleCharacters = computed(() => characters.value);

const characterGroups = computed(() => {
    const groups = [];
    const indexByChronicle = new Map();

    for (const row of visibleCharacters.value) {
        const id = Number(row.chronicle_id);
        if (!indexByChronicle.has(id)) {
            indexByChronicle.set(id, groups.length);
            groups.push({
                id,
                title: row.chronicle_title || 'Хроника',
                characters: [],
            });
        }
        groups[indexByChronicle.get(id)].characters.push(row);
    }

    return groups;
});

const emptyLabel = computed(() => (
    showArchived.value
        ? 'У вас пока нет персонажей.'
        : 'Нет активных персонажей. Включите «Показывать архивных», чтобы увидеть архив.'
));

function isArchived(row) {
    return row.is_active === false;
}

function characterPath(row) {
    return `/c/${row.chronicle_id}/characters/${row.id}`;
}

function openCreateMacro() {
    editingMacro.value = null;
    macrosError.value = '';
    macroOpen.value = true;
}

function openEditMacro(macro) {
    editingMacro.value = macro;
    macrosError.value = '';
    macroOpen.value = true;
}

async function onSaveMacro(payload) {
    const saved = editingMacro.value
        ? await updateMacro(editingMacro.value.id, payload)
        : await createMacro(payload);

    if (saved) {
        macroOpen.value = false;
        editingMacro.value = null;
    }
}

async function onDeleteMacro(macro) {
    await deleteMacro(macro.id);
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

async function onCharacterCreated() {
    createOpen.value = false;
    activeTab.value = 'library';
    await fetchLibrary().catch(() => {});
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

function openInteractiveSheet(row) {
    router.push(characterPath(row));
}

function openSheetPhoto(row) {
    if (!row?.sheet_image_path) {
        return;
    }

    sheetPhoto.value = row;
    sheetPhotoOpen.value = true;
}

const storytellerChronicleKey = computed(() => {
    const ids = [...new Set(
        characters.value
            .map((row) => Number(row.chronicle_id))
            .filter((id) => Number.isFinite(id) && id > 0),
    )];

    ids.sort((left, right) => left - right);

    return ids.join(',');
});

function showStorytellerFlag(row) {
    return row?.updated_by_storyteller_at != null
        && row.updated_by_storyteller_at !== '';
}

function isAcknowledging(row) {
    return acknowledgingIds.value.has(Number(row?.id));
}

function setAcknowledging(id, active) {
    const next = new Set(acknowledgingIds.value);

    if (active) {
        next.add(id);
    } else {
        next.delete(id);
    }

    acknowledgingIds.value = next;
}

function setStorytellerFlag(characterId, value) {
    characters.value = characters.value.map((character) => (
        Number(character.id) === characterId
            ? { ...character, updated_by_storyteller_at: value }
            : character
    ));
}

function onStorytellerEdited(payload) {
    const ownerId = Number(payload?.user_id);
    const characterId = Number(payload?.character_id);
    const currentUserId = Number(auth.user.value?.id);

    if (!Number.isFinite(ownerId) || ownerId !== currentUserId || !Number.isFinite(characterId)) {
        return;
    }

    setStorytellerFlag(characterId, new Date().toISOString());
}

function unbindStorytellerChannels() {
    for (const [chronicleId, channel] of storytellerChannels) {
        channel.stopListening('.CharacterEditedByStorytellerEvent');
        window.Echo?.leave(`chronicle.${chronicleId}`);
    }

    storytellerChannels.clear();
}

function bindStorytellerChannels() {
    const ids = storytellerChronicleKey.value === ''
        ? []
        : storytellerChronicleKey.value.split(',').map((id) => Number(id));
    const nextIds = new Set(ids);

    for (const chronicleId of storytellerChannels.keys()) {
        if (!nextIds.has(chronicleId)) {
            storytellerChannels.get(chronicleId)?.stopListening('.CharacterEditedByStorytellerEvent');
            window.Echo?.leave(`chronicle.${chronicleId}`);
            storytellerChannels.delete(chronicleId);
        }
    }

    if (typeof window.Echo?.private !== 'function') {
        return;
    }

    for (const chronicleId of nextIds) {
        if (storytellerChannels.has(chronicleId)) {
            continue;
        }

        const channel = window.Echo.private(`chronicle.${chronicleId}`);
        channel.listen('.CharacterEditedByStorytellerEvent', onStorytellerEdited);
        storytellerChannels.set(chronicleId, channel);
    }
}

async function acknowledgeStorytellerFlag(row) {
    const id = Number(row?.id);

    if (!Number.isFinite(id) || acknowledgingIds.value.has(id)) {
        return;
    }

    const previous = row.updated_by_storyteller_at ?? null;

    setAcknowledging(id, true);
    setStorytellerFlag(id, null);

    try {
        await acknowledgeUpdate(id);
    } catch {
        setStorytellerFlag(id, previous);
    } finally {
        setAcknowledging(id, false);
    }
}

watch(storytellerChronicleKey, bindStorytellerChannels, { immediate: true });

onUnmounted(unbindStorytellerChannels);

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
.profile-chronicle {
    margin-top: 1.25rem;
}

.profile-chronicle-title {
    margin: 0 0 0.75rem;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.profile-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    cursor: pointer;
    border-color: var(--gold);
}

.storyteller-flag {
    position: absolute;
    top: 0.45rem;
    right: 0.45rem;
    z-index: 2;
    width: 1.6rem;
    height: 1.6rem;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: #b42318;
    color: #fff;
    font-weight: 700;
    line-height: 1.6rem;
}

.storyteller-flag::after {
    content: 'Рассказчик внёс изменения в чарлист';
    position: absolute;
    top: calc(100% + 0.35rem);
    right: 0;
    width: max-content;
    max-width: 14rem;
    padding: 0.35rem 0.5rem;
    border-radius: 4px;
    background: #1c120e;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.3;
    white-space: normal;
    opacity: 0;
    pointer-events: none;
}

.storyteller-flag:hover::after,
.storyteller-flag:focus-visible::after {
    opacity: 1;
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

.profile-macros {
    margin-top: 2rem;
}

.profile-section-title {
    margin: 0;
    font-family: var(--font-lore), serif;
    font-size: 1.35rem;
    font-weight: 600;
    color: var(--gold);
}

.profile-macro-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-color: var(--gold);
}

.profile-macro-card .profile-name {
    margin: 0;
}

.profile-confirm-actions {
    display: flex;
    gap: 0.75rem;
}

.profile-confirm-actions button {
    margin-top: 0;
}

.profile-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 0 1.25rem;
}

.profile-tab {
    margin-top: 0;
    background: var(--bg-light);
    border: 1px solid var(--border);
    color: var(--text);
}

.profile-tab--active {
    border-color: var(--gold);
    color: var(--gold);
}

.profile-success {
    margin: 0 0 1rem;
    color: var(--gold);
}
</style>
