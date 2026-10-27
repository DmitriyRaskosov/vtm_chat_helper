import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { registerUnauthorizedHandler } from './auth';
import './styles.css';

if (!document.body.classList.contains('theme-gothic') && !document.body.classList.contains('theme-blood')) {
    document.body.classList.add('theme-gothic');
}

registerUnauthorizedHandler(() => {
    const current = router.currentRoute.value;
    if (current.name !== 'login') {
        router.push({ name: 'login' });
    }
});

createApp(App).use(router).mount('#app');
