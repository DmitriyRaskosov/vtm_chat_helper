import { computed, ref, watch } from 'vue';
import { api, isStoryteller } from '../auth';
import { CHRONICLE_NOT_SELECTED, chronicleCollectionPath } from './useCurrentChronicle';
import { useFlash } from './useFlash';
import { apiErrorMessage } from '../utils/apiError';
export function sceneStatusLabel(status) {
    return {
        active: 'активна',
        draft: 'ожидает',
        closed: 'закрыта',
    }[status] ?? status;
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

export function useSceneSession({ auth, chronicleId }) {
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
    const participantSystemMessage = ref('');
    const selectedNpcId = ref(null);
    const sceneSituation = ref('');
    const environmentDetails = ref('');
    const sceneSituationRevision = ref(0);
    const sceneSituationSaving = ref(false);
    const sceneSituationError = ref('');
    let boundMessages = null;
    const { flashRef } = useFlash();

    const scenes = computed(() => gameSession.value?.scenes ?? []);
    const selectedScene = computed(
        () => scenes.value.find((scene) => scene.id === selectedSceneId.value) ?? null,
    );
    const canPost = computed(() => selectedScene.value?.status === 'active');
    const myCharacterId = computed(() => {
        const user = auth.user.value;
        if (!user) {
            return null;
        }
    
        const pc = currentParticipants.value.find(
            (p) => p.character_type === 'player'
                && Number(p.user_id) === Number(user.id),
        );
    
        return pc?.character_id ?? null;
    });
    const canEditParticipants = computed(() => (
        isStoryteller.value
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
        flashRef(participantFlash, text, 'participant');
    }

    async function passTurn(characterId) {
        const session = gameSession.value;
        if (!session?.id || !isStoryteller.value) {
            return;
        }

        participantBusy.value = true;
        participantError.value = '';

        try {
            const { data } = await api.patch(`/game-sessions/${session.id}/turn`, {
                character_id: characterId,
            });
            gameSession.value = data.game_session;
            flashParticipant(characterId == null ? 'Ход сброшен' : 'Ход передан');
        } catch (error) {
            participantError.value = apiErrorMessage(error, 'Не удалось передать ход.');
        } finally {
            participantBusy.value = false;
        }
    }

    async function releaseMyTurn() {
        const session = gameSession.value;
        if (!session?.id || !isMyTurn.value || isStoryteller.value) {
            return;
        }

        participantBusy.value = true;

        try {
            const { data } = await api.patch(`/game-sessions/${session.id}/turn`, {
                character_id: null,
            });
            gameSession.value = data.game_session;
        } catch (error) {
            participantError.value = apiErrorMessage(error, 'Не удалось завершить ход.');
        } finally {
            participantBusy.value = false;
        }
    }

    const activeCharacterId = computed(() => {
        const id = gameSession.value?.active_character_id;

        return id == null ? null : Number(id);
    });

    const isMyTurn = computed(() => {
        if (activeCharacterId.value == null || myCharacterId.value == null) {
            return false;
        }

        return activeCharacterId.value === Number(myCharacterId.value);
    });

    async function loadGameSession(preferredSceneId = selectedSceneId.value) {
        let path;
        try {
            path = chronicleCollectionPath(chronicleId, 'game-sessions/active');
        } catch {
            sceneError.value = CHRONICLE_NOT_SELECTED;
            gameSession.value = null;
            selectedSceneId.value = null;
            if (boundMessages) {
                boundMessages.value = [];
            }

            return;
        }

        const { data } = await api.get(path);
        gameSession.value = data.game_session;

        if (!gameSession.value) {
            selectedSceneId.value = null;
            if (boundMessages) {
                boundMessages.value = [];
            }
            return;
        }

        const scenes = gameSession.value.scenes;
        const preferredExists = preferredSceneId != null
            && scenes.some((scene) => scene.id === preferredSceneId);
        selectedSceneId.value = preferredExists
            ? preferredSceneId
            : gameSession.value.active_scene_id ?? scenes[0]?.id ?? null;
    }

    async function loadSceneSituation(sceneId = selectedSceneId.value) {
        sceneSituationError.value = '';

        if (!sceneId) {
            sceneSituation.value = '';
            environmentDetails.value = '';
            sceneSituationRevision.value = 0;
            return;
        }

        try {
            const { data } = await api.get(`/scenes/${sceneId}/context`);
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            sceneSituation.value = data.context?.situation ?? '';
            environmentDetails.value = data.context?.environment_details ?? '';
            sceneSituationRevision.value = Number(data.context?.revision ?? 0);
        } catch (error) {
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            sceneSituationError.value = apiErrorMessage(error, 'Не удалось загрузить локацию сцены.');
        }
    }

    async function pollSceneSituation() {
        const sceneId = selectedSceneId.value;
        if (!sceneId || isStoryteller.value) {
            return;
        }

        try {
            const { data } = await api.get(`/scenes/${sceneId}/context`);
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            const revision = Number(data.context?.revision ?? 0);
            if (revision === sceneSituationRevision.value) {
                return;
            }
            sceneSituation.value = data.context?.situation ?? '';
            sceneSituationRevision.value = revision;
        } catch {
            // poll retries on the next tick
        }
    }

    async function saveSceneSituation() {
        const sceneId = selectedSceneId.value;
        if (!sceneId || selectedScene.value?.status === 'closed' || sceneSituationSaving.value) {
            return;
        }

        sceneSituationSaving.value = true;
        sceneSituationError.value = '';

        try {
            const { data } = await api.put(`/scenes/${sceneId}/context`, {
                expected_revision: sceneSituationRevision.value,
                situation: sceneSituation.value,
                environment_details: environmentDetails.value,
            });
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            sceneSituationRevision.value = Number(data.context?.revision ?? sceneSituationRevision.value);
        } catch (error) {
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            sceneSituationError.value = apiErrorMessage(error, 'Не удалось сохранить локацию сцены.');
        } finally {
            sceneSituationSaving.value = false;
        }
    }

    watch(selectedSceneId, (sceneId) => {
        loadSceneSituation(sceneId);
    });

    async function loadParticipants() {
        const sceneId = selectedSceneId.value;
        const previousNpcId = selectedNpcId.value;

        participants.value = [];
        addCharacterId.value = null;
        participantError.value = '';
        if (!sceneId) {
            chronicleCharacters.value = [];
            selectedNpcId.value = null;
            return;
        }
    
        // Участники — видят все (игрок и storyteller).
        try {
            const { data } = await api.get(`/scenes/${sceneId}/participants`);
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            participants.value = data.participants ?? [];
        } catch (error) {
            if (selectedSceneId.value === sceneId) {
                participantError.value = apiErrorMessage(error, 'Не удалось загрузить участников сцены.');
            }
            chronicleCharacters.value = [];
            return;
        }
    
        // Полный список персонажей хроники — только storyteller'у (для блока «Добавить»).
        if (!isStoryteller.value) {
            chronicleCharacters.value = [];
            return;
        }
    
        try {
            const { data } = await api.get(chronicleCollectionPath(chronicleId, 'characters'));
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            chronicleCharacters.value = flattenRoster(data.characters ?? []);
            const npcIds = sceneNpcs.value.map((row) => row.character_id);
            if (previousNpcId != null && npcIds.includes(previousNpcId)) {
                selectedNpcId.value = previousNpcId;
            } else {
                selectedNpcId.value = sceneNpcs.value[0]?.character_id ?? null;
            }
        } catch (error) {
            if (selectedSceneId.value === sceneId) {
                participantError.value = apiErrorMessage(error, 'Не удалось загрузить список персонажей.');
            }
        }
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
            const payload = {
                character_id: picked.id,
                role: sceneRoleFor(picked.character_type),
            };
            const customText = participantSystemMessage.value.trim();
            if (customText !== '') {
                payload.system_message = customText;
            }
            await api.post(`/scenes/${selectedSceneId.value}/participants`, payload);
            participantSystemMessage.value = '';
            const addedId = picked.id;
            addCharacterId.value = null;
            await loadParticipants();
            if (picked.character_type === 'npc') {
                selectedNpcId.value = addedId;
            }
            flashParticipant('Добавлено');
        } catch (error) {
            participantError.value = apiErrorMessage(error, 'Не удалось добавить персонажа на сцену.');
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
            const payload = {};
            const customText = participantSystemMessage.value.trim();
            if (customText !== '') {
                payload.system_message = customText;
            }
            await api.patch(`/scenes/${selectedSceneId.value}/participants/${characterId}`, payload);
            participantSystemMessage.value = '';
            await loadParticipants();
            flashParticipant('Убрано');
        } catch (error) {
            participantError.value = apiErrorMessage(error, 'Не удалось убрать персонажа со сцены.');
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
            const { data } = await api.post(chronicleCollectionPath(chronicleId, 'game-sessions'), { title });
            newGameSessionTitle.value = '';
            gameSession.value = data.game_session;
            selectedSceneId.value = gameSession.value.active_scene_id;
            return true;
        } catch (error) {
            sceneError.value = apiErrorMessage(error, 'Не удалось создать игровую сессию.');
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
            sceneError.value = apiErrorMessage(error, 'Не удалось создать сцену.');
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
            sceneError.value = apiErrorMessage(error, 'Не удалось активировать сцену.');
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
            sceneError.value = apiErrorMessage(error, 'Не удалось закрыть сцену.');
        } finally {
            sceneLoading.value = false;
        }
    }

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
        participantSystemMessage,
        selectedNpcId,
        sceneSituation,
        environmentDetails,
        sceneSituationSaving,
        sceneSituationError,
        scenes,
        selectedScene,
        canPost,
        myCharacterId,
        canEditParticipants,
        currentParticipants,
        availableToAdd,
        sceneNpcs,
        activeCharacterId,
        isMyTurn,
        bindMessages,
        loadGameSession,
        passTurn,
        releaseMyTurn,
        saveSceneSituation,
        pollSceneSituation,
        loadParticipants,
        addToScene,
        removeFromScene,
        createGameSession,
        createScene,
        activateScene,
        closeScene,
    };
}
