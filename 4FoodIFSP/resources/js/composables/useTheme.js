import { ref } from 'vue';

// Shared, app-wide dark-mode state. The class is applied to <html> so every
// screen (admin, tablet, auth) reacts to it; the choice persists in localStorage.
const STORAGE_KEY = 'theme';

function prefersDark() {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'dark';
    } catch (e) {
        return false;
    }
}

const isDark = ref(prefersDark());

function apply(value) {
    document.documentElement.classList.toggle('theme-dark', value);
    try {
        localStorage.setItem(STORAGE_KEY, value ? 'dark' : 'light');
    } catch (e) {
        /* storage unavailable — keep in-memory state only */
    }
}

// Sync DOM with stored preference on first import (covers screens loaded
// before any component mounts).
apply(isDark.value);

export function useTheme() {
    function setDark(value) {
        isDark.value = value;
        apply(value);
    }

    function toggle() {
        setDark(!isDark.value);
    }

    return { isDark, toggle, setDark };
}
