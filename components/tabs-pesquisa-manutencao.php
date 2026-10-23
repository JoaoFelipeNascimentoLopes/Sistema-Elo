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
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelector('[data-page-tabs]');
        if (!tabs) return;

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
        document.querySelectorAll('[data-tab-open]').forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.tabOpen, true));
        });

        const hashTab = window.location.hash.slice(1);
        activate(panels[hashTab] ? hashTab : tabs.dataset.initialTab, false);
    });
</script>
