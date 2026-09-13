import { computed, reactive, ref, watch } from 'vue';
import { api } from '../auth';

export const entityTypes = [
    { value: 'faction', label: 'Фракция' },
    { value: 'location', label: 'Место' },
    { value: 'item', label: 'Предмет' },
    { value: 'concept', label: 'Идея' },
];

const factionSubtypes = [
    { value: 'sect', label: 'Секта' },
    { value: 'clan', label: 'Клан' },
    { value: 'coterie', label: 'Котерия' },
    { value: 'guild', label: 'Гильдия' },
    { value: 'other', label: 'Другое' },
];
const locationSubtypes = [
    { value: 'region', label: 'Регион' },
    { value: 'settlement', label: 'Поселение' },
    { value: 'district', label: 'Район' },
    { value: 'site', label: 'Место' },
    { value: 'room', label: 'Комната' },
];
const itemSubtypes = [
    { value: 'weapon', label: 'Оружие' },
    { value: 'relic', label: 'Реликвия' },
    { value: 'document', label: 'Документ' },
    { value: 'mundane', label: 'Обычный' },
    { value: 'other', label: 'Другое' },
];
const conceptSubtypes = [
    { value: 'tradition', label: 'Традиция' },
    { value: 'principle', label: 'Принцип' },
    { value: 'doctrine', label: 'Доктрина' },
    { value: 'other', label: 'Другое' },
];

export function subtypesFor(type) {
    if (type === 'location') return locationSubtypes;
    if (type === 'item') return itemSubtypes;
    if (type === 'concept') return conceptSubtypes;
    return factionSubtypes;
}

export function typeLabel(type) {
    return entityTypes.find((row) => row.value === type)?.label ?? type;
}

export function subtypeLabel(row) {
    return subtypesFor(row.entity_type).find((item) => item.value === row.subtype)?.label ?? row.subtype ?? '';
}

function defaultSubtype(type) {
    if (type === 'location') return 'site';
    if (type === 'item') return 'mundane';
    if (type === 'concept') return 'other';
    return 'sect';
}

function emptyForm() {
    return {
        id: null,
        canonical_name: '',
        entity_type: 'faction',
        subtype: 'sect',
        short_description: '',
    };
}

export function useWorldEntities({ error, reload }) {
    const entities = ref([]);
    const archived = ref([]);
    const saving = ref(false);
    const form = reactive(emptyForm());

    const factions = computed(() => entities.value.filter((row) => row.entity_type === 'faction'));
    const directoryGroups = computed(() => [
        { type: 'faction', title: 'Фракции', rows: entities.value.filter((row) => row.entity_type === 'faction') },
        { type: 'location', title: 'Места', rows: entities.value.filter((row) => row.entity_type === 'location') },
        { type: 'item', title: 'Предметы', rows: entities.value.filter((row) => row.entity_type === 'item') },
        { type: 'concept', title: 'Идеи', rows: entities.value.filter((row) => row.entity_type === 'concept') },
    ]);
    const editing = computed(() => form.id != null);

    watch(() => form.entity_type, (type) => {
        if (form.id != null) {
            return;
        }
        form.subtype = defaultSubtype(type);
    });

    function resetForm() {
        Object.assign(form, emptyForm());
    }

    function loadEntity(row) {
        form.id = row.id;
        form.canonical_name = row.canonical_name ?? '';
        form.entity_type = row.entity_type;
        form.subtype = row.subtype ?? defaultSubtype(row.entity_type);
        form.short_description = row.short_description ?? '';
    }

    async function fetchEntities() {
        const { data } = await api.get('/world/entities');
        return data;
    }

    function applyEntities(data) {
        entities.value = data.entities ?? [];
        archived.value = data.archived ?? [];
    }

    async function createEntity() {
        saving.value = true;
        error.value = '';
        try {
            const payload = {
                canonical_name: form.canonical_name.trim(),
                entity_type: form.entity_type,
                subtype: form.subtype,
            };
            const description = form.short_description.trim();
            if (description) {
                payload.short_description = description;
            }
            await api.post('/world/entities', payload);
            resetForm();
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.canonical_name?.[0]
                ?? e.response?.data?.errors?.subtype?.[0]
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
            const payload = {
                canonical_name: form.canonical_name.trim(),
                short_description: form.short_description.trim() || null,
                subtype: form.subtype,
            };
            await api.put(`/world/entities/${form.id}`, payload);
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.canonical_name?.[0]
                ?? e.response?.data?.errors?.subtype?.[0]
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
    };
}
