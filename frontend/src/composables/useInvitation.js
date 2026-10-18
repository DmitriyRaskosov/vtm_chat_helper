import { api } from '../auth';

export function useInvitation() {
    async function accept(token) {
        const { data } = await api.post(`/invitations/${token}/accept`);

        return data;
    }

    return { accept };
}
