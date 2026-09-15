<template>
    <section class="card">
            <h2>Справочник</h2>
            <p class="muted">Группы, места, предметы и идеи хроники. Персонажи остаются на отдельном экране.</p>
            <div class="sheet-actions">
                <button v-if="editing" type="button" class="secondary" @click="emit('new')">Новая</button>
            </div>
            <div class="character-create-grid">
                <label>
                    Имя
                    <input v-model="form.canonical_name" type="text" maxlength="120" />
                </label>
                <label>
                    Тип
                    <select v-model="form.entity_type" :disabled="editing">
                        <option v-for="row in entityTypes" :key="row.value" :value="row.value">{{ row.label }}</option>
                    </select>
                </label>
                <label>
                    Подтип
                    <select v-model="form.subtype">
                        <option v-for="row in subtypesFor(form.entity_type)" :key="row.value" :value="row.value">
                            {{ row.label }}
                        </option>
                    </select>
                </label>
                <label v-if="form.entity_type === 'faction'">
                    Входит в группу
                    <select v-model="form.parent_faction_id">
                        <option value="">— ни в какую —</option>
                        <option
                            v-for="row in parentFactionOptions"
                            :key="'parent-'+row.id"
                            :value="String(row.id)"
                        >
                            {{ row.canonical_name }}
                        </option>
                    </select>
                </label>
                <label class="world-desc">
                    Кратко
                    <input v-model="form.short_description" type="text" maxlength="4000" />
                </label>
                <label class="world-desc">
                    Ещё имена
                    <div class="alias-row">
                        <input
                            v-model="aliasDraft"
                            type="text"
                            maxlength="120"
                            placeholder="Gangrel"
                            @keydown.enter.prevent="addAlias"
                        />
                        <button type="button" class="secondary" @click="addAlias">Добавить</button>
                    </div>
                    <ul v-if="form.aliases.length" class="character-tree">
                        <li v-for="(alias, index) in form.aliases" :key="'aka-'+alias+index">
                            <div class="character-row">
                                <span>{{ alias }}</span>
                                <button class="link" type="button" @click="form.aliases.splice(index, 1)">Убрать</button>
                            </div>
                        </li>
                    </ul>
                </label>
            </div>
            <button
                type="button"
                :disabled="saving || !form.canonical_name.trim()"
                @click="emit(editing ? 'save' : 'create')"
            >
                {{ editing ? 'Сохранить' : 'Создать' }}
            </button>
        </section>

        <section v-for="group in directoryGroups" :key="group.type" class="card">
            <h2>{{ group.title }}</h2>
            <p v-if="!group.rows.length" class="muted">Пока нет.</p>
            <ul v-else class="character-tree">
                <li v-for="row in group.rows" :key="row.id">
                    <div class="character-row">
                        <button
                            class="link"
                            type="button"
                            :class="{ 'nav-current': form.id === row.id }"
                            @click="emit('edit', row)"
                        >
                            {{ row.canonical_name }}
                        </button>
                        <span v-if="subtypeLabel(row)" class="muted"> · {{ subtypeLabel(row) }}</span>
                        <span v-if="parentFactionName(row)" class="muted"> · в {{ parentFactionName(row) }}</span>
                        <span v-if="row.short_description" class="muted"> — {{ row.short_description }}</span>
                        <button class="link" type="button" @click="emit('archive', row.id)">Скрыть</button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="card">
            <h2>Скрытые</h2>
            <p v-if="!archived.length" class="muted">Скрытых сущностей нет.</p>
            <ul v-else class="character-tree">
                <li v-for="row in archived" :key="row.id">
                    <div class="character-row">
                        <strong>{{ row.canonical_name }}</strong>
                        <span class="muted">
                            · {{ typeLabel(row.entity_type) }}<template v-if="subtypeLabel(row)"> · {{ subtypeLabel(row) }}</template>
                        </span>
                        <button class="link" type="button" @click="emit('restore', row.id)">Вернуть</button>
                    </div>
                </li>
            </ul>
        </section>

        <WorldPoliticsSection
            :politics="politics"
            :factions="factions"
            :relations="relations"
            :saving-politics="savingPolitics"
            @add="emit('add-politics')"
            @end="emit('end-politics', $event)"
        />
</template>

<script setup>
import { computed, ref } from 'vue';
import { entityTypes, subtypeLabel, subtypesFor, typeLabel } from '../../composables/useWorldEntities';
import WorldPoliticsSection from './WorldPoliticsSection.vue';

const props = defineProps({
    form: { type: Object, required: true },
    editing: { type: Boolean, required: true },
    directoryGroups: { type: Array, required: true },
    archived: { type: Array, required: true },
    saving: { type: Boolean, required: true },
    factions: { type: Array, required: true },
    politics: { type: Object, required: true },
    relations: { type: Array, required: true },
    savingPolitics: { type: Boolean, required: true },
});

const emit = defineEmits(['create', 'save', 'new', 'edit', 'archive', 'restore', 'add-politics', 'end-politics']);
const aliasDraft = ref('');

const parentFactionOptions = computed(() => (
    props.factions.filter((row) => row.id !== props.form.id)
));

function parentFactionName(row) {
    if (!row.parent_faction_id) {
        return '';
    }
    return props.factions.find((item) => item.id === row.parent_faction_id)?.canonical_name ?? '';
}

function addAlias() {
    const value = aliasDraft.value.trim();
    if (!value) {
        return;
    }
    const list = props.form.aliases ?? [];
    if (list.some((item) => item.toLowerCase() === value.toLowerCase())) {
        aliasDraft.value = '';
        return;
    }
    props.form.aliases = [...list, value];
    aliasDraft.value = '';
}
</script>
