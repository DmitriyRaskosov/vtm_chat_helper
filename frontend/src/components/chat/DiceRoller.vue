<template>
    <div v-if="open" class="diary-backdrop" @click.self="close">
        <div class="diary-modal card dice-roller">
            <header class="diary-modal-header">
                <h2>Бросок кубиков</h2>
                <button type="button" class="link" @click="close">Закрыть</button>
            </header>

            <label for="dice-pool">Пул</label>
            <input id="dice-pool" v-model.number="pool" type="number" min="1" max="100" />

            <label for="dice-difficulty">Сложность</label>
            <input id="dice-difficulty" v-model.number="difficulty" type="number" min="1" max="20" />

            <label for="dice-type">Тип кубика</label>
            <select id="dice-type" v-model.number="diceType">
                <option :value="4">d4</option>
                <option :value="6">d6</option>
                <option :value="8">d8</option>
                <option :value="10">d10</option>
                <option :value="12">d12</option>
                <option :value="20">d20</option>
            </select>

            <label for="dice-character">Персонаж</label>
            <select id="dice-character" v-model="characterId">
                <option :value="null">Без привязки</option>
                <option
                    v-for="participant in characterOptions"
                    :key="participant.character_id"
                    :value="participant.character_id"
                >
                    {{ participant.character_name }}
                </option>
            </select>

            <label v-if="isStoryteller" class="toggle dice-hidden-toggle">
                <input v-model="isHidden" type="checkbox" />
                Скрытый бросок
            </label>

            <button type="button" :disabled="rolling || !canRoll" @click="onRoll">
                {{ rolling ? 'Бросаем…' : 'Бросить' }}
            </button>

            <p v-if="rollError" class="error">{{ rollError }}</p>

            <div v-if="displayResult" class="dice-result">
                <div class="dice-faces">
                    <span
                        v-for="(face, index) in displayResult.dice"
                        :key="index"
                        class="dice-face"
                        :class="diceFaceClass(face)"
                    >{{ face }}</span>
                </div>
                <p class="dice-outcome">
                    Итог: <strong>{{ outcomeLabel(displayResult.outcome) }}</strong>
                    <span v-if="displayResult.outcome === 'success'"> (чистых: {{ displayResult.net }})</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    isStoryteller: { type: Boolean, required: true },
    characterOptions: { type: Array, required: true },
    rolling: { type: Boolean, default: false },
    rollError: { type: String, default: '' },
    lastResult: { type: Object, default: null },
    canRoll: { type: Boolean, default: true },
});

const emit = defineEmits(['roll', 'clear-result']);

const pool = ref(5);
const difficulty = ref(6);
const diceType = ref(10);
const characterId = ref(null);
const isHidden = ref(false);
const localResult = ref(null);

const displayResult = computed(() => localResult.value ?? props.lastResult?.result ?? null);

watch(open, (visible) => {
    if (!visible) {
        localResult.value = null;
        emit('clear-result');
        return;
    }

    pool.value = 5;
    difficulty.value = 6;
    diceType.value = 10;
    characterId.value = null;
    isHidden.value = false;
    localResult.value = null;
});

watch(() => props.lastResult, (value) => {
    if (value?.result) {
        localResult.value = value.result;
    }
});

function diceFaceClass(face) {
    if (face === 1) {
        return 'dice-one';
    }
    if (face >= difficulty.value) {
        return 'dice-success';
    }
    return '';
}

function outcomeLabel(outcome) {
    if (outcome === 'botch') {
        return 'BOTCH';
    }
    if (outcome === 'failure') {
        return 'FAILURE';
    }
    return 'SUCCESS';
}

function close() {
    open.value = false;
}

async function onRoll() {
    emit('roll', {
        pool: pool.value,
        difficulty: difficulty.value,
        dice_type: diceType.value,
        character_id: characterId.value,
        is_hidden: props.isStoryteller ? isHidden.value : false,
    });
}

</script>
