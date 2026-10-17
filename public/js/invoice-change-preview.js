(() => {
    if (window.invoiceChangePreviewInstalled) return;
    window.invoiceChangePreviewInstalled = true;
    window.addEventListener('pageshow', () => {
        for (const form of document.querySelectorAll('form[data-invoice-preview]')) {
            delete form.dataset.previewApproved;
            delete form.dataset.previewBusy;
            form.querySelector('[name="preview_token"]')?.remove();
        }
    });
    const money = value => new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'}).format(value);
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.invoicePreview || form.dataset.previewApproved === '1') return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (form.dataset.previewBusy === '1') return;
        form.dataset.previewBusy = '1';
        let dialog;
        try {
            const data = new FormData(form); data.delete('_method'); data.delete('preview_token');
            const response = await fetch(form.dataset.invoicePreview, {method:'POST', body:data, headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store'});
            const result = await response.json();
            if (!response.ok) throw Error(Object.values(result.errors || {}).flat()[0] || result.message || 'İşlem özeti alınamadı.');
            dialog = document.createElement('dialog'); dialog.className = 'invoice-preview-dialog';
            const title = document.createElement('h5'); title.textContent = 'Değişiklikleri kontrol edin'; dialog.append(title);
            const lines = [`Fatura tutarı: ${money(result.before_total)} → ${money(result.after_total)}`, `Kayıtlı ödeme toplamı: ${money(result.before_paid)} → ${money(result.after_paid)}`];
            for (const row of result.stocks) lines.push(`${row.title}: ${row.delta > 0 ? row.delta + ' adet stoğa dönecek' : -row.delta + ' adet stoktan düşecek'}`);
            if (!result.stocks.length) lines.push('Stok miktarları değişmeyecek.');
            if (result.payment_adjustment < 0) lines.push(`${money(-result.payment_adjustment)} otomatik gelir düzeltmesi oluşacak. Bu işlem müşteriye para iadesi yapmaz.`);
            if (result.deleting) lines.push('Fatura ve bağlı ödeme kayıtları silinecek. Bu işlem müşteriye para iadesi yapmaz.');
            for (const text of lines) { const p = document.createElement('p'); p.textContent = text; dialog.append(p); }
            const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn btn-light me-2'; cancel.textContent = 'Vazgeç';
            const approve = document.createElement('button'); approve.type = 'button'; approve.className = 'btn btn-secondary'; approve.textContent = 'Onayla ve uygula';
            dialog.append(cancel, approve); document.body.append(dialog); dialog.showModal();
            const accepted = await new Promise(resolve => { cancel.onclick = () => {dialog.close();resolve(false);}; approve.onclick = () => {dialog.close();resolve(true);}; dialog.addEventListener('cancel',()=>resolve(false),{once:true}); });
            if (accepted) { let input = form.querySelector('[name="preview_token"]'); if (!input) {input = document.createElement('input');input.type='hidden';input.name='preview_token';form.append(input);} input.value = result.token; form.dataset.previewApproved='1'; HTMLFormElement.prototype.submit.call(form); }
        } catch(error) { window.alert(error.message || 'İşlem özeti alınamadı.'); }
        finally {dialog?.remove();delete form.dataset.previewBusy;}
    }, true);
})();
