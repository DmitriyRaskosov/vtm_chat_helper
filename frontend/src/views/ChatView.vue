<template>
    <div>
        <div class="top">
            <h1>
                <RouterLink to="/chat" class="nav-current">Чат</RouterLink>
                <span class="nav-sep">·</span>
                <RouterLink to="/characters">Персонажи</RouterLink>
            </h1>
            <span class="muted">
                {{ auth.user.value?.name }}
                <template v-if="auth.user.value?.is_storyteller"> · рассказчик</template>
                ·
                <button class="link" type="button" @click="logout">Выйти</button>
            </span>
        </div>

        <section v-if="gameSession" class="card scene-toolbar">
            <div class="scene-heading">
                <div>
                    <span class="muted">Игровая сессия</span>
                    <strong>{{ gameSession.title }}</strong>
                </div>
                <label for="scene-select">
                    Сцена
                    <select
                        id="scene-select"
                        v-model.number="selectedSceneId"
                        @change="switchScene"
                    >
                        <option v-for="scene in scenes" :key="scene.id" :value="scene.id">
                            {{ scene.position }}. {{ scene.title }} · {{ sceneStatusLabel(scene.status) }}
                        </option>
                    </select>
                </label>
            </div>

            <p v-if="selectedScene?.description" class="muted scene-description">
                {{ selectedScene.description }}
            </p>
            <p v-if="sceneError" class="error">{{ sceneError }}</p>

            <div v-if="auth.user.value?.is_storyteller" class="scene-management">
                <input
                    v-model="newSceneTitle"
                    type="text"
                    maxlength="120"
                    placeholder="Название новой сцены"
                />
                <button
                    type="button"
                    :disabled="sceneLoading || !newSceneTitle.trim()"
                    @click="createScene"
                >
                    Создать и открыть
                </button>
                <button
                    v-if="selectedScene?.status === 'draft'"
                    type="button"
                    class="secondary"
                    :disabled="sceneLoading"
                    @click="activateScene"
                >
                    Сделать активной
                </button>
                <button
                    v-if="selectedScene?.status === 'active'"
                    type="button"
                    class="secondary"
                    :disabled="sceneLoading"
                    @click="closeScene"
                >
                    Закрыть сцену
                </button>
            </div>

            <div v-if="auth.user.value?.is_storyteller" class="scene-cast">
                <div>
                    <span class="muted">На сцене</span>
                    <ul v-if="currentParticipants.length" class="scene-cast-list">
                        <li v-for="row in currentParticipants" :key="row.character_id">
                            {{ row.character_name }}
                            <span class="muted"> · {{ characterTypeLabel(row.character_type) }}</span>
                            <button
                                v-if="canEditParticipants"
                                type="button"
                                class="link"
                                :disabled="participantBusy"
                                @click="removeFromScene(row.character_id)"
                            >
                                убрать
                            </button>
                        </li>
                    </ul>
                    <p v-else class="muted">Пока никого. Добавьте персонажа из списка ниже.</p>
                </div>
                <div v-if="canEditParticipants" class="scene-cast-add">
                    <label for="scene-add-character">Добавить на сцену</label>
                    <select
                        id="scene-add-character"
                        v-model.number="addCharacterId"
                        :disabled="participantBusy || !availableToAdd.length"
                    >
                        <option :value="null">{{ availableToAdd.length ? 'Выберите персонажа' : 'Все уже на сцене' }}</option>
                        <option
                            v-for="row in availableToAdd"
                            :key="row.id"
                            :value="row.id"
                        >
                            {{ row.canonical_name }} · {{ characterTypeLabel(row.character_type) }}
                        </option>
                    </select>
                    <button
                        type="button"
                        :disabled="participantBusy || !addCharacterId"
                        @click="addToScene"
                    >
                        Добавить
                    </button>
                    <span v-if="participantFlash" class="saved-flash">{{ participantFlash }}</span>
                </div>
                <p v-if="participantError" class="error">{{ participantError }}</p>
            </div>
        </section>
        <section v-else class="card session-empty">
            <template v-if="auth.user.value?.is_storyteller">
                <h2>Нет активной игровой сессии</h2>
                <p class="muted">
                    Создайте игровую сессию. Начальная активная сцена будет добавлена автоматически.
                </p>
                <form class="session-create" @submit.prevent="createGameSession">
                    <input
                        v-model="newGameSessionTitle"
                        type="text"
                        maxlength="120"
                        required
                        placeholder="Название игровой сессии"
                    />
                    <button
                        type="submit"
                        :disabled="sceneLoading || !newGameSessionTitle.trim()"
                    >
                        {{ sceneLoading ? 'Создание…' : 'Создать сессию' }}
                    </button>
                </form>
                <p v-if="sceneError" class="error">{{ sceneError }}</p>
            </template>
            <template v-else>
                <h2>Нет активной игровой сессии</h2>
                <p class="muted">Рассказчик ещё не открыл игровую сессию.</p>
            </template>
        </section>

        <div :class="{ stage: auth.user.value?.is_storyteller }">
            <div>
                <div ref="logEl" class="chat-log" aria-live="polite">
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="msg"
                        :class="{ mine: message.mine }"
                    >
                        <div class="meta">{{ message.author }} · {{ message.created_at }}</div>
                        <div>{{ message.body }}</div>
                    </article>
                    <p v-if="!messages.length" class="muted">Пока пусто. Напишите первое сообщение.</p>
                </div>

                <form class="composer" @submit.prevent="send">
                    <textarea
                        v-model="body"
                        maxlength="4000"
                        required
                        :disabled="!canPost"
                        :placeholder="composerPlaceholder"
                    ></textarea>
                    <button type="submit" :disabled="!canPost">Отправить</button>
                </form>
            </div>

            <aside v-if="auth.user.value?.is_storyteller" class="card storyteller-panel">
                <h2>Панель рассказчика</h2>
                <p class="muted panel-hint">Черновики реплики НПС: промпт, три варианта, правка и отправка в чат.</p>

                <label for="npc-character">НПС сцены</label>
                <select id="npc-character" v-model.number="selectedNpcId">
                    <option :value="null" disabled>Выберите участника</option>
                    <option v-for="npc in sceneNpcs" :key="npc.character_id" :value="npc.character_id">
                        {{ npc.character_name }}
                    </option>
                </select>
                <p v-if="!sceneNpcs.length" class="muted">
                    Copilot видит только НПС, которые сейчас на сцене. Добавьте персонажа блоком «На сцене» выше.
                </p>

                <label for="copilot-prompt">Ситуация / промпт</label>
                <textarea
                    id="copilot-prompt"
                    v-model="copilotPrompt"
                    maxlength="2000"
                    placeholder="Что происходит, тон, на что ответить…"
                />

                <button
                    type="button"
                    :disabled="copilotLoading || !canGenerate || !canPost"
                    @click="generateDrafts"
                >
                    {{ copilotLoading ? 'Генерация…' : 'Сгенерировать черновики' }}
                </button>

                <p v-if="copilotError" class="error">{{ copilotError }}</p>

                <div v-if="drafts.length" class="draft-list">
                    <button
                        v-for="(draft, index) in drafts"
                        :key="index"
                        type="button"
                        class="draft-card"
                        :class="{ selected: selectedDraftIndex === index }"
                        @click="selectDraft(index)"
                    >
                        <span class="draft-label">Вариант {{ index + 1 }}</span>
                        <span class="draft-text">{{ draft }}</span>
                    </button>
                </div>

                <template v-if="selectedDraft !== null">
                    <label for="draft-edit">Редактирование</label>
                    <textarea id="draft-edit" v-model="editedDraft" maxlength="4000" />
                    <button type="button" :disabled="!canSendNpc" @click="sendAsNpc">Отправить в чат от НПС</button>
                </template>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api, useAuth } from '../auth';

const router = useRouter();
const auth = useAuth();
const gameSession = ref(null);
const newGameSessionTitle = ref('');
const selectedSceneId = ref(null);
const newSceneTitle = ref('');
const sceneLoading = ref(false);
const sceneError = ref('');
const messages = ref([]);
const body = ref('');
const logEl = ref(null);
const participants = ref([]);
const chronicleCharacters = ref([]);
const addCharacterId = ref(null);
const participantBusy = ref(false);
const participantError = ref('');
const participantFlash = ref('');
const selectedNpcId = ref(null);
const copilotPrompt = ref('');
const copilotRequestId = ref(null);
const drafts = ref([]);
const selectedDraftIndex = ref(null);
const editedDraft = ref('');
const copilotLoading = ref(false);
const copilotError = ref('');
let timer;
let polling = false;

const scenes = computed(() => gameSession.value?.scenes ?? []);
const selectedScene = computed(
    () => scenes.value.find((scene) => scene.id === selectedSceneId.value) ?? null,
);
const canPost = computed(() => selectedScene.value?.status === 'active');
const canEditParticipants = computed(() => (
    auth.user.value?.is_storyteller === true
    && selectedScene.value != null
    && selectedScene.value.status !== 'closed'
));
const currentParticipants = computed(() =>
    participants.value.filter((participant) => participant.is_current && participant.character_name),
);
const onSceneIds = computed(() => new Set(currentParticipants.value.map((row) => row.character_id)));
const availableToAdd = computed(() =>
    chronicleCharacters.value.filter((row) => !onSceneIds.value.has(row.id)),
);
const sceneNpcs = computed(() =>
    currentParticipants.value.filter((participant) => participant.character_type === 'npc'),
);
const composerPlaceholder = computed(() => {
    if (!gameSession.value) {
        return 'Нет активной игровой сессии';
    }

    return canPost.value ? 'Сообщение…' : 'Сцена доступна только для чтения';
});
const canGenerate = computed(() => selectedNpcId.value !== null && copilotPrompt.value.trim() !== '');
const selectedDraft = computed(() =>
    selectedDraftIndex.value === null ? null : drafts.value[selectedDraftIndex.value] ?? null,
);
const canSendNpc = computed(() => selectedNpcId.value !== null && editedDraft.value.trim() !== '');

function lastId() {
    return messages.value.at(-1)?.id ?? 0;
}

function sceneStatusLabel(status) {
    return {
        active: 'активна',
        draft: 'ожидает',
        closed: 'закрыта',
    }[status] ?? status;
}

function characterTypeLabel(type) {
    if (type === 'player') {
        return 'игрок';
    }
    if (type === 'ghoul') {
        return 'гуль';
    }
    return 'НПС';
}

function sceneRoleFor(type) {
    if (type === 'player') {
        return 'player';
    }
    if (type === 'ghoul') {
        return 'extra';
    }
    return 'npc';
}

function flattenRoster(rows) {
    const out = [];
    for (const row of rows) {
        out.push({
            id: row.id,
            canonical_name: row.canonical_name,
            character_type: row.character_type,
        });
        for (const ghoul of row.ghouls ?? []) {
            out.push({
                id: ghoul.id,
                canonical_name: ghoul.canonical_name,
                character_type: ghoul.character_type,
            });
        }
    }
    return out;
}

let participantFlashTimer;

function flashParticipant(text) {
    participantFlash.value = text;
    clearTimeout(participantFlashTimer);
    participantFlashTimer = setTimeout(() => {
        participantFlash.value = '';
    }, 2000);
}

function merge(incoming) {
    for (const message of incoming) {
        if (!messages.value.some((item) => item.id === message.id)) {
            messages.value.push(message);
        }
    }
}

async function scrollDown() {
    await nextTick();
    if (logEl.value) {
        logEl.value.scrollTop = logEl.value.scrollHeight;
    }
}

async function load(afterId = 0) {
    if (!selectedSceneId.value) {
        return;
    }

    const sceneIdAtStart = selectedSceneId.value;
    const { data } = await api.get('/messages', {
        params: {
            scene_id: sceneIdAtStart,
            after_id: afterId,
        },
    });

    if (selectedSceneId.value !== sceneIdAtStart) {
        return;
    }

    merge(data.messages);
    await scrollDown();
}

async function loadGameSession(preferredSceneId = selectedSceneId.value) {
    const { data } = await api.get('/game-sessions/active');
    gameSession.value = data.game_session;

    if (!gameSession.value) {
        selectedSceneId.value = null;
        messages.value = [];
        return;
    }

    const preferredExists = gameSession.value.scenes.some(
        (scene) => scene.id === preferredSceneId,
    );
    selectedSceneId.value = preferredExists
        ? preferredSceneId
        : gameSession.value.active_scene_id ?? gameSession.value.scenes[0]?.id ?? null;
}

async function switchScene() {
    messages.value = [];
    resetCopilotDrafts();
    await loadParticipants();
    await load();
}

async function loadParticipants() {
    participants.value = [];
    selectedNpcId.value = null;
    addCharacterId.value = null;
    participantError.value = '';

    if (!selectedSceneId.value || !auth.user.value?.is_storyteller) {
        return;
    }

    const [participantsRes, charactersRes] = await Promise.all([
        api.get(`/scenes/${selectedSceneId.value}/participants`),
        api.get('/characters'),
    ]);
    participants.value = participantsRes.data.participants ?? [];
    chronicleCharacters.value = flattenRoster(charactersRes.data.characters ?? []);
    selectedNpcId.value = sceneNpcs.value[0]?.character_id ?? null;
}

async function addToScene() {
    if (!addCharacterId.value || !selectedSceneId.value) {
        return;
    }

    const picked = chronicleCharacters.value.find((row) => row.id === addCharacterId.value);
    if (!picked) {
        return;
    }

    participantBusy.value = true;
    participantError.value = '';

    try {
        await api.post(`/scenes/${selectedSceneId.value}/participants`, {
            character_id: picked.id,
            role: sceneRoleFor(picked.character_type),
        });
        const addedId = picked.id;
        addCharacterId.value = null;
        await loadParticipants();
        if (picked.character_type === 'npc') {
            selectedNpcId.value = addedId;
        }
        flashParticipant('Добавлено');
    } catch (error) {
        participantError.value = error.response?.data?.message ?? 'Не удалось добавить персонажа на сцену.';
    } finally {
        participantBusy.value = false;
    }
}

async function removeFromScene(characterId) {
    if (!selectedSceneId.value) {
        return;
    }

    participantBusy.value = true;
    participantError.value = '';

    try {
        await api.patch(`/scenes/${selectedSceneId.value}/participants/${characterId}`);
        await loadParticipants();
        flashParticipant('Убрано');
    } catch (error) {
        participantError.value = error.response?.data?.message ?? 'Не удалось убрать персонажа со сцены.';
    } finally {
        participantBusy.value = false;
    }
}

async function poll() {
    if (polling) {
        return;
    }

    polling = true;

    try {
        const previousSceneId = selectedSceneId.value;
        const preferredSceneId = auth.user.value?.is_storyteller
            ? previousSceneId
            : null;

        await loadGameSession(preferredSceneId);

        if (selectedSceneId.value !== previousSceneId) {
            messages.value = [];
            resetCopilotDrafts();
            await loadParticipants();
            await load();
        } else {
            await load(lastId());
        }
    } catch {
        // A later poll retries transient API failures.
    } finally {
        polling = false;
    }
}

async function createGameSession() {
    const title = newGameSessionTitle.value.trim();
    if (!title) {
        return;
    }

    sceneLoading.value = true;
    sceneError.value = '';

    try {
        const { data } = await api.post('/game-sessions', { title });
        newGameSessionTitle.value = '';
        gameSession.value = data.game_session;
        selectedSceneId.value = gameSession.value.active_scene_id;
        messages.value = [];
        await loadParticipants();
        await load();
    } catch (error) {
        sceneError.value = error.response?.data?.message ?? 'Не удалось создать игровую сессию.';
    } finally {
        sceneLoading.value = false;
    }
}

async function createScene() {
    const title = newSceneTitle.value.trim();
    if (!title || !gameSession.value) {
        return;
    }

    sceneLoading.value = true;
    sceneError.value = '';

    try {
        const { data } = await api.post(`/game-sessions/${gameSession.value.id}/scenes`, {
            title,
            activate: true,
        });
        newSceneTitle.value = '';
        await loadGameSession(data.scene.id);
        messages.value = [];
        await loadParticipants();
        await load();
    } catch (error) {
        sceneError.value = error.response?.data?.message ?? 'Не удалось создать сцену.';
    } finally {
        sceneLoading.value = false;
    }
}

async function activateScene() {
    if (!selectedScene.value) {
        return;
    }

    sceneLoading.value = true;
    sceneError.value = '';

    try {
        await api.patch(`/scenes/${selectedScene.value.id}/activate`);
        await loadGameSession(selectedScene.value.id);
    } catch (error) {
        sceneError.value = error.response?.data?.message ?? 'Не удалось активировать сцену.';
    } finally {
        sceneLoading.value = false;
    }
}

async function closeScene() {
    if (!selectedScene.value) {
        return;
    }

    sceneLoading.value = true;
    sceneError.value = '';

    try {
        await api.patch(`/scenes/${selectedScene.value.id}/close`);
        await loadGameSession(selectedScene.value.id);
    } catch (error) {
        sceneError.value = error.response?.data?.message ?? 'Не удалось закрыть сцену.';
    } finally {
        sceneLoading.value = false;
    }
}

async function send() {
    const text = body.value.trim();
    if (!text || !canPost.value) {
        return;
    }
    const { data } = await api.post('/messages', {
        body: text,
        scene_id: selectedSceneId.value,
    });
    merge([data.message]);
    body.value = '';
    await scrollDown();
}

function selectDraft(index) {
    selectedDraftIndex.value = index;
    editedDraft.value = drafts.value[index] ?? '';
}

function resetCopilotDrafts() {
    copilotRequestId.value = null;
    drafts.value = [];
    selectedDraftIndex.value = null;
    editedDraft.value = '';
}

async function generateDrafts() {
    copilotError.value = '';
    copilotLoading.value = true;
    resetCopilotDrafts();

    try {
        const { data } = await api.post('/copilot/drafts', {
            character_id: selectedNpcId.value,
            prompt: copilotPrompt.value.trim(),
            scene_id: selectedSceneId.value,
        });
        copilotRequestId.value = data.copilot_request_id ?? null;
        drafts.value = data.drafts ?? [];
        if (drafts.value.length > 0) {
            selectDraft(0);
        }
    } catch (error) {
        copilotError.value =
            error.response?.data?.message ?? 'Не удалось сгенерировать черновики. Проверьте Ollama.';
    } finally {
        copilotLoading.value = false;
    }
}

async function sendAsNpc() {
    const text = editedDraft.value.trim();
    if (!text || selectedNpcId.value === null) {
        return;
    }

    copilotError.value = '';

    try {
        const { data } = await api.post('/messages', {
            body: text,
            character_id: selectedNpcId.value,
            scene_id: selectedSceneId.value,
            copilot_request_id: copilotRequestId.value,
            copilot_draft_index: selectedDraftIndex.value,
        });
        merge([data.message]);
        resetCopilotDrafts();
        await scrollDown();
    } catch (error) {
        copilotError.value =
            error.response?.data?.message ?? 'Не удалось отправить выбранный черновик.';
    }
}

async function logout() {
    await auth.logout();
    await router.push('/login');
}

onMounted(async () => {
    await loadGameSession();
    await loadParticipants();
    await load();
    timer = setInterval(poll, 3000);
});

onUnmounted(() => {
    clearInterval(timer);
    clearTimeout(participantFlashTimer);
});
</script>
