<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.usuarios');
$pdo = appPdo();
$erro = '';

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
            $idPerfil = filter_var($_POST['idPerfil'] ?? 0, FILTER_VALIDATE_INT);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '' || mb_strlen($nome) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || mb_strlen($email) > 190 || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $login)
                || !$idPerfil || ($idUsuario === false) || $idUsuario < 0
                || ($idUsuario === 0 && strlen($senha) < 12)
                || ($senha !== '' && strlen($senha) < 12)) {
                throw new InvalidArgumentException('Confira os dados. A senha deve ter ao menos 12 caracteres.');
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
                    'SELECT u.idUsuario, u.login, u.idPerfil, u.ativo, p.administradorGlobal
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
                    || $ativo !== 1)) {
                    throw new InvalidArgumentException('A conta admin-global e seu perfil não podem ser alterados por esta ação.');
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
                             idPerfil = :idPerfil, ativo = :ativo
                         WHERE idUsuario = :idUsuario'
                    );
                    $parameters['senhaHash'] = password_hash($senha, PASSWORD_DEFAULT);
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE tbUsuario
                         SET nomeCompleto = :nome, email = :email, login = :login,
                             idPerfil = :idPerfil, ativo = :ativo
                         WHERE idUsuario = :idUsuario'
                    );
                }
                $parameters += [
                    'nome' => $nome, 'email' => $email, 'login' => $login,
                    'idPerfil' => $idPerfil, 'ativo' => $ativo, 'idUsuario' => $idUsuario,
                ];
                $statement->execute($parameters);
                header('Location: usuarios.php?resultado=atualizado');
                exit;
            }
            if ($login === 'admin-global' || (int) $selectedProfile['administradorGlobal'] === 1) {
                throw new InvalidArgumentException('A conta e o perfil Administração Global são reservados.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbUsuario (nomeCompleto, email, login, senhaHash, idPerfil, ativo)
                 VALUES (:nome, :email, :login, :senhaHash, :idPerfil, :ativo)'
            );
            $statement->execute([
                'nome' => $nome,
                'email' => $email,
                'login' => $login,
                'senhaHash' => password_hash($senha, PASSWORD_DEFAULT),
                'idPerfil' => $idPerfil,
                'ativo' => $ativo,
            ]);
            header('Location: usuarios.php?resultado=criado');
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
            header('Location: usuarios.php?resultado=excluido');
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
if ($idEdicao) {
    $statement = $pdo->prepare(
        'SELECT u.idUsuario, u.nomeCompleto, u.email, u.login, u.idPerfil, u.ativo, p.administradorGlobal
         FROM tbUsuario u INNER JOIN tbPerfilAcesso p ON p.idPerfil = u.idPerfil
         WHERE u.idUsuario = :idUsuario'
    );
    $statement->execute(['idUsuario' => $idEdicao]);
    $usuarioEdicao = $statement->fetch() ?: null;
}
$usuarios = $pdo->query(
    'SELECT u.idUsuario, u.nomeCompleto, u.email, u.login, u.ativo, p.nomePerfil, p.administradorGlobal
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
    <title>Usuários - Sistema Elo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script><script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Poppins', 'sans-serif'] }, colors: { 'elo-teal': '#007C89', 'elo-green': '#48C0B5' } } } };</script>
</head>
<body class="flex h-screen flex-col overflow-hidden bg-gray-100 font-sans">
    <?php include __DIR__ . '/../../../components/geral/navbar-geral.php'; ?>
    <div class="flex flex-1 overflow-hidden">
        <?php include __DIR__ . '/../../../components/geral/sidebar-geral.php'; ?>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Cadastro de Usuários</h1>
            <?php $abaInicial = ($usuarioEdicao || $erro !== '') ? 'manutencao' : 'pesquisa'; include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
            <?php if ($erro !== ''): ?><p role="alert" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p><?php endif; ?>

            <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-4 text-lg font-semibold"><?php echo $usuarioEdicao ? 'Alterar usuário' : 'Novo usuário'; ?></h2>
                <form method="post" onsubmit="return confirm('Confirma gravar estes dados?')" class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idUsuario" value="<?php echo (int) ($usuarioEdicao['idUsuario'] ?? 0); ?>">
                    <label class="text-sm font-medium text-gray-700">Nome completo
                        <input name="nomeCompleto" required maxlength="150" value="<?php echo appEscape((string) ($usuarioEdicao['nomeCompleto'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-gray-700">E-mail
                        <input name="email" type="email" required maxlength="190" value="<?php echo appEscape((string) ($usuarioEdicao['email'] ?? '')); ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Login
                        <input name="login" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" value="<?php echo appEscape((string) ($usuarioEdicao['login'] ?? '')); ?>" <?php echo ($usuarioEdicao['login'] ?? '') === 'admin-global' ? 'readonly' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Senha <?php echo $usuarioEdicao ? '(deixe vazia para manter)' : '(mínimo 12 caracteres)'; ?>
                        <input name="senha" type="password" autocomplete="new-password" <?php echo $usuarioEdicao ? '' : 'required'; ?> minlength="12" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Perfil de acesso
                        <select name="idPerfil" required class="mt-1 w-full rounded border border-gray-300 bg-white px-3 py-2">
                            <option value="">Selecione</option>
                            <?php foreach ($perfis as $perfil): ?>
                                <?php if ((int) $perfil['administradorGlobal'] === 1
                                    && (($usuarioEdicao['login'] ?? '') !== 'admin-global' || !appIsPrimaryAdmin($usuarioAtual))) {
                                    continue;
                                } ?>
                                <option value="<?php echo (int) $perfil['idPerfil']; ?>" <?php echo (int) ($usuarioEdicao['idPerfil'] ?? 0) === (int) $perfil['idPerfil'] ? 'selected' : ''; ?>>
                                    <?php echo appEscape($perfil['nomePerfil']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                        <input type="checkbox" name="ativo" value="1" <?php echo !$usuarioEdicao || (int) $usuarioEdicao['ativo'] === 1 ? 'checked' : ''; ?>>
                        Usuário ativo
                    </label>
                    <div class="flex gap-2 md:col-span-2">
                        <button class="rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green"><?php echo $usuarioEdicao ? 'Salvar alterações' : 'Cadastrar usuário'; ?></button>
                        <?php if ($usuarioEdicao): ?><a href="usuarios.php#pesquisa" class="rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Usuários cadastrados</h2>
                    <div class="flex gap-2">
                        <input id="pesquisaUsuarios" type="search" placeholder="Pesquisar usuários..." aria-label="Pesquisar usuários" class="rounded border border-gray-300 px-3 py-2 text-sm">
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
                                <td class="p-3"><?php echo (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></td>
                                <td class="p-3">
                                    <div class="flex gap-3">
                                        <?php if ($usuario['login'] !== 'admin-global' || appIsPrimaryAdmin($usuarioAtual)): ?>
                                            <a href="?editar=<?php echo (int) $usuario['idUsuario']; ?>#manutencao" class="font-medium text-elo-teal hover:underline">Alterar</a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">Conta protegida</span>
                                        <?php endif; ?>
                                        <?php if ((int) $usuario['idUsuario'] !== (int) $usuarioAtual['idUsuario']): ?>
                                            <form method="post" onsubmit="return confirm('Confirma excluir este usuário?')">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idUsuario" value="<?php echo (int) $usuario['idUsuario']; ?>">
                                                <button class="font-medium text-red-600 hover:underline">Excluir</button>
                                            </form>
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
    <script>
        document.getElementById('pesquisaUsuarios').addEventListener('input', (event) => {
            const termo = event.target.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            document.querySelectorAll('#listaUsuarios tr').forEach((linha) => {
                const texto = linha.textContent.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                linha.hidden = !texto.includes(termo);
            });
        });
    </script>
    <?php $toastSuccess = $resultado; include __DIR__ . '/../../../components/toast.php'; ?>
</body>
</html>
