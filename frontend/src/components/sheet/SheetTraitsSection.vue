<template>
    <section class="card">
        <details>
        <summary>Черты личности</summary>
        <p class="muted">Короткие заметки для отыгрыша и Copilot.</p>

        <div class="trait-presets">
            <span class="muted">Добавить:</span>
            <button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                class="secondary trait-preset-btn"
                :disabled="traits.some((row) => row.key === preset.key)"
                @click="emit('add-preset', preset)"
            >
                {{ preset.label }}
            </button>
            <button type="button" class="secondary trait-preset-btn" @click="emit('add-custom')">
                Свой трейт
            </button>
        </div>

        <div v-for="(trait, index) in traits" :key="trait._id" class="personality-trait">
            <div class="personality-trait-header">
                <strong>{{ trait.label || 'Свой трейт' }}</strong>
                <button
                    v-if="!isPreset(trait.key)"
                    type="button"
                    class="link"
                    @click="emit('remove', index)"
                >
                    Удалить
                </button>
            </div>
            <div v-if="!trait.key" class="personality-trait-meta">
                <label>
                    Ключ
                    <input v-model="trait.key" type="text" maxlength="64" placeholder="custom_trait" />
                </label>
                <label>
                    Название
                    <input v-model="trait.label" type="text" maxlength="120" placeholder="Название" />
                </label>
            </div>
            <label class="personality-trait-field">
                <span class="personality-trait-field-label">{{ trait.key ? 'Текст' : 'Значение' }}</span>
                <textarea
                    v-model="trait.value"
                    rows="6"
                    maxlength="2000"
                    :placeholder="traitPlaceholder(trait)"
                />
            </label>
        </div>

        <div class="sheet-actions">
            <button type="button" @click="emit('save')">Сохранить черты</button>
            <span v-if="flash" class="saved-flash">Сохранено!</span>
        </div>
        </details>
    </section>
</template>

<script setup>
const props = defineProps({
    traits: { type: Array, required: true },
    presets: { type: Array, required: true },
    flash: { type: Boolean, required: true },
});

const emit = defineEmits(['save', 'add-preset', 'add-custom', 'remove']);

function isPreset(key) {
    return props.presets.some((row) => row.key === key);
}

function traitPlaceholder(trait) {
    if (!trait.key) {
        return 'Опишите черту персонажа…';
    }

    const preset = props.presets.find((row) => row.key === trait.key);

    return preset?.placeholder ?? '';
}
</script>
