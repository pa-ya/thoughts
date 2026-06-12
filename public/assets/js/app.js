document.documentElement.classList.add('js-enabled');

const openModal = (modal) => {
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    const focusTarget = modal.querySelector('input, textarea, button, select, a[href]');

    if (focusTarget instanceof HTMLElement) {
        focusTarget.focus();
    }
};

const closeModal = (modal) => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
};

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-accordion-toggle]');

    if (toggle instanceof HTMLButtonElement) {
        const panelId = toggle.getAttribute('aria-controls');

        if (!panelId) {
            return;
        }

        const panel = document.getElementById(panelId);

        if (!panel) {
            return;
        }

        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isExpanded));
        panel.hidden = isExpanded;
        toggle.closest('.accordion-item')?.classList.toggle('is-open', !isExpanded);
        return;
    }

    const opener = event.target.closest('[data-modal-open]');

    if (opener instanceof HTMLElement) {
        const modalId = opener.getAttribute('data-modal-open');
        const modal = modalId ? document.getElementById(modalId) : null;

        if (modal) {
            openModal(modal);
        }

        return;
    }

    const closeButton = event.target.closest('[data-modal-close]');

    if (closeButton instanceof HTMLElement) {
        const modal = closeButton.closest('[data-modal]');

        if (modal) {
            closeModal(modal);
        }

        return;
    }

    if (event.target instanceof HTMLElement && event.target.matches('[data-modal]')) {
        closeModal(event.target);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const open = document.querySelector('[data-modal]:not([hidden])');

    if (open) {
        closeModal(open);
    }
});

document.querySelectorAll('[data-modal]:not([hidden])').forEach((modal) => {
    if (modal instanceof HTMLElement) {
        document.body.classList.add('modal-open');
    }
});
