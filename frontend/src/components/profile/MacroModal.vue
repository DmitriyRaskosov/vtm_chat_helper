<template>
    <div v-if="open" class="diary-backdrop" @click.self="open = false">
        <form class="diary-modal card macro-form" @submit.prevent="submit">
            <header class="diary-modal-header">
                <h2>{{ macro ? 'Редактировать макрос' : 'Новый макрос' }}</h2>
                <button type="button" class="link" @click="open = false">Закрыть</button>
            </header>

            <p v-if="error" class="error">{{ error }}</p>

            <label>
                Название
                <input v-model="name" type="text" maxlength="255" required />
            </label>
            <label>
                Пул
                <input v-model="pool" type="number" min="0" max="100" />
                <span class="muted">Оставьте пустым, если используете статы.</span>
            </label>
            <label>
                Сложность
                <input v-model.number="difficulty" type="number" min="1" max="20" required />
            </label>
            <label>
                Тип куба
                <select v-model.number="diceType">
                    <option v-for="sides in diceTypes" :key="sides" :value="sides">d{{ sides }}</option>
                </select>
            </label>
            <label class="toggle">
                <input v-model="useWillpower" type="checkbox" />
                Трата Воли
            </label>
            <div class="macro-stats">
                <label>
                    Статы
                    <input
                        v-model="statQuery"
                        type="text"
                        placeholder="Сил"
                        @keydown.enter.prevent="addHighlightedStat"
                    />
                </label>
                <ul v-if="suggestions.length" class="macro-stat-suggestions">
                    <li v-for="row in suggestions" :key="row.key">
                        <button type="button" @click="addStat(row.key)">
                            {{ row.label }} ({{ row.key }})
                        </button>
                    </li>
                </ul>
                <ul v-if="selectedStats.length" class="macro-stat-picked">
                    <li v-for="key in selectedStats" :key="key">
                        {{ sheetStatLabel(key) }} ({{ key }})
                        <button type="button" class="link" @click="removeStat(key)">Убрать</button>
                    </li>
                </ul>
            </div>

            <button type="submit" :disabled="!canSubmit">
                {{ saving ? 'Сохранение…' : 'Сохранить' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { sheetStatLabel, sheetStatSuggestions } from '../../utils/sheetStatCatalog';

const diceTypes = [4, 6, 8, 10, 12, 20];

const props = defineProps({
    macro: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['save']);

const name = ref('');
const pool = ref('');
const difficulty = ref(6);
const diceType = ref(10);
const useWillpower = ref(false);
const selectedStats = ref([]);
const statQuery = ref('');

const suggestions = computed(() => sheetStatSuggestions(statQuery.value, selectedStats.value));

const poolIsValid = computed(() => {
    if (pool.value === '' || pool.value === null) {
        return true;
    }

    const value = Number(pool.value);

    return Number.isInteger(value) && value >= 0 && value <= 100;
});

const canSubmit = computed(() => (
    !props.saving
    && name.value.trim() !== ''
    && poolIsValid.value
    && Number.isInteger(difficulty.value)
    && difficulty.value >= 1
    && difficulty.value <= 20
    && diceTypes.includes(diceType.value)
));

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const source = props.macro;
    name.value = source?.name ?? '';
    pool.value = Number(source?.pool) > 0 ? String(source.pool) : '';
    difficulty.value = Number(source?.difficulty ?? 6);
    diceType.value = Number(source?.dice_type ?? 10);
    useWillpower.value = Boolean(source?.use_willpower);
    selectedStats.value = statKeys(source?.stats);
    statQuery.value = '';
});

function statKeys(stats) {
    const source = Array.isArray(stats) ? stats : [];
    const keys = [];

    for (const name of source) {
        for (const part of String(name).split(',')) {
            const key = part.trim();
            if (key !== '' && !keys.includes(key)) {
                keys.push(key);
            }
        }
    }

    return keys;
}

function addStat(key) {
    if (!selectedStats.value.includes(key)) {
        selectedStats.value = [...selectedStats.value, key];
    }
    statQuery.value = '';
}

function addHighlightedStat() {
    const first = suggestions.value[0];
    if (first) {
        addStat(first.key);
    }
}

function removeStat(key) {
    selectedStats.value = selectedStats.value.filter((item) => item !== key);
}

function submit() {
    if (!canSubmit.value) {
        return;
    }

    const payload = {
        name: name.value.trim(),
        difficulty: difficulty.value,
        dice_type: diceType.value,
        use_willpower: useWillpower.value,
        stats: [...selectedStats.value],
    };

    if (pool.value !== '' && pool.value !== null) {
        payload.pool = Number(pool.value);
    }

    emit('save', payload);
}
</script>

<style scoped>
.macro-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-color: var(--gold);
}

.macro-form button[type='submit'] {
    margin-top: 0.25rem;
}

.macro-stat-suggestions,
.macro-stat-picked {
    list-style: none;
    margin: 0.35rem 0 0;
    padding: 0;
}

.macro-stat-suggestions button,
.macro-stat-picked {
    text-align: left;
}
</style>
