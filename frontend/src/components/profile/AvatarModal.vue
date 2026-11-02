<template>
    <div v-if="open" class="avatar-modal" @click.self="open = false">
        <button type="button" class="secondary avatar-modal-close" @click="open = false">Закрыть</button>
        <img v-if="src" :src="src" alt="Аватар персонажа" />
    </div>
</template>

<script setup>
defineProps({
    src: {
        type: String,
        default: '',
    },
});

const open = defineModel({ type: Boolean, default: false });
</script>

<style scoped>
.avatar-modal {
    position: fixed;
    inset: 0;
    z-index: 120;
    background: var(--overlay);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.5rem;
}

.avatar-modal img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    box-shadow: 0 12px 40px var(--shadow);
}

.avatar-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    margin-top: 0;
}
</style>
