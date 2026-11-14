<template>
    <div v-if="open" class="sheet-image-modal" @click.self="open = false">
        <button type="button" class="secondary sheet-image-close" @click="open = false">Закрыть</button>
        <div class="sheet-image-stage">
            <iframe
                v-if="isPdf && src"
                :src="src"
                class="sheet-image-pdf"
                :title="title || 'Фото листа'"
            />
            <img
                v-else-if="src"
                :src="src"
                :alt="title || 'Фото листа'"
            />
            <p v-else class="muted">Фото листа не загружено</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { storagePublicUrl } from '../../utils/storagePublicUrl';

const props = defineProps({
    imagePath: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
});

const open = defineModel({ type: Boolean, default: false });
const bust = ref(Date.now());

watch(() => props.imagePath, () => {
    bust.value = Date.now();
});

const isPdf = computed(() => String(props.imagePath ?? '').toLowerCase().endsWith('.pdf'));

const src = computed(() => {
    const url = storagePublicUrl(props.imagePath);

    if (url === '') {
        return '';
    }

    return `${url}?v=${bust.value}`;
});
</script>

<style scoped>
.sheet-image-modal {
    position: fixed;
    inset: 0;
    z-index: 120;
    background: var(--overlay);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.5rem;
}

.sheet-image-stage {
    max-width: 96vw;
    max-height: 90vh;
    overflow: auto;
}

.sheet-image-stage img {
    display: block;
    width: auto;
    height: auto;
    max-width: none;
    box-shadow: 0 12px 40px var(--shadow);
}

.sheet-image-pdf {
    width: min(96vw, 52rem);
    height: 85vh;
    border: 0;
    background: white;
}

.sheet-image-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    margin-top: 0;
}
</style>
