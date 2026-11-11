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

        <section v-if="canEditNotes" class="card sheet-notes">
            <h2 class="sheet-section-title">Заметки</h2>
            <p class="muted">Личные заметки. Видны только вам.</p>
            <textarea
                v-model="notesContent"
                rows="8"
                maxlength="20000"
                placeholder="Планы, напоминания, детали…"
                :disabled="notesLoading"
            />
            <p v-if="notesError" class="error">{{ notesError }}</p>
            <p v-else-if="notesSaving" class="muted">Сохранение…</p>
            <div class="sheet-actions">
                <button type="button" :disabled="notesLoading || notesSaving" @click="saveNotes">
                    Сохранить
                </button>
            </div>
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
import { useAuth } from '../auth';
import { useCharacterSheet } from '../composables/useCharacterSheet';
import { useUserNotes } from '../composables/useUserNotes';

const route = useRoute();
const auth = useAuth();
const characterId = computed(() => route.params.id);
const chronicleId = computed(() => route.params.chronicleId);
const diaryLink = computed(() => `/c/${route.params.chronicleId}/characters/${route.params.id}/diary`);

const {
    load,
    sheet,
    error,
    isStoryteller,
    clans,
} = useCharacterSheet(characterId);

const canEditNotes = computed(() => {
    if (!sheet.value) {
        return false;
    }
    if (isStoryteller.value) {
        return true;
    }

    const ownerId = sheet.value.user_id;
    const userId = auth.user.value?.id;

    return ownerId != null && userId != null && Number(ownerId) === Number(userId);
});

const {
    content: notesContent,
    loading: notesLoading,
    saving: notesSaving,
    error: notesError,
    load: loadNotes,
    save: saveNotes,
} = useUserNotes(chronicleId, characterId, { autoSave: true, reloadOnCharacterChange: true });

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

watch(canEditNotes, (allowed) => {
    if (allowed) {
        loadNotes();
    }
});

watch(sheet, (value) => {
    if (value && canEditNotes.value) {
        loadNotes();
    }
});

onMounted(() => {
    load();
});
</script>
