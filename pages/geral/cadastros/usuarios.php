<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.usuarios');
$pdo = appPdo();
$erro = '';
$formSubmitted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = (string) ($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar') {
            $idUsuario = filter_var($_POST['idUsuario'] ?? 0, FILTER_VALIDATE_INT);
            $nome = trim((string) ($_POST['nomeCompleto'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $login = trim((string) ($_POST['login'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');
            $confirmarSenha = (string) ($_POST['confirmarSenha'] ?? '');
            $idPerfil = filter_var($_POST['idPerfil'] ?? 0, FILTER_VALIDATE_INT);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $bloqueado = isset($_POST['bloqueado']) ? 1 : 0;
            $forcarTrocaSenha = isset($_POST['forcarTrocaSenha']) ? 1 : 0;
            $tempoExpiracaoSessao = filter_var($_POST['tempoExpiracaoSessao'] ?? null, FILTER_VALIDATE_INT);
            $formSubmitted = [
                'idUsuario' => $idUsuario !== false ? $idUsuario : 0,
                'nomeCompleto' => $nome,
                'email' => $email,
                'login' => $login,
                'idPerfil' => $idPerfil !== false ? $idPerfil : 0,
                'ativo' => $ativo,
                'bloqueado' => $bloqueado,
                'forcarTrocaSenha' => $forcarTrocaSenha,
                'tempoExpiracaoSessao' => $tempoExpiracaoSessao !== false ? $tempoExpiracaoSessao : 30,
            ];

            $emailValido = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
            if (!$emailValido && $idUsuario !== false && $idUsuario > 0) {
                $storedEmailQuery = $pdo->prepare(
                    'SELECT email FROM tbUsuario WHERE idUsuario = :idUsuario'
                );
                $storedEmailQuery->execute(['idUsuario' => $idUsuario]);
                $storedEmail = $storedEmailQuery->fetchColumn();
                $emailValido = is_string($storedEmail) && hash_equals($storedEmail, $email);
            }

            if ($nome === '' || mb_strlen($nome) > 150 || !$emailValido
                || mb_strlen($email) > 190 || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $login)
                || !$idPerfil || ($idUsuario === false) || $idUsuario < 0) {
                throw new InvalidArgumentException('Confira o nome, e-mail, login e perfil selecionados.');
            }
            if ($tempoExpiracaoSessao === false || $tempoExpiracaoSessao < 30 || $tempoExpiracaoSessao > 180) {
                throw new InvalidArgumentException('O tempo de expiração da sessão deve ficar entre 30 e 180 minutos.');
            }
            if ($senha === '' && $idUsuario === 0) {
                throw new InvalidArgumentException('Informe uma senha para o novo usuário.');
            }
            if ($senha !== '' && $senha !== $confirmarSenha) {
                throw new InvalidArgumentException('A senha e a confirmação não são iguais.');
            }
            if ($senha !== '') {
                $passwordPolicyError = appPasswordPolicyError($senha);
                if ($passwordPolicyError !== null) {
                    throw new InvalidArgumentException($passwordPolicyError);
                }
            }

            $profileCheck = $pdo->prepare(
                'SELECT idPerfil, administradorGlobal FROM tbPerfilAcesso WHERE idPerfil = :idPerfil'
            );
            $profileCheck->execute(['idPerfil' => $idPerfil]);
            $selectedProfile = $profileCheck->fetch();
            if (!$selectedProfile) {
                throw new InvalidArgumentException('Selecione um perfil de acesso válido.');
            }

            if ($idUsuario > 0) {
                $parameters = [];
                $existingUser = $pdo->prepare(
                    'SELECT u.idUsuario, u.login, u.idPerfil, u.ativo, u.bloqueado, p.administradorGlobal
                     FROM tbUsuario u INNER JOIN tbPerfilAcesso p ON p.idPerfil = u.idPerfil
                     WHERE u.idUsuario = :idUsuario'
                );
                $existingUser->execute(['idUsuario' => $idUsuario]);
                $userToEdit = $existingUser->fetch();
                if (!$userToEdit) {
                    throw new InvalidArgumentException('Usuário não encontrado.');
                }
                $editingPrimaryAdmin = $userToEdit['login'] === 'admin-global';
                if ($editingPrimaryAdmin && (!appIsPrimaryAdmin($usuarioAtual)
                    || $login !== 'admin-global'
                    || (int) $selectedProfile['administradorGlobal'] !== 1
                    || (int) $selectedProfile['idPerfil'] !== (int) $userToEdit['idPerfil']
                    || $ativo !== 1 || $bloqueado !== 0)) {
                    throw new InvalidArgumentException('A conta admin-global e seu perfil não podem ser alterados por esta ação.');
                }
                if ($idUsuario === (int) $usuarioAtual['idUsuario'] && $bloqueado === 1) {
                    throw new InvalidArgumentException('Não é possível bloquear a própria conta.');
                }
                if (!$editingPrimaryAdmin && $login === 'admin-global') {
                    throw new InvalidArgumentException('O login admin-global é reservado.');
                }
                if ((int) $selectedProfile['administradorGlobal'] === 1 && !$editingPrimaryAdmin) {
                    throw new InvalidArgumentException('O perfil Administração Global só pode ser atribuído à conta admin-global.');
                }

                if ($senha !== '') {
                    $statement = $pdo->prepare(
                        'UPDATE tbUsuario
                         SET nomeCompleto = :nome, email = :email, login = :login, senhaHash = :senhaHash,
                             idPerfil = :idPerfil, ativo = :ativo, bloqueado = :bloqueado,
                             forcarTrocaSenha = :forcarTrocaSenha, tempoExpiracaoSessao = :tempoExpiracaoSessao
                         WHERE idUsuario = :idUsuario'
                    );
                    $parameters['senhaHash'] = password_hash($senha, PASSWORD_DEFAULT);
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE tbUsuario
                         SET nomeCompleto = :nome, email = :email, login = :login,
                             idPerfil = :idPerfil, ativo = :ativo, bloqueado = :bloqueado,
                             forcarTrocaSenha = :forcarTrocaSenha, tempoExpiracaoSessao = :tempoExpiracaoSessao
                         WHERE idUsuario = :idUsuario'
                    );
                }
                $parameters += [
                    'nome' => $nome, 'email' => $email, 'login' => $login,
                    'idPerfil' => $idPerfil, 'ativo' => $ativo, 'bloqueado' => $bloqueado,
                    'forcarTrocaSenha' => $forcarTrocaSenha,
                    'tempoExpiracaoSessao' => $tempoExpiracaoSessao,
                    'idUsuario' => $idUsuario,
                ];
                $statement->execute($parameters);
                header('Location: ' . appTabUrl('usuarios.php?resultado=atualizado'));
                exit;
            }
            if ($login === 'admin-global' || (int) $selectedProfile['administradorGlobal'] === 1) {
                throw new InvalidArgumentException('A conta e o perfil Administração Global são reservados.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbUsuario
                    (nomeCompleto, email, login, senhaHash, idPerfil, ativo, bloqueado,
                     forcarTrocaSenha, tempoExpiracaoSessao)
                 VALUES (:nome, :email, :login, :senhaHash, :idPerfil, :ativo, :bloqueado,
                         :forcarTrocaSenha, :tempoExpiracaoSessao)'
            );
            $statement->execute([
                'nome' => $nome,
                'email' => $email,
                'login' => $login,
                'senhaHash' => password_hash($senha, PASSWORD_DEFAULT),
                'idPerfil' => $idPerfil,
                'ativo' => $ativo,
                'bloqueado' => $bloqueado,
                'forcarTrocaSenha' => $forcarTrocaSenha,
                'tempoExpiracaoSessao' => $tempoExpiracaoSessao,
            ]);
            header('Location: ' . appTabUrl('usuarios.php?resultado=criado'));
            exit;
        }

        if ($acao === 'excluir') {
            $idUsuario = filter_var($_POST['idUsuario'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idUsuario || $idUsuario === (int) $usuarioAtual['idUsuario']) {
                throw new InvalidArgumentException('Não é permitido excluir a própria conta.');
            }

            $protectedUser = $pdo->prepare(
                'SELECT u.login, p.administradorGlobal
                 FROM tbUsuario u INNER JOIN tbPerfilAcesso p ON p.idPerfil = u.idPerfil
                 WHERE u.idUsuario = :idUsuario'
            );
            $protectedUser->execute(['idUsuario' => $idUsuario]);
            $userToDelete = $protectedUser->fetch();
            if (!$userToDelete) {
                throw new InvalidArgumentException('Usuário não encontrado.');
            }
            if ($userToDelete['login'] === 'admin-global') {
                throw new InvalidArgumentException('A conta admin-global é protegida e não pode ser excluída.');
            }

            $delete = $pdo->prepare('DELETE FROM tbUsuario WHERE idUsuario = :idUsuario');
            $delete->execute(['idUsuario' => $idUsuario]);
            header('Location: ' . appTabUrl('usuarios.php?resultado=excluido'));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Falha ao salvar usuário: ' . $exception->getMessage());
        $erro = 'Não foi possível salvar o usuário. Verifique se o login e o e-mail já estão cadastrados.';
    }
}

$perfis = $pdo->query(
    'SELECT idPerfil, nomePerfil, administradorGlobal
     FROM tbPerfilAcesso
     ORDER BY administradorGlobal DESC, nomePerfil'
)->fetchAll();
$usuarioEdicao = null;
$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($formSubmitted !== null && (int) $formSubmitted['idUsuario'] > 0) {
    $idEdicao = (int) $formSubmitted['idUsuario'];
}
if ($idEdicao) {
    $statement = $pdo->prepare(
        'SELECT u.idUsuario, u.nomeCompleto, u.email, u.login, u.idPerfil, u.ativo,
                u.bloqueado, u.forcarTrocaSenha, u.tempoExpiracaoSessao, p.administradorGlobal
         FROM tbUsuario u INNER JOIN tbPerfilAcesso p ON p.idPerfil = u.idPerfil
         WHERE u.idUsuario = :idUsuario'
    );
    $statement->execute(['idUsuario' => $idEdicao]);
    $usuarioEdicao = $statement->fetch() ?: null;
}
if ($formSubmitted !== null) {
    $usuarioEdicao = array_merge($usuarioEdicao ?? [], $formSubmitted);
}
$usuarios = $pdo->query(
    'SELECT u.idUsuario, u.nomeCompleto, u.email, u.login, u.ativo, u.bloqueado,
            p.nomePerfil, p.administradorGlobal
     FROM tbUsuario u INNER JOIN tbPerfilAcesso p ON p.idPerfil = u.idPerfil
     ORDER BY u.nomeCompleto'
)->fetchAll();
$resultado = [
    'criado' => 'Usuário cadastrado.',
    'atualizado' => 'Usuário atualizado.',
    'excluido' => 'Usuário excluído.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Elo</title>
    <link rel="icon" type="image/png" href="<?php echo appEscape(appBaseUrl() . '/assets/imgs/logo-elo.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/tailwind-config.js'); ?>"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Cadastro de Usuários</h1>
            <?php $abaInicial = ($usuarioEdicao || $erro !== '') ? 'manutencao' : 'pesquisa'; include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo $usuarioEdicao ? 'Alterar usuário' : 'Novo usuário'; ?></h2>
                <form method="post" data-confirm="Confirma gravar estes dados?" data-password-policy-form class="space-y-6">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idUsuario" value="<?php echo (int) ($usuarioEdicao['idUsuario'] ?? 0); ?>">
                    <section class="rounded-lg border border-gray-200 p-4">
                        <div class="mb-4">
                            <h3 class="font-semibold text-gray-800">Dados Pessoais</h3>
                            <p class="text-sm text-gray-500">Informações de identificação e contato do usuário.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">Nome Completo
                                <input name="nomeCompleto" required maxlength="150" value="<?php echo appEscape((string) ($usuarioEdicao['nomeCompleto'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700">E-mail
                                <input name="email" type="email" required maxlength="190" value="<?php echo appEscape((string) ($usuarioEdicao['email'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 p-4">
                        <div class="mb-4">
                            <h3 class="font-semibold text-gray-800">Credenciais de Acesso</h3>
                            <p class="text-sm text-gray-500">Login e senha utilizados para entrar no sistema.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">Login
                                <input name="login" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" value="<?php echo appEscape((string) ($usuarioEdicao['login'] ?? '')); ?>" <?php echo ($usuarioEdicao['login'] ?? '') === 'admin-global' ? 'readonly' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <div class="hidden md:block"></div>
                            <label class="text-sm font-medium text-gray-700">Nova Senha
                                <input name="senha" type="password" autocomplete="new-password" data-password-policy-input <?php echo $usuarioEdicao ? '' : 'required'; ?> minlength="8" aria-describedby="senha-ajuda" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <label class="text-sm font-medium text-gray-700">Confirmar Nova Senha
                                <input name="confirmarSenha" type="password" autocomplete="new-password" data-password-confirm-input <?php echo $usuarioEdicao ? '' : 'required'; ?> minlength="8" aria-describedby="senha-ajuda" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            </label>
                            <p id="senha-ajuda" class="text-xs text-gray-500 md:col-span-2">
                                <?php echo $usuarioEdicao
                                    ? 'Para manter a senha atual, deixe Nova Senha em branco. Ao alterá-la, use pelo menos 8 caracteres, com maiúscula, minúscula, número e caractere especial, e confirme ao lado.'
                                    : 'Use pelo menos 8 caracteres, com maiúscula, minúscula, número e caractere especial. Repita a senha no campo de confirmação.'; ?>
                            </p>
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 p-4">
                        <div class="mb-4">
                            <h3 class="font-semibold text-gray-800">Acesso e Sessão</h3>
                            <p class="text-sm text-gray-500">Defina o perfil, o status e as regras de segurança da conta.</p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">Perfil de Acesso
                                <select name="idPerfil" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                    <option value="">Selecione</option>
                                    <?php foreach ($perfis as $perfil): ?>
                                        <?php if ((int) $perfil['administradorGlobal'] === 1
                                            && (($usuarioEdicao['login'] ?? '') !== 'admin-global' || !appIsPrimaryAdmin($usuarioAtual))) {
                                            continue;
                                        } ?>
                                        <option value="<?php echo (int) $perfil['idPerfil']; ?>" <?php echo (int) ($usuarioEdicao['idPerfil'] ?? 0) === (int) $perfil['idPerfil'] ? 'selected' : ''; ?>>
                                            <?php echo appEscape($perfil['idPerfil']); ?> -
                                            <?php echo appEscape($perfil['nomePerfil']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">Expiração da Sessão
                                <select name="tempoExpiracaoSessao" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                                    <?php foreach ([30, 60, 90, 120, 150, 180] as $timeout): ?>
                                        <option value="<?php echo $timeout; ?>" <?php echo (int) ($usuarioEdicao['tempoExpiracaoSessao'] ?? 30) === $timeout ? 'selected' : ''; ?>>
                                            <?php echo $timeout; ?> minutos
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="flex items-center gap-2 rounded bg-gray-50 p-3 text-sm text-gray-700">
                                <input type="checkbox" name="ativo" value="1" <?php echo !$usuarioEdicao || (int) $usuarioEdicao['ativo'] === 1 ? 'checked' : ''; ?>>
                                Usuário Ativo
                            </label>
                            <label class="flex items-center gap-2 rounded bg-gray-50 p-3 text-sm text-gray-700">
                                <input type="checkbox" name="bloqueado" value="1" <?php echo (int) ($usuarioEdicao['bloqueado'] ?? 0) === 1 ? 'checked' : ''; ?>
                                    <?php echo ($usuarioEdicao['login'] ?? '') === 'admin-global' || (int) ($usuarioEdicao['idUsuario'] ?? 0) === (int) $usuarioAtual['idUsuario'] ? 'disabled' : ''; ?>>
                                Bloquear Acesso ao Login
                            </label>
                            <label class="flex items-center gap-2 rounded bg-gray-50 p-3 text-sm text-gray-700 md:col-span-2">
                                <input type="checkbox" name="forcarTrocaSenha" value="1" <?php echo (int) ($usuarioEdicao['forcarTrocaSenha'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                Forçar Troca de Senha no Próximo Login
                            </label>
                        </div>
                    </section>

                    <div class="flex gap-2">
                        <button class="inline-flex items-center gap-2 rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green">
                            <i class="ph ph-floppy-disk" aria-hidden="true"></i>
                            <?php echo $usuarioEdicao ? 'Salvar alterações' : 'Cadastrar usuário'; ?>
                        </button>
                        <?php if ($usuarioEdicao): ?>
                            <a href="usuarios.php#pesquisa" class="inline-flex items-center gap-2 rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">
                                <i class="ph ph-x-circle" aria-hidden="true"></i>
                                Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Usuários cadastrados</h2>
                    <div class="flex gap-2">
                        <input id="pesquisaUsuarios" data-filter-input="listaUsuarios" type="search" placeholder="Pesquisar usuários..." aria-label="Pesquisar usuários" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo usuário</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600"><tr><th class="p-3">Nome</th><th class="p-3">Login / e-mail</th><th class="p-3">Perfil</th><th class="p-3">Status</th><th class="p-3">Ações</th></tr></thead>
                        <tbody id="listaUsuarios">
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3"><?php echo appEscape($usuario['nomeCompleto']); ?></td>
                                <td class="p-3"><?php echo appEscape($usuario['login']); ?><br><span class="text-xs text-gray-500"><?php echo appEscape($usuario['email']); ?></span></td>
                                <td class="p-3"><?php echo appEscape($usuario['nomePerfil']); ?></td>
                                <td class="p-3">
                                    <?php echo (int) $usuario['bloqueado'] === 1
                                        ? 'Bloqueado'
                                        : ((int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo'); ?>
                                </td>
                                <td class="whitespace-nowrap p-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <?php if ($usuario['login'] !== 'admin-global' || appIsPrimaryAdmin($usuarioAtual)): ?>
                                            <a href="?editar=<?php echo (int) $usuario['idUsuario']; ?>#manutencao"
                                               aria-label="Alterar usuário <?php echo appEscape($usuario['nomeCompleto']); ?>"
                                               title="Alterar usuário"
                                               class="inline-flex h-9 w-9 items-center justify-center rounded border border-elo-teal/20 text-elo-teal transition-colors hover:bg-elo-teal/10 focus:outline-none focus:ring-2 focus:ring-elo-teal">
                                                <i class="ph ph-pencil-simple text-lg" aria-hidden="true"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Conta protegida</span>
                                        <?php endif; ?>
                                        <?php if ((int) $usuario['idUsuario'] !== (int) $usuarioAtual['idUsuario']
                                            && $usuario['login'] !== 'admin-global'): ?>
                                            <form method="post" data-confirm="Confirma excluir este usuário?">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="tabToken" value="<?php echo appEscape((string) appCurrentTabToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idUsuario" value="<?php echo (int) $usuario['idUsuario']; ?>">
                                                <button type="submit" aria-label="Excluir usuário <?php echo appEscape($usuario['nomeCompleto']); ?>"
                                                        title="Excluir usuário"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-600 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                    <i class="ph ph-trash text-lg" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php elseif ((int) $usuario['idUsuario'] === (int) $usuarioAtual['idUsuario']): ?>
                                            <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Conta atual</span>
                                        <?php else: ?>
                                            <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Conta global protegida</span>
                                        <?php endif; ?>
                                        <?php if ($usuario['login'] === 'admin-global' && !appIsPrimaryAdmin($usuarioAtual)): ?>
                                            <span class="text-xs text-gray-500">Apenas o admin-global pode gerenciar esta conta</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <?php $toastSuccess = $resultado; $toastError = $erro; include __DIR__ . '/../../../components/toast.php'; ?>
    <script src="<?php echo appEscape(appBaseUrl() . '/assets/js/app.js?v=' . appJavascriptVersion()); ?>"></script>
</body>
</html>
