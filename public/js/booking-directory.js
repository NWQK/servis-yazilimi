(() => {
    const search = document.getElementById('city-search');
    const cities = [...document.querySelectorAll('[data-city]')];
    const normalize = value => value.toLocaleLowerCase('tr').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ı/g, 'i');
    search?.addEventListener('input', () => {
        const query = normalize(search.value.trim());
        cities.forEach(city => city.hidden = !normalize(city.dataset.city).includes(query));
        document.getElementById('city-empty').hidden = cities.some(city => !city.hidden);
    });
    const locate = document.getElementById('locate');
    const status = document.getElementById('geo-status');
    locate?.addEventListener('click', () => {
        if (!navigator.geolocation) { status.textContent = 'Konum desteklenmiyor. Aşağıdan il seçebilirsiniz.'; return; }
        locate.disabled = true; status.textContent = 'Konumunuz bulunuyor…';
        const fail = () => { locate.disabled = false; status.textContent = 'Konum belirlenemedi. Aşağıdan il seçerek devam edebilirsiniz.'; };
        navigator.geolocation.getCurrentPosition(async position => {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const query = new URLSearchParams({latitude:position.coords.latitude, longitude:position.coords.longitude, localityLanguage:'tr'});
                const response = await fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?' + query, {signal:controller.signal, credentials:'omit', referrerPolicy:'no-referrer'});
                if (!response.ok) throw new Error('geo');
                const data = await response.json();
                if (data.countryCode !== 'TR') throw new Error('country');
                const code = (data.principalSubdivisionCode || '').replace(/^TR-/, '').padStart(2, '0');
                if (code === '34') {
                    const sides = document.getElementById('istanbul-sides'); sides.hidden = false;
                    status.textContent = 'İstanbul bulundu. Devam etmek için yakanızı seçin.';
                    sides.querySelector('a').focus(); locate.disabled = false;
                } else {
                    const city = cities.find(city => city.dataset.code.padStart(2,'0') === code);
                    if (!city) throw new Error('city');
                    window.location.assign(city.href);
                }
            } catch (_) { fail(); } finally { clearTimeout(timeout); }
        }, fail, {enableHighAccuracy:false, timeout:10000, maximumAge:0});
    });
})();
