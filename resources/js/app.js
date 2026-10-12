const menu = document.getElementById('user-menu-dropdown');
document.getElementById('user-menu-button')?.addEventListener('click', (e) => {
    e.stopPropagation();
    menu?.classList.toggle('hidden');
});
document.addEventListener('click', (e) => {
    if (!menu?.contains(e.target)) menu?.classList.add('hidden');
});
