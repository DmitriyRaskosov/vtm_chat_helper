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

        <div v-if="traits.length === 0" class="muted">Пока нет черт.</div>

        <div v-for="(trait, index) in traits" :key="trait._id" class="trait-row">
            <div class="trait-row-header">
                <strong>{{ trait.label || 'Свой трейт' }}</strong>
                <button type="button" class="link" @click="emit('remove', index)">Удалить</button>
            </div>
            <div v-if="!trait.key" class="trait-custom-meta">
                <label>
                    Ключ
                    <input v-model="trait.key" type="text" maxlength="64" placeholder="custom_trait" />
                </label>
                <label>
                    Название
                    <input v-model="trait.label" type="text" maxlength="120" placeholder="Название" />
                </label>
            </div>
            <label>
                {{ trait.key ? trait.label : 'Значение' }}
                <textarea
                    v-model="trait.value"
                    rows="2"
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

function traitPlaceholder(trait) {
    if (!trait.key) {
        return 'Опишите черту персонажа…';
    }

    const preset = props.presets.find((row) => row.key === trait.key);

    return preset?.placeholder ?? '';
}
</script>
