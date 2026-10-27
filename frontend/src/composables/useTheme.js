import { ref } from 'vue';

const THEME_KEY = 'app-theme';
const THEME_GOTHIC = 'theme-gothic';
const THEME_BLOOD = 'theme-blood';

function readStoredTheme() {
    const stored = localStorage.getItem(THEME_KEY);

    if (stored === THEME_BLOOD || stored === THEME_GOTHIC) {
        return stored;
    }

    return THEME_GOTHIC;
}

export const currentTheme = ref(readStoredTheme());

export function applyTheme(theme) {
    document.body.classList.remove(THEME_GOTHIC, THEME_BLOOD);
    document.body.classList.add(theme);
}

export function setTheme(theme) {
    const next = theme === THEME_BLOOD ? THEME_BLOOD : THEME_GOTHIC;
    currentTheme.value = next;
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
}

export function toggleTheme() {
    setTheme(currentTheme.value === THEME_GOTHIC ? THEME_BLOOD : THEME_GOTHIC);
}

export function useTheme() {
    return {
        currentTheme,
        setTheme,
        toggleTheme,
    };
}
