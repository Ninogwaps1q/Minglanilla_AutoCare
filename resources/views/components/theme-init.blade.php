<meta name="color-scheme" content="light dark">
<script>
    (() => {
        try {
            const savedTheme = localStorage.getItem('autocare-theme');
            const theme = ['light', 'dark'].includes(savedTheme)
                ? savedTheme
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

            document.documentElement.dataset.theme = theme;
            document.documentElement.dataset.bsTheme = theme;
        } catch {
            document.documentElement.dataset.theme = 'light';
            document.documentElement.dataset.bsTheme = 'light';
        }
    })();
</script>
