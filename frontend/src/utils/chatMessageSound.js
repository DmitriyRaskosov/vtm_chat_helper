const SOUNDS = {
    player: '/sounds/msg-player.mp3',
    npc: '/sounds/msg-npc.mp3',
    storyteller: '/sounds/msg-stor.mp3',
};

function playUrl(url) {
    const audio = new Audio(url);
    audio.volume = 0.35;
    void audio.play().catch(() => {});
}

export function playSoundForMessage(message, { myCharacterId, myUserId, isStoryteller }) {
    if (myCharacterId != null && Number(message.author_character_id) === Number(myCharacterId)) {
        playUrl(SOUNDS.player);

        return;
    }

    if (message.npc_name) {
        playUrl(SOUNDS.npc);

        return;
    }

    if (isStoryteller && myUserId != null && Number(message.user_id) === Number(myUserId)) {
        playUrl(SOUNDS.storyteller);

        return;
    }

    if (!isStoryteller) {
        playUrl(SOUNDS.storyteller);
    }
}
