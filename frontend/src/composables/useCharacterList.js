import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, isStoryteller } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useCharacterList() {
    const router = useRouter();
    const characters = ref([]);
    const archived = ref([]);
    const error = ref('');
    const saving = ref(false);
    const form = reactive({
        canonical_name: '',
        character_type: 'npc',
        user_id: null,
    });

    const visibleCharacters = computed(() => characters.value);

    async function load() {
        error.value = '';
        try {
            const { data } = await api.get('/characters');
            characters.value = data.characters ?? [];
            archived.value = data.archived ?? [];
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось загрузить персонажей.');
        }
    }

    async function createCharacter() {
        saving.value = true;
        error.value = '';
        try {
            const payload = {
                canonical_name: form.canonical_name.trim(),
                character_type: form.character_type,
            };
            if (form.character_type === 'player' && form.user_id) {
                payload.user_id = form.user_id;
            }
            const { data } = await api.post('/characters', payload);
            await router.push(`/characters/${data.character.id}`);
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось создать персонажа.');
        } finally {
            saving.value = false;
        }
    }

    async function hideCharacter(id) {
        error.value = '';
        try {
            await api.post(`/characters/${id}/archive`);
            await load();
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось скрыть персонажа.');
        }
    }

    async function restoreCharacter(id) {
        error.value = '';
        try {
            await api.post(`/characters/${id}/restore`);
            await load();
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось вернуть персонажа.');
        }
    }

    return {
        isStoryteller,
        characters: visibleCharacters,
        archived,
        error,
        saving,
        form,
        load,
        createCharacter,
        hideCharacter,
        restoreCharacter,
    };
}
