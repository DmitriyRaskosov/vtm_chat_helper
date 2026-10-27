<template>
    <div class="chat-tools-toolbar">
        <button
            type="button"
            class="secondary chat-tools-toolbar-btn"
            :class="{ active: isDiceOpen }"
            title="Бросок кубиков"
            @click="toggleDice"
        >
            🎲 Кубы
        </button>
        <button
            type="button"
            class="secondary chat-tools-toolbar-btn"
            :class="{ active: isNotesOpen }"
            title="Личные заметки"
            @click="toggleNotes"
        >
            📝 Заметки
        </button>
        <button
            type="button"
            class="secondary chat-tools-toolbar-btn"
            :class="{ active: isCharacterOpen }"
            :disabled="!characterSheetEnabled"
            :title="characterSheetTitle"
            @click="onCharacterClick"
        >
            📋 Чарлист
        </button>
        <button
            v-if="isStoryteller"
            type="button"
            class="secondary chat-tools-toolbar-btn"
            :class="{ active: isDiaryOpen }"
            title="Дневник NPC"
            @click="toggleDiary"
        >
            📖 Дневник
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useChatPanels } from '../../composables/useChatPanels';

const props = defineProps({
    isStoryteller: { type: Boolean, required: true },
    myCharacterId: { type: [Number, String], default: null },
});

const {
    isDiceOpen,
    isNotesOpen,
    isCharacterOpen,
    isDiaryOpen,
    toggleDice,
    toggleNotes,
    toggleCharacter,
    toggleDiary,
} = useChatPanels();

const characterSheetEnabled = computed(() => (
    props.isStoryteller ? true : props.myCharacterId !== null
));

const characterSheetTitle = computed(() => (
    props.isStoryteller ? 'Чарлист' : 'Мой персонаж'
));

function onCharacterClick() {
    if (!characterSheetEnabled.value) {
        return;
    }
    toggleCharacter();
}
</script>
