<template>
    <section class="card">
        <h2>Humanity / Willpower</h2>
        <div v-for="trait in traits" :key="trait.key" class="trait-row">
            <span>{{ trait.display_name }}</span>
            <TraitDots
                :value="traitValue('other', trait.key)"
                :max="trait.maximum || 10"
                @pick="emit('set', 'other', trait, $event)"
            />
        </div>
    </section>
</template>

<script setup>
import TraitDots from './TraitDots.vue';

defineProps({
    traits: { type: Array, required: true },
    traitValue: { type: Function, required: true },
});

const emit = defineEmits(['set']);
</script>
