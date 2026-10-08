<template>
    <form
        class="composer"
        :class="{ 'composer-ooc': isOoc }"
        @submit.prevent="emit('send')"
    >
        <div class="composer-row">
            <button
                type="button"
                class="composer-label"
                :class="{ 'composer-label-ooc': isOoc }"
                :disabled="!canPost"
                @click="isOoc = !isOoc"
            >
                {{ isOoc ? 'Out Of Character' : 'In Character' }}
            </button>

            <textarea
                v-model="body"
                maxlength="4000"
                required
                :disabled="!canPost"
                :placeholder="isOoc ? 'Вопрос мастеру, мета-обсуждение…' : placeholder"
            ></textarea>

            <button type="submit" :disabled="!canPost">Отправить</button>
        </div>
    </form>
</template>

<script setup>
defineProps({
    canPost: { type: Boolean, required: true },
    placeholder: { type: String, required: true },
});

const body = defineModel('body', { type: String });
const isOoc = defineModel('isOoc', { type: Boolean, default: false });

const emit = defineEmits(['send']);
</script>