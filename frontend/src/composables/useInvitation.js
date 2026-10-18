import { api, refreshUser } from '../auth';

const ALREADY_MEMBER_MESSAGE = 'You are already a member of this chronicle.';

async function refreshUserAfterMembershipChange() {
    try {
        await refreshUser();
    } catch {
        // Сеть: accept уже прошёл, переход в чат не блокируем; роль подтянется после F5.
    }
}

function isAlreadyMemberConflict(error) {
    const status = error.response?.status;
    const message = error.response?.data?.message;

    return status === 409 && message === ALREADY_MEMBER_MESSAGE;
}

export function useInvitation() {
    async function accept(token) {
        try {
            const { data } = await api.post(`/invitations/${token}/accept`);
            await refreshUserAfterMembershipChange();

            return data;
        } catch (error) {
            if (isAlreadyMemberConflict(error)) {
                await refreshUserAfterMembershipChange();
            }

            throw error;
        }
    }

    return { accept };
}
