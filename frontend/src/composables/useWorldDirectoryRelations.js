import { ref } from 'vue';
import { api } from '../auth';

const DIRECTORY_RELATION_KEYS = 'controls,owns,part_of';

export function useWorldDirectoryRelations() {
    const relations = ref([]);

    async function fetchRelations() {
        const { data } = await api.get('/world/relations', { params: { keys: DIRECTORY_RELATION_KEYS } });
        return data;
    }

    function applyRelations(data) {
        relations.value = data.relations ?? [];
    }

    function factionIdsForEntity(entityId, relationKey) {
        return relations.value
            .filter((row) => row.relation_key === relationKey && row.target_entity_id === entityId)
            .map((row) => row.source_entity_id);
    }

    function partOfTargetIdsForConcept(conceptId) {
        return relations.value
            .filter((row) => row.relation_key === 'part_of' && row.source_entity_id === conceptId)
            .map((row) => row.target_entity_id);
    }

    async function syncFactionLinks(entityId, entityType, factionIds) {
        const relationKey = entityType === 'location' ? 'controls' : entityType === 'item' ? 'owns' : null;
        if (!relationKey) {
            return;
        }

        const desired = new Set(factionIds.map((id) => Number(id)));
        const current = relations.value.filter(
            (row) => row.relation_key === relationKey && row.target_entity_id === entityId,
        );

        for (const factionId of desired) {
            if (!current.some((row) => row.source_entity_id === factionId)) {
                await api.post('/world/relations', {
                    source_entity_id: factionId,
                    target_entity_id: entityId,
                    relation_key: relationKey,
                });
            }
        }

        for (const row of current) {
            if (!desired.has(row.source_entity_id)) {
                await api.post(`/world/relations/${row.id}/end`);
            }
        }
    }

    async function syncPartOfForConcept(conceptId, targetIds) {
        const desired = new Set(targetIds.map((id) => Number(id)));
        const current = relations.value.filter(
            (row) => row.relation_key === 'part_of' && row.source_entity_id === conceptId,
        );

        for (const targetId of desired) {
            if (!current.some((row) => row.target_entity_id === targetId)) {
                await api.post('/world/relations', {
                    source_entity_id: conceptId,
                    target_entity_id: targetId,
                    relation_key: 'part_of',
                });
            }
        }

        for (const row of current) {
            if (!desired.has(row.target_entity_id)) {
                await api.post(`/world/relations/${row.id}/end`);
            }
        }
    }

    async function syncForEntity(entityId, entityType, factionIds, partOfTargetIds = []) {
        if (entityType === 'concept') {
            await syncPartOfForConcept(entityId, partOfTargetIds);
            return;
        }
        await syncFactionLinks(entityId, entityType, factionIds);
    }

    return {
        relations,
        fetchRelations,
        applyRelations,
        factionIdsForEntity,
        partOfTargetIdsForConcept,
        syncForEntity,
    };
}
