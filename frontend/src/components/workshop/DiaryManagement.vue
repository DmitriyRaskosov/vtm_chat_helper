<template>
    <div class="workshop-diary">
        <aside class="workshop-diary-list">
            <p v-if="!npcs.length" class="muted">НПС в хронике пока нет.</p>
            <button
                v-for="npc in npcs"
                :key="npc.id"
                type="button"
                class="workshop-diary-npc"
                :class="{ active: Number(npc.id) === Number(selectedId) }"
                @click="selectedId = npc.id"
            >
                {{ npc.canonical_name }}
            </button>
        </aside>
        <section class="workshop-diary-entries">
            <p v-if="selectedId == null" class="muted">Выберите NPC слева.</p>
            <template v-else>
                <h2 class="workshop-diary-title">{{ characterName }}</h2>
                <p v-if="loading" class="muted">Загрузка…</p>
                <p v-else-if="error" class="error">{{ error }}</p>
                <p v-else-if="!entries.length" class="muted">Записей пока нет.</p>
                <article v-for="entry in entries" :key="entry.id" class="card workshop-diary-entry">
                    <header class="diary-entry-header">
                        <span class="diary-level">L{{ entry.level }}</span>
                        <span class="muted">{{ formatDate(entry.created_at) }}</span>
                        <span v-if="entry.scene_title" class="muted">{{ entry.scene_title }}</span>
                    </header>
                    <p class="diary-preview">{{ entry.preview }}</p>
                    <div class="sheet-actions">
                        <button
                            type="button"
                            class="secondary"
                            :disabled="regeneratingId !== null"
                            @click="regenerate(entry)"
                        >
                            {{ regeneratingId === entry.id ? 'Пересоздаём…' : 'Пересоздать' }}
                        </button>
                        <button type="button" class="secondary" @click="remove(entry)">Удалить</button>
                    </div>
                </article>
            </template>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useDiary } from '../../composables/useDiary';

defineProps({
    npcs: { type: Array, default: () => [] },
});

const selectedId = ref(null);

const {
    entries,
    characterName,
    loading,
    error,
    regeneratingId,
    regenerate,
    remove,
    formatDate,
} = useDiary(selectedId);
</script>
