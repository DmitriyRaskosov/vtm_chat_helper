<template>
    <div>
        <div class="top">
            <h1>Game Chat</h1>
            <span class="muted">регистрация</span>
        </div>
        <div class="card">
            <form @submit.prevent="submit">
                <label for="login">Логин</label>
                <input id="login" v-model="form.login" type="text" required autofocus autocomplete="username">
                <p v-if="errors.login" class="error">{{ errors.login }}</p>

                <label for="name">Имя в чате</label>
                <input id="name" v-model="form.name" type="text" required>
                <p v-if="errors.name" class="error">{{ errors.name }}</p>

                <label for="password">Пароль</label>
                <input id="password" v-model="form.password" type="password" required>
                <p v-if="errors.password" class="error">{{ errors.password }}</p>

                <label for="password_confirmation">Ещё раз пароль</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" required>

                <button type="submit">Создать аккаунт</button>
            </form>
            <p class="muted">Первый зарегистрированный становится рассказчиком, остальные — игроками.</p>
            <p class="muted">Уже есть аккаунт? <RouterLink to="/login">Войти</RouterLink></p>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuth } from '../auth';

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
        errors.password = data.password?.[0] ?? error.response?.data?.message ?? 'Не удалось зарегистрироваться.';
    }
}
</script>
