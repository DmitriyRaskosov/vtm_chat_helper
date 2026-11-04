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
                <input v-model.number="pool" type="number" min="1" max="100" required />
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
            <label>
                Статы
                <input
                    v-model="statsText"
                    type="text"
                    maxlength="500"
                    placeholder="dexterity, athletics"
                />
            </label>

            <button type="submit" :disabled="!canSubmit">
                {{ saving ? 'Сохранение…' : 'Сохранить' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const diceTypes = [4, 6, 8, 10, 12, 20];

const props = defineProps({
    macro: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['save']);

const name = ref('');
const pool = ref(1);
const difficulty = ref(6);
const diceType = ref(10);
const useWillpower = ref(false);
const statsText = ref('');

const canSubmit = computed(() => (
    !props.saving
    && name.value.trim() !== ''
    && Number.isInteger(pool.value)
    && pool.value >= 1
    && pool.value <= 100
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
    pool.value = Number(source?.pool ?? 1);
    difficulty.value = Number(source?.difficulty ?? 6);
    diceType.value = Number(source?.dice_type ?? 10);
    useWillpower.value = Boolean(source?.use_willpower);
    statsText.value = Array.isArray(source?.stats) ? source.stats.join(', ') : '';
});

function statsList(text) {
    return String(text)
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');
}

function submit() {
    if (!canSubmit.value) {
        return;
    }

    emit('save', {
        name: name.value.trim(),
        pool: pool.value,
        difficulty: difficulty.value,
        dice_type: diceType.value,
        use_willpower: useWillpower.value,
        stats: statsList(statsText.value),
    });
}
</script>

<style scoped>
.macro-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-color: var(--gold);
}

.macro-form button {
    margin-top: 0.25rem;
}
</style>
