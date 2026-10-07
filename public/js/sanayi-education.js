(() => {
    const search = document.getElementById('learn-search');
    if (!search) return;
    const topics = [...document.querySelectorAll('.learn-topic')];
    const sections = [...document.querySelectorAll('.learn-group')];
    const buttons = [...document.querySelectorAll('[data-group]')];
    const normalize = text => text.toLocaleLowerCase('tr-TR').normalize('NFD').replace(/\p{M}/gu, '').replace(/ı/g, 'i');
    const searchable = new Map(topics.map(topic => [topic, normalize(topic.textContent)]));
    let selected = 'all';
    function filter() {
        const words = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
        let count = 0;
        topics.forEach(topic => {
            const visible = (selected === 'all' || topic.dataset.topicGroup === selected) && words.every(word => searchable.get(topic).includes(word));
            topic.hidden = !visible;
            if (words.length && visible) topic.open = true;
            if (visible) count++;
        });
        sections.forEach(section => { section.hidden = ![...section.querySelectorAll('.learn-topic')].some(topic => !topic.hidden); });
        buttons.forEach(button => {
            const active = button.dataset.group === selected;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        document.getElementById('learn-results').textContent = count + ' konu görüntüleniyor';
        document.getElementById('learn-empty').hidden = count !== 0;
    }
    buttons.forEach(button => button.addEventListener('click', () => { selected = button.dataset.group; filter(); }));
    let timer;
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(filter, 120); });
    search.addEventListener('search', filter);
    document.getElementById('learn-reset').addEventListener('click', () => {
        search.value = ''; selected = 'all'; filter(); search.focus();
    });
    function openTopic(hash) {
        const topic = topics.find(item => '#' + item.id === hash);
        if (!topic) return;
        selected = 'all'; search.value = ''; filter(); topic.open = true;
        requestAnimationFrame(() => topic.scrollIntoView({block: 'start'}));
    }
    document.querySelectorAll('a[href^="#"]').forEach(link => link.addEventListener('click', () => openTopic(link.getAttribute('href'))));
    window.addEventListener('hashchange', () => openTopic(location.hash));
    openTopic(location.hash);
})();
