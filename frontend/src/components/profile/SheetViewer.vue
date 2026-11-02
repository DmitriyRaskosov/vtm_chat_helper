<template>
    <div class="sheet-viewer">
        <p v-if="loading" class="muted">Загрузка чарлиста…</p>
        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="formError" class="error">{{ formError }}</p>

        <div v-if="imageUrl && !isPdf" class="sheet-viewer-preview">
            <img
                :src="imageUrl"
                alt="Чарлист персонажа"
                @click="zoomOpen = true"
            />
        </div>

        <VuePdfEmbed v-else-if="imageUrl && isPdf" :source="imageUrl" class="sheet-viewer-pdf" />

        <p v-else-if="!loading" class="muted">Чарлист не загружен</p>

        <div v-if="isPdf && hasFields && !editing" class="sheet-viewer-actions">
            <button type="button" class="secondary" @click="editing = true">Редактировать</button>
        </div>

        <form v-if="editing" class="sheet-viewer-form" @submit.prevent="saveForm">
            <label v-for="field in fields" :key="field.name">
                {{ field.name }}
                <input
                    v-if="field.type === 'text'"
                    type="text"
                    :value="field.value"
                    @input="setField(field.name, $event.target.value)"
                />
                <input
                    v-else-if="field.type === 'checkbox'"
                    type="checkbox"
                    :checked="field.value"
                    @change="setField(field.name, $event.target.checked)"
                />
                <select
                    v-else-if="field.type === 'dropdown' || field.type === 'radio'"
                    :value="field.value"
                    @change="setField(field.name, $event.target.value)"
                >
                    <option value="">—</option>
                    <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                </select>
                <select
                    v-else-if="field.type === 'option-list'"
                    multiple
                    :value="field.value"
                    @change="setOptionList(field.name, $event.target)"
                >
                    <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                </select>
            </label>
            <div class="sheet-viewer-actions">
                <button type="submit" :disabled="saving">{{ saving ? 'Сохраняем…' : 'Сохранить' }}</button>
                <button type="button" class="secondary" @click="editing = false">Отмена</button>
            </div>
        </form>

        <div v-if="canUpload" class="sheet-viewer-actions">
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                class="sheet-viewer-file"
                :disabled="uploading"
                @change="onFileSelected"
            />
            <button type="button" :disabled="uploading" @click="fileInput?.click()">
                {{ uploading ? 'Загружаем…' : 'Загрузить чарлист' }}
            </button>
        </div>

        <div v-if="zoomOpen && imageUrl && !isPdf" class="diary-backdrop" @click.self="zoomOpen = false">
            <div class="diary-modal card">
                <header class="diary-modal-header">
                    <h2>Чарлист</h2>
                    <button type="button" class="link" @click="zoomOpen = false">Закрыть</button>
                </header>
                <img :src="imageUrl" alt="Чарлист персонажа" class="sheet-viewer-zoom" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, toRef, watch } from 'vue';
import VuePdfEmbed from 'vue-pdf-embed';
import { useCharacterSheetImage } from '../../composables/useCharacterSheetImage';
import { usePdfForm } from '../../composables/usePdfForm';

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
const {
    imageUrl,
    contentType,
    fileBlob,
    loading,
    error,
    upload,
} = useCharacterSheetImage(characterId);

const {
    fields,
    hasFields,
    error: formError,
    load: loadForm,
    reset: resetForm,
    setField,
    toFile,
} = usePdfForm();

const editing = ref(false);
const saving = ref(false);
const uploading = ref(false);
const zoomOpen = ref(false);
const fileInput = ref(null);

const isPdf = computed(() => contentType.value.toLowerCase().includes('pdf'));

watch(fileBlob, (blob) => {
    editing.value = false;
    zoomOpen.value = false;

    if (!(blob instanceof Blob) || !isPdf.value) {
        resetForm();

        return;
    }

    loadForm(blob).catch(() => {});
});

function setOptionList(name, select) {
    setField(name, [...select.selectedOptions].map((option) => option.value));
}

async function saveForm() {
    saving.value = true;

    try {
        const file = await toFile('sheet.pdf');
        await upload(characterId.value, file);
        editing.value = false;
    } catch {
        // сообщения уже в error / formError
    } finally {
        saving.value = false;
    }
}

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
.sheet-viewer-preview img {
    max-width: 100%;
    cursor: zoom-in;
}

.sheet-viewer-pdf {
    margin: 0.75rem 0;
}

.sheet-viewer-form {
    margin-top: 0.75rem;
}

.sheet-viewer-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.sheet-viewer-actions button {
    margin-top: 0.75rem;
}

.sheet-viewer-file {
    display: none;
}

.sheet-viewer-zoom {
    max-width: 100%;
    height: auto;
}
</style>
