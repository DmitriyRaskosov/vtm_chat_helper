import { ref } from 'vue';
import { api } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

const invitations = ref([]);
const loading = ref(false);
const error = ref('');

export function invitationUrl(token) {
    return `${window.location.origin}/invitations/${token}`;
}

function defaultExpiresAt() {
    const date = new Date();
    date.setDate(date.getDate() + 7);

    return date.toISOString();
}

export function useChronicleInvitations() {
    async function load(chronicleId) {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.get(`/chronicles/${chronicleId}/invitations`);
            invitations.value = data.invitations ?? [];
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось загрузить приглашения.');
            invitations.value = [];
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function create(chronicleId, { role }) {
        error.value = '';

        try {
            const { data } = await api.post(`/chronicles/${chronicleId}/invitations`, {
                role,
                expires_at: defaultExpiresAt(),
                max_uses: 5,
            });

            const created = data.invitation;
            invitations.value = [created, ...invitations.value.filter((item) => item.id !== created.id)];

            return {
                ...created,
                url: invitationUrl(created.token),
            };
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось создать приглашение.');
            throw err;
        }
    }

    async function revoke(chronicleId, invitationId) {
        error.value = '';

        try {
            await api.delete(`/chronicles/${chronicleId}/invitations/${invitationId}`);
            invitations.value = invitations.value.filter((item) => item.id !== invitationId);
        } catch (err) {
            error.value = apiErrorMessage(err, 'Не удалось отозвать приглашение.');
            throw err;
        }
    }

    return {
        invitations,
        loading,
        error,
        load,
        create,
        revoke,
    };
}
