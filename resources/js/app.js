// SportHub Application JavaScript

document.addEventListener('DOMContentLoaded', () => {
    const userBtn = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-menu-dropdown');
    const userArrow = document.getElementById('user-menu-arrow');

    if (userBtn && userDropdown && !userBtn.dataset.dropdownInit) {
        userBtn.dataset.dropdownInit = 'true';

        userBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = userDropdown.classList.contains('hidden');
            if (isHidden) {
                userDropdown.classList.remove('hidden');
                userBtn.setAttribute('aria-expanded', 'true');
                if (userArrow) userArrow.classList.add('rotate-180');
            } else {
                userDropdown.classList.add('hidden');
                userBtn.setAttribute('aria-expanded', 'false');
                if (userArrow) userArrow.classList.remove('rotate-180');
            }
        });

        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target) && !userBtn.contains(e.target)) {
                userDropdown.classList.add('hidden');
                userBtn.setAttribute('aria-expanded', 'false');
                if (userArrow) userArrow.classList.remove('rotate-180');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !userDropdown.classList.contains('hidden')) {
                userDropdown.classList.add('hidden');
                userBtn.setAttribute('aria-expanded', 'false');
                if (userArrow) userArrow.classList.remove('rotate-180');
            }
        });
    }
});
