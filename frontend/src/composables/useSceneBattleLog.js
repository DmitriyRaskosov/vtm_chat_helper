import { ref, toValue, watch } from 'vue';
import { api } from '../auth';
import { chronicleCollectionPath } from './useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

export function useSceneBattleLog({ chronicleId, selectedSceneId, isStoryteller }) {
    const logs = ref([]);
    const lastLogId = ref(0);
    const logError = ref('');
    let polling = false;

    function resetLogs() {
        logs.value = [];
        lastLogId.value = 0;
        logError.value = '';
    }

    function mergeLogs(incoming) {
        if (!incoming?.length) {
            return;
        }

        const known = new Set(logs.value.map((row) => row.id));
        const appended = [];

        for (const row of incoming) {
            if (!known.has(row.id)) {
                known.add(row.id);
                appended.push(row);
            }
        }

        if (appended.length) {
            logs.value = [...logs.value, ...appended];
            lastLogId.value = logs.value[logs.value.length - 1].id;
        }
    }

    async function fetchLogs(initial = false) {
        const sceneId = toValue(selectedSceneId);
        const chronicle = toValue(chronicleId);

        if (!sceneId || !chronicle) {
            return;
        }

        const params = {};
        if (!initial && lastLogId.value > 0) {
            params.after_id = lastLogId.value;
        }

        const { data } = await api.get(
            chronicleCollectionPath(chronicle, `scenes/${sceneId}/logs`),
            { params },
        );

        if (initial) {
            logs.value = data.logs ?? [];
            lastLogId.value = logs.value.length ? logs.value[logs.value.length - 1].id : 0;
        } else {
            mergeLogs(data.logs ?? []);
        }
    }

    async function pollLogs() {
        if (polling) {
            return;
        }

        polling = true;

        try {
            await fetchLogs(false);
            logError.value = '';
        } catch (error) {
            logError.value = apiErrorMessage(error, 'Не удалось обновить лог боя.');
        } finally {
            polling = false;
        }
    }

    async function reloadLogs() {
        try {
            await fetchLogs(true);
            logError.value = '';
        } catch (error) {
            logError.value = apiErrorMessage(error, 'Не удалось загрузить лог боя.');
        }
    }

    watch(selectedSceneId, async () => {
        resetLogs();
        await reloadLogs();
    });

    return {
        logs,
        logError,
        reloadLogs,
        pollLogs,
        resetLogs,
        mergeLogs,
    };
}

export function useDiceRoll({ chronicleId, selectedSceneId }) {
    const rolling = ref(false);
    const rollError = ref('');
    const lastResult = ref(null);

    async function rollDice(payload) {
        const sceneId = toValue(selectedSceneId);
        const chronicle = toValue(chronicleId);

        if (!sceneId || !chronicle) {
            return null;
        }

        rolling.value = true;
        rollError.value = '';

        try {
            const { data } = await api.post(
                chronicleCollectionPath(chronicle, `scenes/${sceneId}/dice-rolls`),
                payload,
            );
            lastResult.value = data;
            return data;
        } catch (error) {
            rollError.value = apiErrorMessage(error, 'Не удалось выполнить бросок.');
            return null;
        } finally {
            rolling.value = false;
        }
    }

    function clearLastResult() {
        lastResult.value = null;
        rollError.value = '';
    }

    return {
        rolling,
        rollError,
        lastResult,
        rollDice,
        clearLastResult,
    };
}
