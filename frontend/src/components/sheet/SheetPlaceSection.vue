<template>
    <section class="card">
        <h2>Место в мире</h2>
        <template v-if="isStoryteller">
            <div class="character-create-grid">
                <label>
                    Секта / фракция
                    <select v-model="place.sect_id">
                        <option value="">—</option>
                        <option v-if="missingPlaceOption('sect')" :value="String(sheet.sect_id)">
                            {{ sheet.sect?.name || '—' }}
                        </option>
                        <option v-for="row in sects" :key="row.id" :value="String(row.id)">
                            {{ row.name }}
                        </option>
                    </select>
                </label>
                <label>
                    Клан
                    <select v-model="place.clan_id">
                        <option value="">—</option>
                        <option
                            v-if="missingPlaceOption('clan')"
                            :value="String(sheet.clan_id)"
                        >
                            {{ sheet.clan?.name }} (скрыт)
                        </option>
                        <option v-for="row in clans" :key="row.id" :value="String(row.id)">
                            {{ row.name }}
                        </option>
                    </select>
                </label>
            </div>
            <div class="sheet-actions">
                <button type="button" @click="emit('save')">Сохранить место в мире</button>
                <span v-if="flash" class="saved-flash">Сохранено!</span>
            </div>
        </template>
        <p v-else class="muted">
            Секта / фракция: {{ sheet.sect?.name || '—' }}
            · Клан: {{ sheet.clan?.name || '—' }}
        </p>
    </section>
</template>

<script setup>

defineProps({
    sheet: { type: Object, required: true },
    place: { type: Object, required: true },
    sects: { type: Array, required: true },
    clans: { type: Array, required: true },
    isStoryteller: { type: Boolean, required: true },
    missingPlaceOption: { type: Function, required: true },
    flash: { type: Boolean, required: true },
});

const emit = defineEmits(['save']);
</script>
