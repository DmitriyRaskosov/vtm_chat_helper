<template>
    <div
        class="combat-placeholder"
        :class="{ 'is-current': current, 'is-out': !active }"
        aria-label="Скрытый участник"
    >
        <span class="combat-placeholder-mark" aria-hidden="true">?</span>
    </div>
</template>

<script setup>
defineProps({
    current: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
});
</script>

<style scoped>
.combat-placeholder {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 4.5rem;
    height: 5.75rem;
    border-radius: 6px;
    background: #4a4a4a;
    color: #b5b5b5;
}

.combat-placeholder.is-current {
    box-shadow: 0 0 0 2px var(--gold);
}

.combat-placeholder.is-out {
    filter: grayscale(1);
    opacity: 0.55;
}

.combat-placeholder-mark {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}
</style>
