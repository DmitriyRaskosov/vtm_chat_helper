<template>
    <section class="not-found">
        <p class="not-found-rune" aria-hidden="true">F</p>
        <h1>Страница не найдена</h1>
        <p class="not-found-text">Такой страницы нет.</p>
        <p><RouterLink class="not-found-link" to="/">На главную</RouterLink></p>
    </section>
</template>

<script setup>
import { RouterLink } from 'vue-router';
</script>

<style scoped>
.not-found {
    margin: 3rem auto;
    max-width: 28rem;
    padding: 2.75rem 1.75rem 2.25rem;
    text-align: center;
    background:
        radial-gradient(ellipse at center, color-mix(in srgb, var(--gold) 12%, transparent), transparent 70%),
        var(--bg-panel);
    border: 1px solid var(--border);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--gold) 28%, transparent);
}

.not-found-rune {
    margin: 0 0 0.75rem;
    font-size: 3.25rem;
    line-height: 1;
    color: var(--gold);
}

.not-found h1 {
    margin: 0 0 0.75rem;
    font-family: var(--font-lore), 'Lora', serif;
    font-size: 2rem;
    font-weight: 400;
    letter-spacing: 0.04em;
    color: var(--gold);
}

.not-found-text {
    margin: 0 0 1.25rem;
    color: var(--text-muted);
}

.not-found-link {
    color: var(--gold);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-size: 0.85rem;
}
</style>
