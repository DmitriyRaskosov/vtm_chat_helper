<template>
    <div class="dots">
        <button
            v-for="n in max"
            :key="n"
            type="button"
            class="dot"
            :class="{ filled: value >= n }"
            @click="emit('pick', n)"
        />
    </div>
</template>

<script setup>
defineProps({
    value: { type: Number, required: true },
    max: { type: Number, default: 5 },
});

const emit = defineEmits(['pick']);
</script>
