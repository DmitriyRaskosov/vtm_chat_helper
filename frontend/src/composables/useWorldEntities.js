import { computed, reactive, ref, watch } from 'vue';
import { api } from '../auth';

export const entityTypes = [
    { value: 'faction', label: 'Секта / фракция' },
    { value: 'clan', label: 'Клан' },
    { value: 'coterie', label: 'Котерия' },
    { value: 'circle', label: 'Круг' },
    { value: 'other', label: 'Прочее' },
    { value: 'location', label: 'Место' },
    { value: 'item', label: 'Предмет' },
    { value: 'concept', label: 'Идея' },
];

const sectAffiliatedTypes = ['clan', 'coterie', 'circle'];

export function hasSectFactionField(type) {
    return sectAffiliatedTypes.includes(type);
}

export function typeLabel(type) {
    return entityTypes.find((row) => row.value === type)?.label ?? type;
}

export function sectFactionName(row, factions) {
    if (!hasSectFactionField(row.entity_type) || !row.sect_faction_id) {
        return '';
    }
    const faction = factions.find((item) => item.id === row.sect_faction_id);
    return faction?.canonical_name ?? '';
}

function emptyForm() {
    return {
        id: null,
        canonical_name: '',
        entity_type: 'faction',
        short_description: '',
        aliases: [],
        parent_faction_id: '',
        sect_faction_id: '',
    };
}

export function useWorldEntities({ error, reload }) {
    const entities = ref([]);
    const archived = ref([]);
    const saving = ref(false);
    const form = reactive(emptyForm());

    const factions = computed(() => entities.value.filter((row) => row.entity_type === 'faction'));
    const directoryGroups = computed(() => [
        { type: 'faction', title: 'Секты / фракции', rows: entities.value.filter((row) => row.entity_type === 'faction') },
        { type: 'clan', title: 'Кланы', rows: entities.value.filter((row) => row.entity_type === 'clan') },
        { type: 'coterie', title: 'Котерии', rows: entities.value.filter((row) => row.entity_type === 'coterie') },
        { type: 'circle', title: 'Круги', rows: entities.value.filter((row) => row.entity_type === 'circle') },
        { type: 'other', title: 'Прочее', rows: entities.value.filter((row) => row.entity_type === 'other') },
        { type: 'location', title: 'Места', rows: entities.value.filter((row) => row.entity_type === 'location') },
        { type: 'item', title: 'Предметы', rows: entities.value.filter((row) => row.entity_type === 'item') },
        { type: 'concept', title: 'Идеи', rows: entities.value.filter((row) => row.entity_type === 'concept') },
    ]);
    const editing = computed(() => form.id != null);

    watch(() => form.entity_type, () => {
        if (form.id != null) {
            return;
        }
        form.parent_faction_id = '';
        form.sect_faction_id = '';
    });

    function resetForm() {
        Object.assign(form, emptyForm());
    }

    function loadEntity(row) {
        form.id = row.id;
        form.canonical_name = row.canonical_name ?? '';
        form.entity_type = row.entity_type;
        form.short_description = row.short_description ?? '';
        form.aliases = [...(row.aliases ?? [])];
        form.parent_faction_id = row.parent_faction_id ? String(row.parent_faction_id) : '';
        form.sect_faction_id = row.sect_faction_id ? String(row.sect_faction_id) : '';
    }

    async function fetchEntities() {
        const { data } = await api.get('/world/entities');
        return data;
    }

    function applyEntities(data) {
        entities.value = data.entities ?? [];
        archived.value = data.archived ?? [];
    }

    function buildPayload() {
        const payload = {
            canonical_name: form.canonical_name.trim(),
            aliases: [...form.aliases],
        };
        const description = form.short_description.trim();
        if (description) {
            payload.short_description = description;
        }
        if (form.entity_type === 'faction') {
            payload.parent_faction_id = form.parent_faction_id ? Number(form.parent_faction_id) : null;
        }
        if (hasSectFactionField(form.entity_type)) {
            payload.sect_faction_id = form.sect_faction_id ? Number(form.sect_faction_id) : null;
        }
        return payload;
    }

    async function createEntity() {
        saving.value = true;
        error.value = '';
        try {
            await api.post('/world/entities', {
                ...buildPayload(),
                entity_type: form.entity_type,
            });
            resetForm();
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.canonical_name?.[0]
                ?? e.response?.data?.errors?.parent_faction_id?.[0]
                ?? e.response?.data?.errors?.sect_faction_id?.[0]
                ?? 'Не удалось создать сущность.';
        } finally {
            saving.value = false;
        }
    }

    async function saveEntity() {
        if (!form.id) {
            return createEntity();
        }
        saving.value = true;
        error.value = '';
        try {
            await api.put(`/world/entities/${form.id}`, {
                ...buildPayload(),
                short_description: form.short_description.trim() || null,
            });
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.canonical_name?.[0]
                ?? e.response?.data?.errors?.parent_faction_id?.[0]
                ?? e.response?.data?.errors?.sect_faction_id?.[0]
                ?? 'Не удалось сохранить сущность.';
        } finally {
            saving.value = false;
        }
    }

    async function archiveEntity(id) {
        error.value = '';
        try {
            await api.post(`/world/entities/${id}/archive`);
            if (form.id === id) {
                resetForm();
            }
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось скрыть сущность.';
        }
    }

    async function restoreEntity(id) {
        error.value = '';
        try {
            await api.post(`/world/entities/${id}/restore`);
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось вернуть сущность.';
        }
    }

    return {
        entities,
        archived,
        saving,
        form,
        editing,
        factions,
        directoryGroups,
        fetchEntities,
        applyEntities,
        createEntity,
        saveEntity,
        resetForm,
        loadEntity,
        archiveEntity,
        restoreEntity,
        hasSectFactionField,
        sectFactionName,
    };
}
