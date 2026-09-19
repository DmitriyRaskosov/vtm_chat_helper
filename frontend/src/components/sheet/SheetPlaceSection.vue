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
                <label>
                    Гавань
                    <select v-model="place.haven_entity_id">
                        <option value="">—</option>
                        <option
                            v-if="missingPlaceOption('haven')"
                            :value="String(sheet.haven_entity_id)"
                        >
                            {{ sheet.haven_name }} (скрыта)
                        </option>
                        <option v-for="row in havens" :key="row.id" :value="String(row.id)">
                            {{ row.canonical_name }}
                        </option>
                    </select>
                </label>
            </div>
            <div>
                <p class="muted">Допуск к лору (уровни 0–5)</p>
                <div class="lore-checks">
                    <label v-for="level in loreClearanceLevels" :key="level" class="check-row">
                        <input v-model="place.lore_clearance_levels" type="checkbox" :value="level" />
                        уровень {{ level }}
                    </label>
                </div>
                <div class="sheet-actions">
                    <button
                        v-for="level in loreClearanceLevels"
                        :key="'through-'+level"
                        type="button"
                        class="secondary"
                        @click="place.lore_clearance_levels = setClearanceThrough(level, place.lore_clearance_levels)"
                    >
                        0–{{ level }}
                    </button>
                </div>
            </div>
            <div class="place-create">
                <div>
                    <button class="link" type="button" @click="creating.sect = !creating.sect">Создать секту / фракцию…</button>
                    <div v-if="creating.sect" class="place-create-row">
                        <input v-model="createNames.sect" type="text" maxlength="120" placeholder="Камарилья" />
                        <button type="button" class="secondary" @click="emit('create-entity', 'sect')">Добавить</button>
                    </div>
                </div>
                <div>
                    <button class="link" type="button" @click="creating.haven = !creating.haven">Создать место…</button>
                    <div v-if="creating.haven" class="place-create-row">
                        <input v-model="createNames.haven" type="text" maxlength="120" placeholder="Элизиум" />
                        <button type="button" class="secondary" @click="emit('create-entity', 'haven')">Добавить</button>
                    </div>
                </div>
            </div>
            <div class="sheet-actions">
                <button type="button" @click="emit('save')">Сохранить место в мире</button>
                <span v-if="flash" class="saved-flash">Сохранено!</span>
            </div>
        </template>
        <p v-else class="muted">
            Секта / фракция: {{ sheet.sect_name || '—' }}
            · Клан: {{ sheet.clan?.name || '—' }}
            · Гавань: {{ sheet.haven_name || '—' }}
            · Допуск к лору: {{ loreClearanceLevelsLabel(sheet.lore_clearance_levels) }}
        </p>
    </section>
</template>

<script setup>
import { loreClearanceLevels, loreClearanceLevelsLabel, setClearanceThrough } from '../../composables/useCharacterSheet';

defineProps({
    sheet: { type: Object, required: true },
    place: { type: Object, required: true },
    creating: { type: Object, required: true },
    createNames: { type: Object, required: true },
    sects: { type: Array, required: true },
    clans: { type: Array, required: true },
    havens: { type: Array, required: true },
    isStoryteller: { type: Boolean, required: true },
    missingPlaceOption: { type: Function, required: true },
    flash: { type: Boolean, required: true },
});

const emit = defineEmits(['save', 'create-entity']);
</script>
