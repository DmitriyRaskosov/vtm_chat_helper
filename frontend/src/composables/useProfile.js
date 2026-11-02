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

    return {
        characters,
        loading,
        error,
        storytellerDenied,
        loadCharacters,
        uploadAvatar,
        uploadSheet,
    };
}
