(() => {
    const normalize = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');

    document.querySelectorAll('[data-searchable-combobox]').forEach((combobox) => {
        const input = combobox.querySelector('[data-searchable-input]');
        const valueInput = combobox.querySelector('[data-searchable-value]');
        const toggle = combobox.querySelector('[data-searchable-toggle]');
        const results = combobox.querySelector('[data-searchable-results]');
        const message = combobox.querySelector('[data-searchable-message]');
        const options = Array.from(results.querySelectorAll('[data-searchable-option]'));
        const emptyMessage = document.createElement('p');
        let activeIndex = -1;

        options.forEach((option, index) => {
            if (!option.id) option.id = `${input.id || 'searchable-combobox'}-option-${index}`;
        });
        emptyMessage.className = 'hidden px-3 py-2 text-sm text-gray-500';
        emptyMessage.textContent = 'Nenhum resultado encontrado.';
        emptyMessage.setAttribute('role', 'status');
        results.append(emptyMessage);

        const close = () => {
            results.classList.add('hidden');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        };
        const show = (query = '') => {
            const normalizedQuery = normalize(query.trim());
            let visibleCount = 0;
            options.forEach((option) => {
                const visible = normalize(option.dataset.label || option.textContent).includes(normalizedQuery);
                option.hidden = !visible;
                option.setAttribute('aria-selected', 'false');
                option.classList.remove('bg-elo-teal/10');
                if (visible) visibleCount++;
            });
            emptyMessage.classList.toggle('hidden', visibleCount > 0);
            results.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
            message.textContent = visibleCount === options.length
                ? 'Abra a lista ou digite para filtrar.'
                : `${visibleCount} opção(ões) encontrada(s).`;
        };
        const select = (option) => {
            input.value = option.dataset.label || option.textContent.trim();
            valueInput.value = option.dataset.value || '';
            input.setCustomValidity('');
            close();
        };

        options.forEach((option) => {
            option.addEventListener('pointerdown', (event) => event.preventDefault());
            option.addEventListener('click', () => select(option));
        });
        toggle.addEventListener('pointerdown', (event) => event.preventDefault());
        toggle.addEventListener('click', () => {
            if (input.getAttribute('aria-expanded') === 'true') close();
            else show();
        });
        input.addEventListener('focus', () => show());
        input.addEventListener('input', () => {
            valueInput.value = '';
            input.setCustomValidity(input.value.trim() === '' ? '' : 'Selecione uma opção da lista.');
            show(input.value);
        });
        input.addEventListener('keydown', (event) => {
            const visibleOptions = options.filter((option) => !option.hidden);
            if (event.key === 'Escape') {
                close();
                return;
            }
            if (input.getAttribute('aria-expanded') !== 'true') return;
            if (event.key === 'Enter' && input.getAttribute('aria-expanded') === 'true' && visibleOptions.length === 0) {
                event.preventDefault();
                return;
            }
            if (!visibleOptions.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
            event.preventDefault();
            if (event.key === 'Enter') {
                if (activeIndex >= 0) select(visibleOptions[activeIndex]);
                return;
            }
            activeIndex = event.key === 'ArrowDown'
                ? (activeIndex + 1) % visibleOptions.length
                : (activeIndex - 1 + visibleOptions.length) % visibleOptions.length;
            visibleOptions.forEach((option, index) => {
                const active = index === activeIndex;
                option.setAttribute('aria-selected', String(active));
                option.classList.toggle('bg-elo-teal/10', active);
            });
            input.setAttribute('aria-activedescendant', visibleOptions[activeIndex].id);
            visibleOptions[activeIndex].scrollIntoView({block: 'nearest'});
        });
        input.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (!combobox.contains(document.activeElement)) close();
            }, 100);
        });
        input.form?.addEventListener('submit', (event) => {
            if (valueInput.value !== '') return;
            input.setCustomValidity('Selecione uma opção da lista.');
            input.reportValidity();
            event.preventDefault();
            input.focus();
        });
    });
})();
