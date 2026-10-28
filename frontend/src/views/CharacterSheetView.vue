<template>
    <div class="with-sidebar">
        <AppNav current="characters" />
        <div class="page-main">
        <div v-if="sheet">

        <p v-if="error" class="error">{{ error }}</p>

        <div v-if="isStoryteller" class="sheet-top-links">
            <RouterLink :to="diaryLink" class="link">Дневник</RouterLink>
        </div>

        <SheetImageSection :character-id="characterId" :can-upload="isStoryteller" />

        <SheetHeaderSection
            :identity="identity"
            :role-details="roleDetails"
            :sheet="sheet"
            :flash="flash.identity"
            :role-flash="flash.role"
            :is-storyteller="isStoryteller"
            @save="saveIdentity"
            @save-role="saveRoleDetails"
        />

        <SheetAdvantagesSection
            v-model:new-discipline-id="newDisciplineId"
            :discipline-rows="disciplineRows"
            :unused-disciplines="unusedDisciplines"
            @set-discipline="setDiscipline"
            @add-discipline="addDiscipline"
            @remove-discipline="removeDiscipline"
        />

        <SheetBiographySection
            :sheet="sheet"
            :biography="biography"
            :flash="flash.biography"
            @save="saveBiography"
        />

        <SheetTraitsSection
            :traits="traits"
            :presets="traitPresets"
            :flash="flash.traits"
            @save="saveTraits"
            @add-preset="addTraitPreset"
            @add-custom="addCustomTrait"
            @remove="removeTrait"
        />

        <SheetPlaceSection
            :sheet="sheet"
            :place="place"
            :sects="sects"
            :clans="clans"
            :is-storyteller="isStoryteller"
            :missing-place-option="missingPlaceOption"
            :flash="flash.place"
            @save="savePlace"
        />
        </div>
        <p v-else-if="error" class="error">{{ error }}</p>
        <p v-else class="muted">Загрузка…</p>
        </div>
    </div>
</template>

<script setup>
import AppNav from '../components/layout/AppNav.vue';
import SheetAdvantagesSection from '../components/sheet/SheetAdvantagesSection.vue';
import SheetBiographySection from '../components/sheet/SheetBiographySection.vue';
import SheetHeaderSection from '../components/sheet/SheetHeaderSection.vue';
import SheetImageSection from '../components/sheet/SheetImageSection.vue';
import SheetPlaceSection from '../components/sheet/SheetPlaceSection.vue';
import SheetTraitsSection from '../components/sheet/SheetTraitsSection.vue';
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
    identity,
    roleDetails,
    biography,
    place,
    newDisciplineId,
    traits,
    traitPresets,
    flash,
    sects,
    clans,
    disciplineRows,
    unusedDisciplines,
    formatSigned,
    missingPlaceOption,
    saveIdentity,
    saveRoleDetails,
    saveBiography,
    saveTraits,
    addTraitPreset,
    addCustomTrait,
    removeTrait,
    savePlace,
    setDiscipline,
    addDiscipline,
    removeDiscipline,
} = useCharacterSheet(characterId);

watch(characterId, load);
onMounted(load);
</script>
