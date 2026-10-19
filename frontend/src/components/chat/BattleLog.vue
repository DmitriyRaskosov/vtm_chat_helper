<template>
    <section class="card battle-log">
        <h2>Лог боя</h2>
        <p v-if="!selectedSceneId" class="muted">Выберите сцену, чтобы видеть броски кубиков.</p>
        <p v-else-if="logError" class="error">{{ logError }}</p>
        <ul v-else class="battle-log-list">
            <li v-for="entry in logs" :key="entry.id" class="battle-log-item">
                <span class="battle-log-time">{{ entry.created_at }}</span>
                <span v-if="entry.visibility === 'storyteller_only'" class="battle-log-hidden" title="Скрытый бросок">скрыто</span>
                <span class="battle-log-message">{{ entry.message }}</span>
            </li>
            <li v-if="!logs.length" class="muted">Пока нет бросков.</li>
        </ul>
    </section>
</template>

<script setup>
defineProps({
    logs: { type: Array, required: true },
    logError: { type: String, default: '' },
    selectedSceneId: { type: Number, default: null },
});
</script>
