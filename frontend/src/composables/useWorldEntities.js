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

const sectMemberGroups = [
    { type: 'clan', title: 'Кланы' },
    { type: 'coterie', title: 'Котерии' },
    { type: 'circle', title: 'Круги' },
];

const factionRelationGroups = [
    { type: 'location', title: 'Места', relationKey: 'controls' },
    { type: 'item', title: 'Предметы', relationKey: 'owns' },
    { type: 'concept', title: 'Идеи', relationKey: 'part_of' },
];

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
        linked_faction_ids: [],
        part_of_target_ids: [],
    };
}

export function useWorldEntities({
    error,
    reload,
    directoryRelations,
    syncForEntity,
    factionIdsForEntity,
    partOfTargetIdsForConcept,
}) {
    const entities = ref([]);
    const archived = ref([]);
    const saving = ref(false);
    const form = reactive(emptyForm());

    const factions = computed(() => entities.value.filter((row) => row.entity_type === 'faction'));

    const directoryEntityOptions = computed(() => (
        entities.value.filter((row) => row.id !== form.id)
    ));

    function conceptParentConceptId(conceptId) {
        for (const rel of directoryRelations?.value ?? []) {
            if (rel.relation_key !== 'part_of' || rel.source_entity_id !== conceptId) {
                continue;
            }
            const target = entities.value.find((row) => row.id === rel.target_entity_id);
            if (target?.entity_type === 'concept') {
                return target.id;
            }
        }
        return null;
    }

    function conceptsLinkedTo(targetId) {
        return entities.value.filter((row) => (
            row.entity_type === 'concept'
            && (directoryRelations?.value ?? []).some(
                (rel) => rel.relation_key === 'part_of'
                    && rel.source_entity_id === row.id
                    && rel.target_entity_id === targetId,
            )
        ));
    }

    function buildConceptTree(parentConceptId) {
        return entities.value
            .filter((row) => (
                row.entity_type === 'concept'
                && conceptParentConceptId(row.id) === parentConceptId
            ))
            .map((entity) => ({
                entity,
                children: buildConceptTree(entity.id),
            }));
    }

    const conceptTree = computed(() => buildConceptTree(null));

    const flatConceptTree = computed(() => {
        const rows = [];
        const walk = (nodes, depth) => {
            for (const node of nodes) {
                rows.push({ entity: node.entity, depth });
                walk(node.children, depth + 1);
            }
        };
        walk(conceptTree.value, 0);
        return rows;
    });

    const factionTree = computed(() => (
        factions.value.map((faction) => {
            const sectGroups = sectMemberGroups
                .map(({ type, title }) => ({
                    type,
                    title,
                    rows: entities.value.filter(
                        (row) => row.entity_type === type && row.sect_faction_id === faction.id,
                    ),
                }))
                .filter((group) => group.rows.length > 0);

            const relationGroups = factionRelationGroups
                .map(({ type, title, relationKey }) => ({
                    type,
                    title,
                    rows: entities.value.filter((row) => {
                        if (row.entity_type !== type) {
                            return false;
                        }
                        if (relationKey === 'part_of') {
                            return (directoryRelations?.value ?? []).some(
                                (rel) => rel.relation_key === 'part_of'
                                    && rel.source_entity_id === row.id
                                    && rel.target_entity_id === faction.id,
                            );
                        }
                        return (directoryRelations?.value ?? []).some(
                            (rel) => rel.relation_key === relationKey
                                && rel.source_entity_id === faction.id
                                && rel.target_entity_id === row.id,
                        );
                    }),
                }))
                .filter((group) => group.rows.length > 0);

            return {
                faction,
                groups: [...sectGroups, ...relationGroups],
            };
        })
    ));

    const unaffiliatedSectMembers = computed(() => (
        entities.value.filter(
            (row) => hasSectFactionField(row.entity_type) && !row.sect_faction_id,
        )
    ));

    const flatDirectoryGroups = computed(() => [
        { type: 'other', title: 'Прочее', rows: entities.value.filter((row) => row.entity_type === 'other'), collapsible: false },
        { type: 'location', title: 'Места', rows: entities.value.filter((row) => row.entity_type === 'location'), collapsible: true },
        { type: 'item', title: 'Предметы', rows: entities.value.filter((row) => row.entity_type === 'item'), collapsible: true },
    ]);
    const editing = computed(() => form.id != null);

    watch(() => form.entity_type, () => {
        if (form.id != null) {
            return;
        }
        form.parent_faction_id = '';
        form.sect_faction_id = '';
        form.linked_faction_ids = [];
        form.part_of_target_ids = [];
    });

    function resetForm() {
        Object.assign(form, emptyForm());
    }

    function loadLinkedFields(entityId, entityType) {
        if (entityType === 'location') {
            form.linked_faction_ids = [...factionIdsForEntity(entityId, 'controls')];
            form.part_of_target_ids = [];
            return;
        }
        if (entityType === 'item') {
            form.linked_faction_ids = [...factionIdsForEntity(entityId, 'owns')];
            form.part_of_target_ids = [];
            return;
        }
        if (entityType === 'concept') {
            form.linked_faction_ids = [];
            form.part_of_target_ids = [...partOfTargetIdsForConcept(entityId)];
            return;
        }
        form.linked_faction_ids = [];
        form.part_of_target_ids = [];
    }

    function loadEntity(row) {
        form.id = row.id;
        form.canonical_name = row.canonical_name ?? '';
        form.entity_type = row.entity_type;
        form.short_description = row.short_description ?? '';
        form.aliases = [...(row.aliases ?? [])];
        form.parent_faction_id = row.parent_faction_id ? String(row.parent_faction_id) : '';
        form.sect_faction_id = row.sect_faction_id ? String(row.sect_faction_id) : '';
        loadLinkedFields(row.id, row.entity_type);
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

    async function syncEntityRelations(entityId) {
        if (form.entity_type === 'location' || form.entity_type === 'item') {
            await syncForEntity(entityId, form.entity_type, form.linked_faction_ids);
            return;
        }
        if (form.entity_type === 'concept') {
            await syncForEntity(entityId, form.entity_type, [], form.part_of_target_ids);
        }
    }

    async function createEntity() {
        saving.value = true;
        error.value = '';
        try {
            const { data } = await api.post('/world/entities', {
                ...buildPayload(),
                entity_type: form.entity_type,
            });
            const entityId = data.entity?.id;
            if (entityId) {
                await syncEntityRelations(entityId);
            }
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
            await syncEntityRelations(form.id);
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
        directoryEntityOptions,
        factionTree,
        unaffiliatedSectMembers,
        flatDirectoryGroups,
        flatConceptTree,
        conceptsLinkedTo,
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
