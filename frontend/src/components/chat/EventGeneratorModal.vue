<template>
    <div v-if="open" class="diary-backdrop" @click.self="emit('close')">
        <div class="diary-modal card event-generator-modal" role="dialog">
            <header class="chat-panel-header">
                <h2>Описать событие</h2>
                <button type="button" class="chat-panel-close" aria-label="Закрыть" @click="emit('close')">×</button>
            </header>

            <label for="event-action">Действие</label>
            <textarea
                id="event-action"
                v-model="actionDescription"
                rows="4"
                maxlength="4000"
                placeholder="Что произошло в бою или сцене…"
            />

            <div class="event-generator-row">
                <label for="event-attack">Успехи атаки</label>
                <input id="event-attack" v-model.number="attackSuccesses" type="number" min="0" max="100" />
                <label class="toggle">
                    <input v-model="attackBotch" type="checkbox" />
                    Ботч
                </label>
            </div>

            <div class="event-generator-row">
                <label for="event-defense">Успехи защиты</label>
                <input id="event-defense" v-model.number="defenseSuccesses" type="number" min="0" max="100" />
                <label class="toggle">
                    <input v-model="defenseBotch" type="checkbox" />
                    Ботч
                </label>
            </div>

            <p v-if="error" class="error">{{ error }}</p>
            <p v-if="generatedText" class="event-generator-result">{{ generatedText }}</p>

            <div class="sheet-actions">
                <button type="button" :disabled="generating || !actionDescription.trim()" @click="onGenerate">
                    {{ generating ? 'Генерация…' : 'Сгенерировать' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    open: { type: Boolean, required: true },
    generating: { type: Boolean, default: false },
    error: { type: String, default: '' },
    generatedText: { type: String, default: '' },
});

const emit = defineEmits(['close', 'generate']);

const actionDescription = ref('');
const attackSuccesses = ref(0);
const attackBotch = ref(false);
const defenseSuccesses = ref(0);
const defenseBotch = ref(false);

function onGenerate() {
    emit('generate', {
        action_description: actionDescription.value.trim(),
        attack_successes: attackSuccesses.value,
        attack_botch: attackBotch.value,
        defense_successes: defenseSuccesses.value,
        defense_botch: defenseBotch.value,
    });
}

</script>
