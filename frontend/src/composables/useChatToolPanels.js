import { computed } from 'vue';

export function useChatToolPanels({
    diceOpen,
    notesOpen,
    characterDrawerOpen,
    closeNotes,
}) {
    const anyOpen = computed(() => (
        diceOpen.value
        || notesOpen.value
        || characterDrawerOpen.value
    ));

    function closeAll() {
        diceOpen.value = false;
        closeNotes();
        characterDrawerOpen.value = false;
    }

    return {
        anyOpen,
        closeAll,
    };
}
