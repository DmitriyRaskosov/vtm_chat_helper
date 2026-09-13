<template>
    <section class="card">
        <h2>{{ title }}</h2>
        <div class="sheet-cols">
            <div v-for="group in groups" :key="group.key">
                <h3>{{ group.title }}</h3>
                <div v-for="trait in group.traits" :key="trait.key" class="trait-row">
                    <span>{{ trait.display_name }}</span>
                    <TraitDots
                        :value="traitValue(category, trait.key)"
                        :max="5"
                        @pick="emit('set', category, trait, $event)"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import TraitDots from './TraitDots.vue';

defineProps({
    title: { type: String, required: true },
    category: { type: String, required: true },
    groups: { type: Array, required: true },
    traitValue: { type: Function, required: true },
});

const emit = defineEmits(['set']);
</script>
