import { computed, ref, toValue, watch } from 'vue';
import { api } from '../auth';
import { chronicleCollectionPath } from './useCurrentChronicle';
import { apiErrorMessage } from '../utils/apiError';

export function useCopilotPanel({ selectedSceneId, selectedNpcId, onSent, chronicleId }) {
    const copilotPrompt = ref('');
    const copilotRequestId = ref(null);
    const copilotNpcId = ref(null);
    const drafts = ref([]);
    const selectedDraftIndex = ref(null);
    const editedDraft = ref('');
    const copilotLoading = ref(false);
    const copilotError = ref('');
    const previewOpen = ref(false);
    const previewLoading = ref(false);
    const previewError = ref('');
    const previewData = ref(null);

    const canGenerate = computed(() => selectedNpcId.value !== null && copilotPrompt.value.trim() !== '');
    const selectedDraft = computed(() =>
        selectedDraftIndex.value === null ? null : drafts.value[selectedDraftIndex.value] ?? null,
    );
    const canSendNpc = computed(() => selectedNpcId.value !== null && editedDraft.value.trim() !== '');

    function selectDraft(index) {
        selectedDraftIndex.value = index;
        editedDraft.value = drafts.value[index] ?? '';
    }

    function resetDrafts() {
        copilotRequestId.value = null;
        copilotNpcId.value = null;
        drafts.value = [];
        selectedDraftIndex.value = null;
        editedDraft.value = '';
    }

    watch(selectedNpcId, (npcId, previousNpcId) => {
        if (previousNpcId === undefined || npcId === previousNpcId) {
            return;
        }
        if (copilotRequestId.value !== null || drafts.value.length > 0) {
            resetDrafts();
        }
    });

    async function generateDrafts() {
        copilotError.value = '';
        copilotLoading.value = true;
        resetDrafts();

        try {
            const { data } = await api.post(chronicleCollectionPath(chronicleId, 'copilot/drafts'), {
                character_id: selectedNpcId.value,
                prompt: copilotPrompt.value.trim(),
                scene_id: toValue(selectedSceneId),
            });
            copilotRequestId.value = data.copilot_request_id ?? null;
            copilotNpcId.value = selectedNpcId.value;
            drafts.value = data.drafts ?? [];
            if (drafts.value.length > 0) {
                selectDraft(0);
            }
        } catch (error) {
            copilotError.value = apiErrorMessage(
                error,
                'Не удалось сгенерировать черновики. Проверьте DeepSeek API и сеть.',
            );
        } finally {
            copilotLoading.value = false;
        }
    }

    async function sendAsNpc() {
        const text = editedDraft.value.trim();
        const npcId = copilotNpcId.value ?? selectedNpcId.value;
        if (!text || npcId === null) {
            return;
        }

        copilotError.value = '';

        try {
            const { data } = await api.post(chronicleCollectionPath(chronicleId, 'messages'), {
                body: text,
                character_id: npcId,
                scene_id: toValue(selectedSceneId),
                copilot_request_id: copilotRequestId.value,
                copilot_draft_index: selectedDraftIndex.value,
            });
            onSent(data.message);
            resetDrafts();
            copilotPrompt.value = '';
        } catch (error) {
            copilotError.value = apiErrorMessage(error, 'Не удалось отправить выбранный черновик.');
        }
    }

    async function openPreview() {
        if (selectedNpcId.value === null) {
            return;
        }

        previewOpen.value = true;
        previewLoading.value = true;
        previewError.value = '';
        previewData.value = null;

        try {
            const { data } = await api.post(`/characters/${selectedNpcId.value}/diary/preview`, {
                scene_id: toValue(selectedSceneId),
                prompt: copilotPrompt.value.trim(),
            });
            previewData.value = data;
        } catch (error) {
            previewError.value = apiErrorMessage(error, 'Не удалось получить preview дневника.');
        } finally {
            previewLoading.value = false;
        }
    }

    function closePreview() {
        previewOpen.value = false;
    }

    return {
        copilotPrompt,
        copilotRequestId,
        drafts,
        selectedDraftIndex,
        editedDraft,
        copilotLoading,
        copilotError,
        previewOpen,
        previewLoading,
        previewError,
        previewData,
        canGenerate,
        selectedDraft,
        canSendNpc,
        selectDraft,
        resetDrafts,
        generateDrafts,
        sendAsNpc,
        openPreview,
        closePreview,
    };
}
