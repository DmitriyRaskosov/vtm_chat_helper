import { ref, toValue } from 'vue';
import { api } from '../auth';
import { chronicleCollectionPath } from './useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

export function useEventGenerator({ chronicleId, selectedSceneId }) {
    const generating = ref(false);
    const generateError = ref('');

    async function generateEvent(payload) {
        const sceneId = toValue(selectedSceneId);
        const chronicle = toValue(chronicleId);

        if (!sceneId || !chronicle) {
            return null;
        }

        generating.value = true;
        generateError.value = '';

        try {
            const { data } = await api.post(
                chronicleCollectionPath(chronicle, `scenes/${sceneId}/events/generate`),
                payload,
            );

            return data.text ?? '';
        } catch (error) {
            generateError.value = apiErrorMessage(error, 'Не удалось сгенерировать описание.');
            return null;
        } finally {
            generating.value = false;
        }
    }

    return {
        generating,
        generateError,
        generateEvent,
    };
}
