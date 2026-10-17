import { ref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

const EMOTION_COLORS = {
    neutral: '#888888',
    joy: '#eab308',
    sadness: '#3b82f6',
    anger: '#dc2626',
    fear: '#7c3aed',
    trust: '#16a34a',
    contempt: '#78350f',
    resolve: '#1e40af',
    hunger: '#9f1239',
    satisfaction: '#ea580c',
};

export function formatDiaryDate(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('ru-RU', {
        year: 'numeric', month: 'short', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

export function diaryEmotionColor(emotion) {
    if (!emotion) {
        return 'transparent';
    }

    return EMOTION_COLORS[emotion] ?? '#888888';
}

export function useDiary(characterId) {
    const entries = ref([]);
    const characterName = ref('Дневник');
    const loading = ref(true);
    const error = ref('');
    const activeEntry = ref(null);
    const editing = ref(false);
    const editText = ref('');
    const saving = ref(false);
    const regeneratingId = ref(null);

    async function loadFull(entry) {
        const { data } = await api.get(`/diary-entries/${entry.id}`);
        return data.entry;
    }

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
            error.value = apiErrorMessage(e, 'Не удалось загрузить дневник.');
        } finally {
            loading.value = false;
        }
    }

    async function open(entry) {
        try {
            activeEntry.value = await loadFull(entry);
            editing.value = false;
            editText.value = '';
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось открыть запись.');
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
            error.value = apiErrorMessage(e, 'Не удалось открыть запись.');
        }
    }

    function cancelEdit() {
        editing.value = false;
        editText.value = '';
    }

    async function save() {
        if (!activeEntry.value) {
            return;
        }
        saving.value = true;
        error.value = '';
        try {
            const { data } = await api.patch(`/diary-entries/${activeEntry.value.id}`, {
                entry: editText.value.trim(),
            });
            const updated = data.entry;
            const idx = entries.value.findIndex((e) => e.id === updated.id);
            if (idx !== -1) {
                entries.value[idx] = { ...entries.value[idx], ...updated };
            }
            activeEntry.value = updated;
            editing.value = false;
            editText.value = '';
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось сохранить.');
        } finally {
            saving.value = false;
        }
    }

    async function remove(entry) {
        if (!confirm(`Удалить запись от ${formatDiaryDate(entry.created_at)}?`)) {
            return;
        }
        error.value = '';
        try {
            await api.delete(`/diary-entries/${entry.id}`);
            entries.value = entries.value.filter((e) => e.id !== entry.id);
            if (activeEntry.value?.id === entry.id) {
                close();
            }
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось удалить.');
        }
    }

    async function regenerate(entry) {
        if (!entry.id) {
            return;
        }
        if (!confirm('Пересоздать запись? Текущий текст будет заменён.')) {
            return;
        }

        regeneratingId.value = entry.id;
        error.value = '';
        try {
            const { data } = await api.post(`/diary-entries/${entry.id}/regenerate`);
            const updated = data.entry;
            const idx = entries.value.findIndex((e) => e.id === updated.id);
            if (idx !== -1) {
                entries.value[idx] = { ...entries.value[idx], ...updated };
            }
            if (activeEntry.value?.id === updated.id) {
                activeEntry.value = updated;
            }
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось пересоздать.');
        } finally {
            regeneratingId.value = null;
        }
    }

    function close() {
        activeEntry.value = null;
        editing.value = false;
        editText.value = '';
    }

    watch(characterId, load, { immediate: true });

    return {
        entries,
        characterName,
        loading,
        error,
        activeEntry,
        editing,
        editText,
        saving,
        regeneratingId,
        load,
        open,
        startEdit,
        cancelEdit,
        save,
        remove,
        regenerate,
        close,
        formatDate: formatDiaryDate,
        emotionColor: diaryEmotionColor,
    };
}
