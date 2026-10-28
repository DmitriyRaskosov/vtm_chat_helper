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
import { RouterLink } from 'vue-router';
import { useLogin } from '../composables/useLogin';

const { login, password, errors, submit } = useLogin();
</script>
