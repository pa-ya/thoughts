document.documentElement.classList.add('js-enabled');

const openModal = (modal) => {
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    const focusTarget = modal.querySelector('input:not([disabled]), textarea:not([disabled]), button:not([disabled]), select:not([disabled]), a[href]');

    if (focusTarget instanceof HTMLElement) {
        focusTarget.focus();
    }
};

const closeModal = (modal) => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
};

const commentSummary = (commentCount, unreadCommentCount) => {
    if (commentCount === 0) {
        return 'No comments yet.';
    }

    const comments = `${commentCount} ${commentCount === 1 ? 'comment' : 'comments'}`;

    if (unreadCommentCount === 0) {
        return comments;
    }

    return `${comments}, ${unreadCommentCount} new`;
};

const detailValue = (value) => {
    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number') {
        return String(value);
    }

    return '';
};

const fillEventDetailModal = (modal, detail) => {
    const count = Number.parseInt(detail.commentCount, 10) || 0;
    const unread = Number.parseInt(detail.unreadCommentCount, 10) || 0;
    const values = {
        eventText: detailValue(detail.eventText),
        eventDateLabel: detailValue(detail.eventDateLabel || detail.eventDate),
        feelingRate: detailValue(detail.feelingRate),
        thoughts: detailValue(detail.thoughts),
        physicalEffect: detailValue(detail.physicalEffect),
        commentSummary: commentSummary(count, unread),
        adminCommentsPreview: commentSummary(count, unread),
    };

    modal.querySelectorAll('[data-detail-field]').forEach((field) => {
        if (!(field instanceof HTMLElement)) {
            return;
        }

        const key = field.getAttribute('data-detail-field');
        field.textContent = key && Object.prototype.hasOwnProperty.call(values, key) ? values[key] : '';
    });

    const eventId = modal.querySelector('input[name="event_id"]');

    if (eventId instanceof HTMLInputElement) {
        eventId.value = detailValue(detail.id);
    }
};

const openEventDetail = (trigger) => {
    const modal = document.getElementById('event-detail-modal');

    if (!(modal instanceof HTMLElement)) {
        return;
    }

    const rawDetail = trigger.getAttribute('data-event-detail');

    if (!rawDetail) {
        return;
    }

    try {
        fillEventDetailModal(modal, JSON.parse(rawDetail));
        openModal(modal);
    } catch (error) {
        console.error('Could not open event detail modal.', error);
    }
};

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const toggle = target.closest('[data-accordion-toggle]');

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

    const detailTrigger = target.closest('[data-event-detail]');

    if (detailTrigger instanceof HTMLElement) {
        openEventDetail(detailTrigger);
        return;
    }

    const opener = target.closest('[data-modal-open]');

    if (opener instanceof HTMLElement) {
        const modalId = opener.getAttribute('data-modal-open');
        const modal = modalId ? document.getElementById(modalId) : null;

        if (modal instanceof HTMLElement) {
            openModal(modal);
        }

        return;
    }

    const closeButton = target.closest('[data-modal-close]');

    if (closeButton instanceof HTMLElement) {
        const modal = closeButton.closest('[data-modal]');

        if (modal instanceof HTMLElement) {
            closeModal(modal);
        }

        return;
    }

    if (target instanceof HTMLElement && target.matches('[data-modal]')) {
        closeModal(target);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const open = document.querySelector('[data-modal]:not([hidden])');

        if (open instanceof HTMLElement) {
            closeModal(open);
        }

        return;
    }

    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const detailTrigger = target.closest('[data-event-detail]');

    if (detailTrigger instanceof HTMLElement) {
        event.preventDefault();
        openEventDetail(detailTrigger);
    }
});

document.querySelectorAll('[data-modal]:not([hidden])').forEach((modal) => {
    if (modal instanceof HTMLElement) {
        document.body.classList.add('modal-open');
    }
});
