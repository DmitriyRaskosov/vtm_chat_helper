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
            v-else
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
            :new-lore="newLore"
            :select-lore="selectLore"
            :save-lore="saveLore"
            :archive-lore="archiveLore"
            :restore-lore="restoreLore"
            :exception-toggle="onExceptionToggle"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AppNav from '../components/layout/AppNav.vue';
import WorldDirectoryTab from '../components/world/WorldDirectoryTab.vue';
import WorldLoreTab from '../components/world/WorldLoreTab.vue';
import { useWorldEntities } from '../composables/useWorldEntities';
import { useWorldLore } from '../composables/useWorldLore';
import { useWorldPolitics } from '../composables/useWorldPolitics';

const error = ref('');
const tab = ref('directory');

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
    newLore,
    selectLore,
    saveLore,
    archiveLore,
    restoreLore,
} = useWorldLore({ error, entities, archived });

async function loadBase() {
    error.value = '';
    try {
        const [worldData, politicsData] = await Promise.all([
            fetchEntities(),
            fetchRelations(),
        ]);
        applyEntities(worldData);
        applyRelations(politicsData);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить мир.';
    }
}

async function openLoreTab() {
    tab.value = 'lore';
    await loadLoreTab();
}

onMounted(loadBase);
</script>
