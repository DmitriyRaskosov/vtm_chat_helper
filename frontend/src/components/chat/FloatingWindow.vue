<template>
    <Teleport to="body">
        <div
            ref="root"
            class="floating-window"
            :class="{ 'is-pinned': isPinned, 'is-dragging': dragging }"
            :style="windowStyle"
            @pointerdown="bringToFront"
        >
            <header class="floating-window-header" @mousedown="onHeaderMouseDown">
                <span class="floating-window-title">{{ title }}</span>
                <span class="floating-window-actions">
                    <button
                        type="button"
                        class="floating-window-pin"
                        :class="{ active: isPinned }"
                        :title="isPinned ? 'Открепить' : 'Закрепить'"
                        :aria-pressed="isPinned"
                        @click="isPinned = !isPinned"
                    >
                        📌
                    </button>
                    <button type="button" class="floating-window-close" title="Закрыть" @click="emit('close')">
                        ×
                    </button>
                </span>
            </header>
            <div class="floating-window-body">
                <slot />
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { takeFloatingZ, useDraggable } from '../../composables/useDraggable';

const props = defineProps({
    title: { type: String, required: true },
    initialLeft: { type: Number, required: true },
    initialTop: { type: Number, required: true },
});

const emit = defineEmits(['close']);

const root = ref(null);
const isPinned = ref(false);
const zIndex = ref(takeFloatingZ());

const {
    left,
    top,
    dragging,
    onHeaderMouseDown,
} = useDraggable({
    initialLeft: props.initialLeft,
    initialTop: props.initialTop,
});

const windowStyle = computed(() => ({
    left: `${left.value}px`,
    top: `${top.value}px`,
    zIndex: zIndex.value,
}));

function bringToFront() {
    zIndex.value = takeFloatingZ();
}

function onDocumentPointerDown(event) {
    if (isPinned.value) {
        return;
    }

    const el = root.value;
    if (!el || el.contains(event.target)) {
        return;
    }

    if (!event.target.closest('.chat-container')) {
        return;
    }

    if (event.target.closest('.chat-split-resizer')) {
        return;
    }

    emit('close');
}

onMounted(() => {
    bringToFront();
    document.addEventListener('pointerdown', onDocumentPointerDown);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
});
</script>
