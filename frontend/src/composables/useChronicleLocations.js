import { ref, unref, watch } from 'vue';
import { api } from '../auth';
import { chronicleCollectionPath } from './useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

export function useChronicleLocations(chronicleId) {
    const locations = ref([]);
    const loading = ref(false);
    const error = ref('');

    async function load() {
        const id = unref(chronicleId);
        if (id == null) {
            locations.value = [];
            return;
        }

        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get(chronicleCollectionPath(id, 'locations'));
            locations.value = data.locations ?? [];
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить библиотеку локаций.');
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function create(payload) {
        const id = unref(chronicleId);
        const { data } = await api.post(chronicleCollectionPath(id, 'locations'), payload);
        await load();
        return data.location;
    }

    async function update(locationId, payload) {
        const { data } = await api.patch(`/locations/${locationId}`, payload);
        await load();
        return data.location;
    }

    async function remove(locationId) {
        await api.delete(`/locations/${locationId}`);
        await load();
    }

    async function uploadImage(locationId, file) {
        const formData = new FormData();
        formData.append('image', file);
        const { data } = await api.post(`/locations/${locationId}/image`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await load();
        return data.image_path;
    }

    async function applyTemplate(sceneId, locationId, expectedRevision) {
        const id = unref(chronicleId);
        error.value = '';

        try {
            const { data } = await api.post(
                chronicleCollectionPath(id, `scenes/${sceneId}/context/apply-template`),
                {
                    location_id: locationId,
                    expected_revision: expectedRevision,
                },
            );
            return data.context;
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось применить шаблон локации.');
            return null;
        }
    }

    watch(
        () => unref(chronicleId),
        () => {
            load();
        },
        { immediate: true },
    );

    return {
        locations,
        loading,
        error,
        load,
        create,
        update,
        remove,
        uploadImage,
        applyTemplate,
    };
}
