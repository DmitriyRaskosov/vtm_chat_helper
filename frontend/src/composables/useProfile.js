import { ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useProfile() {
    const characters = ref([]);
    const loading = ref(true);
    const error = ref('');
    const storytellerDenied = ref(false);

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

            characters.value = characters.value.map((character) => (
                Number(character.id) === numericId
                    ? { ...character, avatar_path: avatarPath }
                    : character
            ));

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

    return {
        characters,
        loading,
        error,
        storytellerDenied,
        loadCharacters,
        uploadAvatar,
        uploadSheet,
        loadClans,
        createCharacter,
    };
}
