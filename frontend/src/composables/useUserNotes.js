import { ref, unref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

let saveTimer;

/**
 * @param {import('vue').MaybeRefOrGetter<string|number|null|undefined>} chronicleId
 * @param {import('vue').MaybeRefOrGetter<string|number|null|undefined>} selectedCharacterId
 * @param {{ autoSave?: boolean, trackOpen?: boolean, reloadOnCharacterChange?: boolean }} [options]
 */
export function useUserNotes(chronicleId, selectedCharacterId, options = {}) {
    const autoSave = options.autoSave ?? false;
    const trackOpen = options.trackOpen ?? false;
    const reloadOnCharacterChange = options.reloadOnCharacterChange ?? false;

    const open = ref(false);
    const content = ref('');
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');
    const loadedCharacterId = ref(null);

    async function fetchNote(characterId) {
        const cid = unref(chronicleId);
        const id = Number(characterId);

        if (!cid || !Number.isFinite(id) || id <= 0) {
            return null;
        }

        const { data } = await api.get(`/chronicles/${cid}/characters/${id}/note`);

        return data.note;
    }

    async function saveNote(characterId, body) {
        const cid = unref(chronicleId);
        const id = Number(characterId);

        if (!cid || !Number.isFinite(id) || id <= 0) {
            return null;
        }

        const { data } = await api.put(`/chronicles/${cid}/characters/${id}/note`, { body });

        return data.note;
    }

    async function load() {
        const characterId = unref(selectedCharacterId);
        const id = Number(characterId);

        if (!Number.isFinite(id) || id <= 0) {
            content.value = '';
            loadedCharacterId.value = null;
            return;
        }

        loading.value = true;
        error.value = '';

        try {
            const note = await fetchNote(id);
            if (Number(unref(selectedCharacterId)) !== id) {
                return;
            }
            content.value = note?.body ?? '';
            loadedCharacterId.value = id;
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось загрузить заметки.');
        } finally {
            loading.value = false;
        }
    }

    async function save() {
        const characterId = unref(selectedCharacterId);
        const id = Number(characterId);

        if (!Number.isFinite(id) || id <= 0 || saving.value) {
            return;
        }

        saving.value = true;
        error.value = '';

        try {
            const note = await saveNote(id, content.value);
            if (Number(unref(selectedCharacterId)) === id) {
                content.value = note?.body ?? content.value;
                loadedCharacterId.value = id;
            }
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось сохранить заметки.');
        } finally {
            saving.value = false;
        }
    }

    function scheduleSave() {
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
        saveTimer = setTimeout(() => {
            save();
        }, 800);
    }

    function openModal() {
        open.value = true;
        load();
    }

    function closeModal() {
        open.value = false;
    }

    if (trackOpen) {
        watch(selectedCharacterId, () => {
            if (open.value) {
                load();
            }
        });
    }

    if (reloadOnCharacterChange) {
        watch(selectedCharacterId, () => {
            load();
        });
    }

    if (autoSave) {
        watch(content, () => {
            if (trackOpen && !open.value) {
                return;
            }
            if (loading.value || loadedCharacterId.value !== Number(unref(selectedCharacterId))) {
                return;
            }
            scheduleSave();
        });
    }

    return {
        open,
        content,
        loading,
        saving,
        error,
        fetchNote,
        saveNote,
        openModal,
        closeModal,
        load,
        save,
    };
}
