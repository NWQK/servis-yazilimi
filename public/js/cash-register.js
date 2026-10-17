(() => {
    const preview = document.getElementById('cash-preview');
    if (!preview) return;
    const opening = document.getElementById('opening');
    const counted = document.getElementById('counted');
    const money = new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'});
    const update = () => {
        const expected = Math.round(Number(opening.value || 0) * 100) + Number(preview.dataset.net);
        document.getElementById('cash-expected').textContent = money.format(expected / 100);
        document.getElementById('cash-difference').textContent = counted.value === '' ? '—' : money.format((Math.round(Number(counted.value) * 100) - expected) / 100);
    };
    opening.addEventListener('input', update);
    counted.addEventListener('input', update);
    update();
})();
