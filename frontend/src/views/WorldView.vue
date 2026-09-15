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
                @click="openInboxTab"
            >
                Разбор
                <span v-if="inboxCount > 0" class="inbox-tab-count">{{ inboxCount }}</span>
            </button>
        </div>

        <WorldDirectoryTab
            v-if="tab === 'directory'"
            :form="form"
            :editing="editing"
            :directory-groups="directoryGroups"
            :archived="archived"
            :saving="saving"
            :factions="factions"
            :politics="politics"
            :relations="relations"
            :saving-politics="savingPolitics"
            @create="createEntity"
            @save="saveEntity"
            @new="resetForm"
            @edit="loadEntity"
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
            :new-lore="newLore"
            :select-lore="selectLore"
            :save-lore="saveLore"
            :archive-lore="archiveLore"
            :restore-lore="restoreLore"
            :run-extraction="onLoreExtract"
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
            :subtypes-for="inboxSubtypesFor"
            :default-subtype="inboxDefaultSubtype"
            :scene-event-candidate-label="inboxSceneEventCandidateLabel"
            :mention-draft-for="inboxMentionDraftFor"
            :directory-entity-options="directoryEntityOptions"
            @select="onSelectInboxRun"
            @reparse="reparseSelected"
            @accept="inboxAcceptCandidate"
            @discard="inboxDiscardCandidate"
            @patch-mention="inboxPatchMention"
            @add-mention-alias="inboxAddMentionAlias"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import WorldDirectoryTab from '../components/world/WorldDirectoryTab.vue';
import WorldExtractionInboxTab from '../components/world/WorldExtractionInboxTab.vue';
import WorldLoreTab from '../components/world/WorldLoreTab.vue';
import { useExtractionInbox } from '../composables/useExtractionInbox';
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
} = useWorldEntities({ error, reload: () => loadBase() });

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
    subtypesFor: inboxSubtypesFor,
    defaultSubtype: inboxDefaultSubtype,
    sceneEventCandidateLabel: inboxSceneEventCandidateLabel,
    mentionDraftFor: inboxMentionDraftFor,
    loadInbox,
    selectRun,
    openRunById,
    reparseSelected,
    acceptCandidate: inboxAcceptCandidate,
    discardCandidate: inboxDiscardCandidate,
    patchMention: inboxPatchMention,
    addMentionAlias: inboxAddMentionAlias,
} = useExtractionInbox({ error, directoryEntityOptions });

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
        const [worldData, politicsData] = await Promise.all([
            fetchEntities(),
            fetchRelations(),
        ]);
        applyEntities(worldData);
        applyRelations(politicsData);
        await loadInboxCount();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить мир.';
    }
}

async function openLoreTab() {
    tab.value = 'lore';
    await loadLoreTab();
}

async function openInboxTab(runId = null) {
    tab.value = 'inbox';
    if (runId) {
        await openRunById(runId);
    } else {
        await loadInbox();
    }
    await loadInboxCount();
}

async function onLoreExtract() {
    const runId = await runExtraction();
    if (runId) {
        await openInboxTab(runId);
        router.replace({ query: { tab: 'inbox', run: String(runId) } });
    }
}

async function onSelectInboxRun(runId) {
    await selectRun(runId);
    router.replace({ query: { tab: 'inbox', run: String(runId) } });
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
