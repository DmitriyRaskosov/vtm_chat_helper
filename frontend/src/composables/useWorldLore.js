import { computed, reactive, ref } from 'vue';
import { api } from '../auth';
import { typeLabel } from './useWorldEntities';

export const extractionKindLabels = {
    faction: 'секта / фракция',
    clan: 'клан',
    coterie: 'котерия',
    circle: 'круг',
    other: 'прочее',
    location: 'место',
    item: 'предмет',
    concept: 'идея',
    character: 'персонаж',
    event: 'событие',
};

export const extractionDirectoryKinds = [
    { value: 'faction', label: 'секта / фракция' },
    { value: 'clan', label: 'клан' },
    { value: 'coterie', label: 'котерия' },
    { value: 'circle', label: 'круг' },
    { value: 'other', label: 'прочее' },
    { value: 'location', label: 'место' },
    { value: 'item', label: 'предмет' },
    { value: 'concept', label: 'идея' },
];

export const loreKinds = [
    { value: 'history', label: 'История' },
    { value: 'place', label: 'Место' },
    { value: 'faction', label: 'Секта / фракция' },
    { value: 'person', label: 'Персона' },
    { value: 'ritual', label: 'Ритуал' },
    { value: 'item', label: 'Предмет' },
    { value: 'custom', label: 'Другое' },
];

export const loreAccessLevels = [
    { value: '0', label: 'уровень 0' },
    { value: '1', label: 'уровень 1' },
    { value: '2', label: 'уровень 2' },
    { value: '3', label: 'уровень 3' },
    { value: '4', label: 'уровень 4' },
    { value: '5', label: 'уровень 5' },
];

export const aboutFilterChips = [
    { value: 'all', label: 'все' },
    { value: 'characters', label: 'НПС и гули' },
    { value: 'faction', label: 'секты / фракции' },
    { value: 'location', label: 'места' },
    { value: 'item', label: 'предметы' },
    { value: 'concept', label: 'идеи' },
];

export function characterTypeLabel(type) {
    if (type === 'player') return 'игрок';
    if (type === 'ghoul') return 'гуль';
    return 'НПС';
}

export function loreKindLabel(kind) {
    return loreKinds.find((row) => row.value === kind)?.label ?? kind;
}

export function loreClassificationLabel(classification) {
    return loreAccessLevels.find((row) => row.value === classification)?.label ?? `уровень ${classification}`;
}

export function aboutTypeLabel(row) {
    if (row.entity_type === 'character') {
        return characterTypeLabel(row.character_type);
    }
    return typeLabel(row.entity_type);
}

function flattenCharacters(rows, hidden = false) {
    const out = [];
    for (const row of rows ?? []) {
        out.push({
            id: row.id,
            canonical_name: row.canonical_name,
            character_type: row.character_type,
            hidden,
        });
        for (const ghoul of row.ghouls ?? []) {
            out.push({
                id: ghoul.id,
                canonical_name: ghoul.canonical_name,
                character_type: ghoul.character_type ?? 'ghoul',
                hidden,
            });
        }
    }
    return out;
}

function matchesAboutFilter(row, filter) {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'characters') {
        return row.entity_type === 'character';
    }
    return row.entity_type === filter;
}

export function extractionCandidateLabel(candidate, type) {
    if (type === 'relation') {
        return `${candidate.source} → ${candidate.target} (${candidate.key})`;
    }
    const kind = extractionKindLabels[candidate.kind] ?? candidate.kind;
    if (candidate.candidate_type === 'new_entity') {
        return `${candidate.name} · новая ${kind}`;
    }
    return `${candidate.name} · ${kind}`;
}

export function inboxRunLabel(run) {
    if (run.source_type === 'lore') {
        return run.source_label || `Статья #${run.source_id}`;
    }
    if (run.source_type === 'biography') {
        return run.source_label ? `Био: ${run.source_label}` : `Персонаж #${run.source_id}`;
    }
    return run.source_label || run.scene_title || `Сцена #${run.source_id}`;
}

export function inboxRunMeta(run) {
    if (run.source_type === 'scene') {
        return `сообщения ${run.from_message_id}–${run.to_message_id} (${run.message_count ?? '?'})`;
    }
    if (run.source_type === 'lore') {
        if (run.from_char_offset != null && run.to_char_offset != null && run.to_char_offset > run.from_char_offset) {
            return `символы ${run.from_char_offset + 1}–${run.to_char_offset}`;
        }
        return 'статья лора';
    }
    return 'биография';
}

export function useWorldLore({ error, entities, archived }) {
    const savingLore = ref(false);
    const flashLore = ref(false);
    const extractorEnabled = ref(false);
    const extracting = ref(false);
    const loreLimits = ref({ article_max_chars: 10000, characters_per_token: 2 });
    const loreWindow = ref(null);
    const loreList = ref([]);
    const loreArchived = ref([]);
    const characterOptions = ref([]);
    const linkedAbout = ref([]);
    const aboutSearch = ref('');
    const aboutFilter = ref('all');
    let flashLoreTimer;
    const loreForm = reactive({
        id: null,
        title: '',
        kind: 'custom',
        classification: '0',
        situational: false,
        canonical_text: '',
        entity_ids: [],
        granted_character_ids: [],
        denied_character_ids: [],
    });

    const aboutOptions = computed(() => {
        const byId = new Map();
        for (const row of entities.value) {
            byId.set(row.id, row);
        }
        for (const row of archived.value) {
            if (!byId.has(row.id)) {
                byId.set(row.id, { ...row, hidden: true });
            }
        }
        for (const row of characterOptions.value) {
            byId.set(row.id, {
                id: row.id,
                canonical_name: row.canonical_name,
                entity_type: 'character',
                character_type: row.character_type,
                hidden: Boolean(row.hidden),
            });
        }
        for (const row of linkedAbout.value) {
            if (!byId.has(row.id)) {
                byId.set(row.id, { ...row, hidden: true });
            }
        }
        return [...byId.values()].sort((a, b) => a.canonical_name.localeCompare(b.canonical_name, 'ru'));
    });

    const filteredAboutOptions = computed(() => {
        const query = aboutSearch.value.trim().toLowerCase();
        return aboutOptions.value.filter((row) => {
            if (!matchesAboutFilter(row, aboutFilter.value)) {
                return false;
            }
            if (!query) {
                return true;
            }
            return row.canonical_name.toLowerCase().includes(query);
        });
    });

    function onExceptionToggle(kind, id, checked) {
        if (!checked) {
            return;
        }
        const other = kind === 'grant' ? 'denied_character_ids' : 'granted_character_ids';
        loreForm[other] = loreForm[other].filter((value) => value !== id);
    }

    async function loadExtractorStatus(loreEntryId = null) {
        try {
            const params = loreEntryId ? { lore_entry_id: loreEntryId } : {};
            const { data } = await api.get('/extract/status', { params });
            extractorEnabled.value = Boolean(data.enabled);
            if (data.lore) {
                loreLimits.value = {
                    article_max_chars: data.lore.article_max_chars ?? 10000,
                    characters_per_token: data.lore.characters_per_token ?? 2,
                };
            }
            loreWindow.value = data.lore_window ?? null;
        } catch {
            extractorEnabled.value = false;
            loreWindow.value = null;
        }
    }

    function resetLoreForm() {
        loreForm.id = null;
        loreForm.title = '';
        loreForm.kind = 'custom';
        loreForm.classification = '0';
        loreForm.situational = false;
        loreForm.canonical_text = '';
        loreForm.entity_ids = [];
        loreForm.granted_character_ids = [];
        loreForm.denied_character_ids = [];
        linkedAbout.value = [];
        aboutSearch.value = '';
        aboutFilter.value = 'all';
    }

    function applyLore(entry) {
        loreForm.id = entry.id;
        loreForm.title = entry.title ?? '';
        loreForm.kind = entry.kind ?? 'custom';
        loreForm.classification = entry.classification ?? '0';
        loreForm.situational = Boolean(entry.situational);
        loreForm.canonical_text = entry.canonical_text ?? '';
        loreForm.entity_ids = [...(entry.entity_ids ?? [])];
        loreForm.granted_character_ids = [...(entry.granted_character_ids ?? [])];
        loreForm.denied_character_ids = [...(entry.denied_character_ids ?? [])];
        linkedAbout.value = entry.entities ?? [];
    }

    async function loadLore() {
        const [loreRes, charactersRes] = await Promise.all([
            api.get('/lore'),
            api.get('/characters'),
        ]);
        loreList.value = loreRes.data.lore ?? [];
        loreArchived.value = loreRes.data.archived ?? [];
        characterOptions.value = [
            ...flattenCharacters(charactersRes.data.characters ?? []),
            ...flattenCharacters(charactersRes.data.archived ?? [], true),
        ];
    }

    async function openLoreTab() {
        error.value = '';
        try {
            await loadLore();
            await loadExtractorStatus(loreForm.id);
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось загрузить лор.';
        }
    }

    async function runExtraction() {
        if (!loreForm.id || extracting.value) {
            return null;
        }
        extracting.value = true;
        error.value = '';
        try {
            const { data } = await api.post('/extract', { lore_entry_id: loreForm.id }, { timeout: 320000 });
            return data.extraction_run_id ?? data.run?.id ?? null;
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось разобрать статью.';
            return null;
        } finally {
            extracting.value = false;
        }
    }

    function newLore() {
        resetLoreForm();
    }

    async function selectLore(id) {
        error.value = '';
        try {
            const { data } = await api.get(`/lore/${id}`);
            applyLore(data.lore);
            await loadExtractorStatus(id);
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось открыть статью.';
        }
    }

    async function saveLore() {
        savingLore.value = true;
        error.value = '';
        try {
            const payload = {
                title: loreForm.title.trim(),
                kind: loreForm.kind,
                visibility: 'public',
                classification: loreForm.classification,
                situational: loreForm.situational,
                canonical_text: loreForm.canonical_text.trim(),
                entity_ids: [...loreForm.entity_ids],
                granted_character_ids: [...loreForm.granted_character_ids],
                denied_character_ids: [...loreForm.denied_character_ids],
            };
            const { data } = loreForm.id
                ? await api.put(`/lore/${loreForm.id}`, payload)
                : await api.post('/lore', payload);
            applyLore(data.lore);
            await loadLore();
            if (loreForm.id) {
                await loadExtractorStatus(loreForm.id);
            }
            flashLore.value = true;
            clearTimeout(flashLoreTimer);
            flashLoreTimer = setTimeout(() => {
                flashLore.value = false;
            }, 2000);
        } catch (e) {
            error.value = e.response?.data?.message
                ?? e.response?.data?.errors?.title?.[0]
                ?? e.response?.data?.errors?.canonical_text?.[0]
                ?? 'Не удалось сохранить статью.';
        } finally {
            savingLore.value = false;
        }
    }

    async function archiveLore(id) {
        error.value = '';
        try {
            await api.post(`/lore/${id}/archive`);
            if (loreForm.id === id) {
                resetLoreForm();
            }
            await loadLore();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось скрыть статью.';
        }
    }

    async function restoreLore(id) {
        error.value = '';
        try {
            const { data } = await api.post(`/lore/${id}/restore`);
            await loadLore();
            applyLore(data.lore);
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось вернуть статью.';
        }
    }

    return {
        savingLore,
        flashLore,
        extractorEnabled,
        extracting,
        loreLimits,
        loreWindow,
        loadExtractorStatus,
        loreList,
        loreArchived,
        characterOptions,
        loreForm,
        aboutOptions,
        filteredAboutOptions,
        aboutSearch,
        aboutFilter,
        onExceptionToggle,
        openLoreTab,
        runExtraction,
        newLore,
        selectLore,
        saveLore,
        archiveLore,
        restoreLore,
    };
}
