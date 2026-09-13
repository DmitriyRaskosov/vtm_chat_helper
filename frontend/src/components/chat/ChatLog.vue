<template>
    <div ref="logEl" class="chat-log" aria-live="polite">
        <article
            v-for="message in messages"
            :key="message.id"
            class="msg"
            :class="{ mine: message.mine }"
        >
            <div class="meta">{{ message.author }} · {{ message.created_at }}</div>
            <div>{{ message.body }}</div>
        </article>
        <p v-if="!messages.length" class="muted">Пока пусто. Напишите первое сообщение.</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    messages: { type: Array, required: true },
});

const logEl = ref(null);

defineExpose({
    scrollToBottom() {
        if (logEl.value) {
            logEl.value.scrollTop = logEl.value.scrollHeight;
        }
    },
});
</script>
