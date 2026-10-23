<?php
require_once __DIR__ . '/../../../auth/access.php';
$usuarioAtual = appRequirePage('geral.perfis-acesso');
$pdo = appPdo();
$catalogo = appPageCatalog();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appValidateCsrf();
    $acao = (string) ($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar') {
            $idPerfil = filter_var($_POST['idPerfil'] ?? 0, FILTER_VALIDATE_INT);
            $nome = trim((string) ($_POST['nomePerfil'] ?? ''));
            if ($idPerfil === false || $idPerfil < 0 || $nome === '' || mb_strlen($nome) > 100) {
                throw new InvalidArgumentException('Informe um nome de perfil válido.');
            }

            $permissoes = [];
            $selecionadas = $_POST['permissoes'] ?? [];
            if (!is_array($selecionadas)) {
                throw new InvalidArgumentException('A seleção de permissões é inválida.');
            }

            foreach ($catalogo as $moduloKey => $modulo) {
                $solicitadas = $selecionadas[$moduloKey] ?? [];
                if (!is_array($solicitadas)) {
                    throw new InvalidArgumentException('A seleção de telas é inválida.');
                }
                $validas = array_intersect(array_keys($modulo['paginas']), array_map('strval', $solicitadas));
                if ($validas) {
                    $permissoes[$moduloKey] = array_values($validas);
                }
            }

            if ($idPerfil > 0) {
                $existingProfile = $pdo->prepare(
                    'SELECT administradorGlobal FROM tbPerfilAcesso WHERE idPerfil = :idPerfil'
                );
                $existingProfile->execute(['idPerfil' => $idPerfil]);
                $isGlobal = $existingProfile->fetchColumn();
                if ($isGlobal === false) {
                    throw new InvalidArgumentException('Perfil não encontrado.');
                }
                if ((int) $isGlobal === 1) {
                    if (!appIsPrimaryAdmin($usuarioAtual)) {
                        throw new InvalidArgumentException('Somente o login admin-global pode alterar este perfil.');
                    }
                    $statement = $pdo->prepare(
                        'UPDATE tbPerfilAcesso SET permissoes = :permissoes WHERE idPerfil = :idPerfil'
                    );
                    $statement->execute([
                        'permissoes' => json_encode($permissoes, JSON_THROW_ON_ERROR),
                        'idPerfil' => $idPerfil,
                    ]);
                    header('Location: perfis-acesso.php?resultado=atualizado');
                    exit;
                }
                $statement = $pdo->prepare(
                    'UPDATE tbPerfilAcesso SET nomePerfil = :nome, permissoes = :permissoes WHERE idPerfil = :idPerfil'
                );
                $statement->execute([
                    'nome' => $nome,
                    'permissoes' => json_encode($permissoes, JSON_THROW_ON_ERROR),
                    'idPerfil' => $idPerfil,
                ]);
                header('Location: perfis-acesso.php?resultado=atualizado');
                exit;
            }

            $statement = $pdo->prepare(
                'INSERT INTO tbPerfilAcesso (nomePerfil, permissoes, administradorGlobal)
                 VALUES (:nome, :permissoes, 0)'
            );
            $statement->execute([
                'nome' => $nome,
                'permissoes' => json_encode($permissoes, JSON_THROW_ON_ERROR),
            ]);
            header('Location: perfis-acesso.php?resultado=criado');
            exit;
        }

        if ($acao === 'excluir') {
            $idPerfil = filter_var($_POST['idPerfil'] ?? 0, FILTER_VALIDATE_INT);
            if (!$idPerfil) {
                throw new InvalidArgumentException('Perfil inválido.');
            }
            $profileCheck = $pdo->prepare(
                'SELECT administradorGlobal FROM tbPerfilAcesso WHERE idPerfil = :idPerfil'
            );
            $profileCheck->execute(['idPerfil' => $idPerfil]);
            $isGlobal = $profileCheck->fetchColumn();
            if ($isGlobal === false || (int) $isGlobal === 1) {
                throw new InvalidArgumentException('O perfil de Administração Global é protegido.');
            }
            $userCount = $pdo->prepare('SELECT COUNT(*) FROM tbUsuario WHERE idPerfil = :idPerfil');
            $userCount->execute(['idPerfil' => $idPerfil]);
            if ((int) $userCount->fetchColumn() > 0) {
                throw new InvalidArgumentException('Não é possível excluir um perfil atribuído a usuários.');
            }
            $delete = $pdo->prepare('DELETE FROM tbPerfilAcesso WHERE idPerfil = :idPerfil');
            $delete->execute(['idPerfil' => $idPerfil]);
            header('Location: perfis-acesso.php?resultado=excluido');
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $exception) {
        $erro = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Falha ao salvar perfil de acesso: ' . $exception->getMessage());
        $erro = 'Não foi possível salvar o perfil. O nome pode já estar sendo usado.';
    }
}

$perfilEdicao = null;
$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($idEdicao) {
    $statement = $pdo->prepare(
        'SELECT idPerfil, nomePerfil, permissoes, administradorGlobal
         FROM tbPerfilAcesso WHERE idPerfil = :idPerfil'
    );
    $statement->execute(['idPerfil' => $idEdicao]);
    $profile = $statement->fetch();
    if ($profile && ((int) $profile['administradorGlobal'] !== 1 || appIsPrimaryAdmin($usuarioAtual))) {
        $perfilEdicao = $profile;
        $perfilEdicao['permissoes'] = json_decode($profile['permissoes'], true, 512, JSON_THROW_ON_ERROR);
    }
}
$perfis = $pdo->query(
    'SELECT p.idPerfil, p.nomePerfil, p.administradorGlobal, COUNT(u.idUsuario) AS quantidadeUsuarios
     FROM tbPerfilAcesso p
     LEFT JOIN tbUsuario u ON u.idPerfil = p.idPerfil
     GROUP BY p.idPerfil
     ORDER BY p.administradorGlobal DESC, p.nomePerfil'
)->fetchAll();
$resultado = [
    'criado' => 'Perfil cadastrado.',
    'atualizado' => 'Perfil atualizado.',
    'excluido' => 'Perfil excluído.',
][(string) ($_GET['resultado'] ?? '')] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfis de Acesso - Sistema Elo</title>
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
            <h1 class="mb-6 text-2xl font-bold text-gray-800">Perfis de Acesso</h1>
        <?php $abaInicial = ($perfilEdicao || $erro !== '') ? 'manutencao' : 'pesquisa'; include __DIR__ . '/../../../components/tabs-pesquisa-manutencao.php'; ?>
        <?php if ($erro !== ''): ?><p role="alert" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700"><?php echo appEscape($erro); ?></p><?php endif; ?>
        <section id="painel-manutencao" role="tabpanel" aria-labelledby="tab-manutencao" class="mb-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'manutencao' ? '' : 'hidden'; ?>>
                <h2 class="mb-1 text-lg font-semibold"><?php echo $perfilEdicao ? 'Alterar perfil' : 'Novo perfil'; ?></h2>
                <p class="mb-5 text-sm text-gray-500">Escolha os módulos e as telas que os usuários deste perfil poderão acessar.</p>
                <form method="post" onsubmit="return confirm('Confirma gravar este perfil e suas permissões?')">
                    <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="idPerfil" value="<?php echo (int) ($perfilEdicao['idPerfil'] ?? 0); ?>">
                    <label class="mb-5 block max-w-xl text-sm font-medium text-gray-700">Nome do perfil
                        <input name="nomePerfil" required maxlength="100" value="<?php echo appEscape((string) ($perfilEdicao['nomePerfil'] ?? '')); ?>" <?php echo (int) ($perfilEdicao['administradorGlobal'] ?? 0) === 1 ? 'readonly' : ''; ?> class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <?php foreach ($catalogo as $moduleKey => $modulo): ?>
                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="flex items-center gap-2 px-1 font-semibold text-gray-800">
                                    <i class="ph <?php echo appEscape($modulo['icone']); ?> text-xl text-elo-teal"></i>
                                    <?php echo appEscape($modulo['nome']); ?>
                                </legend>
                                <div class="mt-2 space-y-2">
                                    <?php foreach ($modulo['paginas'] as $pageKey => $pagina): ?>
                                        <?php
                                        $globalWildcard = (int) ($perfilEdicao['administradorGlobal'] ?? 0) === 1
                                            && in_array('*', $perfilEdicao['permissoes']['*'] ?? [], true);
                                        $checked = $globalWildcard || in_array($pageKey, $perfilEdicao['permissoes'][$moduleKey] ?? [], true);
                                        ?>
                                        <label class="flex items-center gap-2 rounded p-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <input type="checkbox" name="permissoes[<?php echo appEscape($moduleKey); ?>][]" value="<?php echo appEscape($pageKey); ?>" <?php echo $checked ? 'checked' : ''; ?> class="rounded border-gray-300 text-elo-teal focus:ring-elo-teal">
                                            <span><?php echo appEscape($pagina['nome']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-5 flex gap-2">
                        <button class="rounded bg-elo-teal px-4 py-2 font-medium text-white hover:bg-elo-green"><?php echo $perfilEdicao ? 'Salvar alterações' : 'Criar perfil'; ?></button>
                        <?php if ($perfilEdicao): ?><a href="perfis-acesso.php#pesquisa" class="rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </section>

            <section id="painel-pesquisa" role="tabpanel" aria-labelledby="tab-pesquisa" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" <?php echo $abaInicial === 'pesquisa' ? '' : 'hidden'; ?>>
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <h2 class="text-lg font-semibold">Perfis cadastrados</h2>
                    <div class="flex gap-2">
                        <input id="pesquisaPerfis" type="search" placeholder="Pesquisar perfis..." aria-label="Pesquisar perfis" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" data-tab-open="manutencao" class="rounded bg-elo-teal px-4 py-2 text-sm font-medium text-white hover:bg-elo-green">+ Novo perfil</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-left text-sm">
                        <thead class="border-b bg-gray-50 text-gray-600"><tr><th class="p-3">Perfil</th><th class="p-3">Usuários</th><th class="p-3">Ações</th></tr></thead>
                        <tbody id="listaPerfis">
                        <?php foreach ($perfis as $perfil): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3"><?php echo appEscape($perfil['nomePerfil']); ?><?php if ((int) $perfil['administradorGlobal'] === 1): ?><span class="ml-2 rounded bg-elo-teal/10 px-2 py-1 text-xs font-medium text-elo-teal">Perfil global</span><?php endif; ?></td>
                                <td class="p-3"><?php echo (int) $perfil['quantidadeUsuarios']; ?></td>
                                <td class="p-3">
                                    <?php if ((int) $perfil['administradorGlobal'] !== 1): ?>
                                        <div class="flex gap-3">
                                            <a href="?editar=<?php echo (int) $perfil['idPerfil']; ?>#manutencao" class="font-medium text-elo-teal hover:underline">Alterar</a>
                                            <form method="post" onsubmit="return confirm('Confirma excluir este perfil?')">
                                                <input type="hidden" name="csrfToken" value="<?php echo appEscape(appCsrfToken()); ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="idPerfil" value="<?php echo (int) $perfil['idPerfil']; ?>">
                                                <button class="font-medium text-red-600 hover:underline">Excluir</button>
                                            </form>
                                        </div>
                                    <?php elseif (appIsPrimaryAdmin($usuarioAtual)): ?>
                                        <a href="?editar=<?php echo (int) $perfil['idPerfil']; ?>#manutencao" class="font-medium text-elo-teal hover:underline">Alterar permissões</a>
                                    <?php else: ?><span class="text-xs text-gray-500">Somente admin-global pode alterar</span><?php endif; ?>
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
        document.getElementById('pesquisaPerfis').addEventListener('input', (event) => {
            const termo = event.target.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            document.querySelectorAll('#listaPerfis tr').forEach((linha) => {
                const texto = linha.textContent.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                linha.hidden = !texto.includes(termo);
            });
        });
    </script>
    <?php $toastSuccess = $resultado; include __DIR__ . '/../../../components/toast.php'; ?>
</body>
</html>
