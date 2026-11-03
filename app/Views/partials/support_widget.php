<?php
$settingModel = new \App\Models\AppSettingModel();
$supportPhone = $settingModel->getSetting('support_phone');
$supportEmail = $settingModel->getSetting('support_email');

if ($supportPhone || $supportEmail):
    ?>
    <!-- Floating Support Widget -->
    <div id="support-widget" class="fixed bottom-8 right-8 z-[1050]">
        <!-- Popup / Call to Action -->
        <div id="support-popup"
            class="absolute bottom-[70px] right-0 hidden w-64 origin-bottom-right rounded-lg border border-line bg-surface p-4 shadow-lg">

            <div class="mb-2 flex items-center justify-between">
                <h6 class="font-semibold text-ink">¿Necesitas ayuda?</h6>
                <button type="button" onclick="toggleSupportWidget()" aria-label="Close"
                    class="text-muted hover:text-ink">
                    <i class="icon icon-x-lg"></i>
                </button>
            </div>

            <p class="mb-3 text-xs text-muted">Contáctanos directamente:</p>

            <div class="flex justify-around">
                <?php if ($supportPhone): ?>
                    <a href="https://wa.me/<?= esc($supportPhone) ?>" target="_blank"
                        class="support-action-btn flex flex-col items-center" title="WhatsApp">
                        <div class="mb-1 flex size-[50px] items-center justify-center rounded-full bg-canvas transition-all">
                            <i class="icon icon-whatsapp text-2xl text-primary"></i>
                        </div>
                        <span class="text-xs text-muted">WhatsApp</span>
                    </a>
                <?php endif; ?>

                <?php if ($supportEmail): ?>
                    <a href="mailto:<?= esc($supportEmail) ?>"
                        class="support-action-btn flex flex-col items-center" title="Email">
                        <div class="mb-1 flex size-[50px] items-center justify-center rounded-full bg-canvas transition-all">
                            <i class="icon icon-envelope text-2xl text-primary"></i>
                        </div>
                        <span class="text-xs text-muted">Email</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Floating Button -->
        <button onclick="toggleSupportWidget()"
            class="flex size-[60px] items-center justify-center rounded-full bg-primary text-white shadow-lg transition-transform hover:scale-110">
            <i class="icon icon-headset text-3xl"></i>
        </button>
    </div>

    <style>
        .support-action-btn:hover div {
            background-color: var(--color-primary-light) !important;
            transform: translateY(-3px);
        }

        @keyframes popIn {
            0%   { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        .show-popup {
            display: block !important;
            animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
    </style>

    <script>
        function toggleSupportWidget() {
            const popup = document.getElementById('support-popup');
            if (popup.classList.contains('show-popup')) {
                popup.classList.remove('show-popup');
                setTimeout(() => { popup.style.display = 'none'; }, 200);
            } else {
                popup.style.display = 'block';
                requestAnimationFrame(() => { popup.classList.add('show-popup'); });
            }
        }

        document.addEventListener('click', function (event) {
            const widget = document.getElementById('support-widget');
            const popup = document.getElementById('support-popup');
            if (!widget.contains(event.target) && popup.classList.contains('show-popup')) {
                toggleSupportWidget();
            }
        });
    </script>
<?php endif; ?>
