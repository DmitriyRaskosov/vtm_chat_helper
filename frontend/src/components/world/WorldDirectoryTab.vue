<template>
    <section id="world-directory-form" class="card">
            <h2>Справочник</h2>
            <p class="muted">Секты / фракции, кланы, места, предметы и идеи хроники. Персонажи остаются на отдельном экране.</p>
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
                <label v-if="form.entity_type === 'faction'">
                    Родительская секта / фракция
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
                <label v-if="hasSectFactionField(form.entity_type)">
                    Секта / фракция
                    <select v-model="form.sect_faction_id">
                        <option value="">— не указана —</option>
                        <option
                            v-for="row in factions"
                            :key="'sect-'+row.id"
                            :value="String(row.id)"
                        >
                            {{ row.canonical_name }}
                        </option>
                    </select>
                </label>
                <label v-if="form.entity_type === 'location'" class="world-desc">
                    Контролируют
                    <p v-if="!factions.length" class="muted">Сначала добавьте секту / фракцию.</p>
                    <div v-else class="lore-checks">
                        <label v-for="row in factions" :key="'ctrl-'+row.id" class="check-row">
                            <input v-model="form.linked_faction_ids" type="checkbox" :value="row.id" />
                            {{ row.canonical_name }}
                        </label>
                    </div>
                </label>
                <label v-if="form.entity_type === 'item'" class="world-desc">
                    Владеют
                    <p v-if="!factions.length" class="muted">Сначала добавьте секту / фракцию.</p>
                    <div v-else class="lore-checks">
                        <label v-for="row in factions" :key="'own-'+row.id" class="check-row">
                            <input v-model="form.linked_faction_ids" type="checkbox" :value="row.id" />
                            {{ row.canonical_name }}
                        </label>
                    </div>
                </label>
                <label v-if="form.entity_type === 'concept'" class="world-desc">
                    Часть чего / привязано к
                    <p v-if="!directoryEntityOptions.length" class="muted">Сначала добавьте другие сущности справочника.</p>
                    <div v-else class="lore-checks">
                        <label v-for="row in directoryEntityOptions" :key="'part-'+row.id" class="check-row">
                            <input v-model="form.part_of_target_ids" type="checkbox" :value="row.id" />
                            {{ row.canonical_name }}
                            <span class="muted"> · {{ typeLabel(row.entity_type) }}</span>
                        </label>
                    </div>
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

        <section class="card">
            <h2>Секты / фракции</h2>
            <p v-if="!factionTree.length" class="muted">Пока нет.</p>
            <ul v-else class="character-tree">
                <li v-for="entry in factionTree" :key="entry.faction.id">
                    <div class="world-list-row">
                        <div>
                            <div class="world-list-row-title">
                                <button
                                    class="link"
                                    type="button"
                                    :class="{ 'nav-current': form.id === entry.faction.id }"
                                    @click="emit('edit', entry.faction)"
                                >
                                    {{ entry.faction.canonical_name }}
                                </button>
                                <span v-if="parentFactionName(entry.faction)" class="muted"> · в {{ parentFactionName(entry.faction) }}</span>
                            </div>
                            <p v-if="entry.faction.short_description" class="muted world-list-row-desc">{{ entry.faction.short_description }}</p>
                        </div>
                        <button class="link world-list-row-action" type="button" @click="emit('archive', entry.faction.id)">Скрыть</button>
                    </div>
                    <div v-for="group in entry.groups" :key="group.type" class="world-faction-nested">
                        <details>
                            <summary>{{ group.title }}</summary>
                            <ul class="character-tree">
                                <li v-for="row in group.rows" :key="row.id">
                                    <div class="world-list-row">
                                        <div>
                                            <div class="world-list-row-title">
                                                <button
                                                    class="link"
                                                    type="button"
                                                    :class="{ 'nav-current': form.id === row.id }"
                                                    @click="emit('edit', row)"
                                                >
                                                    {{ row.canonical_name }}
                                                </button>
                                            </div>
                                            <p v-if="row.short_description" class="muted world-list-row-desc">{{ row.short_description }}</p>
                                        </div>
                                        <button class="link world-list-row-action" type="button" @click="emit('archive', row.id)">Скрыть</button>
                                    </div>
                                    <div v-if="conceptsLinkedTo(row.id).length" class="world-faction-nested">
                                        <details>
                                            <summary>Идеи</summary>
                                            <ul class="character-tree">
                                                <li v-for="concept in conceptsLinkedTo(row.id)" :key="'concept-'+concept.id">
                                                    <div class="world-list-row">
                                                        <div>
                                                            <div class="world-list-row-title">
                                                                <button
                                                                    class="link"
                                                                    type="button"
                                                                    :class="{ 'nav-current': form.id === concept.id }"
                                                                    @click="emit('edit', concept)"
                                                                >
                                                                    {{ concept.canonical_name }}
                                                                </button>
                                                            </div>
                                                            <p v-if="concept.short_description" class="muted world-list-row-desc">{{ concept.short_description }}</p>
                                                        </div>
                                                        <button class="link world-list-row-action" type="button" @click="emit('archive', concept.id)">Скрыть</button>
                                                    </div>
                                                </li>
                                            </ul>
                                        </details>
                                    </div>
                                </li>
                            </ul>
                        </details>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="unaffiliatedSectMembers.length" class="card">
            <h2>Без секты</h2>
            <ul class="character-tree">
                <li v-for="row in unaffiliatedSectMembers" :key="row.id">
                    <div class="world-list-row">
                        <div>
                            <div class="world-list-row-title">
                                <button
                                    class="link"
                                    type="button"
                                    :class="{ 'nav-current': form.id === row.id }"
                                    @click="emit('edit', row)"
                                >
                                    {{ row.canonical_name }}
                                </button>
                                <span class="muted"> · {{ typeLabel(row.entity_type) }}</span>
                            </div>
                            <p v-if="row.short_description" class="muted world-list-row-desc">{{ row.short_description }}</p>
                        </div>
                        <button class="link world-list-row-action" type="button" @click="emit('archive', row.id)">Скрыть</button>
                    </div>
                </li>
            </ul>
        </section>

        <template v-for="group in flatDirectoryGroups" :key="group.type">
            <details v-if="group.collapsible" class="card world-directory-details">
                <summary><h2>{{ group.title }}</h2></summary>
                <p v-if="!group.rows.length" class="muted">Пока нет.</p>
                <ul v-else class="character-tree">
                    <li v-for="row in group.rows" :key="row.id">
                        <div class="world-list-row">
                            <div>
                                <div class="world-list-row-title">
                                    <button
                                        class="link"
                                        type="button"
                                        :class="{ 'nav-current': form.id === row.id }"
                                        @click="emit('edit', row)"
                                    >
                                        {{ row.canonical_name }}
                                    </button>
                                </div>
                                <p v-if="row.short_description" class="muted world-list-row-desc">{{ row.short_description }}</p>
                            </div>
                            <button class="link world-list-row-action" type="button" @click="emit('archive', row.id)">Скрыть</button>
                        </div>
                    </li>
                </ul>
            </details>
            <section v-else class="card">
                <h2>{{ group.title }}</h2>
                <p v-if="!group.rows.length" class="muted">Пока нет.</p>
                <ul v-else class="character-tree">
                    <li v-for="row in group.rows" :key="row.id">
                        <div class="world-list-row">
                            <div>
                                <div class="world-list-row-title">
                                    <button
                                        class="link"
                                        type="button"
                                        :class="{ 'nav-current': form.id === row.id }"
                                        @click="emit('edit', row)"
                                    >
                                        {{ row.canonical_name }}
                                    </button>
                                </div>
                                <p v-if="row.short_description" class="muted world-list-row-desc">{{ row.short_description }}</p>
                            </div>
                            <button class="link world-list-row-action" type="button" @click="emit('archive', row.id)">Скрыть</button>
                        </div>
                    </li>
                </ul>
            </section>
        </template>

        <section class="card">
            <h2>Идеи</h2>
            <p v-if="!flatConceptTree.length" class="muted">Пока нет.</p>
            <ul v-else class="character-tree">
                <li
                    v-for="row in flatConceptTree"
                    :key="row.entity.id"
                    class="world-concept-indent"
                    :style="{ marginLeft: (row.depth * 1) + 'rem' }"
                >
                    <div class="world-list-row">
                        <div>
                            <div class="world-list-row-title">
                                <button
                                    class="link"
                                    type="button"
                                    :class="{ 'nav-current': form.id === row.entity.id }"
                                    @click="emit('edit', row.entity)"
                                >
                                    {{ row.entity.canonical_name }}
                                </button>
                            </div>
                            <p v-if="row.entity.short_description" class="muted world-list-row-desc">{{ row.entity.short_description }}</p>
                        </div>
                        <button class="link world-list-row-action" type="button" @click="emit('archive', row.entity.id)">Скрыть</button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="card">
            <h2>Скрытые</h2>
            <p v-if="!archived.length" class="muted">Скрытых сущностей нет.</p>
            <ul v-else class="character-tree">
                <li v-for="row in archived" :key="row.id">
                    <div class="world-list-row">
                        <div class="world-list-row-title">
                            <strong>{{ row.canonical_name }}</strong>
                            <span class="muted"> · {{ typeLabel(row.entity_type) }}</span>
                        </div>
                        <button class="link world-list-row-action" type="button" @click="emit('restore', row.id)">Вернуть</button>
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
import { entityTypes, hasSectFactionField, typeLabel } from '../../composables/useWorldEntities';
import WorldPoliticsSection from './WorldPoliticsSection.vue';

const props = defineProps({
    form: { type: Object, required: true },
    editing: { type: Boolean, required: true },
    factionTree: { type: Array, required: true },
    unaffiliatedSectMembers: { type: Array, required: true },
    flatDirectoryGroups: { type: Array, required: true },
    flatConceptTree: { type: Array, required: true },
    directoryEntityOptions: { type: Array, required: true },
    conceptsLinkedTo: { type: Function, required: true },
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
