<template>
    <div v-if="sheet">
        <AppNav current="characters" />

        <p v-if="error" class="error">{{ error }}</p>

        <div v-if="isStoryteller" class="sheet-top-links">
            <RouterLink :to="`/characters/${sheet.id}/diary`" class="link">Дневник</RouterLink>
        </div>

        <SheetHeaderSection
            :identity="identity"
            :sheet="sheet"
            :flash="flash.identity"
            @save="saveIdentity"
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
</template>

<script setup>
import AppNav from '../components/layout/AppNav.vue';
import SheetAdvantagesSection from '../components/sheet/SheetAdvantagesSection.vue';
import SheetBiographySection from '../components/sheet/SheetBiographySection.vue';
import SheetHeaderSection from '../components/sheet/SheetHeaderSection.vue';
import SheetPlaceSection from '../components/sheet/SheetPlaceSection.vue';
import SheetTraitsSection from '../components/sheet/SheetTraitsSection.vue';
import { computed, onMounted, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useCharacterSheet } from '../composables/useCharacterSheet';

const route = useRoute();
const characterId = computed(() => route.params.id);

const {
    load,
    sheet,
    error,
    isStoryteller,
    identity,
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
