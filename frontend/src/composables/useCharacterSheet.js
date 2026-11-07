import { computed, ref, unref } from 'vue';
import { api, isStoryteller } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useCharacterSheet(characterId) {
    const sheet = ref(null);
    const catalog = ref(null);
    const error = ref('');
    const clans = computed(() => catalog.value?.clans ?? []);

    async function load() {
        error.value = '';
        try {
            const requests = [
                api.get(`/characters/${unref(characterId)}`),
                catalog.value
                    ? Promise.resolve({ data: { catalog: catalog.value } })
                    : api.get('/character-sheet/catalog'),
            ];
            const [sheetRes, catalogRes] = await Promise.all(requests);
            if (!catalog.value) {
                catalog.value = {
                    ...catalogRes.data.catalog,
                    clans: catalogRes.data.clans ?? [],
                };
            }
            sheet.value = sheetRes.data.character;
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось загрузить лист.');
        }
    }

    return {
        load,
        sheet,
        error,
        isStoryteller,
        clans,
    };
}
