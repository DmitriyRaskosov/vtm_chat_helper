import { computed, ref } from 'vue';
import { api, isStoryteller, useAuth } from '../auth';
import { CHRONICLE_NOT_SELECTED, chronicleCollectionPath } from './useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

export function useCharacterList(chronicleId) {
    const { user } = useAuth();
    const characters = ref([]);
    const archived = ref([]);
    const error = ref('');

    const visibleCharacters = computed(() => {
        if (isStoryteller.value) {
            return characters.value;
        }

        const userId = user.value?.id;
        if (userId == null) {
            return [];
        }

        return characters.value.filter(
            (row) => row.character_type === 'player' && Number(row.user_id) === Number(userId),
        );
    });

    async function load() {
        error.value = '';

        let path;
        try {
            path = chronicleCollectionPath(chronicleId, 'characters');
        } catch {
            error.value = CHRONICLE_NOT_SELECTED;

            return;
        }

        try {
            const { data } = await api.get(path);
            characters.value = data.characters ?? [];
            archived.value = data.archived ?? [];
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось загрузить персонажей.');
        }
    }

    return {
        isStoryteller,
        characters: visibleCharacters,
        archived,
        error,
        load,
    };
}
