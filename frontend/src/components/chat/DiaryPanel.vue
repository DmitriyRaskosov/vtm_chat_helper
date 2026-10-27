<template>
    <div class="diary-panel">
        <p v-if="loading" class="muted">Загрузка…</p>
        <p v-else-if="error" class="error">{{ error }}</p>
        <template v-else-if="activeEntry">
            <button type="button" class="link diary-panel-back" @click="close">← К списку</button>
            <h3 class="diary-panel-heading">
                L{{ activeEntry.level }}
                <template v-if="activeEntry.emotion"> · {{ activeEntry.emotion }}</template>
            </h3>
            <p class="muted diary-panel-meta">
                {{ activeEntry.scene_title || 'без сцены' }}
                · {{ formatDate(activeEntry.created_at) }}
            </p>
            <p class="diary-full">{{ activeEntry.entry }}</p>
        </template>
        <template v-else>
            <p class="diary-panel-name">{{ characterName }}</p>
            <p v-if="!entries.length" class="muted">Записей пока нет.</p>
            <article
                v-for="entry in entries"
                :key="entry.id"
                class="diary-entry diary-panel-entry"
                :style="{ borderLeftColor: emotionColor(entry.emotion) }"
            >
                <header class="diary-entry-header">
                    <span class="diary-level">L{{ entry.level }}</span>
                    <span v-if="entry.emotion" class="diary-emotion">{{ entry.emotion }}</span>
                    <span class="muted">{{ formatDate(entry.created_at) }}</span>
                </header>
                <p class="diary-preview">{{ entry.preview }}</p>
                <button type="button" class="link" @click="open(entry)">Читать</button>
            </article>
        </template>
    </div>
</template>

<script setup>
import { toRef, watch } from 'vue';
import { useDiary } from '../../composables/useDiary';

const props = defineProps({
    characterId: { type: [Number, String], required: true },
});

const characterId = toRef(props, 'characterId');

const {
    entries,
    characterName,
    loading,
    error,
    activeEntry,
    open,
    close,
    formatDate,
    emotionColor,
} = useDiary(characterId);

watch(characterId, () => {
    close();
});
</script>
