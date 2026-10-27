import { ref, watch } from 'vue';

const STORAGE_KEY = 'trpg_chat_sounds_muted';

const soundsMuted = ref(localStorage.getItem(STORAGE_KEY) === '1');

watch(soundsMuted, (muted) => {
    localStorage.setItem(STORAGE_KEY, muted ? '1' : '0');
});

export function useSoundSettings() {
    function toggleSoundsMuted() {
        soundsMuted.value = !soundsMuted.value;
    }

    return {
        soundsMuted,
        toggleSoundsMuted,
    };
}

export function areChatSoundsMuted() {
    return soundsMuted.value;
}
