import { computed, reactive, ref } from 'vue';
import { api } from '../auth';
import { extractionMemoryLabel } from './useCharacterSheet';
import {
    extractionCandidateLabel,
    extractionDirectoryKinds,
} from './useWorldLore';
import { hasSectFactionField } from './useWorldEntities';
import { sceneEventCandidateLabel } from './useSceneExtraction';

export function useExtractionInbox({ error, directoryEntityOptions, factions }) {
    const runs = ref([]);
    const selectedRunId = ref(null);
    const loading = ref(false);
    const reparsing = ref(false);
    const managingCandidate = ref(false);
    const patchingMentionIndex = ref(null);
    const mentionDrafts = reactive({});
    const candidateErrors = reactive({});

    const selectedRun = computed(() => runs.value.find((row) => row.id === selectedRunId.value) ?? null);

    const pendingEvents = computed(() => (
        selectedRun.value?.candidates?.events?.filter((row) => row.status === 'pending') ?? []
    ));
    const pendingMentions = computed(() => (
        selectedRun.value?.candidates?.mentions?.filter((row) => row.status === 'pending') ?? []
    ));
    const pendingRelations = computed(() => (
        selectedRun.value?.candidates?.relations?.filter((row) => row.status === 'pending') ?? []
    ));
    const discardedRelations = computed(() => (
        selectedRun.value?.candidates?.relations?.filter((row) => row.status === 'discarded') ?? []
    ));
    const pendingMemories = computed(() => (
        selectedRun.value?.candidates?.memories?.filter((row) => row.status === 'pending') ?? []
    ));
    const hasPendingCandidates = computed(() => (
        pendingEvents.value.length > 0
        || pendingMentions.value.length > 0
        || pendingRelations.value.length > 0
        || pendingMemories.value.length > 0
    ));

    function mentionDraftFor(row) {
        const key = String(row.index);
        if (!mentionDrafts[key]) {
            mentionDrafts[key] = {
                name: row.name ?? '',
                kind: row.kind ?? 'faction',
                sect_faction_id: row.sect_faction_id ? String(row.sect_faction_id) : '',
                alias_of_entity_id: row.alias_of_entity_id ? String(row.alias_of_entity_id) : '',
                aliases: [...(row.aliases ?? [])],
                aliasDraft: '',
            };
        }
        return mentionDrafts[key];
    }

    function resetMentionDrafts() {
        Object.keys(mentionDrafts).forEach((key) => delete mentionDrafts[key]);
        Object.keys(candidateErrors).forEach((key) => delete candidateErrors[key]);
    }

    function candidateErrorKey(type, index) {
        return `${type}:${index}`;
    }

    function setCandidateError(type, index, message) {
        candidateErrors[candidateErrorKey(type, index)] = message;
    }

    function clearCandidateError(type, index) {
        delete candidateErrors[candidateErrorKey(type, index)];
    }

    function getCandidateError(type, index) {
        return candidateErrors[candidateErrorKey(type, index)] ?? '';
    }

    async function loadInbox() {
        loading.value = true;
        error.value = '';
        try {
            const { data } = await api.get('/extract/inbox');
            runs.value = data.runs ?? [];
            if (selectedRunId.value && !runs.value.some((row) => row.id === selectedRunId.value)) {
                selectedRunId.value = runs.value[0]?.id ?? null;
            }
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось загрузить очередь разбора.';
        } finally {
            loading.value = false;
        }
    }

    async function selectRun(runId) {
        selectedRunId.value = runId;
        resetMentionDrafts();
        error.value = '';
        try {
            const { data } = await api.get(`/extract/${runId}`);
            const index = runs.value.findIndex((row) => row.id === runId);
            if (index >= 0) {
                runs.value[index] = data.run;
            }
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось открыть прогон.';
        }
    }

    async function openRunById(runId) {
        if (!runId) {
            return;
        }
        await loadInbox();
        selectedRunId.value = runId;
        await selectRun(runId);
    }

    function replaceRun(run) {
        const index = runs.value.findIndex((row) => row.id === run.id);
        if (index >= 0) {
            runs.value[index] = run;
        } else {
            runs.value.unshift(run);
        }
        selectedRunId.value = run.id;
    }

    async function reparseSelected() {
        if (!selectedRun.value || reparsing.value || selectedRun.value.source_type !== 'scene') {
            return;
        }
        reparsing.value = true;
        error.value = '';
        try {
            const { data } = await api.post(`/extract/${selectedRun.value.id}/reparse`, {}, { timeout: 320000 });
            replaceRun(data.run);
            resetMentionDrafts();
            await loadInbox();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось переразобрать окно.';
        } finally {
            reparsing.value = false;
        }
    }

    function buildMentionPatch(index) {
        const draft = mentionDraftFor({ index });
        const payload = {
            candidate_type: 'mention',
            name: draft.name.trim(),
            kind: draft.kind,
            aliases: [...draft.aliases],
            alias_of_entity_id: draft.alias_of_entity_id ? Number(draft.alias_of_entity_id) : null,
        };
        if (hasSectFactionField(draft.kind)) {
            payload.sect_faction_id = draft.sect_faction_id ? Number(draft.sect_faction_id) : null;
        }
        return payload;
    }

    async function patchMention(index) {
        if (!selectedRun.value || managingCandidate.value || patchingMentionIndex.value !== null) {
            return;
        }
        patchingMentionIndex.value = index;
        clearCandidateError('mention', index);
        try {
            const { data } = await api.patch(
                `/extract/${selectedRun.value.id}/candidates/${index}`,
                buildMentionPatch(index),
            );
            replaceRun(data.run);
            delete mentionDrafts[String(index)];
        } catch (e) {
            setCandidateError('mention', index, e.response?.data?.message ?? 'Не удалось сохранить правку.');
        } finally {
            patchingMentionIndex.value = null;
        }
    }

    async function acceptCandidate(type, index) {
        if (!selectedRun.value || managingCandidate.value) {
            return;
        }
        managingCandidate.value = true;
        clearCandidateError(type, index);
        try {
            if (type === 'mention') {
                await api.patch(
                    `/extract/${selectedRun.value.id}/candidates/${index}`,
                    buildMentionPatch(index),
                );
            }
            const { data } = await api.post(
                `/extract/${selectedRun.value.id}/candidates/${index}/accept`,
                { candidate_type: type },
            );
            replaceRun(data.run);
            delete mentionDrafts[String(index)];
            if (data.run.status !== 'needs_review') {
                await loadInbox();
            }
        } catch (e) {
            setCandidateError(type, index, e.response?.data?.message ?? 'Не удалось принять кандидата.');
        } finally {
            managingCandidate.value = false;
        }
    }

    async function discardCandidate(type, index) {
        if (!selectedRun.value || managingCandidate.value) {
            return;
        }
        managingCandidate.value = true;
        clearCandidateError(type, index);
        try {
            const { data } = await api.post(
                `/extract/${selectedRun.value.id}/candidates/${index}/discard`,
                { candidate_type: type },
            );
            replaceRun(data.run);
            if (data.run.status !== 'needs_review') {
                await loadInbox();
            }
        } catch (e) {
            setCandidateError(type, index, e.response?.data?.message ?? 'Не удалось отбросить кандидата.');
        } finally {
            managingCandidate.value = false;
        }
    }

    function addMentionAlias(row) {
        const draft = mentionDraftFor(row);
        const value = draft.aliasDraft.trim();
        if (!value || draft.aliases.includes(value)) {
            return;
        }
        draft.aliases.push(value);
        draft.aliasDraft = '';
    }

    return {
        runs,
        selectedRunId,
        selectedRun,
        loading,
        reparsing,
        managingCandidate,
        patchingMentionIndex,
        pendingEvents,
        pendingMentions,
        pendingRelations,
        discardedRelations,
        pendingMemories,
        hasPendingCandidates,
        extractionCandidateLabel,
        extractionDirectoryKinds,
        extractionMemoryLabel,
        sceneEventCandidateLabel,
        mentionDraftFor,
        directoryEntityOptions,
        factions,
        getCandidateError,
        loadInbox,
        selectRun,
        openRunById,
        reparseSelected,
        acceptCandidate,
        discardCandidate,
        patchMention,
        addMentionAlias,
    };
}
