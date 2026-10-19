<template>
    <section class="card sheet-image-section">
        <h2>Чарлист</h2>

        <p v-if="loading" class="muted">Загрузка изображения…</p>
        <p v-if="error" class="error">{{ error }}</p>

        <div v-if="imageUrl" class="sheet-image-preview">
            <img
                :src="imageUrl"
                alt="Чарлист персонажа"
                style="max-width: 100%; cursor: zoom-in;"
                @click="zoomOpen = true"
            />
        </div>
        <p v-else-if="!loading" class="muted">Чарлист не загружен</p>

        <div v-if="canUpload" class="sheet-image-upload">
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sheet-image-file-input"
                :disabled="uploading"
                @change="onFileSelected"
            />
            <button type="button" :disabled="uploading" @click="fileInput?.click()">
                {{ uploading ? 'Загружаем…' : 'Загрузить чарлист' }}
            </button>
        </div>

        <div v-if="zoomOpen && imageUrl" class="diary-backdrop" @click.self="zoomOpen = false">
            <div class="diary-modal card sheet-image-zoom">
                <header class="diary-modal-header">
                    <h2>Чарлист</h2>
                    <button type="button" class="link" @click="zoomOpen = false">Закрыть</button>
                </header>
                <img :src="imageUrl" alt="Чарлист персонажа" class="sheet-image-zoom-img" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, toRef } from 'vue';
import { useCharacterSheetImage } from '../../composables/useCharacterSheetImage';

const props = defineProps({
    characterId: {
        type: [Number, String],
        required: true,
    },
    canUpload: {
        type: Boolean,
        default: false,
    },
});

const characterId = toRef(props, 'characterId');
const { imageUrl, loading, error, upload } = useCharacterSheetImage(characterId);

const uploading = ref(false);
const zoomOpen = ref(false);
const fileInput = ref(null);

async function onFileSelected(event) {
    const input = event.target;
    const file = input.files?.[0];

    input.value = '';

    if (!file) {
        return;
    }

    uploading.value = true;

    try {
        await upload(characterId.value, file);
    } catch {
        // error ref уже заполнен
    } finally {
        uploading.value = false;
    }
}
</script>

<style scoped>
.sheet-image-section {
    margin-bottom: 1rem;
}

.sheet-image-preview {
    margin: 0.75rem 0;
}

.sheet-image-upload {
    margin-top: 0.75rem;
}

.sheet-image-file-input {
    display: none;
}

.sheet-image-zoom-img {
    max-width: 100%;
    height: auto;
}
</style>
