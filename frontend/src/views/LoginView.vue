<template>
    <div>
        <div class="top">
            <h1>Game Chat</h1>
            <span class="muted">вход</span>
        </div>
        <div class="card">
            <form @submit.prevent="submit">
                <label for="login">Логин</label>
                <input id="login" v-model="login" type="text" required autofocus autocomplete="username">
                <p v-if="errors.login" class="error">{{ errors.login }}</p>

                <label for="password">Пароль</label>
                <input id="password" v-model="password" type="password" required>
                <p v-if="errors.password" class="error">{{ errors.password }}</p>

                <button type="submit">Войти</button>
            </form>
            <p class="muted">Нет аккаунта? <RouterLink to="/register">Регистрация</RouterLink></p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuth } from '../auth';

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
        errors.login = data.login?.[0] ?? error.response?.data?.message ?? 'Не удалось войти.';
        errors.password = data.password?.[0] ?? '';
    }
}
</script>
