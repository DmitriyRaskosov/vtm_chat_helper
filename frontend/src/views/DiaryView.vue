<template>
    <div class="with-sidebar">
        <AppNav current="characters" />
        <div class="page-main">

        <p v-if="error" class="error">{{ error }}</p>

        <section class="card">
            <header class="diary-top">
                <h1>{{ characterName }} — дневник</h1>
                <RouterLink :to="sheetLink" class="link">← К листу</RouterLink>
            </header>

            <p v-if="loading" class="muted">Загрузка…</p>
            <p v-else-if="!entries.length" class="muted">Записей пока нет.</p>

            <div v-else class="diary-list">
                <article
                    v-for="entry in entries"
                    :key="entry.id"
                    class="diary-entry"
                    :class="{
                        'diary-entry-l1': entry.level === 1,
                        'diary-entry-stale': entry.is_stale,
                    }"
                    :style="{ borderLeftColor: emotionColor(entry.emotion) }"
                >
                    <header class="diary-entry-header">
                        <span class="diary-level">L{{ entry.level }}</span>
                        <span v-if="entry.is_stale" class="stale-badge">ИСТОЧНИК ИЗМЕНЁН</span>
                        <span v-if="entry.emotion" class="diary-emotion">{{ entry.emotion }}</span>
                        <span v-if="entry.importance !== null" class="muted">importance: {{ entry.importance.toFixed(2) }}</span>
                        <span v-if="entry.scene_title" class="muted">{{ entry.scene_title }}</span>
                        <span class="muted">{{ formatDate(entry.created_at) }}</span>
                        <span class="muted">{{ entry.word_count }} сл.</span>
                    </header>

                    <p class="diary-preview">{{ entry.preview }}<span v-if="entry.preview.length >= 300">…</span></p>

                    <div class="diary-actions">
                        <button type="button" class="link" @click="open(entry)">Открыть</button>
                        <button type="button" class="link" @click="startEdit(entry)">Редактировать</button>
                        <button
                            type="button"
                            class="link"
                            :disabled="regeneratingId !== null"
                            @click="regenerate(entry)"
                        >
                            {{ regeneratingId === entry.id ? 'Пересоздаём…' : 'Пересоздать' }}
                        </button>
                        <button type="button" class="link danger" @click="remove(entry)">Удалить</button>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="activeEntry" class="diary-backdrop" @click.self="close">
            <div class="diary-modal card">
                <header class="diary-modal-header">
                    <h2>
                        L{{ activeEntry.level }}
                        <template v-if="activeEntry.emotion"> · <span class="diary-emotion">{{ activeEntry.emotion }}</span></template>
                        <template v-if="activeEntry.importance !== null"> · importance: {{ activeEntry.importance.toFixed(2) }}</template>
                        · {{ activeEntry.scene_title || 'без сцены' }}
                        <span v-if="activeEntry.is_stale" class="stale-badge">ИСТОЧНИК ИЗМЕНЁН</span>
                    </h2>
                    <button type="button" class="link" @click="close">Закрыть</button>
                </header>

                <div v-if="!editing" class="diary-body">
                    <p class="diary-full">{{ activeEntry.entry }}</p>
                    <div class="sheet-actions">
                        <button type="button" @click="startEdit(activeEntry)">Редактировать</button>
                    </div>
                </div>

                <div v-else class="diary-body">
                    <textarea v-model="editText" rows="15" maxlength="20000" />
                    <div class="sheet-actions">
                        <button type="button" :disabled="saving || !editText.trim()" @click="save">
                            {{ saving ? 'Сохраняем…' : 'Сохранить' }}
                        </button>
                        <button type="button" class="secondary" :disabled="saving" @click="cancelEdit">Отмена</button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppNav from '../components/layout/AppNav.vue';
import { useDiary } from '../composables/useDiary';

const route = useRoute();
const characterId = computed(() => Number(route.params.id));
const sheetLink = computed(() => `/c/${route.params.chronicleId}/characters/${route.params.id}`);

const {
    entries,
    characterName,
    loading,
    error,
    activeEntry,
    editing,
    editText,
    saving,
    regeneratingId,
    open,
    startEdit,
    cancelEdit,
    save,
    remove,
    regenerate,
    close,
    formatDate,
    emotionColor,
} = useDiary(characterId);
</script>

<style scoped>
.diary-entry {
    border-left: 3px solid transparent;
    padding-left: 12px;
}

.diary-emotion {
    font-weight: 600;
    font-variant: small-caps;
    letter-spacing: 0.03em;
}
</style>
