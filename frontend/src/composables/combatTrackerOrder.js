export function isAnonymousParticipant(participant) {
    return participant == null || participant.id == null;
}

export function sortCombatParticipants(participants) {
    const rows = Array.isArray(participants) ? participants.map((row) => ({ ...row })) : [];
    const allMissing = rows.every((row) => row.initiative == null);

    if (allMissing) {
        return rows.sort((left, right) => (left.position ?? 0) - (right.position ?? 0));
    }

    return rows.sort((left, right) => {
        const byActive = Number(Boolean(right.is_active)) - Number(Boolean(left.is_active));
        if (byActive !== 0) {
            return byActive;
        }

        const leftMissing = left.initiative == null;
        const rightMissing = right.initiative == null;
        if (leftMissing !== rightMissing) {
            return leftMissing ? 1 : -1;
        }

        if (!leftMissing && left.initiative !== right.initiative) {
            return right.initiative - left.initiative;
        }

        return (left.position ?? 0) - (right.position ?? 0);
    });
}

export function charactersAvailableToAdd(sceneParticipants, combatParticipants) {
    const taken = new Set(
        (combatParticipants ?? [])
            .map((row) => row.character_id)
            .filter((id) => id != null),
    );

    return (sceneParticipants ?? []).filter((row) => (
        row.is_current !== false
        && row.character_id != null
        && !taken.has(row.character_id)
    ));
}

export function reorderCombatParticipants(participants, fromIndex, toIndex, currentTurnIndex = 0) {
    const rows = sortCombatParticipants(participants);
    if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex)) {
        return null;
    }
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= rows.length || toIndex >= rows.length) {
        return null;
    }

    const source = rows[fromIndex];
    const target = rows[toIndex];
    if (isAnonymousParticipant(source) || isAnonymousParticipant(target)) {
        return null;
    }
    if (Boolean(source.is_active) !== Boolean(target.is_active)) {
        return null;
    }

    const swapped = rows.map((row) => ({ ...row }));
    swapped[fromIndex] = { ...source, initiative: target.initiative };
    swapped[toIndex] = { ...target, initiative: source.initiative };

    const participantsNext = sortCombatParticipants(swapped).map((row, position) => ({
        ...row,
        position,
    }));
    const current = rows[currentTurnIndex];
    const found = current == null
        ? -1
        : participantsNext.findIndex((row) => sameParticipant(row, current));

    return {
        participants: participantsNext,
        changes: [
            { id: source.id, initiative: target.initiative },
            { id: target.id, initiative: source.initiative },
        ],
        currentTurnIndex: found === -1 ? 0 : found,
    };
}

function sameParticipant(left, right) {
    if (left.id != null && right.id != null) {
        return left.id === right.id;
    }

    return left.id == null && right.id == null && left.position === right.position;
}
