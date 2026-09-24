<template>
    <section class="card">
            <h2>Статьи</h2>
            <p class="muted">
                Канон хроники. Copilot видит статью у NPC, если уровень статьи входит в набор допуска персонажа;
                ниже — редкие исключения grant/deny. Ситуационные факты — только через grant.
            </p>
            <div class="sheet-actions">
                <button type="button" @click="newLore">Новая статья</button>
            </div>
            <p v-if="!loreList.length" class="muted">Пока нет статей.</p>
            <ul v-else class="character-tree">
                <li v-for="row in loreList" :key="row.id">
                    <div class="world-list-row">
                        <div class="world-list-row-title">
                            <button
                                class="link"
                                type="button"
                                :class="{ 'nav-current': loreForm.id === row.id }"
                                @click="selectLore(row.id)"
                            >
                                {{ row.title }}
                            </button>
                            <span class="muted">
                                · {{ loreKindLabel(row.kind) }}
                                · {{ loreClassificationLabel(row.classification) }}
                                <template v-if="row.situational"> · ситуационная</template>
                            </span>
                        </div>
                        <button class="link world-list-row-action" type="button" @click="archiveLore(row.id)">Скрыть</button>
                    </div>
                </li>
            </ul>
        </section>

        <section id="world-lore-form" class="card">
            <h2>{{ loreForm.id ? 'Статья' : 'Новая статья' }}</h2>
            <label>
                Название
                <input v-model="loreForm.title" type="text" maxlength="200" />
            </label>
            <label>
                Текст
                <textarea v-model="loreForm.canonical_text" class="bio-full" rows="8" maxlength="50000" />
                <span v-if="loreForm.id" class="muted">
                    {{ canonicalTextLength }} символов
                    <template v-if="loreLimits.article_max_chars">
                        · разбор за раз ≤ {{ loreLimits.article_max_chars }}
                    </template>
                </span>
            </label>
            <label>
                Уровень
                <select v-model="loreForm.classification">
                    <option v-for="row in loreAccessLevels" :key="row.value" :value="row.value">{{ row.label }}</option>
                </select>
            </label>
            <label class="check-row">
                <input v-model="loreForm.situational" type="checkbox" />
                Ситуационная
            </label>
            <details>
                <summary>Ещё</summary>
                <label>
                    Вид
                    <select v-model="loreForm.kind">
                        <option v-for="row in loreKinds" :key="row.value" :value="row.value">{{ row.label }}</option>
                    </select>
                </label>
            </details>
            <details>
                <summary>О ком статья</summary>
                <p class="muted">Сущности мира и персонажи, к которым статья привязана. Необязательно.</p>
                <input v-model="aboutSearch" type="search" placeholder="Поиск по имени" />
                <div class="lore-filter-chips">
                    <button
                        v-for="chip in aboutFilterChips"
                        :key="chip.value"
                        type="button"
                        class="secondary"
                        :class="{ current: aboutFilter === chip.value }"
                        @click="aboutFilter = chip.value"
                    >
                        {{ chip.label }}
                    </button>
                </div>
                <div class="lore-checks">
                    <label v-for="row in filteredAboutOptions" :key="'about-'+row.id" class="check-row">
                        <input v-model="loreForm.entity_ids" type="checkbox" :value="row.id" />
                        {{ row.canonical_name }}
                        <span class="muted"> · {{ aboutTypeLabel(row) }}<template v-if="row.hidden"> · скрыт</template></span>
                    </label>
                    <p v-if="!aboutOptions.length" class="muted">Сначала добавьте сущности или персонажей в справочник.</p>
                    <p v-else-if="!filteredAboutOptions.length" class="muted">Ничего не найдено по фильтру.</p>
                </div>
            </details>
            <details :open="loreForm.situational">
                <summary>Исключения</summary>
                <p v-if="loreForm.situational" class="muted">
                    Шкала не действует. Отметьте, кто сейчас знает этот факт.
                </p>
                <p v-else class="muted">
                    По умолчанию персонаж видит статью, если её уровень входит в его набор допуска.
                    Здесь — редкие исключения: знает сверх допуска или не знает вопреки допуску.
                </p>
                <h4>Знает сверх допуска</h4>
                <div class="lore-checks">
                    <label v-for="row in characterOptions" :key="'grant-'+row.id" class="check-row">
                        <input
                            v-model="loreForm.granted_character_ids"
                            type="checkbox"
                            :value="row.id"
                            @change="exceptionToggle('grant', row.id, $event.target.checked)"
                        />
                        {{ row.canonical_name }}
                        <span class="muted"> · {{ characterTypeLabel(row.character_type) }}<template v-if="row.hidden"> · скрыт</template></span>
                    </label>
                    <p v-if="!characterOptions.length" class="muted">Пока нет персонажей.</p>
                </div>
                <h4>Не знает вопреки допуску</h4>
                <div class="lore-checks">
                    <label v-for="row in characterOptions" :key="'deny-'+row.id" class="check-row">
                        <input
                            v-model="loreForm.denied_character_ids"
                            type="checkbox"
                            :value="row.id"
                            @change="exceptionToggle('deny', row.id, $event.target.checked)"
                        />
                        {{ row.canonical_name }}
                        <span class="muted"> · {{ characterTypeLabel(row.character_type) }}<template v-if="row.hidden"> · скрыт</template></span>
                    </label>
                </div>
            </details>
            <div class="sheet-actions">
                <button type="button" :disabled="savingLore || !loreForm.title.trim() || !loreForm.canonical_text.trim()" @click="saveLore">
                    Сохранить статью
                </button>
                <button
                    v-if="extractorEnabled && loreForm.id && canExtractNext"
                    type="button"
                    class="secondary"
                    :disabled="extracting || savingLore"
                    @click="runExtraction()"
                >
                    {{ extracting ? 'Разбор…' : extractButtonLabel }}
                </button>
                <button
                    v-if="extractorEnabled && loreForm.id"
                    type="button"
                    class="secondary"
                    :disabled="extracting || savingLore"
                    @click="runReparse"
                >
                    {{ extracting ? 'Разбор…' : 'Разобрать заново' }}
                </button>
                <span v-if="flashLore" class="saved-flash">Сохранено!</span>
            </div>
            <p v-if="extractorEnabled && loreForm.id" class="muted">
                Кандидаты графа появятся на вкладке «Разбор».
                <template v-if="loreWindow && loreWindow.window_count > 1">
                    Следующее окно: символы {{ loreWindow.from_char_offset + 1 }}–{{ loreWindow.to_char_offset }}
                    из {{ loreWindow.total_chars }} ({{ loreWindow.window_index }}/{{ loreWindow.window_count }}).
                </template>
            </p>
        </section>

        <section class="card">
            <h2>Скрытые статьи</h2>
            <p v-if="!loreArchived.length" class="muted">Скрытых статей нет.</p>
            <ul v-else class="character-tree">
                <li v-for="row in loreArchived" :key="row.id">
                    <div class="world-list-row">
                        <div class="world-list-row-title">
                            <strong>{{ row.title }}</strong>
                            <span class="muted"> · {{ loreKindLabel(row.kind) }}</span>
                        </div>
                        <button class="link world-list-row-action" type="button" @click="restoreLore(row.id)">Вернуть</button>
                    </div>
                </li>
            </ul>
        </section>
</template>

<script setup>
import { computed } from 'vue';
import {
    aboutFilterChips,
    aboutTypeLabel,
    characterTypeLabel,
    loreAccessLevels,
    loreClassificationLabel,
    loreKindLabel,
    loreKinds,
} from '../../composables/useWorldLore';

const props = defineProps({
    loreList: { type: Array, required: true },
    loreArchived: { type: Array, required: true },
    loreForm: { type: Object, required: true },
    aboutOptions: { type: Array, required: true },
    filteredAboutOptions: { type: Array, required: true },
    characterOptions: { type: Array, required: true },
    savingLore: { type: Boolean, required: true },
    flashLore: { type: Boolean, required: true },
    extractorEnabled: { type: Boolean, required: true },
    extracting: { type: Boolean, required: true },
    loreLimits: { type: Object, required: true },
    loreWindow: { type: Object, default: null },
    newLore: { type: Function, required: true },
    selectLore: { type: Function, required: true },
    saveLore: { type: Function, required: true },
    archiveLore: { type: Function, required: true },
    restoreLore: { type: Function, required: true },
    runExtraction: { type: Function, required: true },
    runReparse: { type: Function, required: true },
    exceptionToggle: { type: Function, required: true },
});

const aboutSearch = defineModel('aboutSearch', { type: String, required: true });
const aboutFilter = defineModel('aboutFilter', { type: String, required: true });

const canonicalTextLength = computed(() => [...(props.loreForm.canonical_text ?? '')].length);

const canExtractNext = computed(() => props.loreWindow?.can_extract !== false);

const extractButtonLabel = computed(() => {
    const window = props.loreWindow;
    if (window && window.window_count > 1 && window.to_char_offset > window.from_char_offset) {
        return `Разобрать (${window.from_char_offset + 1}–${window.to_char_offset} из ${window.total_chars})`;
    }
    return 'Разобрать';
});
</script>
