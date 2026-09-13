<template>
    <form class="composer" @submit.prevent="emit('send')">
        <textarea
            v-model="body"
            maxlength="4000"
            required
            :disabled="!canPost"
            :placeholder="placeholder"
        ></textarea>
        <button type="submit" :disabled="!canPost">Отправить</button>
    </form>
</template>

<script setup>
defineProps({
    canPost: { type: Boolean, required: true },
    placeholder: { type: String, required: true },
});

const body = defineModel('body', { type: String });

const emit = defineEmits(['send']);
</script>
