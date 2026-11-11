<template>
    <div class="stats-row" :class="{ 'stats-row-plain': !label }">
        <span v-if="label" class="stats-label">{{ label }}</span>
        <div class="stats-track" :class="{ 'stats-track-pool': pool }">
            <button
                v-for="index in max"
                :key="index"
                type="button"
                class="stats-dot"
                :class="[dotKind(index), { 'is-locked': locked }]"
                :aria-disabled="locked"
                :aria-label="`${label || 'Точка'} ${index}`"
                @click.prevent="emit('pick', index)"
            />
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    label: { type: String, default: '' },
    max: { type: Number, required: true },
    pool: { type: Boolean, default: false },
    dots: { type: Array, default: () => [] },
    locked: { type: Boolean, default: false },
});

function dotKind(index) {
    const state = Number(props.dots[index - 1] ?? 0);

    if (state === 2) {
        return 'is-temporary';
    }
    if (state === 1) {
        return 'is-permanent';
    }

    return 'is-empty';
}

const emit = defineEmits(['pick']);
</script>

<style scoped>
.stats-row {
    display: grid;
    grid-template-columns: minmax(7rem, 1fr) auto;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.3rem;
}

.stats-row-plain {
    grid-template-columns: 1fr;
}

.stats-label {
    min-width: 0;
}

.stats-track {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.35rem;
    justify-self: end;
}

.stats-dot {
    width: 0.9rem;
    height: 0.9rem;
    min-width: 0.9rem;
    margin: 0;
    padding: 0;
    line-height: 0;
    box-sizing: border-box;
    border: 1px solid var(--text-muted, #8a8078);
    border-radius: 50%;
    background: transparent;
}

.stats-dot.is-locked {
    cursor: not-allowed;
}

.stats-track-pool .stats-dot {
    width: 1.15rem;
    height: 1.15rem;
}

button.stats-dot.is-permanent {
    background: var(--text, #1c140f);
    border-color: var(--text, #1c140f);
}

button.stats-dot.is-temporary {
    background: #9d2c2c;
    border-color: #9d2c2c;
}

button.stats-dot.is-empty {
    background: transparent;
}
</style>
