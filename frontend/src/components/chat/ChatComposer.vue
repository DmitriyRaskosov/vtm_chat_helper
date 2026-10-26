<template>
    <form
        class="composer"
        :class="{
            'composer-ic': !isOoc,
            'composer-ooc': isOoc,
            'composer-your-turn': isMyTurn,
        }"
        @submit.prevent="emit('send')"
    >
        <div v-if="isMyTurn" class="composer-turn-banner">
            <span class="composer-turn-label">ВАШ ХОД</span>
            <button
                type="button"
                class="composer-turn-done"
                :disabled="turnReleasing"
                @click="emit('end-turn')"
            >
                {{ turnReleasing ? '…' : 'Готово' }}
            </button>
        </div>
        <div v-if="myCharacterName" class="composer-as">
            Вы пишете за: <strong>{{ myCharacterName }}</strong>
        </div>
        <div class="composer-body">
            <textarea
                v-model="body"
                maxlength="8000"
                required
                :disabled="!canPost"
                :placeholder="isOoc ? 'Вопрос мастеру, мета-обсуждение…' : placeholder"
                @keydown="onTextareaKeydown"
            ></textarea>
        </div>

        <div class="composer-actions">
            <button
                type="button"
                class="composer-label"
                :class="{
                    'composer-label-ic': !isOoc,
                    'composer-label-ooc': isOoc,
                }"
                :disabled="!canPost"
                @click="isOoc = !isOoc"
            >
                {{ isOoc ? 'Out Of Character' : 'In Character' }}
            </button>

            <ChatToolsToolbar
                :is-storyteller="isStoryteller"
                :my-character-id="myCharacterId"
            />

            <button type="submit" class="composer-send" :disabled="!canPost">Отправить</button>
        </div>
    </form>
</template>

<script setup>
import ChatToolsToolbar from './ChatToolsToolbar.vue';

const props = defineProps({
    canPost: { type: Boolean, required: true },
    placeholder: { type: String, required: true },
    myCharacterName: { type: String, default: null },
    isStoryteller: { type: Boolean, required: true },
    myCharacterId: { type: [Number, String], default: null },
    isMyTurn: { type: Boolean, default: false },
    turnReleasing: { type: Boolean, default: false },
});

const body = defineModel('body', { type: String });
const isOoc = defineModel('isOoc', { type: Boolean, default: false });

const emit = defineEmits(['send', 'end-turn']);

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