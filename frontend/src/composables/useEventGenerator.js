import { ref, toValue } from 'vue';
import { api } from '../auth';
import { chronicleCollectionPath } from './useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

export function useEventGenerator({ chronicleId, selectedSceneId }) {
    const generating = ref(false);
    const generateError = ref('');

    async function generate(payload) {
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
                {
                    action_description: payload.action_description,
                    attack_successes: payload.attack_successes,
                    attack_botch: payload.attack_botch === true,
                    attack_willpower: payload.attack_willpower === true,
                    defense_successes: payload.defense_successes,
                    defense_botch: payload.defense_botch === true,
                    defense_willpower: payload.defense_willpower === true,
                },
            );

            return data.description ?? '';
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
        generate,
    };
}
