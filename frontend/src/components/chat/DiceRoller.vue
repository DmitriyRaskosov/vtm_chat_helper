<template>
    <div class="chat-panel-popover chat-floating-panel card dice-roller">
            <header class="chat-panel-header">
                <h2>Бросок кубиков</h2>
                <button type="button" class="chat-panel-close" aria-label="Закрыть" @click="close">×</button>
            </header>

            <div class="dice-top-row" :class="{ 'dice-top-row--macro': hasActiveMacro }">
                <div class="dice-top-cell">
                    <h3 class="dice-top-heading">Выбор макроса</h3>
                    <label class="sr-only" for="dice-macro">Макрос</label>
                    <select id="dice-macro" v-model="selectedMacroId" @change="onMacroChange">
                        <option value="">Без макроса</option>
                        <option v-for="macro in macros" :key="macro.id" :value="String(macro.id)">
                            {{ macro.name }}
                        </option>
                    </select>
                </div>
                <div class="dice-top-cell">
                    <h3 class="dice-top-heading">Выбор персонажа</h3>
                    <label class="sr-only" for="dice-character">Персонаж</label>
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
                </div>
                <div v-if="!hasActiveMacro" class="dice-top-cell">
                    <h3 class="dice-top-heading">Режим броска</h3>
                    <label class="sr-only" for="dice-mode">Режим броска</label>
                    <select id="dice-mode" v-model="rollMode">
                        <option value="successes">Успехи (V20)</option>
                        <option value="sum">Сумма кубов</option>
                    </select>
                </div>
            </div>

            <p v-if="macrosError" class="error">{{ macrosError }}</p>

            <div
                v-if="showPoolOrExtraInputs"
                class="dice-inputs-grid"
                :class="diceInputsGridClass"
            >
                <div v-if="!hasActiveMacro" class="dice-input-cell">
                    <label for="dice-pool">Пул</label>
                    <input
                        id="dice-pool"
                        v-model.number="pool"
                        type="number"
                        min="1"
                        max="100"
                        :class="{ 'dice-field-from-macro': appliedMacroName }"
                    />
                </div>
                <div v-if="rollMode === 'successes'" class="dice-input-cell">
                    <label for="dice-difficulty">Сложность</label>
                    <input
                        id="dice-difficulty"
                        v-model.number="difficulty"
                        type="number"
                        min="1"
                        max="20"
                        :class="{ 'dice-field-from-macro': appliedMacroName }"
                    />
                </div>
                <div class="dice-input-cell">
                    <label for="dice-type">Тип кубика</label>
                    <select
                        id="dice-type"
                        v-model.number="diceType"
                        :class="{ 'dice-field-from-macro': appliedMacroName }"
                    >
                        <option :value="4">d4</option>
                        <option :value="6">d6</option>
                        <option :value="8">d8</option>
                        <option :value="10">d10</option>
                        <option :value="12">d12</option>
                        <option :value="20">d20</option>
                    </select>
                </div>
            </div>

            <label for="dice-comment">Комментарий</label>
            <input
                id="dice-comment"
                v-model="comment"
                type="text"
                maxlength="200"
                placeholder="Уклонение, урон, проверка…"
            />

            <label
                v-if="rollMode === 'successes'"
                class="toggle dice-willpower-toggle"
                :class="{ 'dice-field-from-macro': appliedMacroName }"
            >
                <input v-model="useWillpower" type="checkbox" />
                Использовать Силу Воли (1 успех)
            </label>

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
                        <span
                            v-if="displayResult.willpower_spent"
                            class="dice-wp-badge"
                            title="Сила Воли"
                        >WP</span>
                        <span v-if="displayResult.outcome === 'success'"> (чистых: {{ displayResult.net }})</span>
                    </template>
                </p>
            </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { calculatePool, useDiceMacros } from '../../composables/useDiceMacros';
import { useCharacterStats } from '../../composables/useCharacterStats';

const props = defineProps({
    isStoryteller: { type: Boolean, required: true },
    myCharacterId: { type: [Number, String], default: null },
    rollCharacterId: { type: [Number, String], default: null },
    characterOptions: { type: Array, required: true },
    rolling: { type: Boolean, default: false },
    rollError: { type: String, default: '' },
    lastResult: { type: Object, default: null },
    canRoll: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'roll', 'clear-result']);

const {
    macros,
    error: macrosError,
    load: loadMacros,
} = useDiceMacros();

const selectedMacroId = ref('');
const appliedMacroName = ref('');
const characterStats = ref({});
const pool = ref(5);
const difficulty = ref(6);
const diceType = ref(10);
const characterId = ref(null);
const rollMode = ref('successes');
const comment = ref('');
const isHidden = ref(false);
const useWillpower = ref(false);
const localResult = ref(null);

const isSumMode = computed(() => rollMode.value === 'sum');

const hasActiveMacro = computed(() => {
    if (selectedMacroId.value === '' || selectedMacroId.value === null || selectedMacroId.value === undefined) {
        return false;
    }

    return selectedMacro() !== null;
});

/** С макросом блок настроек показывает сложность/тип (без пула); в режиме суммы — только тип. */
const showPoolOrExtraInputs = computed(() => {
    if (!hasActiveMacro.value) {
        return true;
    }

    return rollMode.value === 'successes' || rollMode.value === 'sum';
});

const diceInputsGridClass = computed(() => {
    if (!hasActiveMacro.value) {
        return rollMode.value === 'sum' ? 'dice-inputs-grid--sum' : '';
    }
    if (rollMode.value === 'sum') {
        return 'dice-inputs-grid--macro-sum';
    }

    return 'dice-inputs-grid--macro';
});

const displayResult = computed(() => localResult.value ?? props.lastResult?.result ?? null);

const { load: loadCharacterStats } = useCharacterStats(() => characterId.value);
const statsLoading = ref(false);
let statsEpoch = 0;

watch(characterId, (id) => {
    loadStatsForCharacter(id);
}, { immediate: true });

watch(characterStats, () => {
    applyCalculatedPool();
});

function macroStatNames(stats) {
    if (!Array.isArray(stats)) {
        return [];
    }

    return stats.filter((name) => typeof name === 'string' && name !== '');
}

function selectedMacro() {
    const id = Number(selectedMacroId.value);
    if (!Number.isInteger(id) || id <= 0) {
        return null;
    }

    return macros.value.find((row) => Number(row.id) === id) ?? null;
}

function applyCalculatedPool() {
    if (statsLoading.value) {
        return;
    }

    const macro = selectedMacro();
    const names = macroStatNames(macro?.stats);
    if (names.length === 0) {
        return;
    }

    pool.value = calculatePool(names, characterStats.value);
}

async function loadStatsForCharacter(id) {
    const current = ++statsEpoch;
    const numericId = Number(id);

    if (!Number.isFinite(numericId) || numericId <= 0) {
        statsLoading.value = false;
        characterStats.value = {};

        return;
    }

    statsLoading.value = true;

    try {
        const loaded = await loadCharacterStats(numericId);
        if (current !== statsEpoch) {
            return;
        }
        characterStats.value = loaded.stats ?? {};
    } catch {
        if (current !== statsEpoch) {
            return;
        }
        characterStats.value = {};
    } finally {
        if (current === statsEpoch) {
            statsLoading.value = false;
            applyCalculatedPool();
        }
    }
}

function applyMacroById(raw) {
    if (raw === null || raw === undefined || raw === '') {
        appliedMacroName.value = '';

        return;
    }

    const id = Number(raw);
    if (!Number.isInteger(id) || id <= 0) {
        return;
    }

    const macro = macros.value.find((row) => Number(row.id) === id);
    if (!macro) {
        return;
    }

    const names = macroStatNames(macro.stats);
    if (names.length > 0 && !statsLoading.value) {
        pool.value = calculatePool(names, characterStats.value);
    } else if (names.length === 0 && (!Number.isFinite(Number(pool.value)) || Number(pool.value) < 1)) {
        pool.value = 1;
    }
    difficulty.value = Number(macro.difficulty);
    diceType.value = Number(macro.dice_type);
    useWillpower.value = Boolean(macro.use_willpower);
    appliedMacroName.value = String(macro.name ?? '');
}

function onMacroChange(event) {
    applyMacroById(event.target.value);
}

watch(selectedMacroId, (id) => {
    applyMacroById(id);
});

function presetCharacterId() {
    const preset = props.rollCharacterId ?? (props.isStoryteller ? null : props.myCharacterId);
    const numericId = Number(preset);

    return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
}

function resetForm() {
    selectedMacroId.value = '';
    appliedMacroName.value = '';
    pool.value = 5;
    difficulty.value = 6;
    diceType.value = 10;
    characterId.value = presetCharacterId();
    rollMode.value = 'successes';
    comment.value = '';
    isHidden.value = false;
    useWillpower.value = false;
    localResult.value = null;
}

onMounted(() => {
    resetForm();
    loadMacros();
});

onUnmounted(() => {
    localResult.value = null;
    emit('clear-result');
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
    emit('close');
}

async function onRoll() {
    const payload = {
        pool: pool.value,
        dice_type: diceType.value,
        mode: rollMode.value,
        character_id: characterId.value,
        is_hidden: props.isStoryteller ? isHidden.value : false,
        use_willpower: rollMode.value === 'successes' ? useWillpower.value : false,
        comment: comment.value.trim() || null,
    };

    if (rollMode.value === 'successes') {
        payload.difficulty = difficulty.value;
    }

    emit('roll', payload);
}

</script>
