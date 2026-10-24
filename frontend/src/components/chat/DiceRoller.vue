<template>
    <div
        v-if="open"
        class="diary-backdrop dice-roller-backdrop"
        :class="{ 'dice-backdrop-beside-drawer': characterDrawerOpen }"
    >
        <div class="diary-modal card dice-roller">
            <header class="diary-modal-header">
                <h2>Бросок кубиков</h2>
                <button type="button" class="link" @click="close">Закрыть</button>
            </header>

            <label for="dice-pool">Пул</label>
            <input id="dice-pool" v-model.number="pool" type="number" min="1" max="100" />

            <label for="dice-mode">Режим</label>
            <select id="dice-mode" v-model="rollMode">
                <option value="successes">Успехи (V20)</option>
                <option value="sum">Сумма кубов</option>
            </select>

            <label v-if="rollMode === 'successes'" for="dice-difficulty">Сложность</label>
            <input
                v-if="rollMode === 'successes'"
                id="dice-difficulty"
                v-model.number="difficulty"
                type="number"
                min="1"
                max="20"
            />

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

            <label for="dice-comment">Комментарий</label>
            <input
                id="dice-comment"
                v-model="comment"
                type="text"
                maxlength="200"
                placeholder="Уклонение, урон, проверка…"
            />

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
                    <template v-if="displayResult.mode === 'sum'">
                        Сумма: <strong>{{ displayResult.sum ?? displayResult.net }}</strong>
                    </template>
                    <template v-else>
                        Итог: <strong>{{ outcomeLabel(displayResult.outcome) }}</strong>
                        <span v-if="displayResult.outcome === 'success'"> (чистых: {{ displayResult.net }})</span>
                    </template>
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
    myCharacterId: { type: [Number, String], default: null },
    characterDrawerOpen: { type: Boolean, default: false },
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
const rollMode = ref('successes');
const comment = ref('');
const isHidden = ref(false);
const localResult = ref(null);

const isSumMode = computed(() => rollMode.value === 'sum');

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
    characterId.value = props.isStoryteller
        ? null
        : (props.myCharacterId ?? null);
    rollMode.value = 'successes';
    comment.value = '';
    isHidden.value = false;
    localResult.value = null;
});

watch(() => props.lastResult, (value) => {
    if (value?.result) {
        localResult.value = value.result;
    }
});

function diceFaceClass(face) {
    if (isSumMode.value) {
        return '';
    }
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
    const payload = {
        pool: pool.value,
        dice_type: diceType.value,
        mode: rollMode.value,
        character_id: characterId.value,
        is_hidden: props.isStoryteller ? isHidden.value : false,
        comment: comment.value.trim() || null,
    };

    if (rollMode.value === 'successes') {
        payload.difficulty = difficulty.value;
    }

    emit('roll', payload);
}

</script>
