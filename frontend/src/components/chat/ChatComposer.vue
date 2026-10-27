<template>
    <form class="composer-wrapper" @submit.prevent="emit('send')">
        <div class="composer-top">
            <div class="mode-toggle">
                <button
                    type="button"
                    class="mode-btn"
                    :class="{ active: !isOoc, ic: !isOoc }"
                    :disabled="!canPost"
                    @click="isOoc = false"
                >
                    Реплика
                </button>
                <button
                    type="button"
                    class="mode-btn"
                    :class="{ active: isOoc, ooc: isOoc }"
                    :disabled="!canPost"
                    @click="isOoc = true"
                >
                    Обсуждение
                </button>
            </div>

            <label v-if="isStoryteller" class="npc-selector">
                <span class="npc-selector-label">Писать от лица:</span>
                <select
                    :value="speakerCharacterId ?? ''"
                    :disabled="!canPost || !npcParticipants.length"
                    @change="onSpeakerChange"
                >
                    <option v-if="!npcParticipants.length" value="">Нет NPC</option>
                    <option
                        v-for="row in npcParticipants"
                        :key="row.character_id"
                        :value="row.character_id"
                    >
                        {{ row.character_name }} ({{ characterTypeLabel(row.character_type) }})
                    </option>
                </select>
            </label>
            <p v-else-if="myCharacterName" class="composer-as">
                Пишете за: <strong>{{ myCharacterName }}</strong>
            </p>

            <ChatToolsToolbar
                :is-storyteller="isStoryteller"
                :my-character-id="myCharacterId"
            />

            <label class="toggle composer-deleted-toggle">
                <input v-model="showDeleted" type="checkbox" />
                Удалённые
            </label>
        </div>

        <div class="composer-input-row">
            <textarea
                v-model="body"
                class="input-box"
                maxlength="8000"
                required
                :disabled="!canPost"
                :placeholder="fieldPlaceholder"
                @keydown="onTextareaKeydown"
            ></textarea>
            <button type="submit" class="btn-send" :disabled="!canPost">Отправить</button>
        </div>
    </form>
</template>

<script setup>
import { computed } from 'vue';
import ChatToolsToolbar from './ChatToolsToolbar.vue';
import { characterTypeLabel } from '../../utils/labels';

const props = defineProps({
    canPost: { type: Boolean, required: true },
    placeholder: { type: String, required: true },
    myCharacterName: { type: String, default: null },
    isStoryteller: { type: Boolean, required: true },
    myCharacterId: { type: [Number, String], default: null },
    participants: { type: Array, default: () => [] },
});

const body = defineModel('body', { type: String });
const isOoc = defineModel('isOoc', { type: Boolean, default: false });
const showDeleted = defineModel('showDeleted', { type: Boolean, default: false });
const speakerCharacterId = defineModel('speakerCharacterId');

const emit = defineEmits(['send']);

const npcParticipants = computed(() => props.participants.filter(
    (row) => row.character_type === 'npc',
));

const fieldPlaceholder = computed(() => {
    if (!props.canPost) {
        return props.placeholder;
    }

    return isOoc.value
        ? 'Вопрос мастеру, мета-обсуждение...'
        : 'Ваши слова или действия...';
});

function onSpeakerChange(event) {
    const value = Number(event.target.value);
    speakerCharacterId.value = Number.isFinite(value) && value > 0 ? value : null;
}

function onTextareaKeydown(event) {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    event.preventDefault();

    if (!props.canPost) {
        return;
    }

    const trimmed = (body.value ?? '').trim();
    if (!trimmed) {
        return;
    }

    emit('send');
}
</script>
