export function sameCombatScene(sceneId, payload) {
    return Number(payload?.scene_id) === Number(sceneId) && Number(sceneId) > 0;
}

export function applyCombatTurn(combat, payload) {
    if (!combat) {
        return combat;
    }

    if (payload?.status === 'finished') {
        return {
            ...combat,
            status: 'finished',
        };
    }

    return {
        ...combat,
        current_round: payload?.current_round ?? combat.current_round,
        current_turn_index: payload?.current_turn_index ?? combat.current_turn_index,
        status: payload?.status ?? combat.status,
    };
}

export function filterBattleLogs(logs, encounterId, combatOnly) {
    if (!combatOnly || encounterId == null) {
        return logs;
    }

    const id = Number(encounterId);

    return logs.filter((entry) => Number(entry.combat_encounter_id) === id);
}
