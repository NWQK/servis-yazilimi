(() => {
    const source = document.querySelector('.referral-source');
    const vehicle = document.querySelector('.referral-vehicle');
    if (source && vehicle) {
        const filter = () => { vehicle.value = ''; for (const option of vehicle.options) if (option.dataset.owner) { option.hidden = option.dataset.owner !== source.value; option.disabled = option.hidden; } };
        source.addEventListener('change', filter); filter();
    }
    const existing = document.getElementById('existing-referral-vehicle');
    if (existing) existing.addEventListener('change', () => { const option = existing.selectedOptions[0]; if (option?.dataset.customer) document.getElementById('referral-customer').value = option.dataset.customer; });
})();
