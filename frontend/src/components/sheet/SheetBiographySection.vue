<template>
    <section class="card">
        <h2>Биография</h2>
        <p v-if="sheet.biography?.current_version" class="muted">
            Версия {{ sheet.biography.current_version }}
        </p>
        <label>
            Кратко
            <textarea v-model="biography.summary" rows="3" maxlength="4000" />
        </label>
        <label>
            Полный текст
            <textarea v-model="biography.full_text" class="bio-full" rows="8" maxlength="50000" />
        </label>
        <details class="bio-play">
            <summary>Для отыгрыша</summary>
            <label>
                Принципы
                <textarea v-model="biography.principles" rows="3" maxlength="4000" />
            </label>
            <label>
                Мотивация
                <textarea v-model="biography.motivation" rows="3" maxlength="4000" />
            </label>
            <label>
                Страхи
                <textarea v-model="biography.fears" rows="3" maxlength="4000" />
            </label>
            <label>
                Желания
                <textarea v-model="biography.desires" rows="3" maxlength="4000" />
            </label>
            <label>
                Правила поведения
                <textarea v-model="biography.behavioral_rules" rows="3" maxlength="4000" />
            </label>
        </details>
        <div class="sheet-actions">
            <button type="button" @click="emit('save')">Сохранить биографию</button>
            <button
                v-if="extractorEnabled && isStoryteller"
                type="button"
                class="secondary"
                :disabled="extracting"
                @click="emit('extract')"
            >
                {{ extracting ? 'Разбор…' : 'Разобрать' }}
            </button>
            <span v-if="flash" class="saved-flash">Сохранено!</span>
        </div>
        <p v-if="extractorEnabled && isStoryteller" class="muted">
            Кандидаты памяти появятся в «Мир → Разбор».
        </p>
    </section>
</template>

<script setup>
defineProps({
    sheet: { type: Object, required: true },
    biography: { type: Object, required: true },
    flash: { type: Boolean, required: true },
    isStoryteller: { type: Boolean, required: true },
    extractorEnabled: { type: Boolean, required: true },
    extracting: { type: Boolean, required: true },
});

const emit = defineEmits(['save', 'extract']);
</script>
