(() => {
    const search = document.getElementById('city-search');
    const cities = [...document.querySelectorAll('[data-city]')];
    const normalize = value => value.toLocaleLowerCase('tr').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ı/g, 'i');
    search?.addEventListener('input', () => {
        const query = normalize(search.value.trim());
        cities.forEach(city => city.hidden = !normalize(city.dataset.city).includes(query));
        document.getElementById('city-empty').hidden = cities.some(city => !city.hidden);
    });
    const directory = document.getElementById('directory-location');
    const locate = document.getElementById('locate');
    const status = document.getElementById('geo-status');
    locate?.addEventListener('click', () => {
        if (locate.disabled) return;
        const fail = (message = 'Konum belirlenemedi. Aşağıdan il seçerek devam edebilirsiniz.') => {
            locate.disabled = false;
            status.textContent = message;
        };
        if (!window.isSecureContext) {
            fail('Konum için güvenli HTTPS bağlantısı gerekir. Aşağıdan il seçerek devam edebilirsiniz.');
            return;
        }
        if (!navigator.geolocation) {
            fail('Bu tarayıcı konumu desteklemiyor. Aşağıdan il seçebilirsiniz.');
            return;
        }
        locate.disabled = true;
        status.textContent = 'Konumunuz bulunuyor… Sorulursa konum iznini onaylayın.';
        const found = async position => {
            const {latitude, longitude, accuracy} = position.coords ?? {};
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude) ||
                Math.abs(latitude) > 90 || Math.abs(longitude) > 180 ||
                !Number.isFinite(accuracy) || accuracy < 0 || accuracy > 5000) {
                fail('Konumunuz yeterince kesin değil. Yanlış il seçilmemesi için aşağıdan il seçerek devam edin.');
                return;
            }
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const save = async () => {
                    if (!directory) return;
                    const saved = await fetch(directory.dataset.locationUrl, {
                        method:'POST', credentials:'same-origin', signal:controller.signal,
                        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:JSON.stringify({latitude,longitude,accuracy})
                    });
                    if (!saved.ok) throw new Error('location-save');
                };
                if (directory?.dataset.results === '1') {
                    await save();
                    const target = new URL(window.location.href);
                    target.searchParams.set('sort','distance');target.searchParams.delete('page');
                    window.location.assign(target.href);
                    return;
                }
                const query = new URLSearchParams({latitude, longitude, localityLanguage:'tr'});
                const response = await fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?' + query, {
                    signal:controller.signal, credentials:'omit', referrerPolicy:'no-referrer'
                });
                if (!response.ok) throw new Error('geo');
                const data = await response.json();
                if (data?.countryCode !== 'TR') throw new Error('country');
                const code = (data.principalSubdivisionCode || '').replace(/^TR-/, '').padStart(2, '0');
                await save();
                if (code === '34') {
                    const sides = document.getElementById('istanbul-sides');
                    sides.hidden = false;
                    status.textContent = 'İstanbul bulundu. Devam etmek için yakanızı seçin.';
                    sides.querySelector('a').focus();
                    locate.disabled = false;
                } else {
                    const city = cities.find(city => city.dataset.code.padStart(2,'0') === code);
                    if (!city) throw new Error('city');
                    window.location.assign(city.href);
                }
            } catch (_) { fail(); } finally { clearTimeout(timeout); }
        };
        const unavailable = error => {
            const messages = {
                1:'Konum izni verilmedi. Telefonunuzun ve tarayıcınızın konum iznini açabilir veya aşağıdan il seçebilirsiniz.',
                2:'Cihazınız konumunu belirleyemedi. Konum servislerini kontrol edin veya aşağıdan il seçerek devam edin.',
                3:'Konum bulma işlemi uzun sürdü. Tekrar deneyebilir veya aşağıdan il seçebilirsiniz.'
            };
            fail(messages[error?.code]);
        };
        try {
            navigator.geolocation.getCurrentPosition(found, unavailable, {
                enableHighAccuracy:true, timeout:15000, maximumAge:0
            });
        } catch (_) { unavailable({code:1}); }
    });
})();
