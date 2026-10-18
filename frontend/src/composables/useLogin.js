import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function resolveLoginRedirect(queryRedirect) {
    if (typeof queryRedirect !== 'string') {
        return null;
    }

    if (!queryRedirect.startsWith('/invitations/')) {
        return null;
    }

    const token = queryRedirect.slice('/invitations/'.length);

    if (!token || token.includes('/') || token.includes('?')) {
        return null;
    }

    return queryRedirect;
}

export function useLogin() {
    const route = useRoute();
    const router = useRouter();
    const auth = useAuth();
    const login = ref('');
    const password = ref('');
    const errors = reactive({ login: '', password: '' });

    async function submit() {
        errors.login = '';
        errors.password = '';
        try {
            await auth.login({ login: login.value, password: password.value });
            const redirect = resolveLoginRedirect(route.query.redirect);

            if (redirect) {
                await router.push(redirect);
            } else {
                await router.push('/chat');
            }
        } catch (error) {
            const data = error.response?.data?.errors ?? {};
            errors.login = data.login?.[0] ?? apiErrorMessage(error, 'Не удалось войти.');
            errors.password = data.password?.[0] ?? '';
        }
    }

    return { login, password, errors, submit };
}
