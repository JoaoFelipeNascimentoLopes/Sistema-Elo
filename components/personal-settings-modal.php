<div id="configuracoesModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 flex">
    <div class="relative w-[450px] max-w-[95%] rounded-xl bg-white p-6 shadow-2xl">
        <button type="button" data-modal-toggle="configuracoesModal" class="absolute right-4 top-4 text-gray-400 transition-colors hover:text-gray-700" aria-label="Fechar">
            <i class="ph ph-x text-2xl"></i>
        </button>
        <h2 class="mb-5 text-xl font-semibold text-gray-800">Configurações Pessoais</h2>
        <p class="mb-4 text-sm text-gray-600">Acesse aqui as opções pessoais da sua conta.</p>
        <button type="button" data-modal-toggle="alterarSenhaModal" class="flex w-full items-center gap-3 rounded-lg border p-4 text-left text-gray-700 transition-colors hover:border-elo-green hover:bg-elo-green/10 hover:text-elo-teal">
            <i class="ph ph-lock-key text-2xl" aria-hidden="true"></i>
            <span class="font-medium">Alterar Senha</span>
        </button>
        <?php if (appCanAccess('instituicao.informacoes', $usuarioAtual)): ?>
            <button type="button" data-modal-toggle="informacoesInstituicaoModal"
               class="mt-3 flex w-full items-center gap-3 rounded-lg border p-4 text-left text-gray-700 transition-colors hover:border-elo-green hover:bg-elo-green/10 hover:text-elo-teal">
                <i class="ph ph-buildings text-2xl" aria-hidden="true"></i>
                <span class="font-medium">Informações da Instituição</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (appCanAccess('instituicao.informacoes', $usuarioAtual)): ?>
    <?php
    $institutionQuery = appPdo()->query(
        'SELECT cnpj, razaoSocial, nomeFantasia, email, telefone, cep, logradouro,
                numero, complemento, bairro, cidade, uf, camposEnderecoViaCep, site
         FROM tbInformacoesInstituicao
         WHERE idInformacoes = 1'
    );
    $institutionInfo = $institutionQuery->fetch(PDO::FETCH_ASSOC) ?: [];
    $institutionCnpj = (string) ($institutionInfo['cnpj'] ?? '');
    if (preg_match('/^[A-Z0-9]{14}$/i', $institutionCnpj)) {
        $institutionCnpj = strtoupper(preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $institutionCnpj));
    }
    $institutionPhone = preg_replace('/\D+/', '', (string) ($institutionInfo['telefone'] ?? ''));
    if (strlen($institutionPhone) === 10) {
        $institutionPhone = preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2-$3', $institutionPhone);
    } elseif (strlen($institutionPhone) === 11) {
        $institutionPhone = preg_replace('/^(\d{2})(\d{5})(\d{4})$/', '($1) $2-$3', $institutionPhone);
    }
    $institutionCep = preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', preg_replace('/\D+/', '', (string) ($institutionInfo['cep'] ?? '')));
    $cepLockedFields = json_decode((string) ($institutionInfo['camposEnderecoViaCep'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    $cepLockedFields = is_array($cepLockedFields)
        ? array_values(array_filter(
            $cepLockedFields,
            static fn ($field): bool => is_string($field)
                && in_array($field, ['logradouro', 'bairro', 'cidade', 'uf'], true)
        ))
        : [];
    $cepLockedJson = json_encode($cepLockedFields, JSON_THROW_ON_ERROR);
    ?>
    <div id="informacoesInstituicaoModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center bg-black/50 p-4">
        <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl md:p-6">
            <button type="button" data-modal-toggle="informacoesInstituicaoModal" class="absolute right-4 top-4 text-gray-400 transition-colors hover:text-gray-700" aria-label="Fechar">
                <i class="ph ph-x text-2xl"></i>
            </button>
            <h2 class="mb-1 pr-10 text-xl font-semibold text-gray-800">Informações da Instituição</h2>
            <p class="mb-5 text-sm text-gray-600">Estes dados poderão ser utilizados na emissão de recibos e documentos.</p>
            <form method="post" action="<?php echo appEscape($baseUrl . '/pages/informacoes-instituicao.php'); ?>"
                  data-institution-info-form class="space-y-5">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                <section>
                    <h3 class="mb-3 font-semibold text-gray-800">Identificação</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">CNPJ
                            <input name="cnpj" inputmode="text" autocomplete="off" maxlength="18" data-institution-cnpj value="<?php echo appEscape($institutionCnpj); ?>" placeholder="00.000.000/0000-00" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Razão Social
                            <input name="razaoSocial" maxlength="150" value="<?php echo appEscape((string) ($institutionInfo['razaoSocial'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Nome Fantasia
                            <input name="nomeFantasia" maxlength="150" value="<?php echo appEscape((string) ($institutionInfo['nomeFantasia'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </div>
                </section>
                <section class="border-t border-gray-200 pt-4">
                    <h3 class="mb-3 font-semibold text-gray-800">Contato</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">E-mail
                            <input type="email" name="email" maxlength="190" value="<?php echo appEscape((string) ($institutionInfo['email'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Telefone
                            <input name="telefone" type="tel" inputmode="tel" autocomplete="tel" maxlength="15" data-institution-phone value="<?php echo appEscape((string) $institutionPhone); ?>" placeholder="(00) 00000-0000" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                    </div>
                </section>
                <section class="border-t border-gray-200 pt-4">
                    <h3 class="mb-3 font-semibold text-gray-800">Endereço</h3>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">CEP
                                <input name="cep" inputmode="numeric" autocomplete="postal-code" maxlength="9" data-institution-cep value="<?php echo appEscape($institutionCep); ?>" placeholder="00000-000" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <button type="button" data-cep-lookup class="mt-2 rounded border border-elo-teal px-3 py-1.5 text-sm font-medium text-elo-teal hover:bg-elo-teal/5">
                                Buscar CEP
                            </button>
                            <p data-cep-message class="mt-1 text-xs text-gray-500" role="status" aria-live="polite">
                                <?php echo $cepLockedFields !== [] ? 'Endereço preenchido pela consulta de CEP. Altere o CEP para liberar os campos.' : 'Digite o CEP para preencher o endereço automaticamente.'; ?>
                            </p>
                            <input type="hidden" name="camposEnderecoViaCep" value="<?php echo appEscape($cepLockedJson); ?>" data-cep-locked-fields>
                            <input type="hidden" name="cepConsultado" value="0" data-cep-lookup-completed>
                        </div>
                        <label class="text-sm font-medium text-gray-700 md:col-span-3">Logradouro
                            <input name="logradouro" maxlength="150" value="<?php echo appEscape((string) ($institutionInfo['logradouro'] ?? '')); ?>" <?php echo in_array('logradouro', $cepLockedFields, true) ? 'readonly aria-readonly="true" data-cep-autofilled="true"' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2 <?php echo in_array('logradouro', $cepLockedFields, true) ? 'bg-gray-100 text-gray-600' : ''; ?>">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Número
                            <input name="numero" maxlength="20" value="<?php echo appEscape((string) ($institutionInfo['numero'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-3">Complemento
                            <input name="complemento" maxlength="100" value="<?php echo appEscape((string) ($institutionInfo['complemento'] ?? '')); ?>" <?php echo in_array('complemento', $cepLockedFields, true) ? 'readonly aria-readonly="true" data-cep-autofilled="true"' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2 <?php echo in_array('complemento', $cepLockedFields, true) ? 'bg-gray-100 text-gray-600' : ''; ?>">
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">Bairro
                            <input name="bairro" maxlength="100" value="<?php echo appEscape((string) ($institutionInfo['bairro'] ?? '')); ?>" <?php echo in_array('bairro', $cepLockedFields, true) ? 'readonly aria-readonly="true" data-cep-autofilled="true"' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2 <?php echo in_array('bairro', $cepLockedFields, true) ? 'bg-gray-100 text-gray-600' : ''; ?>">
                        </label>
                        <label class="text-sm font-medium text-gray-700">Cidade
                            <input name="cidade" maxlength="100" value="<?php echo appEscape((string) ($institutionInfo['cidade'] ?? '')); ?>" <?php echo in_array('cidade', $cepLockedFields, true) ? 'readonly aria-readonly="true" data-cep-autofilled="true"' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2 <?php echo in_array('cidade', $cepLockedFields, true) ? 'bg-gray-100 text-gray-600' : ''; ?>">
                        </label>
                        <label class="text-sm font-medium text-gray-700">UF
                            <input name="uf" maxlength="2" value="<?php echo appEscape((string) ($institutionInfo['uf'] ?? '')); ?>" <?php echo in_array('uf', $cepLockedFields, true) ? 'readonly aria-readonly="true" data-cep-autofilled="true"' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2 <?php echo in_array('uf', $cepLockedFields, true) ? 'bg-gray-100 text-gray-600' : ''; ?>">
                        </label>
                    </div>
                </section>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
                    <p data-institution-info-message class="hidden rounded p-2 text-sm" role="status" aria-live="polite"></p>
                    <button type="submit" class="inline-flex items-center gap-2 rounded bg-elo-teal px-5 py-2.5 font-medium text-white hover:bg-elo-green">
                        <i class="ph ph-floppy-disk" aria-hidden="true"></i> Salvar Informações
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div id="alterarSenhaModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 flex">
    <div class="relative w-[450px] max-w-[95%] rounded-xl bg-white p-6 shadow-2xl">
        <button type="button" data-modal-toggle="alterarSenhaModal" class="absolute right-4 top-4 text-gray-400 transition-colors hover:text-gray-700" aria-label="Fechar">
            <i class="ph ph-x text-2xl"></i>
        </button>
        <h2 class="mb-5 text-xl font-semibold text-gray-800">Alterar Senha</h2>
        <form method="post" action="<?php echo appEscape($baseUrl . '/auth/change-password.php'); ?>" data-password-change-form data-password-policy-form class="space-y-3">
            <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
            <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
            <label class="block text-sm font-medium text-gray-700">Senha Atual
                <input type="password" name="senhaAtual" autocomplete="current-password" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-gray-700">Nova Senha
                <input type="password" name="novaSenha" autocomplete="new-password" data-password-policy-input minlength="8" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-gray-700">Confirmar Nova Senha
                <input type="password" name="confirmarSenha" autocomplete="new-password" data-password-confirm-input minlength="8" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <p class="text-xs text-gray-500">Use pelo menos 8 caracteres, com uma letra maiúscula, uma minúscula, um número e um caractere especial.</p>
            <p data-password-change-message class="hidden rounded p-2 text-sm" role="status" aria-live="polite"></p>
            <button type="submit" class="rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">Salvar Nova Senha</button>
        </form>
    </div>
</div>

<?php if ((int) ($usuarioAtual['mustChangePassword'] ?? 0) === 1): ?>
    <div id="trocaObrigatoriaSenhaModal" data-forced-password-modal
         class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
            <h2 class="mb-2 text-xl font-semibold text-gray-800">Troca de Senha Obrigatória</h2>
            <p class="mb-5 text-sm text-gray-600">Por segurança, defina uma nova senha para continuar usando o sistema.</p>
            <form method="post" action="<?php echo appEscape($baseUrl . '/auth/change-password.php'); ?>"
                  data-password-change-form data-password-policy-form data-forced-password-change class="space-y-3">
                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                <label class="block text-sm font-medium text-gray-700">Senha Atual
                    <input type="password" name="senhaAtual" autocomplete="current-password" required autofocus
                           class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium text-gray-700">Nova Senha
                    <input type="password" name="novaSenha" autocomplete="new-password" data-password-policy-input minlength="8" required
                           class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium text-gray-700">Confirmar Nova Senha
                    <input type="password" name="confirmarSenha" autocomplete="new-password" data-password-confirm-input minlength="8" required
                           class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <p class="text-xs text-gray-500">Use pelo menos 8 caracteres, com uma letra maiúscula, uma minúscula, um número e um caractere especial.</p>
                <p data-password-change-message class="hidden rounded p-2 text-sm" role="status" aria-live="polite"></p>
                <button type="submit" class="rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">
                    Alterar Senha e Continuar
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
