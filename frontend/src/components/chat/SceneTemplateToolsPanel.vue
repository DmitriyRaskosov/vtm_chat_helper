<template>
    <div class="scene-template-tools" :class="`scene-template-tools--focus-${focus}`">
        <p class="muted scene-template-hint">{{ hint }}</p>
        <div class="sheet-actions">
            <button type="button" class="secondary" @click="emit('open-library')">
                Библиотека локаций
            </button>
            <button
                type="button"
                class="secondary"
                :disabled="locationSaving"
                @click="emit('save-new')"
            >
                Сохранить сцену как новый шаблон
            </button>
        </div>
        <div class="scene-template-apply-row">
            <label class="scene-quick-location">
                Применить шаблон
                <select
                    v-model.number="applyTemplateId"
                    :disabled="busy || !hasLocations"
                >
                    <option :value="null">— выберите шаблон —</option>
                    <option
                        v-for="location in locations"
                        :key="`apply-${focus}-${location.id}`"
                        :value="location.id"
                    >
                        {{ location.name }}
                    </option>
                </select>
            </label>
            <div class="scene-template-apply-modes">
                <button
                    type="button"
                    class="secondary"
                    :disabled="!hasApplySelected || busy"
                    @click="emit('apply-full', applyTemplateId)"
                >
                    Всё (описание + детали + фото)
                </button>
                <button
                    type="button"
                    class="secondary"
                    :class="{ 'scene-template-emphasis': focus === 'situation' }"
                    :disabled="!hasApplySelected || busy"
                    @click="emit('apply-situation', applyTemplateId)"
                >
                    Только описание
                </button>
                <button
                    type="button"
                    class="secondary"
                    :class="{ 'scene-template-emphasis': focus === 'environment' }"
                    :disabled="!hasApplySelected || busy"
                    @click="emit('apply-environment', applyTemplateId)"
                >
                    Только детали локации
                </button>
            </div>
        </div>
        <div class="scene-template-update-row">
            <label class="scene-quick-location">
                Записать в шаблон
                <select
                    v-model.number="updateTemplateId"
                    :disabled="busy || !hasLocations"
                >
                    <option :value="null">— выберите шаблон —</option>
                    <option
                        v-for="location in locations"
                        :key="`upd-${focus}-${location.id}`"
                        :value="location.id"
                    >
                        {{ location.name }}
                    </option>
                </select>
            </label>
            <div class="scene-template-apply-modes">
                <button
                    type="button"
                    class="secondary"
                    :disabled="!hasUpdateSelected || busy"
                    @click="emit('update-both', updateTemplateId)"
                >
                    Описание и детали
                </button>
                <button
                    type="button"
                    class="secondary"
                    :class="{ 'scene-template-emphasis': focus === 'situation' }"
                    :disabled="!hasUpdateSelected || busy"
                    @click="emit('update-situation', updateTemplateId)"
                >
                    Только описание
                </button>
                <button
                    type="button"
                    class="secondary"
                    :class="{ 'scene-template-emphasis': focus === 'environment' }"
                    :disabled="!hasUpdateSelected || busy"
                    @click="emit('update-environment', updateTemplateId)"
                >
                    Только детали локации
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    locations: { type: Array, required: true },
    focus: {
        type: String,
        required: true,
        validator: (value) => value === 'situation' || value === 'environment',
    },
    hint: { type: String, required: true },
    locationSaving: { type: Boolean, default: false },
    sceneSituationSaving: { type: Boolean, default: false },
});

const applyTemplateId = defineModel('applyTemplateId', { type: Number, default: null });
const updateTemplateId = defineModel('updateTemplateId', { type: Number, default: null });

const emit = defineEmits([
    'open-library',
    'save-new',
    'apply-full',
    'apply-situation',
    'apply-environment',
    'update-both',
    'update-situation',
    'update-environment',
]);

const busy = computed(() => props.locationSaving || props.sceneSituationSaving);
const hasLocations = computed(() => props.locations.length > 0);

function isSelectedId(value) {
    const id = Number(value);
    return Number.isFinite(id) && id > 0;
}

const hasApplySelected = computed(() => isSelectedId(applyTemplateId.value));
const hasUpdateSelected = computed(() => isSelectedId(updateTemplateId.value));
</script>
