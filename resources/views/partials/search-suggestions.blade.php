<style>
    .search-suggest-form { position: relative; }
    .search-suggestions { position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 300; display: grid; gap: 0.25rem; padding: 0.4rem; border: 1px solid #e8e2d8; border-radius: 10px; background: #fff; box-shadow: 0 10px 28px rgba(0,0,0,.16); }
    .search-suggestions[hidden] { display: none; }
    .search-suggestion { display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; padding: 0.65rem 0.75rem; border: 0; border-radius: 7px; background: transparent; color: #2d2d2d; cursor: pointer; font: inherit; text-align: left; }
    .search-suggestion:hover, .search-suggestion:focus-visible { background: #fff3f0; color: var(--coral); outline: none; }
    .search-suggestion small { color: #999; font-size: 0.72rem; white-space: nowrap; }
    body.dark .search-suggestions { background: #1b1b1b; border-color: #333; }
    body.dark .search-suggestion { color: #e5e7eb; }
    body.dark .search-suggestion:hover, body.dark .search-suggestion:focus-visible { background: #2a2a2a; color: #ff8068; }
    body.dark .search-suggestion small { color: #9ca3af; }
</style>
<script>
    (() => {
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
                            input.value = item.name;
                            hideSuggestions();
                            if (typeof form.requestSubmit === 'function') form.requestSubmit();
                            else form.submit();
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
    })();
</script>