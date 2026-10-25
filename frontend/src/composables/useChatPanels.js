import { ref } from 'vue';

const isDiceOpen = ref(false);
const isNotesOpen = ref(false);
const isCharacterOpen = ref(false);

export function useChatPanels() {
    function toggleDice() {
        isDiceOpen.value = !isDiceOpen.value;
    }

    function toggleNotes() {
        isNotesOpen.value = !isNotesOpen.value;
    }

    function toggleCharacter() {
        isCharacterOpen.value = !isCharacterOpen.value;
    }

    function closeAll() {
        isDiceOpen.value = false;
        isNotesOpen.value = false;
        isCharacterOpen.value = false;
    }

    return {
        isDiceOpen,
        isNotesOpen,
        isCharacterOpen,
        toggleDice,
        toggleNotes,
        toggleCharacter,
        closeAll,
    };
}
