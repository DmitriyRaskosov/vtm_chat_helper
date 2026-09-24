import { computed, onUnmounted, ref } from 'vue';
import { api } from '../auth';

export function sceneStatusLabel(status) {
    return {
        active: 'активна',
        draft: 'ожидает',
        closed: 'закрыта',
    }[status] ?? status;
}

export function characterTypeLabel(type) {
    if (type === 'player') {
        return 'игрок';
    }
    return 'НПС';
}

function sceneRoleFor(type) {
    if (type === 'player') {
        return 'player';
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
    }
    return out;
}

export function useSceneSession({ auth }) {
    const gameSession = ref(null);
    const newGameSessionTitle = ref('');
    const selectedSceneId = ref(null);
    const newSceneTitle = ref('');
    const sceneLoading = ref(false);
    const sceneError = ref('');
    const participants = ref([]);
    const chronicleCharacters = ref([]);
    const addCharacterId = ref(null);
    const participantBusy = ref(false);
    const participantError = ref('');
    const participantFlash = ref('');
    const selectedNpcId = ref(null);
    let boundMessages = null;
    let participantFlashTimer;

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

    function bindMessages(messages) {
        boundMessages = messages;
    }

    function flashParticipant(text) {
        participantFlash.value = text;
        clearTimeout(participantFlashTimer);
        participantFlashTimer = setTimeout(() => {
            participantFlash.value = '';
        }, 2000);
    }

    async function loadGameSession(preferredSceneId = selectedSceneId.value) {
        const { data } = await api.get('/game-sessions/active');
        gameSession.value = data.game_session;

        if (!gameSession.value) {
            selectedSceneId.value = null;
            if (boundMessages) {
                boundMessages.value = [];
            }
            return;
        }

        const preferredExists = gameSession.value.scenes.some(
            (scene) => scene.id === preferredSceneId,
        );
        selectedSceneId.value = preferredExists
            ? preferredSceneId
            : gameSession.value.active_scene_id ?? gameSession.value.scenes[0]?.id ?? null;
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

    async function createGameSession() {
        const title = newGameSessionTitle.value.trim();
        if (!title) {
            return false;
        }

        sceneLoading.value = true;
        sceneError.value = '';

        try {
            const { data } = await api.post('/game-sessions', { title });
            newGameSessionTitle.value = '';
            gameSession.value = data.game_session;
            selectedSceneId.value = gameSession.value.active_scene_id;
            return true;
        } catch (error) {
            sceneError.value = error.response?.data?.message ?? 'Не удалось создать игровую сессию.';
            return false;
        } finally {
            sceneLoading.value = false;
        }
    }

    async function createScene() {
        const title = newSceneTitle.value.trim();
        if (!title || !gameSession.value) {
            return false;
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
            return true;
        } catch (error) {
            sceneError.value = error.response?.data?.message ?? 'Не удалось создать сцену.';
            return false;
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

    onUnmounted(() => {
        clearTimeout(participantFlashTimer);
    });

    return {
        gameSession,
        newGameSessionTitle,
        selectedSceneId,
        newSceneTitle,
        sceneLoading,
        sceneError,
        participants,
        addCharacterId,
        participantBusy,
        participantError,
        participantFlash,
        selectedNpcId,
        scenes,
        selectedScene,
        canPost,
        canEditParticipants,
        currentParticipants,
        availableToAdd,
        sceneNpcs,
        bindMessages,
        loadGameSession,
        loadParticipants,
        addToScene,
        removeFromScene,
        createGameSession,
        createScene,
        activateScene,
        closeScene,
    };
}
