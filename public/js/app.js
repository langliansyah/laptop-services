const themeToggle = document.querySelector('[data-theme-toggle]');

if (themeToggle) {
    const themeIcon = themeToggle.querySelector('.theme-toggle-icon');

    const syncThemeToggle = () => {
        const isDark = document.documentElement.dataset.theme === 'dark';

        themeToggle.setAttribute('aria-pressed', String(isDark));
        themeToggle.setAttribute('aria-label', isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap');
        themeIcon.textContent = isDark ? '☾' : '☀';
    };

    syncThemeToggle();

    themeToggle.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';

        document.documentElement.dataset.theme = nextTheme;
        localStorage.setItem('ruangservis-theme', nextTheme);
        syncThemeToggle();
    });
}
