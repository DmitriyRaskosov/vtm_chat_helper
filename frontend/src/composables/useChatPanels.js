import { ref } from 'vue';

const isDiceOpen = ref(false);
const isNotesOpen = ref(false);
const isInventoryOpen = ref(false);
const isCharacterOpen = ref(false);
const isDiaryOpen = ref(false);
const isCombatOpen = ref(false);

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

    function toggleCombat() {
        isCombatOpen.value = !isCombatOpen.value;
    }

    function closeAll() {
        isDiceOpen.value = false;
        isNotesOpen.value = false;
        isInventoryOpen.value = false;
        isCharacterOpen.value = false;
        isDiaryOpen.value = false;
        isCombatOpen.value = false;
    }

    return {
        isDiceOpen,
        isNotesOpen,
        isInventoryOpen,
        isCharacterOpen,
        isDiaryOpen,
        isCombatOpen,
        toggleDice,
        toggleNotes,
        toggleInventory,
        toggleCharacter,
        toggleDiary,
        toggleCombat,
        closeAll,
    };
}
