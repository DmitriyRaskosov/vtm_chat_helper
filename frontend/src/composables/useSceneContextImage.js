import { onUnmounted, ref, unref, watch } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useSceneContextImage(sceneId) {
    const imageUrl = ref(null);
    const loading = ref(false);
    const error = ref('');

    let objectUrl = null;

    function revokeObjectUrl() {
        if (objectUrl !== null) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        imageUrl.value = null;
    }

    async function load(id) {
        const numericId = Number(unref(id));

        if (!Number.isFinite(numericId) || numericId <= 0) {
            revokeObjectUrl();

            return null;
        }

        loading.value = true;
        error.value = '';
        revokeObjectUrl();

        try {
            const response = await api.get(`/scenes/${numericId}/image`, {
                responseType: 'blob',
            });

            objectUrl = URL.createObjectURL(response.data);
            imageUrl.value = objectUrl;

            return objectUrl;
        } catch (err) {
            if (err.response?.status === 404) {
                return null;
            }

            error.value = apiErrorMessage(err, 'Не удалось загрузить фото локации.');
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function upload(id, file) {
        const numericId = Number(unref(id));

        if (!Number.isFinite(numericId) || numericId <= 0) {
            throw new Error('Сцена не выбрана.');
        }

        error.value = '';

        const formData = new FormData();
        formData.append('image', file);

        try {
            await api.post(`/scenes/${numericId}/image`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            return load(numericId);
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить изображение.');
            throw err;
        }
    }

    watch(
        () => unref(sceneId),
        (id) => {
            load(id);
        },
        { immediate: true },
    );

    onUnmounted(() => {
        revokeObjectUrl();
    });

    return {
        imageUrl,
        loading,
        error,
        load,
        upload,
    };
}
