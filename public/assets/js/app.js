document.documentElement.classList.add('js-enabled');

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-accordion-toggle]');

    if (!(toggle instanceof HTMLButtonElement)) {
        return;
    }

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
});
