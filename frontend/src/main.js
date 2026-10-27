import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { registerUnauthorizedHandler } from './auth';
import { applyTheme, currentTheme } from './composables/useTheme';
import './styles.css';

applyTheme(currentTheme.value);

registerUnauthorizedHandler(() => {
    const current = router.currentRoute.value;
    if (current.name !== 'login') {
        router.push({ name: 'login' });
    }
});

createApp(App).use(router).mount('#app');
