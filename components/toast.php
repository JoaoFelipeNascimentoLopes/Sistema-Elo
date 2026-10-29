<?php
$toastMessages = [];
if (isset($toastSuccess) && $toastSuccess !== '') {
    $toastMessages[] = ['tipo' => 'sucesso', 'mensagem' => $toastSuccess];
}
if (isset($toastError) && $toastError !== '') {
    $toastMessages[] = ['tipo' => 'erro', 'mensagem' => $toastError];
}
?>
<?php if ($toastMessages): ?>
    <div class="fixed right-4 top-4 z-[100] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3" aria-live="polite" aria-relevant="additions">
        <?php foreach ($toastMessages as $index => $toast): ?>
            <?php $isError = $toast['tipo'] === 'erro'; ?>
            <div id="toast-<?php echo appEscape($toast['tipo'] . '-' . $index); ?>"
                 role="<?php echo $isError ? 'alert' : 'status'; ?>"
                 class="flex items-start gap-3 rounded-lg border <?php echo $isError ? 'border-red-200' : 'border-green-200'; ?> bg-white px-4 py-3 text-sm text-gray-800 shadow-lg transition duration-200"
                 data-toast>
                <?php if ($isError): ?>
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm2.12-10.94a.75.75 0 0 0-1.06 0L10 8.94 8.94 7.88a.75.75 0 1 0-1.06 1.06L8.94 10l-1.06 1.06a.75.75 0 1 0 1.06 1.06L10 11.06l1.06 1.06a.75.75 0 1 0 1.06-1.06L11.06 10l1.06-1.06a.75.75 0 0 0 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                <?php else: ?>
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.78-9.72a.75.75 0 0 0-1.06-1.06L9 10.94 7.28 9.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l4.25-4.25Z" clip-rule="evenodd" />
                    </svg>
                <?php endif; ?>
                <p class="flex-1"><?php echo appEscape($toast['mensagem']); ?></p>
                <button type="button" class="text-gray-400 transition-colors hover:text-gray-700" aria-label="Fechar notificação" data-toast-close>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
