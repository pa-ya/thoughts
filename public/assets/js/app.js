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

const pluralizeCount = (count, singular, plural) => `${count} ${count === 1 ? singular : plural}`;

const commentSummary = (commentCount, unreadCommentCount) => {
    if (commentCount === 0) {
        return 'No comments yet.';
    }

    const comments = pluralizeCount(commentCount, 'comment', 'comments');

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

const renderAdminCommentsMessage = (modal, message, type = '') => {
    const list = modal.querySelector('[data-admin-comments-list]');

    if (!(list instanceof HTMLElement)) {
        return;
    }

    const paragraph = document.createElement('p');
    paragraph.className = `detail-text comment-message${type ? ` comment-message-${type}` : ''}`;
    paragraph.textContent = message;
    list.replaceChildren(paragraph);
};

const renderAdminComments = (modal, comments) => {
    const list = modal.querySelector('[data-admin-comments-list]');

    if (!(list instanceof HTMLElement)) {
        return;
    }

    if (!Array.isArray(comments) || comments.length === 0) {
        renderAdminCommentsMessage(modal, 'No comments yet.');
        return;
    }

    const fragment = document.createDocumentFragment();

    comments.forEach((comment) => {
        const item = document.createElement('article');
        item.className = 'comment-item';

        if (comment?.isUnread === true) {
            item.classList.add('comment-item-new');
        }

        const meta = document.createElement('div');
        meta.className = 'comment-meta';

        const time = document.createElement('time');
        time.textContent = detailValue(comment?.createdAt);
        meta.appendChild(time);

        const status = document.createElement('span');
        status.className = 'comment-status';
        status.textContent = comment?.isUnread === true ? 'New' : 'Read';
        meta.appendChild(status);

        const text = document.createElement('p');
        text.className = 'comment-text';
        text.textContent = detailValue(comment?.text);

        item.append(meta, text);
        fragment.appendChild(item);
    });

    list.replaceChildren(fragment);
};

const setDetailCommentSummary = (modal, commentCount, unreadCommentCount) => {
    const field = modal.querySelector('[data-detail-field="commentSummary"]');

    if (field instanceof HTMLElement) {
        field.textContent = commentSummary(commentCount, unreadCommentCount);
    }
};

const updateEventTriggerCommentState = (trigger, detail, stats) => {
    const commentCount = Number.parseInt(stats?.commentCount, 10) || 0;
    const unreadCommentCount = Number.parseInt(stats?.unreadCommentCount, 10) || 0;

    detail.commentCount = commentCount;
    detail.unreadCommentCount = unreadCommentCount;
    trigger.setAttribute('data-event-detail', JSON.stringify(detail));

    const badges = trigger.querySelector('.event-badges');

    if (!(badges instanceof HTMLElement)) {
        return;
    }

    const countBadge = badges.querySelector('[data-comment-count-badge]');

    if (commentCount > 0) {
        if (countBadge instanceof HTMLElement) {
            countBadge.textContent = pluralizeCount(commentCount, 'comment', 'comments');
        } else {
            const badge = document.createElement('span');
            badge.className = 'comment-badge';
            badge.setAttribute('data-comment-count-badge', '');
            badge.textContent = pluralizeCount(commentCount, 'comment', 'comments');
            badges.prepend(badge);
        }
    } else if (countBadge instanceof HTMLElement) {
        countBadge.remove();
    }

    const unreadBadge = badges.querySelector('[data-unread-comment-badge]');

    if (unreadCommentCount > 0) {
        if (unreadBadge instanceof HTMLElement) {
            unreadBadge.textContent = pluralizeCount(unreadCommentCount, 'new', 'new');
        } else {
            const badge = document.createElement('span');
            badge.className = 'comment-badge comment-badge-new';
            badge.setAttribute('data-unread-comment-badge', '');
            badge.textContent = pluralizeCount(unreadCommentCount, 'new', 'new');
            badges.appendChild(badge);
        }
    } else if (unreadBadge instanceof HTMLElement) {
        unreadBadge.remove();
    }
};

const loadAdminComments = async (modal, trigger, detail) => {
    const url = modal.getAttribute('data-comments-url');
    const csrfToken = modal.getAttribute('data-comments-csrf');

    if (!url || !csrfToken) {
        return;
    }

    renderAdminCommentsMessage(modal, 'Loading comments...');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                csrf_token: csrfToken,
                event_id: detailValue(detail.id),
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(detailValue(payload?.error) || 'Comments could not be loaded.');
        }

        if (modal.dataset.activeEventId !== detailValue(detail.id)) {
            return;
        }

        renderAdminComments(modal, payload.comments);

        const commentCount = Number.parseInt(payload?.stats?.commentCount, 10) || 0;
        const unreadCommentCount = Number.parseInt(payload?.stats?.unreadCommentCount, 10) || 0;
        setDetailCommentSummary(modal, commentCount, unreadCommentCount);
        updateEventTriggerCommentState(trigger, detail, payload.stats);
    } catch (error) {
        console.error('Could not load event comments.', error);
        renderAdminCommentsMessage(modal, 'Comments could not be loaded.', 'error');
    }
};

const fillEventDetailModal = (modal, detail) => {
    const count = Number.parseInt(detail.commentCount, 10) || 0;
    const unread = Number.parseInt(detail.unreadCommentCount, 10) || 0;
    modal.dataset.activeEventId = detailValue(detail.id);
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

    const commentText = modal.querySelector('textarea[name="comment_text"]');

    if (commentText instanceof HTMLTextAreaElement) {
        commentText.value = '';
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
        const detail = JSON.parse(rawDetail);
        fillEventDetailModal(modal, detail);
        openModal(modal);
        loadAdminComments(modal, trigger, detail);
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
