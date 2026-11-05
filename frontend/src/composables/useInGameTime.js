import { computed } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';
import { chronicleCollectionPath, resolveChronicleId, useCurrentChronicle } from './useCurrentChronicle';

export function applyChronicleInGameTime(chronicleId, iso) {
    const id = resolveChronicleId(chronicleId);
    if (id === null || typeof iso !== 'string' || iso === '') {
        return;
    }

    const { chronicles } = useCurrentChronicle();
    const index = chronicles.value.findIndex((item) => item.id === id);
    if (index < 0) {
        return;
    }

    chronicles.value[index] = {
        ...chronicles.value[index],
        current_in_game_date: iso,
    };
}

export function useInGameTime(chronicleId) {
    const { currentChronicle, chronicles } = useCurrentChronicle();
    const id = computed(() => resolveChronicleId(chronicleId));

    const currentInGameDate = computed(() => {
        const routeId = id.value;
        if (routeId === null) {
            return null;
        }

        if (currentChronicle.value?.id === routeId) {
            return currentChronicle.value.current_in_game_date ?? null;
        }

        const chronicle = chronicles.value.find((item) => item.id === routeId);

        return chronicle?.current_in_game_date ?? null;
    });

    async function updateTime(datetime) {
        const { data } = await api.patch(chronicleCollectionPath(chronicleId, 'time'), {
            datetime,
        });
        applyChronicleInGameTime(chronicleId, data.current_in_game_date);

        return data;
    }

    function errorMessage(error) {
        return apiErrorMessage(error, 'Не удалось изменить игровое время.');
    }

    return {
        currentInGameDate,
        updateTime,
        errorMessage,
    };
}
