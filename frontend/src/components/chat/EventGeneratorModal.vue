<template>
    <div v-if="open" class="diary-backdrop" @click.self="emit('close')">
        <div class="diary-modal card event-generator-modal" role="dialog" :aria-busy="generating">
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
                    Атака: Ботч
                </label>
                <label class="toggle">
                    <input v-model="attackWillpower" type="checkbox" />
                    Атака: Трата Воли
                </label>
            </div>

            <div class="event-generator-row">
                <label for="event-defense">Успехи защиты</label>
                <input id="event-defense" v-model.number="defenseSuccesses" type="number" min="0" max="100" />
                <label class="toggle">
                    <input v-model="defenseBotch" type="checkbox" />
                    Защита: Ботч
                </label>
                <label class="toggle">
                    <input v-model="defenseWillpower" type="checkbox" />
                    Защита: Трата Воли
                </label>
            </div>

            <p v-if="error" class="error">{{ error }}</p>
            <p v-if="generatedText" class="event-generator-result">{{ generatedText }}</p>

            <div class="sheet-actions">
                <button type="button" :disabled="generating || !actionDescription.trim()" @click="onGenerate">
                    <span v-if="generating" class="event-generator-spinner" aria-hidden="true" />
                    {{ generating ? 'Генерация…' : 'Сгенерировать' }}
                </button>
                <button
                    type="button"
                    class="secondary"
                    :disabled="!generatedText"
                    @click="copyResult"
                >
                    {{ copied ? 'Скопировано' : 'Скопировать' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    generating: { type: Boolean, default: false },
    error: { type: String, default: '' },
    generatedText: { type: String, default: '' },
    seed: { type: Object, default: null },
});

const emit = defineEmits(['close', 'generate']);

const actionDescription = ref('');
const attackSuccesses = ref(0);
const attackBotch = ref(false);
const attackWillpower = ref(false);
const defenseSuccesses = ref(0);
const defenseBotch = ref(false);
const defenseWillpower = ref(false);
const copied = ref(false);

watch(
    () => [props.open, props.seed],
    ([isOpen, seed]) => {
        if (!isOpen || !seed) {
            return;
        }

        applySide(seed.attack, 'attack');
        applySide(seed.defense, 'defense');
    },
);

watch(() => props.generatedText, () => {
    copied.value = false;
});

function applySide(facts, side) {
    if (!facts) {
        return;
    }

    if (side === 'attack') {
        attackSuccesses.value = facts.successes;
        attackBotch.value = facts.botch;
        attackWillpower.value = facts.willpower;

        return;
    }

    defenseSuccesses.value = facts.successes;
    defenseBotch.value = facts.botch;
    defenseWillpower.value = facts.willpower;
}

function onGenerate() {
    emit('generate', {
        action_description: actionDescription.value.trim(),
        attack_successes: attackSuccesses.value,
        attack_botch: attackBotch.value,
        attack_willpower: attackWillpower.value,
        defense_successes: defenseSuccesses.value,
        defense_botch: defenseBotch.value,
        defense_willpower: defenseWillpower.value,
    });
}

async function copyResult() {
    if (!props.generatedText) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.generatedText);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}
</script>

<style scoped>
.event-generator-result {
    white-space: pre-wrap;
}

.event-generator-spinner {
    display: inline-block;
    width: 0.8rem;
    height: 0.8rem;
    margin-right: 0.4rem;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    vertical-align: -0.1rem;
    animation: event-generator-spin 0.7s linear infinite;
}

@keyframes event-generator-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
