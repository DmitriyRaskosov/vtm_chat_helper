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
            {{ characterTypeLabel(sheet.character_type) }}
            <template v-if="sheet.clan?.name"> · {{ sheet.clan.name }}</template>
        </p>
        <label>
            Описание
            <textarea
                v-model="roleDetails.description"
                rows="4"
                maxlength="10000"
                :readonly="!isStoryteller"
            />
        </label>
        <label v-if="isStoryteller">
            Инвентарь
            <textarea v-model="roleDetails.inventory" rows="4" maxlength="10000" />
        </label>
        <div v-if="isStoryteller" class="sheet-actions">
            <button type="button" class="secondary" @click="emit('save-role')">Сохранить описание и инвентарь</button>
            <span v-if="roleFlash" class="saved-flash">Сохранено!</span>
        </div>
        <div class="sheet-actions">
            <button type="button" class="secondary" @click="emit('save')">Сохранить шапку</button>
            <span v-if="flash" class="saved-flash">Сохранено!</span>
        </div>
    </section>
</template>

<script setup>
import { characterTypeLabel } from '../../utils/labels';

defineProps({
    identity: { type: Object, required: true },
    roleDetails: { type: Object, required: true },
    sheet: { type: Object, required: true },
    flash: { type: Boolean, required: true },
    roleFlash: { type: Boolean, required: true },
    isStoryteller: { type: Boolean, required: true },
});

const emit = defineEmits(['save', 'save-role']);
</script>
