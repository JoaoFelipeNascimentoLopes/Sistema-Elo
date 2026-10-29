<?php
$abaInicial = ($abaInicial ?? 'pesquisa') === 'manutencao' ? 'manutencao' : 'pesquisa';
?>
<div class="mb-5 flex border-b border-gray-200" role="tablist" aria-label="Seções da tela" data-page-tabs data-initial-tab="<?php echo appEscape($abaInicial); ?>">
    <button type="button" id="tab-pesquisa" role="tab" aria-controls="painel-pesquisa" data-tab-target="pesquisa"
            aria-selected="<?php echo $abaInicial === 'pesquisa' ? 'true' : 'false'; ?>" tabindex="<?php echo $abaInicial === 'pesquisa' ? '0' : '-1'; ?>"
            class="border-b-2 px-4 py-2 text-sm font-medium transition-colors <?php echo $abaInicial === 'pesquisa' ? 'border-elo-teal text-elo-teal' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
        Pesquisa
    </button>
    <button type="button" id="tab-manutencao" role="tab" aria-controls="painel-manutencao" data-tab-target="manutencao"
            aria-selected="<?php echo $abaInicial === 'manutencao' ? 'true' : 'false'; ?>" tabindex="<?php echo $abaInicial === 'manutencao' ? '0' : '-1'; ?>"
            class="border-b-2 px-4 py-2 text-sm font-medium transition-colors <?php echo $abaInicial === 'manutencao' ? 'border-elo-teal text-elo-teal' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
        Manutenção
    </button>
</div>
