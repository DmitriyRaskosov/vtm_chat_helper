import { computed, reactive, ref, watch } from 'vue';
import { api } from '../auth';

export const entityTypes = [
    { value: 'faction', label: 'Группа' },
    { value: 'location', label: 'Место' },
    { value: 'item', label: 'Предмет' },
    { value: 'concept', label: 'Идея' },
];

const factionSubtypes = [
    { value: 'sect', label: 'Секта' },
    { value: 'clan', label: 'Клан' },
    { value: 'coterie', label: 'Котерия' },
    { value: 'circle', label: 'Круг' },
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
    const label = subtypesFor(row.entity_type).find((item) => item.value === row.subtype)?.label ?? row.subtype ?? '';
    return label === '—' ? '' : label;
}

export function defaultSubtype(type) {
    if (type === 'location') return 'site';
    if (type === 'item') return 'mundane';
    if (type === 'faction') return 'other';
    return 'other';
}

function emptyForm() {
    return {
        id: null,
        canonical_name: '',
        entity_type: 'faction',
        subtype: 'other',
        short_description: '',
        aliases: [],
        parent_faction_id: '',
    };
}

export function useWorldEntities({ error, reload }) {
    const entities = ref([]);
    const archived = ref([]);
    const saving = ref(false);
    const form = reactive(emptyForm());

    const factions = computed(() => entities.value.filter((row) => row.entity_type === 'faction'));
    const directoryGroups = computed(() => [
        { type: 'faction', title: 'Группы', rows: entities.value.filter((row) => row.entity_type === 'faction') },
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
        form.parent_faction_id = '';
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
        form.aliases = [...(row.aliases ?? [])];
        form.parent_faction_id = row.parent_faction_id ? String(row.parent_faction_id) : '';
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
                aliases: [...form.aliases],
            };
            const description = form.short_description.trim();
            if (description) {
                payload.short_description = description;
            }
            if (form.entity_type === 'faction') {
                payload.parent_faction_id = form.parent_faction_id ? Number(form.parent_faction_id) : null;
            }
            await api.post('/world/entities', payload);
            resetForm();
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.canonical_name?.[0]
                ?? e.response?.data?.errors?.subtype?.[0]
                ?? e.response?.data?.errors?.parent_faction_id?.[0]
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
                aliases: [...form.aliases],
            };
            if (form.entity_type === 'faction') {
                payload.parent_faction_id = form.parent_faction_id ? Number(form.parent_faction_id) : null;
            }
            await api.put(`/world/entities/${form.id}`, payload);
            await reload();
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.canonical_name?.[0]
                ?? e.response?.data?.errors?.subtype?.[0]
                ?? e.response?.data?.errors?.parent_faction_id?.[0]
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
