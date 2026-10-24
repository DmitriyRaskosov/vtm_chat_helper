<template>
    <div class="chat-tools-dock" :class="{ 'chat-tools-dock-drawer-open': drawerOpen }">
        <div v-if="showPanels" class="chat-tools-panels">
            <slot name="panels" />
        </div>
        <div class="chat-tools-dock-bar">
            <slot name="bar" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    drawerOpen: { type: Boolean, default: false },
    showPanels: { type: Boolean, default: false },
});
</script>
