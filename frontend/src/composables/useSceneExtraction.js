import { ref } from 'vue';
import { api } from '../auth';

export function sceneEventCandidateLabel(candidate) {
    const participants = (candidate.participants ?? [])
        .map((row) => row.name)
        .filter(Boolean)
        .join(', ');
    const suffix = participants ? ` · ${participants}` : '';
    return `${candidate.title}${suffix}`;
}

export function useSceneExtraction({ error }) {
    const extractorEnabled = ref(false);
    const extracting = ref(false);
    const inboxCount = ref(0);

    async function loadExtractorStatus() {
        try {
            const { data } = await api.get('/extract/status');
            extractorEnabled.value = Boolean(data.enabled);
        } catch {
            extractorEnabled.value = false;
        }
    }

    async function loadInboxCount() {
        try {
            const { data } = await api.get('/extract/inbox', { params: { count_only: true } });
            inboxCount.value = Number(data.count ?? 0);
        } catch {
            inboxCount.value = 0;
        }
    }

    async function runExtraction(sceneId) {
        if (!sceneId || extracting.value) {
            return;
        }
        extracting.value = true;
        error.value = '';
        try {
            await api.post('/extract', { scene_id: sceneId }, { timeout: 320000 });
            await loadInboxCount();
        } catch (e) {
            error.value = e.response?.data?.message ?? 'Не удалось разобрать сцену.';
        } finally {
            extracting.value = false;
        }
    }

    return {
        extractorEnabled,
        extracting,
        inboxCount,
        loadExtractorStatus,
        loadInboxCount,
        runExtraction,
        sceneEventCandidateLabel,
    };
}
