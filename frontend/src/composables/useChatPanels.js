import { ref } from 'vue';

const isDiceOpen = ref(false);
const isNotesOpen = ref(false);
const isInventoryOpen = ref(false);
const isCharacterOpen = ref(false);
const isDiaryOpen = ref(false);

export function useChatPanels() {
    function toggleDice() {
        isDiceOpen.value = !isDiceOpen.value;
    }

    function toggleNotes() {
        isNotesOpen.value = !isNotesOpen.value;
    }

    function toggleInventory() {
        isInventoryOpen.value = !isInventoryOpen.value;
    }

    function toggleCharacter() {
        isCharacterOpen.value = !isCharacterOpen.value;
    }

    function toggleDiary() {
        isDiaryOpen.value = !isDiaryOpen.value;
    }

    function closeAll() {
        isDiceOpen.value = false;
        isNotesOpen.value = false;
        isInventoryOpen.value = false;
        isCharacterOpen.value = false;
        isDiaryOpen.value = false;
    }

    return {
        isDiceOpen,
        isNotesOpen,
        isInventoryOpen,
        isCharacterOpen,
        isDiaryOpen,
        toggleDice,
        toggleNotes,
        toggleInventory,
        toggleCharacter,
        toggleDiary,
        closeAll,
    };
}
