const inputs = document.querySelectorAll('[data-product-search]');

inputs.forEach(input => {
    const form = input.closest('form');
    if (!form) return;
    form.classList.add('search-suggest-form');
    const dropdown = document.createElement('div');
    dropdown.className = 'search-suggestions';
    dropdown.hidden = true;
    form.appendChild(dropdown);
    let requestId = 0;
    let timer;

    const hideSuggestions = () => {
        dropdown.hidden = true;
        dropdown.replaceChildren();
    };

    const showSuggestions = (data) => {
        dropdown.replaceChildren();
        [...(data.products || []), ...(data.categories || []).map(category => ({ name: category, category: 'Category' }))]
            .slice(0, 8)
            .forEach(item => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'search-suggestion';
                const name = document.createElement('span');
                name.textContent = item.name;
                const detail = document.createElement('small');
                detail.textContent = item.category || 'Product';
                button.append(name, detail);
                button.addEventListener('click', () => {
                    const formAction = form.action || '/buyer/shop';
                    const params = new URLSearchParams();
                    if (item.category === 'Category') {
                        params.set('category', item.name);
                    } else {
                        params.set('search', item.name);
                    }

                    const targetUrl = new URL(formAction, window.location.origin);
                    targetUrl.search = params.toString();
                    window.location.href = targetUrl.toString();
                    hideSuggestions();
                });
                dropdown.appendChild(button);
            });
        dropdown.hidden = dropdown.childElementCount === 0;
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const query = input.value.trim();
        if (query.length < 2) { hideSuggestions(); return; }
        timer = setTimeout(async () => {
            const currentRequest = ++requestId;
            try {
                const response = await fetch(`/api/search-suggestions?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok || currentRequest !== requestId) return;
                showSuggestions(await response.json());
            } catch { hideSuggestions(); }
        }, 180);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2) input.dispatchEvent(new Event('input'));
    });
    document.addEventListener('click', event => {
        if (!form.contains(event.target)) hideSuggestions();
    });
});
