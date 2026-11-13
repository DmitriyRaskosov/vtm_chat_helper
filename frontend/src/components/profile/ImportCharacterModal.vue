<template>
    <div v-if="open" class="diary-backdrop" @click.self="closeModal">
        <form class="diary-modal card" @submit.prevent="submit">
            <header class="diary-modal-header">
                <h2>Импорт в хронику</h2>
                <button type="button" class="link" @click="closeModal">Закрыть</button>
            </header>

            <p v-if="templateName" class="muted">Шаблон: {{ templateName }}</p>
            <p v-if="chronicleError" class="error">{{ chronicleError }}</p>
            <p v-else-if="error" class="error">{{ error }}</p>

            <label>
                Имя в хронике
                <input
                    v-model.trim="importName"
                    type="text"
                    required
                    maxlength="100"
                    :disabled="saving"
                    autocomplete="off"
                />
            </label>

            <label>
                Хроника
                <select v-model="selectedChronicleId" required :disabled="chroniclesLoading || saving">
                    <option value="" disabled>Выберите хронику</option>
                    <option v-for="item in chronicles" :key="item.id" :value="String(item.id)">
                        {{ item.title }}
                    </option>
                </select>
            </label>

            <div class="profile-confirm-actions">
                <button type="submit" :disabled="!canSubmit">
                    {{ saving ? 'Импорт…' : 'Подтвердить' }}
                </button>
                <button type="button" class="secondary" @click="closeModal">Отмена</button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useCurrentChronicle } from '../../composables/useCurrentChronicle';

const props = defineProps({
    templateId: {
        type: [Number, String],
        default: null,
    },
    templateName: {
        type: String,
        default: '',
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['confirm']);

const { chronicles, loading: chroniclesLoading, error: chronicleError, load } = useCurrentChronicle();
const selectedChronicleId = ref('');
const importName = ref('');

const canSubmit = computed(() => (
    !props.saving
    && !chroniclesLoading.value
    && selectedChronicleId.value !== ''
    && props.templateId != null
    && importName.value.trim() !== ''
));

function closeModal() {
    open.value = false;
}

watch(open, (visible) => {
    if (!visible) {
        selectedChronicleId.value = '';
        importName.value = '';

        return;
    }

    importName.value = props.templateName.trim();

    load().catch(() => {});
    const first = chronicles.value[0];

    if (first) {
        selectedChronicleId.value = String(first.id);
    }
});

watch(
    () => props.templateName,
    (name) => {
        if (open.value && importName.value.trim() === '') {
            importName.value = name.trim();
        }
    },
);

watch(chronicles, (rows) => {
    if (selectedChronicleId.value !== '' || !rows.length) {
        return;
    }

    selectedChronicleId.value = String(rows[0].id);
});

function submit() {
    if (!canSubmit.value) {
        return;
    }

    emit('confirm', Number(selectedChronicleId.value), importName.value.trim());
}
</script>
