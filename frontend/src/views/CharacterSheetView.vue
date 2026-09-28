<template>
    <div v-if="sheet">
        <AppNav current="characters" />

        <p v-if="error" class="error">{{ error }}</p>

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
            />
        </template>

        <SheetBiographySection
            :sheet="sheet"
            :biography="biography"
            :flash="flash.biography"
            :is-storyteller="isStoryteller"
            :extractor-enabled="extractorEnabled"
            :extracting="extracting"
            @save="saveBiography"
            @extract="onBiographyExtract"
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
import { useRouter } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import SheetAdvantagesSection from '../components/sheet/SheetAdvantagesSection.vue';
import SheetBiographySection from '../components/sheet/SheetBiographySection.vue';
import SheetHeaderSection from '../components/sheet/SheetHeaderSection.vue';
import SheetPlaceSection from '../components/sheet/SheetPlaceSection.vue';
import { useCharacterSheet } from '../composables/useCharacterSheet';

const router = useRouter();

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
    flash,
    sects,
    clans,
    havens,
    backgroundTraits,
    disciplineRows,
    unusedDisciplines,
    formatSigned,
    missingPlaceOption,
    saveIdentity,
    saveBiography,
    savePlace,
    createPlaceEntity,
    setDiscipline,
    addDiscipline,
    extractorEnabled,
    extracting,
    runBiographyExtraction,
} = useCharacterSheet();

async function onBiographyExtract() {
    const runId = await runBiographyExtraction();
    if (runId) {
        router.push({ name: 'world', query: { tab: 'inbox', run: String(runId) } });
    }
}
</script>
