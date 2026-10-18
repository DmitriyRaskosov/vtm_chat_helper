import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useLogin() {
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
            await router.push('/chat');
        } catch (error) {
            const data = error.response?.data?.errors ?? {};
            errors.login = data.login?.[0] ?? apiErrorMessage(error, 'Не удалось войти.');
            errors.password = data.password?.[0] ?? '';
        }
    }

    return { login, password, errors, submit };
}
