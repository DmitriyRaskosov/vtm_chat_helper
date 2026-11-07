<template>
    <div class="with-sidebar">
        <AppNav current="characters" />
        <div class="page-main">
        <div v-if="sheet">

        <p v-if="error" class="error">{{ error }}</p>

        <div v-if="isStoryteller" class="sheet-top-links">
            <RouterLink :to="diaryLink" class="link">Дневник</RouterLink>
        </div>

        <section class="card">
            <CharacterSheetGrid
                :character-id="characterId"
                :stats="sheet.stats"
                :schema="sheet.sheet_schema"
                :sheet="sheet"
                :sheet-data="sheet.sheet_data"
                :available-disciplines="sheet.available_disciplines"
                :clans="clans"
                @saved="onStatsSaved"
            />
        </section>
        </div>
        <p v-else-if="error" class="error">{{ error }}</p>
        <p v-else class="muted">Загрузка…</p>
        </div>
    </div>
</template>

<script setup>
import AppNav from '../components/layout/AppNav.vue';
import CharacterSheetGrid from '../components/sheet/CharacterSheetGrid.vue';
import { computed, onMounted, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useCharacterSheet } from '../composables/useCharacterSheet';

const route = useRoute();
const characterId = computed(() => route.params.id);
const diaryLink = computed(() => `/c/${route.params.chronicleId}/characters/${route.params.id}/diary`);

const {
    load,
    sheet,
    error,
    isStoryteller,
    clans,
} = useCharacterSheet(characterId);

function onStatsSaved(payload) {
    if (!sheet.value || !payload) {
        return;
    }

    if (payload.character && typeof payload.character === 'object') {
        const c = payload.character;
        sheet.value.canonical_name = c.canonical_name;
        sheet.value.nature = c.nature;
        sheet.value.demeanor = c.demeanor;
        sheet.value.generation = c.generation;
        sheet.value.clan_id = c.clan_id;
        sheet.value.clan = c.clan ?? null;
    }
    if (payload.stats) {
        sheet.value.stats = payload.stats;
    }
    if (payload.sheet_data) {
        sheet.value.sheet_data = payload.sheet_data;
    }
}

watch(characterId, load);
onMounted(load);
</script>
