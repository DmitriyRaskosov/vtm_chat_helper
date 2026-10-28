<template>
    <section class="card sheet-state-section">
        <h2>Состояние</h2>

        <p v-if="loading" class="muted">Загрузка…</p>
        <p v-if="error" class="error">{{ error }}</p>

        <div class="sheet-state-grid">
            <label>
                Blood Pool
                <input
                    v-model.number="bloodPool"
                    type="number"
                    min="0"
                    max="50"
                    :disabled="!canEdit"
                />
            </label>
            <label>
                Willpower (сейчас)
                <input
                    v-model.number="temporaryWillpower"
                    type="number"
                    min="0"
                    max="10"
                    :disabled="!canEdit"
                />
            </label>
        </div>

        <div class="sheet-state-health">
            <label>
                Health
                <select v-model.number="healthLevel" :disabled="!canEdit">
                    <option
                        v-for="option in healthLevelOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <fieldset class="sheet-state-damage" :disabled="!canEdit">
                <legend class="muted">Тип урона</legend>
                <label class="sheet-state-damage-option">
                    <input v-model="healthDamage" type="radio" value="bashing" />
                    Bashing
                </label>
                <label class="sheet-state-damage-option">
                    <input v-model="healthDamage" type="radio" value="lethal" />
                    Lethal
                </label>
                <label class="sheet-state-damage-option">
                    <input v-model="healthDamage" type="radio" value="aggravated" />
                    Aggravated
                </label>
            </fieldset>

            <p class="sheet-state-penalty">
                Штраф к кубам: {{ healthPenalty }}
            </p>
        </div>

        <button
            v-if="canEdit"
            type="button"
            :disabled="saving || loading"
            @click="onSave"
        >
            {{ saving ? 'Сохраняем…' : 'Сохранить' }}
        </button>
    </section>
</template>

<script setup>
import { toRef } from 'vue';
import { useCharacterState } from '../../composables/useCharacterState';

const healthLevelOptions = [
    { value: 0, label: '0 - Healthy' },
    { value: 1, label: '1 - Bruised' },
    { value: 2, label: '2 - Hurt' },
    { value: 3, label: '3 - Injured' },
    { value: 4, label: '4 - Wounded' },
    { value: 5, label: '5 - Mauled' },
    { value: 6, label: '6 - Crippled' },
    { value: 7, label: '7 - Incapacitated' },
];

const props = defineProps({
    characterId: {
        type: [Number, String],
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
});

const characterId = toRef(props, 'characterId');

const {
    bloodPool,
    temporaryWillpower,
    healthLevel,
    healthDamage,
    healthPenalty,
    loading,
    saving,
    error,
    save,
} = useCharacterState(characterId);

async function onSave() {
    try {
        await save(characterId.value);
    } catch {
        // error ref уже заполнен
    }
}
</script>
