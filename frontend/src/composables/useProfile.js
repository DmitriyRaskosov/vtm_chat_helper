import { ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';
import { chronicleCollectionPath } from './useCurrentChronicle';

export function useProfile() {
    const characters = ref([]);
    const loading = ref(true);
    const error = ref('');
    const storytellerDenied = ref(false);
    const avatarCacheKeys = ref({});
    const sheetReloadKeys = ref({});
    const activeSceneId = ref(null);
    const onSceneCharacterIds = ref(new Set());
    const scenePresenceLoading = ref(false);

    async function loadCharacters() {
        loading.value = true;
        error.value = '';
        storytellerDenied.value = false;

        try {
            const response = await api.get('/profile/characters');
            const rows = response.data?.data;
            characters.value = Array.isArray(rows) ? rows : [];

            return characters.value;
        } catch (err) {
            characters.value = [];

            if (err.response?.status === 403) {
                storytellerDenied.value = true;

                return [];
            }

            error.value = apiErrorMessage(err, 'Не удалось загрузить персонажей.');
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function uploadAvatar(characterId, file) {
        const numericId = Number(characterId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        error.value = '';

        const formData = new FormData();
        formData.append('avatar', file);

        try {
            const response = await api.post(`/characters/${numericId}/avatar`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            const avatarPath = response.data?.avatar_path ?? null;
            const updatedAt = response.data?.updated_at ?? Date.now();

            characters.value = characters.value.map((character) => (
                Number(character.id) === numericId
                    ? {
                        ...character,
                        avatar_path: avatarPath,
                        updated_at: updatedAt,
                    }
                    : character
            ));
            avatarCacheKeys.value = {
                ...avatarCacheKeys.value,
                [numericId]: updatedAt,
            };

            return avatarPath;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить аватар.');
            throw err;
        }
    }

    async function uploadSheet(characterId, file) {
        const numericId = Number(characterId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        error.value = '';

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await api.post(`/characters/${numericId}/sheet-image`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            sheetReloadKeys.value = {
                ...sheetReloadKeys.value,
                [numericId]: Date.now(),
            };

            return response.data?.sheet_image_path ?? null;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить чарлист.');
            throw err;
        }
    }

    async function loadClans() {
        try {
            const response = await api.get('/character-sheet/catalog');
            const clans = response.data?.clans;

            return Array.isArray(clans) ? clans : [];
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить кланы.');
            throw err;
        }
    }

    async function createCharacter(chronicleId, payload) {
        const id = Number(chronicleId);

        if (!Number.isFinite(id) || id <= 0) {
            error.value = 'Хроника не выбрана.';
            throw new Error(error.value);
        }

        error.value = '';

        const body = {
            canonical_name: String(payload?.canonical_name ?? '').trim(),
            clan_id: Number(payload?.clan_id),
        };
        const concept = String(payload?.concept ?? '').trim();

        if (concept !== '') {
            body.concept = concept;
        }

        try {
            const response = await api.post(`/chronicles/${id}/characters`, body);

            return response.data?.character ?? null;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось создать персонажа.');
            throw err;
        }
    }

    function requireCharacterId(characterId) {
        const numericId = Number(characterId);

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Персонаж не выбран.');
        }

        return numericId;
    }

    async function archiveCharacter(characterId) {
        const numericId = requireCharacterId(characterId);
        error.value = '';

        try {
            await api.post(`/characters/${numericId}/archive`);
            characters.value = characters.value.map((character) => (
                Number(character.id) === numericId
                    ? { ...character, is_active: false }
                    : character
            ));
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось архивировать персонажа.');
            throw err;
        }
    }

    async function restoreCharacter(characterId) {
        const numericId = requireCharacterId(characterId);
        error.value = '';

        try {
            await api.post(`/characters/${numericId}/restore`);
            characters.value = characters.value.map((character) => (
                Number(character.id) === numericId
                    ? { ...character, is_active: true }
                    : character
            ));
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось восстановить персонажа.');
            throw err;
        }
    }

    async function deleteCharacter(characterId) {
        const numericId = requireCharacterId(characterId);
        error.value = '';

        try {
            await api.delete(`/characters/${numericId}`);
            characters.value = characters.value.filter(
                (character) => Number(character.id) !== numericId,
            );
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось удалить персонажа.');
            throw err;
        }
    }

    function avatarUrl(row) {
        if (!row?.avatar_path) {
            return '';
        }

        const id = Number(row.id);
        const bust = avatarCacheKeys.value[id] ?? row.updated_at ?? 0;

        return `/storage/${row.avatar_path}?v=${bust}`;
    }

    function avatarImageKey(row) {
        const id = Number(row?.id);

        return `${id}-${avatarCacheKeys.value[id] ?? row?.updated_at ?? 0}`;
    }

    function sheetViewerKey(characterId) {
        const id = Number(characterId);
        const version = sheetReloadKeys.value[id] ?? 0;

        return `${id}-${version}`;
    }

    function isCharacterOnScene(characterId) {
        return onSceneCharacterIds.value.has(Number(characterId));
    }

    async function loadScenePresence(chronicleId) {
        const id = Number(chronicleId);

        if (!Number.isFinite(id) || id <= 0) {
            activeSceneId.value = null;
            onSceneCharacterIds.value = new Set();

            return;
        }

        scenePresenceLoading.value = true;

        try {
            const { data } = await api.get(chronicleCollectionPath(id, 'game-sessions/active'));
            const sceneId = data.game_session?.active_scene_id ?? null;
            activeSceneId.value = sceneId == null ? null : Number(sceneId);

            if (activeSceneId.value == null) {
                onSceneCharacterIds.value = new Set();

                return;
            }

            const participantsResponse = await api.get(`/scenes/${activeSceneId.value}/participants`);
            const rows = participantsResponse.data?.participants ?? [];
            const onScene = new Set();

            for (const row of rows) {
                if (row.is_current && row.character_type === 'player') {
                    onScene.add(Number(row.character_id));
                }
            }

            onSceneCharacterIds.value = onScene;
        } catch {
            activeSceneId.value = null;
            onSceneCharacterIds.value = new Set();
        } finally {
            scenePresenceLoading.value = false;
        }
    }

    async function enterCharacterOnScene(characterId) {
        const numericId = requireCharacterId(characterId);
        const sceneId = activeSceneId.value;

        if (sceneId == null) {
            throw new Error('Нет активной сцены.');
        }

        error.value = '';

        try {
            await api.post(`/scenes/${sceneId}/participants`, {
                character_id: numericId,
                role: 'player',
            });
            onSceneCharacterIds.value = new Set([...onSceneCharacterIds.value, numericId]);
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось выйти на сцену.');
            throw err;
        }
    }

    async function leaveCharacterFromScene(characterId) {
        const numericId = requireCharacterId(characterId);
        const sceneId = activeSceneId.value;

        if (sceneId == null) {
            throw new Error('Нет активной сцены.');
        }

        error.value = '';

        try {
            await api.patch(`/scenes/${sceneId}/participants/${numericId}`);
            const next = new Set(onSceneCharacterIds.value);
            next.delete(numericId);
            onSceneCharacterIds.value = next;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось покинуть сцену.');
            throw err;
        }
    }

    return {
        characters,
        loading,
        error,
        storytellerDenied,
        scenePresenceLoading,
        activeSceneId,
        loadCharacters,
        uploadAvatar,
        uploadSheet,
        loadClans,
        createCharacter,
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
    };
}
