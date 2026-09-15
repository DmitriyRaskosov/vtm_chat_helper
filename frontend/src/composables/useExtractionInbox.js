import { computed, reactive, ref } from 'vue';
import { api } from '../auth';
import { extractionMemoryLabel } from './useCharacterSheet';
import {
    extractionCandidateLabel,
    extractionDirectoryKinds,
} from './useWorldLore';
import { defaultSubtype, subtypesFor } from './useWorldEntities';
import { sceneEventCandidateLabel } from './useSceneExtraction';

export function useExtractionInbox({ error, directoryEntityOptions }) {
    const runs = ref([]);
    const selectedRunId = ref(null);
    const loading = ref(false);
    const reparsing = ref(false);
    const managingCandidate = ref(false);
    const patchingMentionIndex = ref(null);
    const mentionDrafts = reactive({});

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
                subtype: row.subtype ?? defaultSubtype(row.kind ?? 'faction'),
                alias_of_entity_id: row.alias_of_entity_id ? String(row.alias_of_entity_id) : '',
                aliases: [...(row.aliases ?? [])],
                aliasDraft: '',
            };
        }
        return mentionDrafts[key];
    }

    function resetMentionDrafts() {
        Object.keys(mentionDrafts).forEach((key) => delete mentionDrafts[key]);
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

    async function acceptCandidate(type, index) {
        if (!selectedRun.value || managingCandidate.value) {
            return;
        }
        managingCandidate.value = true;
        error.value = '';
        try {
            const { data } = await api.post(
                `/extract/${selectedRun.value.id}/candidates/${index}/accept`,
                { candidate_type: type },
            );
            replaceRun(data.run);
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось принять кандидата.';
        } finally {
            managingCandidate.value = false;
        }
    }

    async function discardCandidate(type, index) {
        if (!selectedRun.value || managingCandidate.value) {
            return;
        }
        managingCandidate.value = true;
        error.value = '';
        try {
            const { data } = await api.post(
                `/extract/${selectedRun.value.id}/candidates/${index}/discard`,
                { candidate_type: type },
            );
            replaceRun(data.run);
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось отбросить кандидата.';
        } finally {
            managingCandidate.value = false;
        }
    }

    async function patchMention(index) {
        if (!selectedRun.value || managingCandidate.value || patchingMentionIndex.value !== null) {
            return;
        }
        const draft = mentionDraftFor({ index });
        patchingMentionIndex.value = index;
        error.value = '';
        try {
            const payload = {
                candidate_type: 'mention',
                name: draft.name.trim(),
                kind: draft.kind,
                subtype: draft.subtype,
                aliases: [...draft.aliases],
                alias_of_entity_id: draft.alias_of_entity_id ? Number(draft.alias_of_entity_id) : null,
            };
            const { data } = await api.patch(
                `/extract/${selectedRun.value.id}/candidates/${index}`,
                payload,
            );
            replaceRun(data.run);
            delete mentionDrafts[String(index)];
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось сохранить правку.';
        } finally {
            patchingMentionIndex.value = null;
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
        pendingMemories,
        hasPendingCandidates,
        extractionCandidateLabel,
        extractionDirectoryKinds,
        extractionMemoryLabel,
        subtypesFor,
        defaultSubtype,
        sceneEventCandidateLabel,
        mentionDraftFor,
        directoryEntityOptions,
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
