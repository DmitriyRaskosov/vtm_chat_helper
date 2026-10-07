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

        <template v-if="!compact">
            <SheetAdvantagesSection
                v-model:new-discipline-id="newDisciplineId"
                :discipline-rows="disciplineRows"
                :unused-disciplines="unusedDisciplines"
                @set-discipline="setDiscipline"
                @add-discipline="addDiscipline"
                @remove-discipline="removeDiscipline"
            />
        </template>

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
            :creating="creating"
            :create-names="createNames"
            :sects="sects"
            :clans="clans"
            :havens="havens"
            :is-storyteller="isStoryteller"
            :missing-place-option="missingPlaceOption"
            :flash="flash.place"
            @save="savePlace"
            @create-entity="createPlaceEntity"
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
import { useCharacterSheet } from '../composables/useCharacterSheet';
import { RouterLink } from 'vue-router';

const {
    sheet,
    error,
    isStoryteller,
    identity,
    biography,
    place,
    creating,
    createNames,
    newDisciplineId,
    traits,
    traitPresets,
    flash,
    sects,
    clans,
    havens,
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
    createPlaceEntity,
    setDiscipline,
    addDiscipline,
    removeDiscipline,
} = useCharacterSheet();
</script>
