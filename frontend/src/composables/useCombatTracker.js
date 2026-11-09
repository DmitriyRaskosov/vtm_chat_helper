import { computed, onScopeDispose, ref, toValue, watch } from 'vue';
import { api, isStoryteller } from '../auth';
import { apiErrorMessage } from '../utils/apiError';
import { reorderCombatParticipants, sortCombatParticipants } from './combatTrackerOrder';

const POLL_MS = 5000;

export function useCombatTracker(sceneId) {
    const combat = ref(null);
    const loading = ref(false);
    const busy = ref(false);
    const error = ref('');
    const holdSync = ref(false);

    let pollTimer = null;

    const encounter = computed(() => (
        combat.value && combat.value.status === 'active' ? combat.value : null
    ));
    const participants = computed(() => sortCombatParticipants(encounter.value?.participants ?? []));
    const currentTurnIndex = computed(() => encounter.value?.current_turn_index ?? 0);

    async function load({ silent = false } = {}) {
        const id = toValue(sceneId);
        if (!id) {
            combat.value = null;
            return;
        }

        if (!silent) {
            loading.value = true;
            error.value = '';
        }

        try {
            const { data } = await api.get(`/scenes/${id}/combat`);
            if (!holdSync.value) {
                combat.value = data.combat ?? null;
            }
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить бой.');
        } finally {
            loading.value = false;
        }
    }

    function applyResponse(data) {
        combat.value = data?.combat ?? null;
    }

    async function run(request, fallback) {
        busy.value = true;
        error.value = '';
        try {
            const { data } = await request();
            applyResponse(data);
            return data?.combat ?? null;
        } catch (err) {
            error.value = apiErrorMessage(err, fallback);
            return null;
        } finally {
            busy.value = false;
        }
    }

    function startCombat() {
        const id = toValue(sceneId);
        if (!id) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/scenes/${id}/combat`),
            'Не удалось начать бой.',
        );
    }

    function nextTurn() {
        const id = encounter.value?.id;
        if (!id) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/combat/${id}/next-turn`),
            'Не удалось переключить ход.',
        );
    }

    function setActive(participant, isActive) {
        const id = encounter.value?.id;
        if (!id || participant?.id == null) {
            return Promise.resolve(null);
        }

        return run(
            () => api.patch(`/combat/${id}/participants/${participant.id}/active`, { is_active: isActive }),
            'Не удалось изменить статус участника.',
        );
    }

    function reveal(participant) {
        const id = encounter.value?.id;
        if (!id || participant?.id == null) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/combat/${id}/participants/${participant.id}/reveal`),
            'Не удалось раскрыть участника.',
        );
    }

    function setInitiative(participant, initiative) {
        const id = encounter.value?.id;
        if (!id || participant?.id == null) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/combat/${id}/participants/${participant.id}/initiative`, { initiative }),
            'Не удалось изменить инициативу.',
        );
    }

    function addParticipant(characterId, isHidden = false) {
        const id = encounter.value?.id;
        if (!id || characterId == null) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/combat/${id}/participants`, {
                character_id: characterId,
                is_hidden: isHidden === true,
            }),
            'Не удалось добавить участника.',
        );
    }

    function rollInitiative() {
        const id = encounter.value?.id;
        if (!id) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/combat/${id}/roll-initiative`),
            'Не удалось рассчитать инициативу.',
        );
    }

    function finishCombat() {
        const id = encounter.value?.id;
        if (!id) {
            return Promise.resolve(null);
        }

        return run(
            () => api.post(`/combat/${id}/finish`),
            'Не удалось завершить бой.',
        );
    }

    async function moveParticipant(fromIndex, toIndex) {
        if (!encounter.value || !isStoryteller.value) {
            return;
        }

        const result = reorderCombatParticipants(
            participants.value,
            fromIndex,
            toIndex,
            currentTurnIndex.value,
        );
        if (!result) {
            return;
        }

        const snapshot = combat.value;
        const combatId = snapshot.id;
        holdSync.value = true;
        combat.value = {
            ...snapshot,
            participants: result.participants,
            current_turn_index: result.currentTurnIndex,
        };
        busy.value = true;
        error.value = '';

        try {
            let latest = null;
            for (const change of result.changes) {
                const { data } = await api.post(
                    `/combat/${combatId}/participants/${change.id}/initiative`,
                    { initiative: change.initiative },
                );
                latest = data;
            }
            if (latest) {
                applyResponse(latest);
            }
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось изменить инициативу.');
            holdSync.value = false;
            await load({ silent: true });
        } finally {
            busy.value = false;
            holdSync.value = false;
        }
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function syncPolling() {
        stopPolling();
        if (isStoryteller.value || toValue(sceneId) == null) {
            return;
        }

        pollTimer = setInterval(() => {
            load({ silent: true });
        }, POLL_MS);
    }

    watch(() => toValue(sceneId), () => {
        load();
        syncPolling();
    }, { immediate: true });

    watch(isStoryteller, syncPolling);

    onScopeDispose(stopPolling);

    return {
        encounter,
        participants,
        currentTurnIndex,
        loading,
        busy,
        error,
        isStoryteller,
        load,
        startCombat,
        nextTurn,
        setActive,
        reveal,
        setInitiative,
        rollInitiative,
        addParticipant,
        finishCombat,
        moveParticipant,
    };
}
