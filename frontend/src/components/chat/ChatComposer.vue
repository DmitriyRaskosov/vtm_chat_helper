<template>
    <form
        class="composer"
        :class="{ 'composer-ooc': isOoc }"
        @submit.prevent="emit('send')"
    >
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
            ></textarea>
        </div>

        <div class="composer-actions">
            <button
                type="button"
                class="composer-label"
                :class="{ 'composer-label-ooc': isOoc }"
                :disabled="!canPost"
                @click="isOoc = !isOoc"
            >
                {{ isOoc ? 'Out Of Character' : 'In Character' }}
            </button>

            <button
                type="button"
                class="composer-dice"
                :disabled="!canPost"
                title="Бросок кубиков"
                @click="emit('open-dice')"
            >🎲</button>

            <button type="submit" class="composer-send" :disabled="!canPost">Отправить</button>
        </div>
    </form>
</template>

<script setup>
defineProps({
    canPost: { type: Boolean, required: true },
    placeholder: { type: String, required: true },
    myCharacterName: { type: String, default: null },
});

const body = defineModel('body', { type: String });
const isOoc = defineModel('isOoc', { type: Boolean, default: false });

const emit = defineEmits(['send', 'open-dice']);
</script>