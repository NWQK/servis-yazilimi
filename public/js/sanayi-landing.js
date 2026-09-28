(() => {
    const toggle = document.querySelector('.menu-toggle');
    const menu = document.getElementById('main-nav');
    if (!toggle || !menu) return;
    const close = () => { toggle.setAttribute('aria-expanded', 'false'); menu.classList.remove('is-open'); };
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        menu.classList.toggle('is-open', open);
    });
    menu.addEventListener('click', event => { if (event.target.closest('a')) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { close(); toggle.focus(); } });
    document.addEventListener('click', event => { if (!event.target.closest('.site-header')) close(); });
})();
