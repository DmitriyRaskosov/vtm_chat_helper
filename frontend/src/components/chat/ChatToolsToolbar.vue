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
            :disabled="!notesEnabled"
            :title="notesTitle"
            @click="onNotesClick"
        >
            📝 Заметки
        </button>
        <button
            type="button"
            class="secondary chat-tools-toolbar-btn"
            :class="{ active: isInventoryOpen }"
            :disabled="!inventoryEnabled"
            :title="inventoryTitle"
            @click="onInventoryClick"
        >
            🎒 Инвентарь
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
        <button
            type="button"
            class="secondary chat-tools-toolbar-btn"
            :class="{ active: isCombatOpen }"
            title="Трекер боя"
            @click="toggleCombat"
        >
            ⚔ Бой
        </button>
        <button
            v-if="isStoryteller && !combatActive"
            type="button"
            class="secondary chat-tools-toolbar-btn"
            title="Начать бой"
            :disabled="!canStartCombat"
            @click="emit('start-combat')"
        >
            Начать бой
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useChatPanels } from '../../composables/useChatPanels';

const props = defineProps({
    isStoryteller: { type: Boolean, required: true },
    myCharacterId: { type: [Number, String], default: null },
    inventoryCharacterId: { type: [Number, String], default: null },
    notesCharacterId: { type: [Number, String], default: null },
    combatActive: { type: Boolean, default: false },
    canStartCombat: { type: Boolean, default: false },
});

const emit = defineEmits(['start-combat']);

const {
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

const inventoryEnabled = computed(() => {
    const id = Number(props.inventoryCharacterId);

    return Number.isFinite(id) && id > 0;
});

const inventoryTitle = computed(() => (
    inventoryEnabled.value ? 'Инвентарь' : 'Сначала выберите персонажа'
));

const notesEnabled = computed(() => {
    const id = Number(props.notesCharacterId);

    return Number.isFinite(id) && id > 0;
});

const notesTitle = computed(() => (
    notesEnabled.value ? 'Личные заметки' : 'Сначала выберите персонажа'
));

function onNotesClick() {
    if (!notesEnabled.value) {
        return;
    }
    toggleNotes();
}

function onInventoryClick() {
    if (!inventoryEnabled.value) {
        return;
    }
    toggleInventory();
}
</script>
