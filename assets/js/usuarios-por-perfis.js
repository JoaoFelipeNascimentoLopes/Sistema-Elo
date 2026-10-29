document.addEventListener('DOMContentLoaded', () => {
    const tabs = Array.from(document.querySelectorAll('[data-report-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-report-panel]'));

    const activateTab = (tabKey) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.reportTab === tabKey;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            tab.classList.toggle('border-elo-teal', active);
            tab.classList.toggle('text-elo-teal', active);
            tab.classList.toggle('border-transparent', !active);
            tab.classList.toggle('text-gray-500', !active);
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.reportPanel !== tabKey;
        });
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.reportTab)));
    if (tabs.length) activateTab('filtros');

    const filterList = document.getElementById('filter-list');
    const filterEmpty = document.getElementById('filter-empty');
    const filterTemplate = document.getElementById('filter-row-template');
    const profiles = JSON.parse(filterList?.dataset.profiles || '[]');
    const initialFilters = JSON.parse(filterList?.dataset.initialFilters || '[]');
    const operatorsByType = {
        text: [
            ['contains', 'Contém'],
            ['equals', 'Igual a'],
            ['starts', 'Começa com']
        ],
        option: [['equals', 'Igual a']],
        profile: [['equals', 'Igual a']],
        status: [['equals', 'Igual a']],
        date: [['between', 'Entre']]
    };

    const updateFilterEmpty = () => {
        if (filterEmpty) filterEmpty.hidden = Boolean(filterList?.children.length);
    };

    const renderFilterValue = (row, index, value = '') => {
        const fieldSelect = row.querySelector('[data-filter-field]');
        const fieldKey = fieldSelect.value;
        const fieldType = fieldSelect.selectedOptions[0].dataset.type;
        const valueContainer = row.querySelector('[data-filter-value]');
        valueContainer.replaceChildren();

        if (fieldType === 'profile') {
            const selectedProfiles = new Set(
                (Array.isArray(value) ? value : [value]).map(String)
            );
            const wrapper = document.createElement('details');
            wrapper.className = 'report-profile-combobox';
            const summary = document.createElement('summary');
            summary.className = 'report-profile-combobox-trigger';
            summary.setAttribute('aria-label', `Selecionar perfis para o filtro ${fieldKey}`);
            const summaryText = document.createElement('span');
            summaryText.dataset.profileSummary = '';
            const icon = document.createElement('i');
            icon.className = 'ph ph-caret-down';
            icon.setAttribute('aria-hidden', 'true');
            summary.append(summaryText, icon);

            const options = document.createElement('div');
            options.className = 'report-profile-combobox-options';
            options.setAttribute('role', 'group');
            options.setAttribute('aria-label', 'Perfis de acesso');
            profiles.forEach((profile) => {
                const label = document.createElement('label');
                label.className = 'report-profile-combobox-option';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = `filters[${index}][value][]`;
                checkbox.value = profile.id;
                checkbox.checked = selectedProfiles.has(profile.id);
                checkbox.dataset.profileFilterValue = '';
                checkbox.setAttribute('aria-label', profile.name);
                const profileName = document.createElement('span');
                profileName.dataset.preserveCase = '';
                profileName.textContent = profile.name;
                label.append(checkbox, profileName);
                options.append(label);
            });
            const updateSummary = () => {
                const checkedBoxes = Array.from(options.querySelectorAll('input:checked'));
                const selectedNames = checkedBoxes.map((checkbox) =>
                    profiles.find((profile) => profile.id === checkbox.value)?.name
                ).filter(Boolean);
                summaryText.textContent = selectedNames.length === 0
                    ? 'Selecione os perfis'
                    : selectedNames.length <= 2
                        ? selectedNames.join(', ')
                        : `${selectedNames.length} perfis selecionados`;
            };
            options.addEventListener('change', updateSummary);
            wrapper.append(summary, options);
            valueContainer.append(wrapper);
            updateSummary();
            return;
        }

        if (fieldType === 'date') {
            const dateRange = document.createElement('div');
            dateRange.className = 'grid grid-cols-2 gap-2';
            [['from', 'De'], ['to', 'Até']].forEach(([part, label]) => {
                const inputLabel = document.createElement('label');
                inputLabel.className = 'text-xs text-gray-600';
                inputLabel.textContent = label;
                const input = document.createElement('input');
                input.type = 'date';
                input.name = `filters[${index}][value][${part}]`;
                input.dataset.filterDatePart = part;
                input.className = 'mt-1 w-full rounded border border-gray-300 bg-white px-2 py-2 text-sm';
                input.value = value && typeof value === 'object' ? value[part] || '' : '';
                input.setAttribute('aria-label', `${label} data do filtro ${fieldKey}`);
                inputLabel.append(input);
                dateRange.append(inputLabel);
            });
            valueContainer.append(dateRange);
            return;
        }

        const control = document.createElement(['status', 'option'].includes(fieldType) ? 'select' : 'input');
        control.className = 'w-full rounded border border-gray-300 bg-white px-2 py-2 text-sm';
        control.name = `filters[${index}][value]`;
        control.dataset.filterValueInput = '';
        control.setAttribute('aria-label', `Valor do filtro ${fieldKey}`);
        if (fieldType === 'status') {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione o status';
            control.append(placeholder);
            [['1', 'Ativo'], ['0', 'Inativo']].forEach(([optionValue, label]) => {
                const option = document.createElement('option');
                option.value = optionValue;
                option.textContent = label;
                control.append(option);
            });
        } else if (fieldType === 'option') {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione uma opção';
            control.append(placeholder);
            const options = JSON.parse(fieldSelect.selectedOptions[0].dataset.options || '{}');
            Object.entries(options).forEach(([optionValue, label]) => {
                const option = document.createElement('option');
                option.value = optionValue;
                option.textContent = label;
                control.append(option);
            });
        } else {
            control.type = 'search';
            control.placeholder = 'Informe o valor';
        }
        control.value = value;
        valueContainer.append(control);
    };

    const readFilterValue = (row) => {
        const profileInputs = row.querySelectorAll('[data-profile-filter-value]');
        if (profileInputs.length) {
            return Array.from(profileInputs)
                .filter((input) => input.checked)
                .map((input) => input.value);
        }
        const control = row.querySelector('[data-filter-value-input]');
        if (control) return control.value;
        const dates = row.querySelectorAll('[data-filter-date-part]');
        if (dates.length) {
            return Object.fromEntries(Array.from(dates, (input) => [input.dataset.filterDatePart, input.value]));
        }
        return '';
    };

    const reindexFilters = () => {
        Array.from(filterList.children).forEach((row, index) => {
            row.querySelector('[data-filter-field]').name = `filters[${index}][field]`;
            row.querySelector('[data-filter-operator]').name = `filters[${index}][operator]`;
            renderFilterValue(row, index, readFilterValue(row));
        });
        updateFilterEmpty();
    };

    const addFilter = (filter = {}) => {
        const row = filterTemplate.content.firstElementChild.cloneNode(true);
        const fieldSelect = row.querySelector('[data-filter-field]');
        const operatorSelect = row.querySelector('[data-filter-operator]');
        const index = filterList.children.length;
        if (filter.field) fieldSelect.value = filter.field;

        const updateOperators = (selectedOperator = '') => {
            const type = fieldSelect.selectedOptions[0].dataset.type;
            operatorSelect.replaceChildren();
            operatorsByType[type].forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                operatorSelect.append(option);
            });
            if (selectedOperator && operatorsByType[type].some(([value]) => value === selectedOperator)) {
                operatorSelect.value = selectedOperator;
            }
        };

        updateOperators(filter.operator);
        fieldSelect.name = `filters[${index}][field]`;
        operatorSelect.name = `filters[${index}][operator]`;
        renderFilterValue(row, index, filter.value ?? '');
        fieldSelect.addEventListener('change', () => {
            updateOperators();
            renderFilterValue(row, Array.from(filterList.children).indexOf(row));
        });
        filterList.append(row);
        updateFilterEmpty();
    };

    document.querySelector('[data-add-filter]')?.addEventListener('click', () => addFilter());
    filterList?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-filter]');
        if (!removeButton) return;
        removeButton.closest('.report-filter-row').remove();
        reindexFilters();
    });
    initialFilters.forEach((filter) => addFilter(filter));
    updateFilterEmpty();

    const groupList = document.getElementById('group-list');
    const groupTemplate = document.getElementById('group-row-template');
    const initialGroups = JSON.parse(groupList?.dataset.initialGroups || '[]');
    const addGroupButton = document.querySelector('[data-add-group]');

    const updateGroupOptions = () => {
        if (!groupList) return;
        const selectedValues = Array.from(groupList.querySelectorAll('[data-group-field]'), (select) => select.value);
        groupList.querySelectorAll('[data-group-field]').forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.disabled = selectedValues.includes(option.value) && option.value !== select.value;
            });
        });
        if (addGroupButton) {
            addGroupButton.disabled = selectedValues.length >= groupTemplate.content.querySelector('select').options.length;
        }
    };

    const addGroup = (groupKey) => {
        const row = groupTemplate.content.firstElementChild.cloneNode(true);
        const select = row.querySelector('[data-group-field]');
        select.value = groupKey || Array.from(select.options)
            .find((option) => !Array.from(groupList.querySelectorAll('[data-group-field]'), (item) => item.value).includes(option.value))?.value;
        if (!select.value) return;
        select.addEventListener('change', updateGroupOptions);
        groupList.append(row);
        updateGroupOptions();
    };

    initialGroups.forEach((groupKey) => addGroup(groupKey));
    addGroupButton?.addEventListener('click', () => addGroup());
    groupList?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-group]');
        if (!removeButton) return;
        removeButton.closest('.report-grouping-row').remove();
        updateGroupOptions();
    });
    updateGroupOptions();

    document.getElementById('paper-size')?.addEventListener('change', (event) => {
        document.getElementById('custom-paper-dimensions')?.classList.toggle('hidden', event.target.value !== 'custom');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-order-up], [data-order-down]');
        if (!button) return;
        const item = button.closest('.report-grouping-row');
        if (!item) return;
        if (button.hasAttribute('data-order-up')) {
            item.previousElementSibling?.before(item);
        } else {
            item.nextElementSibling?.after(item);
        }
    });

    document.querySelector('[data-report-print]')?.addEventListener('click', () => window.print());
});
