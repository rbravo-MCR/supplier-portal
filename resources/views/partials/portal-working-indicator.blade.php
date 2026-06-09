<div
    id="portal-working-indicator"
    class="fixed right-4 top-4 z-50 hidden items-center gap-2 rounded-lg border border-sky-200 bg-white px-4 py-3 text-sm font-medium text-sky-800 shadow-lg dark:border-sky-900 dark:bg-zinc-900 dark:text-sky-200"
    role="status"
    aria-live="polite"
>
    <flux:icon.loading variant="mini" />
    <span>{{ __('Trabajando, leyendo datos...') }}</span>
</div>

<script>
    (() => {
        if (window.portalWorkingIndicatorRegistered) {
            return;
        }

        window.portalWorkingIndicatorRegistered = true;

        let activeRequests = 0;
        let showTimer = null;

        const indicator = () => document.getElementById('portal-working-indicator');

        const show = () => {
            clearTimeout(showTimer);

            showTimer = setTimeout(() => {
                indicator()?.classList.remove('hidden');
                indicator()?.classList.add('flex');
            }, 800);
        };

        const hide = () => {
            clearTimeout(showTimer);

            if (activeRequests === 0) {
                indicator()?.classList.add('hidden');
                indicator()?.classList.remove('flex');
            }
        };

        const register = () => {
            if (! window.Livewire?.interceptRequest || window.portalWorkingIndicatorHooked) {
                return;
            }

            window.portalWorkingIndicatorHooked = true;

            window.Livewire.interceptRequest(({ onSend, onFinish }) => {
                onSend(() => {
                    activeRequests++;
                    show();
                });

                onFinish(() => {
                    activeRequests = Math.max(activeRequests - 1, 0);
                    hide();
                });
            });
        };

        if (window.Livewire) {
            register();
        } else {
            document.addEventListener('livewire:init', register, { once: true });
        }
    })();
</script>
