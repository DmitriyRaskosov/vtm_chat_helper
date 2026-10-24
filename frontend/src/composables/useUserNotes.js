import { ref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

let saveTimer;

export function useUserNotes(selectedSceneId) {
    const open = ref(false);
    const content = ref('');
    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');
    const loadedSceneId = ref(null);

    async function load() {
        const sceneId = selectedSceneId.value;
        if (!sceneId) {
            content.value = '';
            loadedSceneId.value = null;
            return;
        }

        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get('/user-notes', { params: { scene_id: sceneId } });
            if (selectedSceneId.value !== sceneId) {
                return;
            }
            content.value = data.note?.content ?? '';
            loadedSceneId.value = sceneId;
        } catch (e) {
            error.value = apiErrorMessage(e, 'Не удалось загрузить заметки.');
        } finally {
            loading.value = false;
        }
    }

    async function save() {
        const sceneId = selectedSceneId.value;
        if (!sceneId || saving.value) {
            return;
        }

        saving.value = true;
        error.value = '';

        try {
            const { data } = await api.put('/user-notes', {
                scene_id: sceneId,
                content: content.value,
            });
            if (selectedSceneId.value === sceneId) {
                content.value = data.note?.content ?? content.value;
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

    watch(selectedSceneId, () => {
        if (open.value) {
            load();
        }
    });

    watch(content, () => {
        if (!open.value || loading.value || loadedSceneId.value !== selectedSceneId.value) {
            return;
        }
        scheduleSave();
    });

    return {
        open,
        content,
        loading,
        saving,
        error,
        openModal,
        closeModal,
        load,
        save,
    };
}
