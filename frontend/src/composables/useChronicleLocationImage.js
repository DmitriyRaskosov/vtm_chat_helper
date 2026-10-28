import { onUnmounted, ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useChronicleLocationImage() {
    const previews = ref({});
    const loadingIds = ref({});
    const error = ref('');

    let objectUrls = {};

    function revokePreview(locationId) {
        const key = String(locationId);
        if (objectUrls[key]) {
            URL.revokeObjectURL(objectUrls[key]);
            delete objectUrls[key];
        }
        const next = { ...previews.value };
        delete next[key];
        previews.value = next;
    }

    function revokeAll() {
        for (const key of Object.keys(objectUrls)) {
            URL.revokeObjectURL(objectUrls[key]);
        }
        objectUrls = {};
        previews.value = {};
    }

    async function loadPreview(locationId) {
        const key = String(locationId);
        if (!locationId || previews.value[key]) {
            return previews.value[key] ?? null;
        }

        loadingIds.value = { ...loadingIds.value, [key]: true };
        error.value = '';

        try {
            const response = await api.get(`/locations/${locationId}/image`, {
                responseType: 'blob',
            });
            const url = URL.createObjectURL(response.data);
            objectUrls[key] = url;
            previews.value = { ...previews.value, [key]: url };
            return url;
        } catch (err) {
            if (err.response?.status !== 404) {
                error.value = apiErrorMessage(err, 'Не удалось загрузить превью.');
            }
            return null;
        } finally {
            const nextLoading = { ...loadingIds.value };
            delete nextLoading[key];
            loadingIds.value = nextLoading;
        }
    }

    onUnmounted(() => {
        revokeAll();
    });

    return {
        previews,
        loadingIds,
        error,
        loadPreview,
        revokePreview,
        revokeAll,
    };
}
