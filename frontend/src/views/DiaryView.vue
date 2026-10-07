<template>
    <div>
        <AppNav current="characters" />

        <p v-if="error" class="error">{{ error }}</p>

        <section class="card">
            <header class="diary-top">
                <h1>{{ characterName }} — дневник</h1>
                <RouterLink :to="`/characters/${characterId}`" class="link">← К листу</RouterLink>
            </header>

            <p v-if="loading" class="muted">Загрузка…</p>
            <p v-else-if="!entries.length" class="muted">Записей пока нет.</p>

            <div v-else class="diary-list">
                <article
                    v-for="entry in entries"
                    :key="entry.id"
                    class="diary-entry"
                    :class="{ 'diary-entry-l1': entry.level === 1 }"
                >
                    <header class="diary-entry-header">
                        <span class="diary-level">L{{ entry.level }}</span>
                        <span v-if="entry.scene_title" class="muted">{{ entry.scene_title }}</span>
                        <span class="muted">{{ formatDate(entry.created_at) }}</span>
                        <span class="muted">{{ entry.word_count }} сл.</span>
                    </header>

                    <p class="diary-preview">{{ entry.preview }}<span v-if="entry.preview.length >= 300">…</span></p>

                    <div class="diary-actions">
                        <button type="button" class="link" @click="open(entry)">Открыть</button>
                        <button type="button" class="link" @click="startEdit(entry)">Редактировать</button>
                        <button type="button" class="link danger" @click="remove(entry)">Удалить</button>
                    </div>
                </article>
            </div>
        </section>

        <div v-if="activeEntry" class="diary-backdrop" @click.self="close">
            <div class="diary-modal card">
                <header class="diary-modal-header">
                    <h2>
                        L{{ activeEntry.level }} · {{ activeEntry.scene_title || 'без сцены' }}
                    </h2>
                    <button type="button" class="link" @click="close">Закрыть</button>
                </header>

                <div v-if="!editing" class="diary-body">
                    <p class="diary-full">{{ activeEntry.entry }}</p>
                    <button type="button" @click="startEdit(activeEntry)">Редактировать</button>
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
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../auth';
import AppNav from '../components/layout/AppNav.vue';

const route = useRoute();
const characterId = computed(() => Number(route.params.id));

const entries = ref([]);
const characterName = ref('Дневник');
const loading = ref(true);
const error = ref('');

const activeEntry = ref(null);
const editing = ref(false);
const editText = ref('');
const saving = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [sheetRes, diaryRes] = await Promise.all([
            api.get(`/characters/${characterId.value}`),
            api.get(`/characters/${characterId.value}/diary`),
        ]);
        characterName.value = sheetRes.data.character?.canonical_name ?? 'Дневник';
        entries.value = diaryRes.data.entries ?? [];
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось загрузить дневник.';
    } finally {
        loading.value = false;
    }
}

async function loadFull(entry) {
    const { data } = await api.get(`/diary-entries/${entry.id}`);
    return data.entry;
}

async function open(entry) {
    try {
        activeEntry.value = await loadFull(entry);
        editing.value = false;
        editText.value = '';
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось открыть запись.';
    }
}

async function startEdit(entry) {
    try {
        const full = activeEntry.value?.id === entry.id && activeEntry.value.entry
            ? activeEntry.value
            : await loadFull(entry);
        activeEntry.value = full;
        editing.value = true;
        editText.value = full.entry;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось открыть запись.';
    }
}

function cancelEdit() {
    editing.value = false;
    editText.value = '';
}

async function save() {
    if (!activeEntry.value) return;
    saving.value = true;
    error.value = '';
    try {
        const { data } = await api.patch(`/diary-entries/${activeEntry.value.id}`, {
            entry: editText.value.trim(),
        });
        const updated = data.entry;
        const idx = entries.value.findIndex((e) => e.id === updated.id);
        if (idx !== -1) entries.value[idx] = { ...entries.value[idx], ...updated };
        activeEntry.value = updated;
        editing.value = false;
        editText.value = '';
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось сохранить.';
    } finally {
        saving.value = false;
    }
}

async function remove(entry) {
    if (!confirm(`Удалить запись от ${formatDate(entry.created_at)}?`)) return;
    error.value = '';
    try {
        await api.delete(`/diary-entries/${entry.id}`);
        entries.value = entries.value.filter((e) => e.id !== entry.id);
        if (activeEntry.value?.id === entry.id) close();
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Не удалось удалить.';
    }
}

function close() {
    activeEntry.value = null;
    editing.value = false;
    editText.value = '';
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('ru-RU', {
        year: 'numeric', month: 'short', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

onMounted(load);
</script>