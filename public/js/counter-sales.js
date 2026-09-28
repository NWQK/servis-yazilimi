document.addEventListener('input', function (event) {
    const input = event.target;
    if (!input.matches('[data-counter-price]')) return;
    const output = input.form.querySelector('[data-counter-total]');
    const quantity = Number(input.value);
    output.textContent = input.validity.valid && Number.isInteger(quantity)
        ? new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'}).format(quantity * Number(input.dataset.counterPrice) / 100)
        : 'Geçerli bir adet girin.';
});
