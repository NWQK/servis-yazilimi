(() => {
    const root = document.getElementById('appointment-notifications');
    if (!root) return;
    const toggle = document.getElementById('appointment-notifications-toggle');
    const badge = document.getElementById('appointment-notifications-count');
    const list = document.getElementById('appointment-notifications-list');
    let busy = false;
    let previous = '';
    const message = text => {
        const p = document.createElement('p');
        p.className = 'p-3 mb-0 text-muted';
        p.textContent = text;
        list.replaceChildren(p);
    };
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(root.dataset.url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('Bildirimler alınamadı');
            const data = await response.json();
            badge.hidden = data.count === 0;
            badge.textContent = data.count > 99 ? '99+' : String(data.count);
            toggle.setAttribute('aria-label', 'Randevu bildirimleri, ' + data.count + ' talep onay bekliyor');
            const current = JSON.stringify(data.items);
            if (current === previous) return;
            previous = current;
            if (!data.items.length) { message('Onay bekleyen yeni randevu talebi yok.'); return; }
            const links = data.items.map(item => {
                const link = document.createElement('a');
                link.className = 'dropdown-item py-3 border-bottom';
                link.style.whiteSpace = 'normal';
                link.style.overflowWrap = 'anywhere';
                link.href = item.url;
                const title = document.createElement('strong');
                title.className = 'd-block';
                title.textContent = item.title;
                const date = document.createElement('small');
                date.className = 'text-muted';
                date.textContent = item.date;
                link.append(title, date);
                return link;
            });
            list.replaceChildren(...links);
        } catch (_) {
            previous = '';
            message('Bildirimler yenilenemedi. Yeniden denemek için zile tıklayın.');
        } finally { busy = false; }
    }
    toggle.addEventListener('click', refresh);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
    setInterval(refresh, 10000);
    refresh();
})();
