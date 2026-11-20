import '../../css/example/example.css';

type Theme = 'light' | 'dark';

const themeButton = document.querySelector<HTMLButtonElement>('[data-theme-toggle]');
const systemTheme = (): Theme => window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

function applyTheme(theme: Theme): void {
    document.documentElement.dataset.theme = theme;
    themeButton?.setAttribute('aria-pressed', String(theme === 'dark'));
}

let savedTheme: string | null = null;
try {
    savedTheme = localStorage.getItem('moztopia-example-theme');
} catch {
    savedTheme = null;
}
applyTheme(savedTheme === 'light' || savedTheme === 'dark' ? savedTheme : systemTheme());

themeButton?.addEventListener('click', () => {
    const theme: Theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(theme);
    try {
        localStorage.setItem('moztopia-example-theme', theme);
    } catch {
        // The selection still works when browser storage is unavailable.
    }
});

const navigationButton = document.querySelector<HTMLButtonElement>('[data-navigation]');
function closeNavigation(): void {
    document.body.classList.remove('navigation-open');
    navigationButton?.setAttribute('aria-expanded', 'false');
}
navigationButton?.addEventListener('click', () => {
    const open = document.body.classList.toggle('navigation-open');
    navigationButton.setAttribute('aria-expanded', String(open));
});

const languageMenu = document.querySelector<HTMLDetailsElement>('[data-language-menu]');
document.addEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        const wasOpen = document.body.classList.contains('navigation-open');
        closeNavigation();
        if (wasOpen) {
            navigationButton?.focus();
        }
        if (languageMenu?.open) {
            languageMenu.open = false;
            languageMenu.querySelector<HTMLElement>('summary')?.focus();
        }
    }
});
document.addEventListener('click', (event: MouseEvent) => {
    if (!(event.target instanceof Node)) {
        return;
    }
    if (languageMenu && !languageMenu.contains(event.target)) {
        languageMenu.open = false;
    }
    if (!document.querySelector('.sidebar')?.contains(event.target) && !navigationButton?.contains(event.target)) {
        closeNavigation();
    }
});

document.querySelector<HTMLSelectElement>('[data-scenario]')?.addEventListener('change', () => {
    document.querySelector<HTMLFormElement>('[data-scenario-form]')?.requestSubmit();
});

for (const button of document.querySelectorAll<HTMLButtonElement>('[data-copy]')) {
    button.addEventListener('click', async () => {
        const code = button.closest('.code-panel')?.querySelector('code')?.textContent ?? '';
        const toast = document.querySelector<HTMLElement>('[data-toast]');
        if (!toast) {
            return;
        }
        try {
            await navigator.clipboard.writeText(code);
            toast.textContent = button.dataset.copied ?? '';
        } catch {
            toast.textContent = button.dataset.copyFailed ?? '';
        }
        toast.hidden = false;
        window.setTimeout(() => { toast.hidden = true; }, 2500);
    });
}

let touchStart: { x: number; y: number } | null = null;
document.addEventListener('touchstart', (event: TouchEvent) => {
    const target = event.target;
    if (window.getSelection()?.toString() || target instanceof Element && target.closest('input, textarea, select, button, [draggable="true"]')) {
        touchStart = null;
        return;
    }
    const touch = event.touches[0];
    touchStart = touch ? { x: touch.clientX, y: touch.clientY } : null;
}, { passive: true });
document.addEventListener('touchmove', (event: TouchEvent) => {
    const touch = event.touches[0];
    if (!touchStart || !touch) {
        return;
    }
    const vertical = Math.abs(touch.clientY - touchStart.y);
    const horizontal = Math.abs(touch.clientX - touchStart.x);
    if (vertical > 12 && vertical > horizontal && document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
        touchStart = null;
    }
}, { passive: true });
