<button id="theme-toggle" type="button" aria-label="Toggle dark/light mode" title="Chuyển đổi Chế độ Sáng / Tối" 
    class="p-2 px-3 rounded-xl text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-amber-400 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700/80 transition-all border border-slate-200 dark:border-slate-700 flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">
    <!-- Sun Icon (showing when dark) -->
    <svg id="theme-toggle-sun" class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
    </svg>
    <!-- Moon Icon (showing when light) -->
    <svg id="theme-toggle-moon" class="w-4 h-4 block dark:hidden text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
    </svg>
    <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
        <span class="inline dark:hidden">Tối</span>
        <span class="hidden dark:inline">Sáng</span>
    </span>
</button>

<script>
    (function() {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
            });
        }
    })();
</script>
