const runWhenReady = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
};

const normalizeSearchText = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

const normalizeUiTitleCase = (text) => {
    const lowercaseWords = new Set([
        'a', 'à', 'às', 'as', 'ao', 'aos', 'com', 'da', 'das', 'de', 'do', 'dos',
        'dum', 'duma', 'dumas', 'duns', 'e', 'em', 'entre', 'na', 'nas', 'no', 'nos',
        'num', 'numa', 'numas', 'nuns', 'o', 'os', 'ou', 'para', 'pela', 'pelas',
        'pelo', 'pelos', 'por', 'sem', 'sob', 'sobre', 'um', 'uma', 'umas', 'uns'
    ]);
    let wordIndex = 0;

    return text.replace(/(^|[^\p{L}\p{N}]+)([\p{L}][\p{L}'’]*)/gu, (match, separator, word) => {
        const normalizedWord = word.toLocaleLowerCase('pt-BR');
        const formattedWord = wordIndex > 0 && lowercaseWords.has(normalizedWord)
            ? normalizedWord
            : word.charAt(0).toLocaleUpperCase('pt-BR') + word.slice(1);
        wordIndex++;
        return separator + formattedWord;
    });
};

const applyUiTitleCase = () => {
    if (!document.body) return;

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;
            if (!parent || !node.nodeValue.trim()
                || parent.closest('script, style, textarea, input, code, pre, td, [contenteditable="true"], [data-preserve-case]')) {
                return NodeFilter.FILTER_REJECT;
            }
            return NodeFilter.FILTER_ACCEPT;
        }
    });
    const textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);

    textNodes.forEach((node) => {
        node.nodeValue = normalizeUiTitleCase(node.nodeValue);
    });
};

runWhenReady(() => {
    const tabTokenKey = 'sistemaEloTabToken';
    const createTabToken = () => {
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    };
    const queryTabToken = new URLSearchParams(window.location.search).get('tabToken');
    let tabToken = /^[a-f0-9]{32}$/.test(queryTabToken || '')
        ? queryTabToken
        : window.sessionStorage.getItem(tabTokenKey);
    if (!/^[a-f0-9]{32}$/.test(tabToken || '') || window.opener) {
        tabToken = createTabToken();
    }
    window.sessionStorage.setItem(tabTokenKey, tabToken);

    if ('BroadcastChannel' in window) {
        const tabChannel = new BroadcastChannel(`sistema-elo-tab-${tabToken}`);
        let tokenInUse = false;
        tabChannel.addEventListener('message', (event) => {
            if (event.data === 'claim') {
                tabChannel.postMessage('in-use');
            } else if (event.data === 'in-use') {
                tokenInUse = true;
            }
        });
        tabChannel.postMessage('claim');
        window.setTimeout(() => {
            if (!tokenInUse) return;
            tabToken = createTabToken();
            window.sessionStorage.setItem(tabTokenKey, tabToken);
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('tabToken', tabToken);
            window.location.replace(currentUrl.href);
        }, 100);
        window.addEventListener('pagehide', () => tabChannel.close(), { once: true });
    }

    const addTabTokenToUrl = (anchor, token = tabToken) => {
        const href = anchor.getAttribute('href');
        if (!href || href.startsWith('#')) return;
        const url = new URL(href, window.location.href);
        if (url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) return;
        url.searchParams.set('tabToken', token);
        anchor.href = url.href;
    };
    const addTabTokenToForm = (form) => {
        let input = form.querySelector('input[name="tabToken"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tabToken';
            form.append(input);
        }
        input.value = tabToken;
    };

    document.querySelectorAll('a[href]').forEach((anchor) => {
        if (anchor.target === '_blank') {
            addTabTokenToUrl(anchor, createTabToken());
            anchor.relList.add('noopener');
        } else {
            addTabTokenToUrl(anchor);
        }
    });
    document.querySelectorAll('form').forEach(addTabTokenToForm);
    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor) return;
        const opensNewTab = anchor.target === '_blank'
            || event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1;
        if (opensNewTab) {
            addTabTokenToUrl(anchor, createTabToken());
            anchor.relList.add('noopener');
        } else {
            addTabTokenToUrl(anchor);
        }
    }, true);
    document.addEventListener('contextmenu', (event) => {
        const anchor = event.target.closest('a[href]');
        if (anchor) addTabTokenToUrl(anchor, createTabToken());
    }, true);

    applyUiTitleCase();

    document.querySelectorAll('[data-institution-info-form]').forEach((form) => {
        const cnpjInput = form.querySelector('[data-institution-cnpj]');
        const phoneInput = form.querySelector('[data-institution-phone]');
        const cepInput = form.querySelector('[data-institution-cep]');
        const cepButton = form.querySelector('[data-cep-lookup]');
        const cepMessage = form.querySelector('[data-cep-message]');
        const lockedFieldsInput = form.querySelector('[data-cep-locked-fields]');
        const lookupCompletedInput = form.querySelector('[data-cep-lookup-completed]');
        const addressFieldNames = ['logradouro', 'bairro', 'cidade', 'uf'];
        const areaCodes = new Set([
            '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22', '24',
            '27', '28', '31', '32', '33', '34', '35', '37', '38', '41', '42', '43',
            '44', '45', '46', '47', '48', '49', '51', '53', '54', '55', '61', '62',
            '63', '64', '65', '66', '67', '68', '69', '71', '73', '74', '75', '77',
            '79', '81', '82', '83', '84', '85', '86', '87', '88', '89', '91', '92',
            '93', '94', '95', '96', '97', '98', '99'
        ]);
        if (!cnpjInput || !phoneInput || !cepInput || !cepButton || !cepMessage || !lockedFieldsInput || !lookupCompletedInput) return;

        const formatCnpj = (value) => {
            const characters = value.toLocaleUpperCase('pt-BR').replace(/[^A-Z0-9]/g, '').slice(0, 14);
            return characters.slice(0, 2)
                + (characters.length > 2 ? `.${characters.slice(2, 5)}` : '')
                + (characters.length > 5 ? `.${characters.slice(5, 8)}` : '')
                + (characters.length > 8 ? `/${characters.slice(8, 12)}` : '')
                + (characters.length > 12 ? `-${characters.slice(12, 14)}` : '');
        };
        const isValidCnpj = (value) => {
            const cnpj = value.replace(/[^A-Z0-9]/g, '');
            if (!/^[A-Z0-9]{12}\d{2}$/.test(cnpj) || /^([A-Z0-9])\1{13}$/.test(cnpj)) return false;
            const calculateDigit = (base, weights) => {
                const sum = Array.from(base, (character, index) => (character.charCodeAt(0) - 48) * weights[index])
                    .reduce((total, current) => total + current, 0);
                const remainder = sum % 11;
                return remainder < 2 ? 0 : 11 - remainder;
            };
            const first = calculateDigit(cnpj.slice(0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            const second = calculateDigit(cnpj.slice(0, 12) + first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            return cnpj.slice(-2) === `${first}${second}`;
        };
        const formatPhone = (value) => {
            const digits = value.replace(/\D/g, '').slice(0, 11);
            if (digits.length <= 2) return digits ? `(${digits}` : '';
            const areaCode = digits.slice(0, 2);
            const subscriber = digits.slice(2);
            const split = digits.length === 11 ? 5 : 4;
            return `(${areaCode}) ${subscriber.slice(0, split)}${subscriber.length > split ? `-${subscriber.slice(split)}` : ''}`;
        };
        const isValidPhone = (value) => {
            const digits = value.replace(/\D/g, '');
            if (!digits) return true;
            if (!areaCodes.has(digits.slice(0, 2)) || /^(\d)\1+$/.test(digits)) return false;
            const subscriber = digits.slice(2);
            return (digits.length === 10 && /^[2-5]\d{7}$/.test(subscriber))
                || (digits.length === 11 && /^9\d{8}$/.test(subscriber));
        };
        const updateCnpjValidity = () => {
            const empty = cnpjInput.value === '';
            cnpjInput.setCustomValidity(empty || isValidCnpj(cnpjInput.value) ? '' : 'Informe um CNPJ válido com 14 caracteres.');
        };
        const updatePhoneValidity = () => {
            phoneInput.setCustomValidity(isValidPhone(phoneInput.value) ? '' : 'Informe um telefone brasileiro válido com DDD.');
        };
        const digitsFromCep = () => cepInput.value.replace(/\D/g, '');
        const setCepMessage = (text, isError = false) => {
            cepMessage.textContent = text;
            cepMessage.classList.toggle('text-red-600', isError);
            cepMessage.classList.toggle('text-gray-500', !isError);
            cepMessage.classList.toggle('text-green-700', !isError && text.startsWith('Endereço preenchido'));
        };
        const updateCepValidity = () => {
            const digits = digitsFromCep();
            cepInput.setCustomValidity(!digits || (/^\d{8}$/.test(digits) && digits !== '00000000')
                ? ''
                : 'Informe um CEP válido com 8 dígitos.');
        };
        const getLockedFields = () => {
            try {
                const fields = JSON.parse(lockedFieldsInput.value);
                return Array.isArray(fields) ? fields.filter((field) => addressFieldNames.includes(field)) : [];
            } catch {
                return [];
            }
        };
        let lockedFields = getLockedFields();
        let lastCepValue = digitsFromCep();
        let lastLookupCep = lockedFields.length > 0 ? lastCepValue : '';
        let lookupTimer = null;
        let lookupController = null;
        let lookupSequence = 0;

        const clearAddressForCepChange = () => {
            addressFieldNames.forEach((fieldName) => {
                const field = form.elements.namedItem(fieldName);
                field.value = '';
                field.readOnly = false;
                field.removeAttribute('aria-readonly');
                field.removeAttribute('data-cep-autofilled');
                field.classList.remove('bg-gray-100', 'text-gray-600');
            });
            lockedFields = [];
            lockedFieldsInput.value = '[]';
            lookupCompletedInput.value = '0';
            lastLookupCep = '';
            setCepMessage('CEP alterado. Faça uma nova consulta ou preencha o endereço manualmente.');
        };

        const lookupAddress = async () => {
            const cep = digitsFromCep();
            window.clearTimeout(lookupTimer);
            updateCepValidity();
            if (!/^\d{8}$/.test(cep) || cep === '00000000') {
                cepInput.reportValidity();
                return;
            }
            if (cep === lastLookupCep) return;

            if (lookupController) lookupController.abort();
            lookupController = new AbortController();
            const controller = lookupController;
            const sequence = ++lookupSequence;
            const payload = new FormData();
            payload.set('acao', 'consultarCep');
            payload.set('cep', cep);
            payload.set('csrfToken', form.elements.namedItem('csrfToken').value);
            payload.set('tabToken', form.elements.namedItem('tabToken').value);
            cepButton.disabled = true;
            setCepMessage('Consultando CEP...');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: payload,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal
                });
                const body = await response.text();
                let result;
                try {
                    result = JSON.parse(body);
                } catch {
                    throw new Error('Não foi possível interpretar a resposta da consulta de CEP.');
                }
                if (!response.ok) throw new Error(result.message || 'Não foi possível consultar o CEP.');
                if (sequence !== lookupSequence || digitsFromCep() !== cep) return;

                addressFieldNames.forEach((fieldName) => {
                    const field = form.elements.namedItem(fieldName);
                    field.value = result[fieldName] || '';
                });
                lockedFields = Array.isArray(result.lockedFields)
                    ? result.lockedFields.filter((field) => addressFieldNames.includes(field))
                    : [];
                lockedFields.forEach((fieldName) => {
                    const field = form.elements.namedItem(fieldName);
                    field.readOnly = true;
                    field.setAttribute('aria-readonly', 'true');
                    field.setAttribute('data-cep-autofilled', 'true');
                    field.classList.add('bg-gray-100', 'text-gray-600');
                });
                lockedFieldsInput.value = JSON.stringify(lockedFields);
                lookupCompletedInput.value = '1';
                lastLookupCep = cep;
                lastCepValue = cep;
                setCepMessage(lockedFields.length
                    ? 'Endereço preenchido pela consulta de CEP. Altere o CEP para liberar os campos.'
                    : 'CEP encontrado, mas o serviço não retornou os campos de endereço. Preencha-os manualmente.');
            } catch (error) {
                if (error.name !== 'AbortError' && sequence === lookupSequence) {
                    lookupCompletedInput.value = '0';
                    setCepMessage(error.message || 'Falha ao consultar o CEP. Tente novamente.', true);
                }
            } finally {
                if (sequence === lookupSequence) {
                    cepButton.disabled = false;
                    lookupController = null;
                }
            }
        };

        cnpjInput.addEventListener('input', () => {
            const cursorPosition = cnpjInput.selectionStart;
            const beforeCursor = cnpjInput.value.slice(0, cursorPosition).replace(/[^A-Z0-9]/gi, '').length;
            cnpjInput.value = formatCnpj(cnpjInput.value);
            let nextCursorPosition = 0;
            let charactersSeen = 0;
            while (nextCursorPosition < cnpjInput.value.length && charactersSeen < beforeCursor) {
                if (/[A-Z0-9]/i.test(cnpjInput.value[nextCursorPosition])) charactersSeen++;
                nextCursorPosition++;
            }
            cnpjInput.setSelectionRange(nextCursorPosition, nextCursorPosition);
            updateCnpjValidity();
        });
        phoneInput.addEventListener('input', () => {
            phoneInput.value = formatPhone(phoneInput.value);
            updatePhoneValidity();
        });
        cepInput.addEventListener('input', () => {
            const currentCep = digitsFromCep().slice(0, 8);
            cepInput.value = currentCep.length > 5
                ? `${currentCep.slice(0, 5)}-${currentCep.slice(5)}`
                : currentCep;
            updateCepValidity();
            if (currentCep !== lastCepValue) {
                if (lookupController) lookupController.abort();
                lookupSequence++;
                clearAddressForCepChange();
                lastCepValue = currentCep;
            }
            window.clearTimeout(lookupTimer);
            if (/^\d{8}$/.test(currentCep) && currentCep !== lastLookupCep) {
                lookupTimer = window.setTimeout(lookupAddress, 450);
            }
        });
        cepButton.addEventListener('click', lookupAddress);
        cnpjInput.addEventListener('blur', updateCnpjValidity);
        phoneInput.addEventListener('blur', updatePhoneValidity);
        updateCnpjValidity();
        updatePhoneValidity();
        updateCepValidity();
    });

    document.querySelectorAll('form[data-password-policy-form]').forEach((form) => {
        const passwordInput = form.querySelector('[data-password-policy-input]');
        const confirmationInput = form.querySelector('[data-password-confirm-input]');
        if (!passwordInput) return;

        const passwordLabel = passwordInput.closest('label');
        const rules = [
            ['length', 'Pelo menos 8 caracteres', (value) => Array.from(value).length >= 8],
            ['uppercase', 'Uma letra maiúscula', (value) => /\p{Lu}/u.test(value)],
            ['lowercase', 'Uma letra minúscula', (value) => /\p{Ll}/u.test(value)],
            ['number', 'Um número', (value) => /\p{N}/u.test(value)],
            ['special', 'Um caractere especial', (value) => /[\p{P}\p{S}]/u.test(value)]
        ];
        const feedback = document.createElement('ul');
        feedback.className = 'mt-2 space-y-1 text-xs text-gray-500';
        feedback.setAttribute('aria-live', 'polite');
        feedback.setAttribute('aria-label', 'Requisitos da senha');
        rules.forEach(([key, description]) => {
            const item = document.createElement('li');
            item.dataset.passwordRule = key;
            item.textContent = `○ ${description}`;
            feedback.append(item);
        });
        passwordLabel?.append(feedback);

        let confirmationFeedback = null;
        if (confirmationInput) {
            confirmationFeedback = document.createElement('p');
            confirmationFeedback.className = 'mt-1 hidden text-xs';
            confirmationFeedback.setAttribute('aria-live', 'polite');
            confirmationInput.closest('label')?.append(confirmationFeedback);
        }

        const validatePasswordPolicy = () => {
            const value = passwordInput.value;
            const isOptionalEmpty = !passwordInput.required && value === '';
            let policyIsValid = true;
            rules.forEach(([key, description, passes]) => {
                const item = feedback.querySelector(`[data-password-rule="${key}"]`);
                const valid = isOptionalEmpty || passes(value);
                policyIsValid = policyIsValid && valid;
                item.textContent = `${valid && !isOptionalEmpty ? '✓' : '○'} ${description}`;
                item.classList.toggle('text-green-700', valid && !isOptionalEmpty);
                item.classList.toggle('text-red-600', !valid && value !== '');
            });
            passwordInput.setCustomValidity(
                value !== '' && !policyIsValid
                    ? 'A senha deve atender a todos os requisitos listados.'
                    : ''
            );

            if (confirmationInput && confirmationFeedback) {
                const passwordEntered = value !== '';
                confirmationInput.required = passwordInput.required || passwordEntered;
                const shouldCompare = passwordEntered && confirmationInput.value !== '';
                const matches = !shouldCompare || value === confirmationInput.value;
                confirmationInput.setCustomValidity(matches ? '' : 'A confirmação não corresponde à nova senha.');
                confirmationInput.setAttribute('aria-invalid', String(!matches));
                confirmationFeedback.textContent = !shouldCompare
                    ? ''
                    : matches ? 'As senhas coincidem.' : 'A confirmação não corresponde à senha.';
                confirmationFeedback.classList.toggle('hidden', !shouldCompare);
                confirmationFeedback.classList.toggle('text-green-700', shouldCompare && matches);
                confirmationFeedback.classList.toggle('text-red-600', shouldCompare && !matches);
            }
        };

        passwordInput.addEventListener('input', validatePasswordPolicy);
        confirmationInput?.addEventListener('input', validatePasswordPolicy);
        form.addEventListener('reset', () => window.setTimeout(validatePasswordPolicy));
        validatePasswordPolicy();
    });

    const forcedPasswordModal = document.querySelector('[data-forced-password-modal]');
    if (forcedPasswordModal) {
        forcedPasswordModal.querySelector('input[name="senhaAtual"]')?.focus();
    }

    document.addEventListener('click', (event) => {
        const modalToggle = event.target.closest('[data-modal-toggle]');
        if (modalToggle) {
            const modal = document.getElementById(modalToggle.dataset.modalToggle);
            if (modal) modal.classList.toggle('hidden');
        }

        const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
        if (sidebarToggle) {
            const folder = sidebarToggle.closest('[data-sidebar-folder]');
            const menu = folder?.querySelector('nav');
            const icon = sidebarToggle.querySelector('i[id]');
            if (menu && icon) {
                const isHidden = menu.classList.toggle('hidden');
                menu.classList.toggle('flex', !isHidden);
                icon.classList.toggle('ph-plus-square', isHidden);
                icon.classList.toggle('ph-minus-square', !isHidden);
                sidebarToggle.setAttribute('aria-expanded', String(!isHidden));
            }
        }

        const permissionDrawerToggle = event.target.closest('[data-permission-drawer-toggle]');
        if (permissionDrawerToggle) {
            const drawer = document.getElementById(permissionDrawerToggle.getAttribute('aria-controls'));
            if (drawer) {
                const expanded = permissionDrawerToggle.getAttribute('aria-expanded') !== 'true';
                drawer.hidden = !expanded;
                permissionDrawerToggle.setAttribute('aria-expanded', String(expanded));
                permissionDrawerToggle.querySelector('i')?.classList.toggle('rotate-180', expanded);
            }
        }

        const tabOpener = event.target.closest('[data-tab-open]');
        if (tabOpener) {
            const tabs = document.querySelector('[data-page-tabs]');
            const target = tabOpener.dataset.tabOpen;
            const button = tabs?.querySelector(`[data-tab-target="${target}"]`);
            if (button) button.click();
        }

        const toastClose = event.target.closest('[data-toast-close]');
        if (toastClose) {
            dismissToast(toastClose.closest('[data-toast]'));
        }

        const passwordToggle = event.target.closest('[data-password-toggle]');
        if (passwordToggle) {
            const passwordInput = document.getElementById(passwordToggle.dataset.passwordToggle);
            const icon = passwordToggle.querySelector('[data-password-icon]');
            if (passwordInput) {
                const showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                passwordToggle.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Mostrar senha');
                passwordToggle.setAttribute('aria-pressed', String(showPassword));
                icon?.classList.toggle('ph-eye', !showPassword);
                icon?.classList.toggle('ph-eye-slash', showPassword);
            }
        }
    });

    document.addEventListener('submit', (event) => {
        const institutionForm = event.target.closest('[data-institution-info-form]');
        if (institutionForm) {
            event.preventDefault();
            const message = institutionForm.querySelector('[data-institution-info-message]');
            const submitButton = institutionForm.querySelector('button[type="submit"]');
            const showMessage = (text, success) => {
                message.textContent = text;
                message.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-green-50', 'text-green-700');
                message.classList.add(success ? 'bg-green-50' : 'bg-red-50', success ? 'text-green-700' : 'text-red-700');
            };

            submitButton.disabled = true;
            fetch(institutionForm.action, {
                method: 'POST',
                body: new FormData(institutionForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(async (response) => {
                    const body = await response.text();
                    let result;
                    try {
                        result = JSON.parse(body);
                    } catch {
                        throw new Error(body || 'Não foi possível salvar as informações da instituição.');
                    }
                    if (!response.ok) throw new Error(result.message || 'Não foi possível salvar as informações da instituição.');
                    showMessage(result.message, true);
                    institutionForm.querySelector('[data-cep-lookup-completed]').value = '0';
                })
                .catch((error) => {
                    showMessage(error.message || 'Falha ao salvar as informações da instituição. Tente novamente.', false);
                    if (!(error instanceof Error)) console.error(error);
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
            return;
        }

        const passwordForm = event.target.closest('[data-password-change-form]');
        if (passwordForm) {
            event.preventDefault();
            const message = passwordForm.querySelector('[data-password-change-message]');
            const submitButton = passwordForm.querySelector('button[type="submit"]');
            const newPassword = passwordForm.elements.namedItem('novaSenha').value;
            const confirmation = passwordForm.elements.namedItem('confirmarSenha').value;
            const showMessage = (text, success) => {
                message.textContent = text;
                message.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-green-50', 'text-green-700');
                message.classList.add(success ? 'bg-green-50' : 'bg-red-50', success ? 'text-green-700' : 'text-red-700');
            };

            if (newPassword !== confirmation) {
                showMessage('A confirmação não corresponde à nova senha.', false);
                return;
            }

            submitButton.disabled = true;
            fetch(passwordForm.action, {
                method: 'POST',
                body: new FormData(passwordForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(async (response) => {
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Não foi possível alterar a senha.');
                    passwordForm.reset();
                    if (passwordForm.hasAttribute('data-forced-password-change')) {
                        window.location.reload();
                        return;
                    }
                    showMessage(result.message, true);
                })
                .catch((error) => {
                    showMessage(error.message || 'Falha ao alterar a senha. Tente novamente.', false);
                    if (!(error instanceof Error)) console.error(error);
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
            return;
        }

        const form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });

    const countdown = document.querySelector('[data-session-countdown]');
    if (countdown) {
        let expiresAt = Number(countdown.dataset.expiresAt);
        let lastRefresh = Date.now();
        let refreshInProgress = false;

        const renderCountdown = () => {
            const remainingSeconds = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            countdown.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            if (remainingSeconds === 0) {
                window.location.assign(countdown.dataset.loginUrl);
            }
        };

        const refreshSession = () => {
            if (refreshInProgress || Date.now() - lastRefresh < 60000) return;
            lastRefresh = Date.now();
            refreshInProgress = true;
            const body = new URLSearchParams({ csrfToken: countdown.dataset.csrf });
            body.set('tabToken', tabToken);
            fetch(countdown.dataset.refreshUrl, {
                method: 'POST',
                body,
                headers: { 'X-Tab-Session': tabToken },
                credentials: 'same-origin'
            })
                .then(async (response) => {
                    const result = await response.json();
                    if (response.status === 401) {
                        window.location.assign(countdown.dataset.loginUrl);
                        return;
                    }
                    if (response.status === 403 && result.mustChangePassword) {
                        window.location.assign(countdown.dataset.passwordUrl);
                        return;
                    }
                    if (!response.ok) throw new Error('Não foi possível renovar a sessão.');
                    expiresAt = result.expiresAt;
                    countdown.dataset.expiresAt = String(expiresAt);
                    renderCountdown();
                })
                .catch((error) => console.error('Falha ao atualizar a sessão:', error))
                .finally(() => {
                    refreshInProgress = false;
                });
        };

        ['pointerdown', 'keydown', 'scroll'].forEach((eventName) => {
            document.addEventListener(eventName, refreshSession, { passive: true });
        });
        renderCountdown();
        window.setInterval(renderCountdown, 1000);
    }

    document.querySelectorAll('[data-page-tabs]').forEach((tabs) => {
        const buttons = Array.from(tabs.querySelectorAll('[data-tab-target]'));
        const panels = {
            pesquisa: document.getElementById('painel-pesquisa'),
            manutencao: document.getElementById('painel-manutencao')
        };

        const activate = (name, updateHash = false) => {
            const selected = panels[name] ? name : 'pesquisa';
            buttons.forEach((button) => {
                const active = button.dataset.tabTarget === selected;
                button.setAttribute('aria-selected', String(active));
                button.tabIndex = active ? 0 : -1;
                button.classList.toggle('border-elo-teal', active);
                button.classList.toggle('text-elo-teal', active);
                button.classList.toggle('border-transparent', !active);
                button.classList.toggle('text-gray-500', !active);
                button.classList.toggle('hover:text-gray-700', !active);
            });
            Object.entries(panels).forEach(([key, panel]) => {
                if (panel) panel.hidden = key !== selected;
            });

            if (updateHash && window.location.hash !== `#${selected}`) {
                window.history.replaceState(null, '', `#${selected}`);
            }
        };

        buttons.forEach((button, index) => {
            button.addEventListener('click', () => activate(button.dataset.tabTarget, true));
            button.addEventListener('keydown', (event) => {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();
                const direction = event.key === 'ArrowRight' ? 1 : -1;
                const next = buttons[(index + direction + buttons.length) % buttons.length];
                activate(next.dataset.tabTarget, true);
                next.focus();
            });
        });

        const hashTab = window.location.hash.slice(1);
        activate(panels[hashTab] ? hashTab : tabs.dataset.initialTab);
    });

    document.querySelectorAll('[data-permission-tab-wrapper]').forEach((wrapper) => {
        const buttons = Array.from(wrapper.querySelectorAll('[data-permission-tab-target]'));
        const panels = Array.from(wrapper.querySelectorAll('[data-permission-tab-panel]'));
        if (buttons.length === 0 || panels.length === 0) return;

        const activate = (name) => {
            const selected = buttons.some((button) => button.dataset.permissionTabTarget === name)
                ? name
                : buttons[0].dataset.permissionTabTarget;
            buttons.forEach((button) => {
                const active = button.dataset.permissionTabTarget === selected;
                button.setAttribute('aria-selected', String(active));
                button.tabIndex = active ? 0 : -1;
                button.classList.toggle('border-elo-teal', active);
                button.classList.toggle('text-elo-teal', active);
                button.classList.toggle('border-transparent', !active);
                button.classList.toggle('text-gray-500', !active);
                button.classList.toggle('hover:text-gray-700', !active);
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.permissionTabPanel !== selected;
            });
        };

        buttons.forEach((button, index) => {
            button.addEventListener('click', () => activate(button.dataset.permissionTabTarget));
            button.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const nextIndex = event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                        ? buttons.length - 1
                        : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
                const next = buttons[nextIndex];
                activate(next.dataset.permissionTabTarget);
                next.focus();
            });
        });

        activate(wrapper.dataset.initialPermissionTab);
    });

    document.querySelectorAll('[data-sidebar]').forEach((sidebar) => {
        const search = sidebar.querySelector('[data-sidebar-search]');
        const emptyMessage = sidebar.querySelector('[data-sidebar-empty]');
        const folders = Array.from(sidebar.querySelectorAll('[data-sidebar-folder]'));
        const initialFolderStates = folders.map((folder) => {
            const menu = folder.querySelector('nav');
            const button = folder.querySelector('[data-sidebar-toggle]');
            const icon = button?.querySelector('i[id]');
            return {
                folder,
                menuHidden: menu?.classList.contains('hidden') ?? true,
                iconClosed: icon?.classList.contains('ph-plus-square') ?? true
            };
        });

        search?.addEventListener('input', () => {
            const term = normalizeSearchText(search.value.trim());
            let visiblePages = 0;

            folders.forEach((folder) => {
                const menu = folder.querySelector('nav');
                const button = folder.querySelector('[data-sidebar-toggle]');
                const icon = button?.querySelector('i[id]');
                const links = Array.from(menu?.querySelectorAll('a') ?? []);
                let visibleInFolder = 0;

                links.forEach((link) => {
                    const matches = !term || normalizeSearchText(link.textContent.trim()).includes(term);
                    link.classList.toggle('hidden', !matches);
                    if (matches) visibleInFolder++;
                });

                visiblePages += visibleInFolder;
                if (term) {
                    const hasMatch = visibleInFolder > 0;
                    folder.classList.toggle('hidden', !hasMatch);
                    menu?.classList.toggle('hidden', !hasMatch);
                    menu?.classList.toggle('flex', hasMatch);
                    icon?.classList.toggle('ph-plus-square', !hasMatch);
                    icon?.classList.toggle('ph-minus-square', hasMatch);
                    button?.setAttribute('aria-expanded', String(hasMatch));
                }
            });

            if (!term) {
                initialFolderStates.forEach(({ folder, menuHidden, iconClosed }) => {
                    const menu = folder.querySelector('nav');
                    const button = folder.querySelector('[data-sidebar-toggle]');
                    const icon = button?.querySelector('i[id]');
                    folder.classList.remove('hidden');
                    menu?.classList.toggle('hidden', menuHidden);
                    menu?.classList.toggle('flex', !menuHidden);
                    icon?.classList.toggle('ph-plus-square', iconClosed);
                    icon?.classList.toggle('ph-minus-square', !iconClosed);
                    button?.setAttribute('aria-expanded', String(!menuHidden));
                });
            }

            if (emptyMessage) emptyMessage.classList.toggle('hidden', !term || visiblePages > 0);
        });

        sidebar.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (link && link.origin === window.location.origin && link.pathname === window.location.pathname) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-permission-module]').forEach((moduleFieldset) => {
        const selectAll = moduleFieldset.querySelector('[data-module-select-all]');
        const pages = Array.from(moduleFieldset.querySelectorAll('[data-module-page]'));
        if (!selectAll) return;

        const updateSelectAll = () => {
            const checkedCount = pages.filter((page) => page.checked).length;
            selectAll.checked = pages.length > 0 && checkedCount === pages.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < pages.length;
        };

        selectAll.addEventListener('change', () => {
            pages.forEach((page) => {
                page.checked = selectAll.checked;
            });
            selectAll.indeterminate = false;
        });
        pages.forEach((page) => page.addEventListener('change', updateSelectAll));
        updateSelectAll();
    });

    document.querySelectorAll('[data-filter-input]').forEach((input) => {
        const target = document.getElementById(input.dataset.filterInput);
        if (!target) return;
        input.addEventListener('input', () => {
            const term = normalizeSearchText(input.value);
            target.querySelectorAll('tr').forEach((row) => {
                row.hidden = !normalizeSearchText(row.textContent).includes(term);
            });
        });
    });

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        window.setTimeout(() => dismissToast(toast), 4000);
    });
});

function dismissToast(toast) {
    if (!toast || toast.dataset.dismissed === 'true') return;
    toast.dataset.dismissed = 'true';
    toast.classList.add('opacity-0', '-translate-y-2');
    window.setTimeout(() => toast.remove(), 200);
}
