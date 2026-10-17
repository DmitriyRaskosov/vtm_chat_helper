export const PLAYER_LABEL = 'игрок';
export const NPC_LABEL = 'НПС';

export function characterTypeLabel(type) {
    if (type === 'player') {
        return PLAYER_LABEL;
    }

    return NPC_LABEL;
}
