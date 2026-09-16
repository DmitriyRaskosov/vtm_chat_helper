<template>
    <div>
        <AppNav current="world" />

        <p v-if="error" class="error">{{ error }}</p>

        <div class="world-tabs">
            <button
                type="button"
                class="secondary"
                :class="{ current: tab === 'directory' }"
                @click="tab = 'directory'"
            >
                Справочник
            </button>
            <button
                type="button"
                class="secondary"
                :class="{ current: tab === 'lore' }"
                @click="openLoreTab"
            >
                Лор
            </button>
            <button
                type="button"
                class="secondary"
                :class="{ current: tab === 'inbox' }"
                @click="openInboxTab()"
            >
                Разбор
                <span v-if="inboxCount > 0" class="inbox-tab-count">{{ inboxCount }}</span>
            </button>
        </div>

        <WorldDirectoryTab
            v-if="tab === 'directory'"
            :form="form"
            :editing="editing"
            :faction-tree="factionTree"
            :unaffiliated-sect-members="unaffiliatedSectMembers"
            :flat-directory-groups="flatDirectoryGroups"
            :flat-concept-tree="flatConceptTree"
            :directory-entity-options="formDirectoryOptions"
            :concepts-linked-to="conceptsLinkedTo"
            :archived="archived"
            :saving="saving"
            :factions="factions"
            :politics="politics"
            :relations="relations"
            :saving-politics="savingPolitics"
            @create="createEntity"
            @save="saveEntity"
            @new="resetForm"
            @edit="onEditEntity"
            @archive="archiveEntity"
            @restore="restoreEntity"
            @add-politics="addPolitics"
            @end-politics="endPolitics"
        />
        <WorldLoreTab
            v-else-if="tab === 'lore'"
            :lore-list="loreList"
            :lore-archived="loreArchived"
            :lore-form="loreForm"
            :about-options="aboutOptions"
            :filtered-about-options="filteredAboutOptions"
            v-model:about-search="aboutSearch"
            v-model:about-filter="aboutFilter"
            :character-options="characterOptions"
            :saving-lore="savingLore"
            :flash-lore="flashLore"
            :extractor-enabled="extractorEnabled"
            :extracting="extracting"
            :lore-limits="loreLimits"
            :lore-window="loreWindow"
            :new-lore="newLore"
            :select-lore="onSelectLore"
            :save-lore="saveLore"
            :archive-lore="archiveLore"
            :restore-lore="restoreLore"
            :run-extraction="onLoreExtract"
            :run-reparse="onLoreReparse"
            :exception-toggle="onExceptionToggle"
        />
        <WorldExtractionInboxTab
            v-else
            :runs="inboxRuns"
            :selected-run-id="selectedRunId"
            :selected-run="selectedRun"
            :loading="inboxLoading"
            :reparsing="inboxReparsing"
            :managing-candidate="inboxManagingCandidate"
            :patching-mention-index="inboxPatchingMentionIndex"
            :pending-events="inboxPendingEvents"
            :pending-mentions="inboxPendingMentions"
            :pending-relations="inboxPendingRelations"
            :pending-memories="inboxPendingMemories"
            :has-pending-candidates="inboxHasPendingCandidates"
            :extraction-candidate-label="inboxExtractionCandidateLabel"
            :extraction-directory-kinds="inboxExtractionDirectoryKinds"
            :extraction-memory-label="inboxExtractionMemoryLabel"
            :discarded-relations="inboxDiscardedRelations"
            :scene-event-candidate-label="inboxSceneEventCandidateLabel"
            :mention-draft-for="inboxMentionDraftFor"
            :directory-entity-options="directoryEntityOptions"
            :factions="factions"
            :get-candidate-error="inboxGetCandidateError"
            @select="onSelectInboxRun"
            @reparse="reparseSelected"
            @accept="onInboxAcceptCandidate"
            @discard="onInboxDiscardCandidate"
            @patch-mention="inboxPatchMention"
            @add-mention-alias="inboxAddMentionAlias"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import WorldDirectoryTab from '../components/world/WorldDirectoryTab.vue';
import WorldExtractionInboxTab from '../components/world/WorldExtractionInboxTab.vue';
import WorldLoreTab from '../components/world/WorldLoreTab.vue';
import { useExtractionInbox } from '../composables/useExtractionInbox';
import { useWorldDirectoryRelations } from '../composables/useWorldDirectoryRelations';
import { useWorldEntities } from '../composables/useWorldEntities';
import { useWorldLore } from '../composables/useWorldLore';
import { useWorldPolitics } from '../composables/useWorldPolitics';
import { api } from '../auth';

const route = useRoute();
const router = useRouter();
const error = ref('');
const tab = ref('directory');
const inboxCount = ref(0);

const {
    relations: directoryRelations,
    fetchRelations: fetchDirectoryRelations,
    applyRelations: applyDirectoryRelations,
    syncForEntity,
    factionIdsForEntity,
    partOfTargetIdsForConcept,
} = useWorldDirectoryRelations();

const {
    entities,
    archived,
    saving,
    form,
    editing,
    factions,
    factionTree,
    unaffiliatedSectMembers,
    flatDirectoryGroups,
    flatConceptTree,
    directoryEntityOptions: formDirectoryOptions,
    conceptsLinkedTo,
    fetchEntities,
    applyEntities,
    createEntity,
    saveEntity,
    resetForm,
    loadEntity,
    archiveEntity,
    restoreEntity,
} = useWorldEntities({
    error,
    reload: () => loadBase(),
    directoryRelations,
    syncForEntity,
    factionIdsForEntity,
    partOfTargetIdsForConcept,
});

const directoryEntityOptions = computed(() => (
    entities.value.filter((row) => row.entity_type !== 'character')
));

const {
    relations,
    savingPolitics,
    politics,
    fetchRelations,
    applyRelations,
    addPolitics,
    endPolitics,
} = useWorldPolitics({ error, reload: () => loadBase() });

const {
    savingLore,
    flashLore,
    loreList,
    loreArchived,
    characterOptions,
    loreForm,
    aboutOptions,
    filteredAboutOptions,
    aboutSearch,
    aboutFilter,
    onExceptionToggle,
    openLoreTab: loadLoreTab,
    extractorEnabled,
    extracting,
    loreLimits,
    loreWindow,
    runExtraction,
    newLore,
    selectLore,
    saveLore,
    archiveLore,
    restoreLore,
} = useWorldLore({
    error,
    entities,
    archived,
});

const {
    runs: inboxRuns,
    selectedRunId,
    selectedRun,
    loading: inboxLoading,
    reparsing: inboxReparsing,
    managingCandidate: inboxManagingCandidate,
    patchingMentionIndex: inboxPatchingMentionIndex,
    pendingEvents: inboxPendingEvents,
    pendingMentions: inboxPendingMentions,
    pendingRelations: inboxPendingRelations,
    pendingMemories: inboxPendingMemories,
    hasPendingCandidates: inboxHasPendingCandidates,
    extractionCandidateLabel: inboxExtractionCandidateLabel,
    extractionDirectoryKinds: inboxExtractionDirectoryKinds,
    extractionMemoryLabel: inboxExtractionMemoryLabel,
    discardedRelations: inboxDiscardedRelations,
    sceneEventCandidateLabel: inboxSceneEventCandidateLabel,
    mentionDraftFor: inboxMentionDraftFor,
    getCandidateError: inboxGetCandidateError,
    loadInbox,
    selectRun,
    openRunById,
    reparseSelected,
    acceptCandidate: inboxAcceptCandidate,
    discardCandidate: inboxDiscardCandidate,
    patchMention: inboxPatchMention,
    addMentionAlias: inboxAddMentionAlias,
} = useExtractionInbox({ error, directoryEntityOptions, factions });

async function loadInboxCount() {
    try {
        const { data } = await api.get('/extract/inbox', { params: { count_only: true } });
        inboxCount.value = Number(data.count ?? 0);
    } catch {
        inboxCount.value = 0;
    }
}

async function loadBase() {
    error.value = '';
    try {
        const [worldData, politicsData, directoryData] = await Promise.all([
            fetchEntities(),
            fetchRelations(),
            fetchDirectoryRelations(),
        ]);
        applyEntities(worldData);
        applyRelations(politicsData);
        applyDirectoryRelations(directoryData);
        await loadInboxCount();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить мир.';
    }
}

function scrollToForm(elementId) {
    nextTick(() => {
        document.getElementById(elementId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function onEditEntity(row) {
    loadEntity(row);
    scrollToForm('world-directory-form');
}

function onSelectLore(id) {
    selectLore(id);
    scrollToForm('world-lore-form');
}

async function openLoreTab() {
    tab.value = 'lore';
    await loadLoreTab();
}

async function openInboxTab(runId = null) {
    tab.value = 'inbox';
    const id = typeof runId === 'number' && Number.isFinite(runId) ? runId : null;
    if (id) {
        await openRunById(id);
    } else {
        await loadInbox();
    }
    await loadInboxCount();
}

async function openLoreExtractionRun(runId) {
    if (runId) {
        await openInboxTab(runId);
        router.replace({ query: { tab: 'inbox', run: String(runId) } });
    }
}

async function onLoreExtract() {
    await openLoreExtractionRun(await runExtraction());
}

async function onLoreReparse() {
    await openLoreExtractionRun(await runExtraction({ reparse: true }));
}

async function onSelectInboxRun(runId) {
    await selectRun(runId);
    router.replace({ query: { tab: 'inbox', run: String(runId) } });
}

async function onInboxAcceptCandidate(type, index) {
    await inboxAcceptCandidate(type, index);
    await loadInboxCount();
}

async function onInboxDiscardCandidate(type, index) {
    await inboxDiscardCandidate(type, index);
    await loadInboxCount();
}

watch(() => route.query.tab, async (value) => {
    if (value === 'inbox') {
        const runId = route.query.run ? Number(route.query.run) : null;
        await openInboxTab(runId);
    }
}, { immediate: true });

onMounted(async () => {
    await loadBase();
    if (route.query.tab === 'inbox') {
        const runId = route.query.run ? Number(route.query.run) : null;
        await openInboxTab(runId);
    }
});
</script>
