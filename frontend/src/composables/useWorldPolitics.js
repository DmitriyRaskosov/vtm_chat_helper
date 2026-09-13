import { reactive, ref } from 'vue';
import { api } from '../auth';

export function politicsLabel(key) {
    return key === 'allied_with' ? 'в союзе с' : 'враждебна к';
}

export function useWorldPolitics({ error, reload }) {
    const relations = ref([]);
    const savingPolitics = ref(false);
    const politics = reactive({
        source_entity_id: '',
        target_entity_id: '',
        relation_key: 'hostile_to',
    });

    async function fetchRelations() {
        const { data } = await api.get('/world/faction-relations');
        return data;
    }

    function applyRelations(data) {
        relations.value = data.relations ?? [];
    }

    async function addPolitics() {
        if (politics.source_entity_id === politics.target_entity_id) {
            error.value = 'Нужны две разные фракции.';
            return;
        }
        savingPolitics.value = true;
        error.value = '';
        try {
            await api.post('/world/faction-relations', {
                source_entity_id: Number(politics.source_entity_id),
                target_entity_id: Number(politics.target_entity_id),
                relation_key: politics.relation_key,
            });
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.target_entity_id?.[0]
                ?? 'Не удалось записать политику.';
        } finally {
            savingPolitics.value = false;
        }
    }

    async function endPolitics(id) {
        error.value = '';
        try {
            await api.post(`/world/faction-relations/${id}/end`);
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось снять отношение.';
        }
    }

    return {
        relations,
        savingPolitics,
        politics,
        fetchRelations,
        applyRelations,
        addPolitics,
        endPolitics,
    };
}
