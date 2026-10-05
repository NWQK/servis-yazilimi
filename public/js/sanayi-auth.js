(() => {
    const button = document.getElementById('toggle-password');
    const input = document.getElementById('password');
    if (!button || !input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
    });
})();
