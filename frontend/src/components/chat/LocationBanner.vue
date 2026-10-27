<template>
    <div class="location-banner" :class="{ 'is-collapsed': collapsed }">
        <button
            v-if="imageUrl"
            type="button"
            class="loc-img-btn"
            title="Открыть фото локации"
            @click="emit('zoom')"
        >
            <img :src="imageUrl" alt="Локация" class="loc-img-large" />
        </button>
        <div v-else class="loc-img-large loc-img-empty">Нет фото</div>
        <div class="loc-desc">
            <div v-show="!collapsed" class="loc-desc-text">
                {{ situation || 'Описание локации ещё не задано.' }}
            </div>
            <div class="loc-actions">
                <button
                    v-if="isStoryteller"
                    type="button"
                    class="btn-sm"
                    @click="emit('open-settings')"
                >
                    Настроить локацию
                </button>
                <button
                    v-if="isStoryteller"
                    type="button"
                    class="btn-sm"
                    @click="emit('open-manage')"
                >
                    Управление сценой
                </button>
                <button type="button" class="btn-sm" @click="collapsed = !collapsed">
                    {{ collapsed ? 'Развернуть' : 'Свернуть' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    imageUrl: { default: null },
    situation: { type: String, default: '' },
    isStoryteller: { type: Boolean, required: true },
});

const emit = defineEmits(['zoom', 'open-settings', 'open-manage']);
const collapsed = ref(false);
</script>
