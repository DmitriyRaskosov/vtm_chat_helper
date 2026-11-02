<template>
    <section class="card sheet-image-section">
        <details ref="detailsEl">
            <summary>Чарлист</summary>
            <SheetViewer :character-id="characterId" :can-upload="canUpload" />
        </details>
    </section>
</template>

<script setup>
import { defineAsyncComponent, onMounted, ref } from 'vue';

const SheetViewer = defineAsyncComponent(() => import('../profile/SheetViewer.vue'));

const props = defineProps({
    characterId: {
        type: [Number, String],
        required: true,
    },
    canUpload: {
        type: Boolean,
        default: false,
    },
    startOpen: {
        type: Boolean,
        default: false,
    },
});

const detailsEl = ref(null);

onMounted(() => {
    if (props.startOpen && detailsEl.value) {
        detailsEl.value.open = true;
    }
});
</script>

<style scoped>
.sheet-image-section {
    margin-bottom: 1rem;
}
</style>
