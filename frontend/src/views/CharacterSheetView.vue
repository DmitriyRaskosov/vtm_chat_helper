<template>
    <div v-if="sheet">
        <AppNav current="characters" />

        <p v-if="error" class="error">{{ error }}</p>

        <SheetPlayBar
            v-model:blood-pool="bloodPool"
            v-model:temp-willpower="tempWillpower"
            v-model:experience="experience"
            :health-boxes="healthBoxes"
            :health-damage="healthDamage"
            :health-level="healthLevel"
            :health-label="healthLabel"
            @save-status="saveStatus"
            @save-experience="saveExperience"
            @set-health-level="setHealthLevel"
            @set-health-damage="setHealthDamage"
        />

        <SheetHeaderSection
            :identity="identity"
            :sheet="sheet"
            :flash="flash.identity"
            @save="saveIdentity"
        />

        <SheetTraitSection
            title="Attributes"
            category="attribute"
            :groups="attributeGroups"
            :trait-value="traitValue"
            @set="setTrait"
        />

        <template v-if="!compact">
            <SheetTraitSection
                title="Abilities"
                category="ability"
                :groups="abilityGroups"
                :trait-value="traitValue"
                @set="setTrait"
            />

            <SheetAdvantagesSection
                v-model:new-discipline-id="newDisciplineId"
                :discipline-rows="disciplineRows"
                :unused-disciplines="unusedDisciplines"
                :background-traits="backgroundTraits"
                :virtue-traits="virtueTraits"
                :trait-value="traitValue"
                @set-discipline="setDiscipline"
                @add-discipline="addDiscipline"
                @set-trait="setTrait"
            />

            <SheetMeritsSection
                :merits="merits"
                :merit-totals="meritTotals"
                :merit-signed="meritSigned"
                :format-signed="formatSigned"
                :flash-add="flash.meritAdd"
                :flash-save="flash.merits"
                @add="addMerit"
                @remove="removeMerit"
                @save="saveMerits"
            />

            <SheetOtherSection
                :traits="otherTraits"
                :trait-value="traitValue"
                @set="setTrait"
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
import SheetMeritsSection from '../components/sheet/SheetMeritsSection.vue';
import SheetOtherSection from '../components/sheet/SheetOtherSection.vue';
import SheetPlaceSection from '../components/sheet/SheetPlaceSection.vue';
import SheetPlayBar from '../components/sheet/SheetPlayBar.vue';
import SheetTraitSection from '../components/sheet/SheetTraitSection.vue';
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
    merits,
    bloodPool,
    tempWillpower,
    experience,
    newDisciplineId,
    healthBoxes,
    healthDamage,
    flash,
    compact,
    sects,
    clans,
    havens,
    attributeGroups,
    abilityGroups,
    backgroundTraits,
    virtueTraits,
    otherTraits,
    disciplineRows,
    unusedDisciplines,
    healthLevel,
    meritTotals,
    traitValue,
    healthLabel,
    formatSigned,
    meritSigned,
    addMerit,
    removeMerit,
    missingPlaceOption,
    saveIdentity,
    saveBiography,
    savePlace,
    createPlaceEntity,
    setTrait,
    saveStatus,
    saveExperience,
    setHealthLevel,
    setHealthDamage,
    saveMerits,
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
