import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../auth';
import { apiErrorMessage } from '../utils/apiError';

export function useRegister() {
    const router = useRouter();
    const auth = useAuth();
    const form = reactive({
        login: '',
        name: '',
        password: '',
        password_confirmation: '',
    });
    const errors = reactive({ login: '', name: '', password: '' });

    async function submit() {
        errors.login = '';
        errors.name = '';
        errors.password = '';
        try {
            await auth.register(form);
            await router.push('/chat');
        } catch (error) {
            const data = error.response?.data?.errors ?? {};
            errors.login = data.login?.[0] ?? '';
            errors.name = data.name?.[0] ?? '';
            errors.password = data.password?.[0] ?? apiErrorMessage(error, 'Не удалось зарегистрироваться.');
        }
    }

    return { form, errors, submit };
}
