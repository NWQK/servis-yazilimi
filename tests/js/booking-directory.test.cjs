const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/booking-directory.js'), 'utf8');

function browser(options = {}) {
    const element = (extra = {}) => ({ hidden: false, disabled: false, textContent: '', value: '', listeners: {},
        addEventListener(type, handler) { this.listeners[type] = handler; }, focus() { this.focused = true; }, ...extra });
    const cities = ['54','6','34-avrupa','34-anadolu'].map((code, i) => element({dataset:{code, city:['Sakarya','Ankara','İstanbul Avrupa','İstanbul Anadolu'][i]}, href:'https://example.test/randevu?region='+code}));
    const elements = {locate:element(), 'geo-status':element(), 'city-search':element(), 'city-empty':element({hidden:true}),
        'istanbul-sides':element({hidden:true,querySelector:()=>cities[2]})};
    if (options.persist) elements['directory-location']=element({dataset:{locationUrl:'https://example.test/randevu/konum',results:options.results ? '1':'0'}});
    const state = {calls:[], redirects:[], positions:[], timers:new Map(),completion:Promise.resolve()};
    let timerId=0;
    const ctx={document:{getElementById:id=>elements[id] ?? null,querySelectorAll:()=>cities,querySelector:()=>({content:'csrf-test'})},
        navigator: options.unsupported ? {} : {geolocation:{getCurrentPosition(success, fail, config) {
            state.positions.push(config);
            if (options.syncError) throw new Error('SecurityError');
            if (options.error) {fail({code:options.error});return;}
            state.completion=success({coords:{latitude:40.77,longitude:30.4,accuracy:80,...options.coords}});
        }}}, window:{isSecureContext:options.secure !== false,location:{href:'https://example.test/randevu?region=54&vehicle=otomobil&service=1&page=2',assign:url=>state.redirects.push(url)}},
        URL,URLSearchParams,AbortController,
        setTimeout:(fn,ms)=>{const id=++timerId;state.timers.set(id,{fn,ms});return id;},
        clearTimeout:id=>state.timers.delete(id),
        fetch:async (url,config)=>{
            state.calls.push({url,config});
            if (config.method==='POST') return {ok:!options.saveError};
            if (options.networkError) throw new Error('network');
            if (options.hang) return new Promise((resolve,reject)=>config.signal.addEventListener('abort',()=>reject(new Error('AbortError'))));
            return {ok:options.httpOk !== false,json:async()=>Object.hasOwn(options,'data') ? options.data : {countryCode:'TR',principalSubdivisionCode:'TR-54'}};
        }};
    vm.runInNewContext(source, ctx);
    return {...state, state, cities,elements, async click() {elements.locate.listeners.click();await state.completion;}};
}

test('konum yalnızca kullanıcı butona bastığında istenir; Sakarya doğru seçilir',async()=>{
    const b=browser();assert.equal(b.state.positions.length,0);await b.click();
    assert.deepEqual(b.state.redirects,['https://example.test/randevu?region=54']);
    const u=new URL(b.state.calls[0].url);assert.equal(u.hostname,'api.bigdatacloud.net');
    assert.equal(u.searchParams.get('latitude'),'40.77');assert.equal(u.searchParams.get('localityLanguage'),'tr');
    assert.equal(b.state.calls[0].config.credentials,'omit');assert.equal(b.state.calls[0].config.referrerPolicy,'no-referrer');
    assert.equal(b.state.timers.size,0);
    assert.equal(b.state.positions[0].enableHighAccuracy,true);assert.equal(b.state.positions[0].maximumAge,0);
});
test('tek haneli il kodu doğru eşleşir',async()=>{const b=browser({data:{countryCode:'TR',principalSubdivisionCode:'TR-06'}});await b.click();assert.equal(b.state.redirects[0],'https://example.test/randevu?region=6');});
test('İstanbul bulunduğunda yaka uydurulmaz; iki yaka seçeneği açılır',async()=>{
    const b=browser({data:{countryCode:'TR',principalSubdivisionCode:'TR-34'}});await b.click();
    assert.equal(b.state.redirects.length,0);assert.equal(b.elements['istanbul-sides'].hidden,false);
    assert.equal(b.cities[2].focused,true);assert.equal(b.elements.locate.disabled,false);
});
test('konum API desteği yokken elle seçim açık kalır',async()=>{const b=browser({unsupported:true});await b.click();assert.equal(b.state.calls.length,0);assert.equal(b.elements.locate.disabled,false);assert.match(b.elements['geo-status'].textContent,/il seç/);});
test('izin reddinde izin açıklaması yapılır ve buton yeniden açılır',async()=>{const b=browser({error:1});await b.click();assert.equal(b.state.calls.length,0);assert.equal(b.elements.locate.disabled,false);assert.match(b.elements['geo-status'].textContent,/Konum izni/);});
test('GPS bulunamazsa elle seçim kullanılabilir',async()=>{const b=browser({error:2});await b.click();assert.equal(b.elements.locate.disabled,false);assert.match(b.elements['geo-status'].textContent,/il seç/);});
test('GPS zaman aşımında kullanıcıya açık bilgi verilir',async()=>{const b=browser({error:3});await b.click();assert.equal(b.elements.locate.disabled,false);assert.match(b.elements['geo-status'].textContent,/uzun|süre|zaman/i);});
test('HTTPS olmayan mobil bağlantıda konum isteği başlatılmaz',async()=>{const b=browser({secure:false});await b.click();assert.equal(b.state.positions.length,0);assert.match(b.elements['geo-status'].textContent,/HTTPS/);});
test('tarayıcı senkron güvenlik hatası fırlatsa da arayüz takılmaz',async()=>{const b=browser({syncError:true});await assert.doesNotReject(()=>b.click());assert.equal(b.elements.locate.disabled,false);});
for (const coords of [{latitude:NaN},{longitude:200},{latitude:91},{accuracy:100000}]) {
    test('geçersiz veya çok yaklaşık koordinatla yanlış il seçilmez '+JSON.stringify(coords),async()=>{const b=browser({coords});await b.click();assert.equal(b.state.calls.length,0);assert.equal(b.state.redirects.length,0);assert.equal(b.elements.locate.disabled,false);});
}
for (const options of [{httpOk:false},{networkError:true},{data:{countryCode:'DE',principalSubdivisionCode:'DE-BE'}},{data:{countryCode:'TR',principalSubdivisionCode:''}},{data:null}]) {
    test('sağlayıcı/ülke/il hatasında elle seçim korunur '+JSON.stringify(options),async()=>{const b=browser(options);await b.click();assert.equal(b.state.redirects.length,0);assert.equal(b.elements.locate.disabled,false);assert.equal(b.state.timers.size,0);});
}
test('yavaş konum servisi isteği zaman aşımında iptal edilir',async()=>{
    const b=browser({hang:true});const pending=b.click();const timer=[...b.state.timers.values()][0];assert.equal(timer.ms,8000);timer.fn();await pending;
    assert.equal(b.state.calls[0].config.signal.aborted,true);assert.equal(b.elements.locate.disabled,false);assert.equal(b.state.timers.size,0);
});
test('Türkçe il araması ve boş sonuç mesajı çalışır',()=>{const b=browser();b.elements['city-search'].value='İSTANBUL';b.elements['city-search'].listeners.input();assert.equal(b.cities[2].hidden,false);assert.equal(b.cities[0].hidden,true);b.elements['city-search'].value='yok';b.elements['city-search'].listeners.input();assert.equal(b.elements['city-empty'].hidden,false);});

test('izinli konum şifreli çerez için CSRF korumalı POST ile kaydedilir',async()=>{const b=browser({persist:true});await b.click();assert.equal(b.state.calls.length,2);const r=b.state.calls[1];assert.equal(r.config.method,'POST');assert.equal(r.config.credentials,'same-origin');assert.equal(r.config.headers['X-CSRF-TOKEN'],'csrf-test');assert.equal(JSON.parse(r.config.body).latitude,40.77);assert.equal(new URL(b.state.redirects[0]).searchParams.has('latitude'),false);});
test('sonuçlarda konum il seçimini değiştirmez, üçüncü tarafa gönderilmez ve sayfa sıfırlanır',async()=>{const b=browser({persist:true,results:true});await b.click();assert.equal(b.state.calls.length,1);assert.equal(b.state.calls[0].config.method,'POST');const u=new URL(b.state.redirects[0]);assert.equal(u.searchParams.get('region'),'54');assert.equal(u.searchParams.get('sort'),'distance');assert.equal(u.searchParams.has('page'),false);assert.equal(u.searchParams.has('latitude'),false);});
test('konum kaydedilemezse mesafe sıralaması varmış gibi yönlendirilmez',async()=>{const b=browser({persist:true,results:true,saveError:true});await b.click();assert.equal(b.state.redirects.length,0);assert.equal(b.elements.locate.disabled,false);});
