<template>
    <div class="in-game-clock">
        <button
            v-if="isStoryteller"
            type="button"
            class="in-game-clock-face"
            :aria-expanded="open"
            @click="open = !open"
        >
            {{ label }}
        </button>
        <span v-else class="in-game-clock-face">{{ label }}</span>

        <div v-if="isStoryteller && open" class="in-game-clock-panel">
            <label class="in-game-clock-field">
                Дата и время
                <input
                    type="datetime-local"
                    :value="inputValue"
                    :disabled="saving"
                    @change="onPick"
                />
            </label>
            <div class="in-game-clock-steps">
                <button
                    v-for="step in steps"
                    :key="step.minutes"
                    type="button"
                    class="btn-sm"
                    :disabled="saving || !currentInGameDate"
                    @click="shift(step.minutes)"
                >
                    {{ step.label }}
                </button>
            </div>
            <p v-if="error" class="error">{{ error }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useInGameTime } from '../../composables/useInGameTime';

const props = defineProps({
    chronicleId: { type: [Number, String], required: true },
    isStoryteller: { type: Boolean, required: true },
});

const steps = [
    { label: '+1м', minutes: 1 },
    { label: '+5м', minutes: 5 },
    { label: '+10м', minutes: 10 },
    { label: '+1ч', minutes: 60 },
    { label: '+1д', minutes: 60 * 24 },
];

const months = ['янв.', 'февр.', 'мар.', 'апр.', 'мая', 'июн.', 'июл.', 'авг.', 'сент.', 'окт.', 'нояб.', 'дек.'];

const open = ref(false);
const saving = ref(false);
const error = ref('');
const { currentInGameDate, updateTime, errorMessage } = useInGameTime(() => props.chronicleId);

const label = computed(() => formatInGame(currentInGameDate.value));
const inputValue = computed(() => toDatetimeLocal(currentInGameDate.value));

function pad(value) {
    return String(value).padStart(2, '0');
}

function formatInGame(iso) {
    if (!iso) {
        return 'Время не задано';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return 'Время не задано';
    }

    return `${date.getUTCDate()} ${months[date.getUTCMonth()]} ${date.getUTCFullYear()}, ${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

function toDatetimeLocal(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

async function commit(datetime) {
    saving.value = true;
    error.value = '';

    try {
        await updateTime(datetime);
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        saving.value = false;
    }
}

function onPick(event) {
    const value = event.target.value;
    if (!value) {
        return;
    }

    commit(`${value}:00Z`);
}

function shift(minutes) {
    if (!currentInGameDate.value) {
        return;
    }

    const next = new Date(new Date(currentInGameDate.value).getTime() + minutes * 60 * 1000);
    commit(next.toISOString());
}
</script>

<style scoped>
.in-game-clock {
    position: relative;
    margin-left: auto;
    flex-shrink: 0;
    align-self: flex-start;
}

.in-game-clock-face {
    font-family: var(--font-lore);
    color: var(--gold);
    background: transparent;
    border: none;
    padding: 0;
    cursor: default;
    font-size: 0.95rem;
    white-space: nowrap;
}

button.in-game-clock-face {
    cursor: pointer;
}

button.in-game-clock-face:hover {
    color: var(--text);
}

.in-game-clock-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 0.4rem);
    z-index: 30;
    width: 16rem;
    padding: 0.75rem;
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.in-game-clock-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.in-game-clock-field input {
    width: 100%;
}

.in-game-clock-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
</style>
