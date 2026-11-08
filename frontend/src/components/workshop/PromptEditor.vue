<template>
    <div class="workshop-prompts">
        <div class="workshop-prompts-toolbar">
            <button type="button" class="secondary" @click="contextMapOpen = true">
                Карта контекста
            </button>
        </div>

        <p v-if="loading" class="muted">Загрузка…</p>
        <p v-else-if="error" class="error">{{ error }}</p>

        <div v-else class="workshop-prompts-layout">
            <aside class="workshop-prompts-list" aria-label="Список промптов">
                <div v-for="group in groupedPrompts" :key="group.name" class="workshop-prompts-group">
                    <h2 class="workshop-prompts-group-title">{{ group.name }}</h2>
                    <button
                        v-for="row in group.items"
                        :key="row.key"
                        type="button"
                        class="workshop-prompts-item"
                        :class="{ active: row.key === selectedKey }"
                        @click="selectPrompt(row)"
                    >
                        <span
                            v-if="row.is_overridden"
                            class="workshop-prompts-override-dot"
                            title="Изменён вручную"
                            aria-hidden="true"
                        />
                        <span class="workshop-prompts-item-title">{{ row.title }}</span>
                        <span v-if="row.is_overridden" class="workshop-prompts-override-label">изм.</span>
                    </button>
                </div>
            </aside>

            <section v-if="selected" class="workshop-prompts-editor">
                <header class="workshop-prompts-editor-header">
                    <h2 class="workshop-prompts-editor-title">
                        {{ selected.title }}
                        <button
                            type="button"
                            class="workshop-prompts-help-btn"
                            :aria-expanded="helpOpen"
                            title="Описание и переменные"
                            @click="helpOpen = !helpOpen"
                        >
                            ?
                        </button>
                    </h2>
                    <p class="muted workshop-prompts-key">{{ selected.key }}</p>
                </header>

                <div v-if="helpOpen" class="card workshop-prompts-help">
                    <p>{{ selected.description }}</p>
                    <p v-if="!selected.variables.length" class="muted">Переменных нет.</p>
                    <ul v-else class="workshop-prompts-variables">
                        <li v-for="name in selected.variables" :key="name">
                            <span class="workshop-prompts-variable-badge">{{ formatVariable(name) }}</span>
                        </li>
                    </ul>
                </div>

                <p v-if="selected.is_json_required" class="workshop-prompts-json-warning">
                    Ответ модели должен быть валидным JSON. Не удаляйте из текста требования к структуре ответа.
                </p>

                <label class="workshop-prompts-textarea-label">
                    Текст промпта
                    <textarea
                        v-model="draft"
                        class="workshop-prompts-textarea"
                        rows="22"
                        spellcheck="false"
                    />
                </label>

                <div class="sheet-actions">
                    <button
                        type="button"
                        :disabled="saving || !canSave"
                        @click="onSave"
                    >
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                    <button
                        type="button"
                        class="secondary"
                        :disabled="saving || !selected.is_overridden"
                        @click="onReset"
                    >
                        {{ saving ? '…' : 'Сбросить' }}
                    </button>
                </div>
            </section>

            <p v-else class="muted workshop-prompts-placeholder">Выберите промпт слева.</p>
        </div>

        <ContextMapModal v-model="contextMapOpen" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import ContextMapModal from './ContextMapModal.vue';
import { usePrompts } from '../../composables/usePrompts';

const {
    prompts,
    loading,
    saving,
    error,
    load,
    save,
    reset,
} = usePrompts();

const selectedKey = ref(null);
const draft = ref('');
const helpOpen = ref(false);
const contextMapOpen = ref(false);

const selected = computed(() => prompts.value.find((row) => row.key === selectedKey.value) ?? null);

const groupedPrompts = computed(() => {
    const order = [];
    const map = new Map();

    for (const row of prompts.value) {
        if (!map.has(row.group)) {
            map.set(row.group, []);
            order.push(row.group);
        }
        map.get(row.group).push(row);
    }

    return order.map((name) => ({
        name,
        items: map.get(name) ?? [],
    }));
});

const canSave = computed(() => {
    if (!selected.value) {
        return false;
    }

    return draft.value.trim() !== '' && draft.value !== selected.value.content;
});

function formatVariable(name) {
    return `{{${name}}}`;
}

function selectPrompt(row) {
    selectedKey.value = row.key;
    draft.value = row.content ?? '';
    helpOpen.value = false;
}

async function onSave() {
    if (!selected.value || !canSave.value) {
        return;
    }

    const updated = await save(selected.value.key, draft.value);
    if (updated) {
        draft.value = updated.content;
    }
}

async function onReset() {
    if (!selected.value?.is_overridden) {
        return;
    }
    if (!window.confirm('Сбросить промпт к заводскому тексту из файла?')) {
        return;
    }

    const ok = await reset(selected.value.key);
    if (ok) {
        const fresh = prompts.value.find((row) => row.key === selectedKey.value);
        if (fresh) {
            draft.value = fresh.content;
        }
    }
}

watch(prompts, (rows) => {
    if (!rows.length) {
        selectedKey.value = null;
        draft.value = '';

        return;
    }

    if (selectedKey.value === null || !rows.some((row) => row.key === selectedKey.value)) {
        selectPrompt(rows[0]);
    } else {
        const current = rows.find((row) => row.key === selectedKey.value);
        if (current && draft.value === selected.value?.content) {
            draft.value = current.content;
        }
    }
});

onMounted(() => {
    load();
});
</script>
