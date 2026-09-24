<template>
    <section class="card sheet-header">
        <label>
            Name
            <input v-model="identity.canonical_name" type="text" />
        </label>
        <label>
            Nature
            <input v-model="identity.nature" type="text" />
        </label>
        <label>
            Demeanor
            <input v-model="identity.demeanor" type="text" />
        </label>
        <label>
            Concept
            <input v-model="identity.concept" type="text" />
        </label>
        <label>
            Generation
            <input v-model.number="identity.generation" type="number" min="4" max="15" />
        </label>
        <p class="muted">
            {{ typeLabel(sheet.character_type) }}
            <template v-if="sheet.clan?.name"> · {{ sheet.clan.name }}</template>
        </p>
        <div class="sheet-actions">
            <button type="button" class="secondary" @click="emit('save')">Сохранить шапку</button>
            <span v-if="flash" class="saved-flash">Сохранено!</span>
        </div>
    </section>
</template>

<script setup>
import { typeLabel } from '../../composables/useCharacterSheet';

defineProps({
    identity: { type: Object, required: true },
    sheet: { type: Object, required: true },
    flash: { type: Boolean, required: true },
});

const emit = defineEmits(['save']);
</script>
