const formatLocalDateTime = (value, format = 'datetime') => {
    if (!value) {
        return '';
    }

    const locale = document.documentElement.lang || undefined;
    const normalizedLocale = locale?.toLowerCase() || '';
    const isBasque = /^eu(?:-|$)/.test(normalizedLocale);
    const isSpanish = /^es(?:-|$)/.test(normalizedLocale);

    if (format === 'date') {
        // Accept YYYY-MM-DD, YYYY/M/D, DD-MM-YYYY, etc.
        const parts = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/.exec(value) ||
                      /^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/.exec(value);

        if (!parts) {
            // If parsing fails, return the original value as last resort
            return value;
        }

        let year, month, day;

        if (parts[1].length === 4) {
            // YYYY-MM-DD or YYYY/M/D
            [, year, month, day] = parts;
        } else {
            // DD-MM-YYYY
            [, day, month, year] = parts;
        }

        month = month.padStart(2, '0');
        day = day.padStart(2, '0');

        if (isBasque) {
            return `${year}-${month}-${day}`;
        }

        if (isSpanish) {
            return `${day}-${month}-${year}`;
        }

        const date = new Date(`${year}-${month}-${day}T12:00:00`);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat(locale, { dateStyle: 'short' }).format(date);
    }

    // datetime format: try parsing as ISO first, then fall back to Date constructor
    let date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        // Try parsing common formats
        const isoMatch = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})[T ](\d{1,2}):(\d{1,2}):?(\d{1,2})?/.exec(value);
        if (isoMatch) {
            const [, y, m, d, h, min, s = '0'] = isoMatch;
            date = new Date(`${y}-${m.padStart(2,'0')}-${d.padStart(2,'0')}T${h.padStart(2,'0')}:${min.padStart(2,'0')}:${s.padStart(2,'0')}`);
        }
    }

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    if (isBasque || isSpanish) {
        const year = String(date.getFullYear()).padStart(4, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hour = String(date.getHours()).padStart(2, '0');
        const minute = String(date.getMinutes()).padStart(2, '0');

        return isBasque
            ? `${year}-${month}-${day} ${hour}:${minute}`
            : `${day}-${month}-${year} ${hour}:${minute}`;
    }

    return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' }).format(date);
};

window.formatLocalDateTime = formatLocalDateTime;

/**
 * How long ago a moment was ("duela 3 egun", "hace 3 días"), in the page language. Falls back to
 * the absolute date when the browser cannot word it.
 */
const formatRelativeTime = (value) => {
    const date = new Date(value);

    if (!value || Number.isNaN(date.getTime())) {
        return formatLocalDateTime(value, 'datetime');
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];

    const locale = document.documentElement.lang || undefined;

    // A browser without data for the page language would word it in English: show the date instead.
    if (locale && Intl.RelativeTimeFormat.supportedLocalesOf([locale]).length === 0) {
        return formatLocalDateTime(value, 'datetime');
    }

    try {
        const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

        for (const [unit, size] of units) {
            if (Math.abs(seconds) >= size) {
                return formatter.format(Math.round(seconds / size), unit);
            }
        }

        return formatter.format(0, 'minute');
    } catch {
        return formatLocalDateTime(value, 'datetime');
    }
};

window.formatRelativeTime = formatRelativeTime;

// Up to two initials of a person's name, for the avatar next to what they wrote.
window.initials = (name) => (name ?? '').trim().split(/\s+/).filter(Boolean).slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase()).join('');

// On small screens each list row becomes a card: its cells take their column name from the table
// header, and cells with nothing to show are skipped.
const labelListCells = (root) => {
    root.querySelectorAll('.resource-list-table table').forEach((table) => {
        const headers = [...table.querySelectorAll('thead th')].map((header) => header.textContent.trim());

        table.querySelectorAll('tbody tr').forEach((row) => {
            [...row.children].forEach((cell, index) => {
                if (cell.colSpan !== 1) {
                    return;
                }

                cell.dataset.label = headers[index] ?? '';
                cell.toggleAttribute('data-empty', cell.textContent.trim() === '' && !cell.querySelector('button, a, input'));
            });
        });
    });
};

const localizeDateTimes = (root = document) => {
    const elements = [];

    if (root instanceof Element && root.matches('[data-local-datetime]')) {
        elements.push(root);
    }

    if (root instanceof Element || root instanceof Document) {
        elements.push(...root.querySelectorAll('[data-local-datetime]'));
    }

    elements.forEach((element) => {
        const formatted = formatLocalDateTime(element.dateTime, element.dataset.localDatetime);

        if (element.textContent.trim() !== formatted) {
            element.textContent = formatted;
        }
    });
};

const localDateTimeObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                localizeDateTimes(node);
            }
        });
    });
});

if (document.documentElement) {
    localDateTimeObserver.observe(document.documentElement, { childList: true, subtree: true });
}

localizeDateTimes();

const trackedFormStates = new WeakMap();

function trackedFormValues(form) {
    return Array.from(form.elements)
        .filter((field) => {
            if (!field.name || field.disabled) {
                return false;
            }

            return !(field instanceof HTMLInputElement && [
                'button',
                'hidden',
                'reset',
                'submit',
            ].includes(field.type));
        })
        .map((field) => {
            if (field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)) {
                return [field.name, field.checked ? field.value : null];
            }

            if (field instanceof HTMLSelectElement && field.multiple) {
                return [field.name, Array.from(field.selectedOptions, (option) => option.value)];
            }

            return [field.name, field.value];
        });
}

function dispatchFormDirtyState(form, state) {
    state.isDirty = JSON.stringify(trackedFormValues(form)) !== state.baseline;

    form.dispatchEvent(new CustomEvent('form-dirty-change', {
        detail: { isDirty: state.isDirty },
    }));
}

function trackForm(form) {
    if (trackedFormStates.has(form)) {
        return;
    }

    const state = {
        baseline: JSON.stringify(trackedFormValues(form)),
        isDirty: false,
        isSubmitting: false,
    };

    trackedFormStates.set(form, state);
    form.addEventListener('input', () => dispatchFormDirtyState(form, state));
    form.addEventListener('change', () => dispatchFormDirtyState(form, state));
    form.addEventListener('reset', () => {
        state.isSubmitting = false;
    });
    form.addEventListener('submit', (event) => {
        if (!state.isDirty || state.isSubmitting) {
            event.preventDefault();

            return;
        }

        if (!form.hasAttribute('wire:submit')) {
            state.isSubmitting = true;
        }
    });
}

function trackedFormsWithin(root = document) {
    const forms = [];

    if (root instanceof HTMLFormElement && root.hasAttribute('data-track-changes')) {
        forms.push(root);
    }

    if (root instanceof Element || root instanceof Document) {
        forms.push(...root.querySelectorAll('form[data-track-changes]'));
    }

    return forms;
}

function resetTrackedForms(root = document) {
    trackedFormsWithin(root).forEach((form) => {
        trackForm(form);

        const state = trackedFormStates.get(form);
        state.baseline = JSON.stringify(trackedFormValues(form));
        state.isSubmitting = false;
        dispatchFormDirtyState(form, state);
    });
}

function dirtyTrackedForms(root = document) {
    return trackedFormsWithin(root).filter((form) => {
        const state = trackedFormStates.get(form);

        return state?.isDirty && !state.isSubmitting;
    });
}

function unsavedChangesMessage(forms) {
    return forms[0]?.dataset.unsavedWarning
        ?? document.body.dataset.unsavedWarning
        ?? '';
}

function allowLeavingForms(forms) {
    return forms.length === 0 || window.confirm(unsavedChangesMessage(forms));
}

window.resetTrackedForms = resetTrackedForms;
window.requestTrackedModalClose = (dialog) => {
    const dirtyForms = dirtyTrackedForms(dialog);

    if (allowLeavingForms(dirtyForms)) {
        dialog.close();
    }
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const closeTrigger = event.target.closest('[data-flux-modal-close]');
    const dialog = closeTrigger?.closest('dialog');

    if (!dialog || allowLeavingForms(dirtyTrackedForms(dialog))) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
}, true);

document.addEventListener('livewire:navigate', (event) => {
    if (!allowLeavingForms(dirtyTrackedForms())) {
        event.preventDefault();
    }
});

window.addEventListener('beforeunload', (event) => {
    if (dirtyTrackedForms().length === 0) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
});

document.addEventListener('livewire:init', () => {
    window.Livewire.interceptMessage(({ message, onSuccess, onFinish }) => {
        let shouldResetForms = false;

        onSuccess(({ payload }) => {
            shouldResetForms = payload.effects.dispatches?.some(({ name }) =>
                ['form-saved', 'form-reset'].includes(name)
            ) ?? false;
        });

        onFinish(() => {
            // Livewire restores disabled controls after dispatching component events.
            if (shouldResetForms) {
                resetTrackedForms(message.component.el);
            }
        });
    });
});

const initializeTrackedForms = () => {
    trackedFormsWithin().forEach(trackForm);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTrackedForms, { once: true });
} else {
    initializeTrackedForms();
}

const trackedFormObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
            if (mutation.target instanceof HTMLDialogElement && mutation.target.open) {
                requestAnimationFrame(() => resetTrackedForms(mutation.target));
            }

            return;
        }

        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                trackedFormsWithin(node).forEach(trackForm);
            }
        });
    });
});

trackedFormObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['open'],
    childList: true,
    subtree: true,
});

/**
 * Validation on leaving a field of a tracked form: the browser's own constraints (required,
 * minlength, min…) are checked as soon as the user moves on, worded with the app's texts rather
 * than the browser's, which follow the browser language instead of the app locale. An empty
 * required field reads the framework's "required" message; any other problem reads the same rule
 * text as the info icon next to the label. The mark clears as soon as the value is valid again.
 */
const fieldSelector = 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), textarea, select';

const clientErrorMessage = (field, form) => {
    const container = field.closest('[data-flux-field]') ?? field.parentElement;
    const label = container.querySelector('[data-flux-label] span')?.textContent.trim() ?? '';

    if (field.validity.valueMissing) {
        const template = form.dataset.requiredMessage ?? '';

        return template
            .replace(':Attribute', label.charAt(0).toUpperCase() + label.slice(1))
            .replace(':attribute', label);
    }

    return container.querySelector('button[data-test$="-info"]')?.getAttribute('aria-label')
        || field.validationMessage;
};

const showClientError = (field) => {
    const form = field.closest('form[data-track-changes]');

    if (!form || !field.matches(fieldSelector)) {
        return;
    }

    const container = field.closest('[data-flux-field]') ?? field.parentElement;
    const errorId = `${field.id || field.name}-client-error`;
    const hint = container.querySelector('[data-field-hint]');
    let error = container.querySelector('[data-client-error]');

    if (hint) {
        delete hint.dataset.error;
    }

    if (field.checkValidity()) {
        if (field.dataset.clientInvalid !== undefined) {
            field.removeAttribute('aria-invalid');
            delete field.dataset.invalid;
            delete field.dataset.clientInvalid;
            field.setAttribute('aria-describedby', (field.getAttribute('aria-describedby') ?? '')
                .split(' ').filter((id) => id && id !== errorId).join(' '));
        }

        error?.remove();

        return;
    }

    field.setAttribute('aria-invalid', 'true');
    field.dataset.invalid = '';
    field.dataset.clientInvalid = '';

    // The rule is already written under the control: it turns red rather than being said twice.
    if (hint && !field.validity.valueMissing) {
        hint.dataset.error = '';
        error?.remove();

        return;
    }

    if (!error) {
        error = document.createElement('p');
        error.id = errorId;
        error.dataset.clientError = '';
        error.className = 'text-sm font-medium text-red-600 dark:text-red-400';
        container.append(error);
    }

    error.textContent = clientErrorMessage(field, form);

    const describedBy = (field.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean);

    if (!describedBy.includes(errorId)) {
        field.setAttribute('aria-describedby', [...describedBy, errorId].join(' '));
    }
};

document.addEventListener('focusout', (event) => showClientError(event.target));
document.addEventListener('input', (event) => {
    if (event.target.dataset?.clientInvalid !== undefined) {
        showClientError(event.target);
    }
});

// A drawer that closes leaves no stale marks behind for the next record it opens with.
document.addEventListener('close', (event) => {
    if (event.target instanceof HTMLDialogElement) {
        clearClientErrors(event.target);
    }
}, true);

const clearClientErrors = (root) => {
    root.querySelectorAll('[data-client-error]').forEach((error) => error.remove());
    root.querySelectorAll('[data-field-hint][data-error]').forEach((hint) => delete hint.dataset.error);
    root.querySelectorAll('[data-client-invalid]').forEach((field) => {
        field.removeAttribute('aria-invalid');
        delete field.dataset.invalid;
        delete field.dataset.clientInvalid;
    });
};

export function clearForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.reset();

    Array.from(form.elements).forEach((field) => {
        if (field instanceof HTMLInputElement) {
            if (['button', 'hidden', 'reset', 'submit'].includes(field.type)) {
                return;
            }

            if (['checkbox', 'radio'].includes(field.type)) {
                field.checked = false;
            } else {
                field.value = '';
            }
        } else if (field instanceof HTMLTextAreaElement) {
            field.value = '';
        } else if (field instanceof HTMLSelectElement) {
            field.selectedIndex = -1;
        } else {
            return;
        }

        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    form.querySelectorAll('[data-flux-error]').forEach((error) => {
        error.hidden = true;
    });

    form.querySelectorAll('[data-invalid]').forEach((field) => {
        field.removeAttribute('aria-invalid');
        delete field.dataset.invalid;
    });

    clearClientErrors(form);
}

/**
 * Shift a Y-m-d date by a number of days; returns '' for an empty or invalid date.
 */
export function addDays(isoDate, days) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate ?? '');

    if (!match) {
        return '';
    }

    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]) + days));

    return date.toISOString().slice(0, 10);
}

window.clearForm = clearForm;
window.addDays = addDays;

document.addEventListener('alpine:init', () => {
    // State and handler behind <x-list.confirm-modal>. It lives here because it is the same for
    // every list: the modal only reads confirmation.*, and x-list.row-actions fills it. The list
    // passes its own local state in, so the three views keep declaring their forms and URLs here
    // instead of each repeating this block.
    /**
     * A chart drawn with ApexCharts. The host bundles the library and exposes it as
     * window.loadApexCharts (a lazy import), as it does with jQuery and select2, so pages without a
     * chart never download it. The series colour is the element's text colour, so it comes from a
     * token class and follows dark mode; a chart with several series reads one colour per series
     * from the hidden spans the component renders for its `colors`; motion is off for users who ask for less of it. The
     * element carries the chart's conclusion in its aria-label, which is what assistive
     * technology reads instead of the drawing.
     */
    window.Alpine.data('chart', (options) => ({
        instance: null,

        async init() {
            if (typeof window.loadApexCharts !== 'function') {
                return;
            }

            const ApexCharts = await window.loadApexCharts();
            const dark = document.documentElement.classList.contains('dark');

            this.instance = new ApexCharts(this.$refs.canvas, {
                chart: {
                    type: options.type ?? 'area',
                    height: options.height ?? 160,
                    sparkline: { enabled: options.sparkline ?? false },
                    toolbar: { show: false },
                    background: 'transparent',
                    fontFamily: 'inherit',
                    animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches },
                },
                series: options.series,
                xaxis: { categories: options.labels ?? [] },
                colors: options.colors > 0
                    ? Array.from({ length: options.colors }, (_, index) => getComputedStyle(this.$refs[`color${index}`]).color)
                    : [getComputedStyle(this.$el).color],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
                dataLabels: { enabled: false },
                tooltip: { enabled: !(options.sparkline ?? false), theme: dark ? 'dark' : 'light' },
                theme: { mode: dark ? 'dark' : 'light' },
            });

            await this.instance.render();
        },

        destroy() {
            this.instance?.destroy();
        },
    }));

    window.Alpine.data('listConfirmation', (local = {}) => ({
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },

        confirmAction(action) {
            this.confirmation = {
                action: action.action,
                method: action.method,
                title: action.confirmTitle,
                text: action.confirmText,
                label: action.confirmLabel,
                danger: action.danger,
            };
        },

        ...local,
    }));

    window.Alpine.data('listSearch', (initialSearch, initialTotal, resultsTemplate) => ({
        currentSearch: initialSearch,
        totalResults: initialTotal,
        requestController: null,

        get hasSearch() {
            return this.currentSearch !== '';
        },

        init() {
            this.handlePopstate = () => {
                const url = new URL(window.location.href);

                void this.refreshList(url, false).catch(() => window.location.reload());
            };
            window.addEventListener('popstate', this.handlePopstate);

            // Listen for filters applied from advanced search modal (Livewire event)
            this.handleFiltersApplied = (event) => {
                const filters = event.detail?.filters;
                if (filters !== undefined) {
                    void this.applyFilters(filters);
                }
            };
            document.addEventListener('filters-applied', this.handleFiltersApplied);

            // "/" jumps to the search box, unless the user is already typing or a dialog is open.
            this.handleSearchShortcut = (event) => {
                if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey
                    || event.target.closest('input, textarea, select, [contenteditable="true"]')
                    || document.querySelector('dialog[open]')) {
                    return;
                }

                const input = this.$root.querySelector('[data-list-search] input[name="search"]');

                if (input) {
                    event.preventDefault();
                    input.focus();
                    input.select();
                }
            };
            document.addEventListener('keydown', this.handleSearchShortcut);

            labelListCells(this.$root);
        },

        destroy() {
            window.removeEventListener('popstate', this.handlePopstate);
            document.removeEventListener('filters-applied', this.handleFiltersApplied);
            document.removeEventListener('keydown', this.handleSearchShortcut);
            this.requestController?.abort();
        },

        searchInput(event) {
            if (event.target.name !== 'search') {
                return;
            }

            const query = event.target.value.trim();

            if (query.length > 3
                || (query.length === 0 && (this.currentSearch !== '' || this.requestController))) {
                this.search(query);

                return;
            }

            if (this.requestController && query !== this.currentSearch) {
                this.requestController.abort();
            }
        },

        submitSearch(event) {
            const form = event.target.closest('[data-list-search]');
            const query = form.elements.search.value.trim();

            // A term too short to fetch is left to the native form submit, so the search the
            // server accepts is also the search the browser gets without this script.
            if ((query.length > 0 && query.length <= 3)
                || (query.length === 0 && this.currentSearch === '')) {
                return;
            }

            event.preventDefault();

            this.search(query);
        },

        handleClick(event) {
            const clearLink = event.target.closest('[data-list-search-clear]');

            if (clearLink) {
                event.preventDefault();
                void this.refreshList(new URL(clearLink.href))
                    .catch(() => window.location.assign(clearLink.href));

                return;
            }

            const paginationLink = event.target.closest('nav[data-test$="-pagination"] a');

            if (paginationLink) {
                event.preventDefault();
                void this.refreshList(new URL(paginationLink.href))
                    .catch(() => window.location.assign(paginationLink.href));
            }
        },

        // Both start from the current URL and change only their own key, so a search keeps the
        // active filters and applying filters keeps the search. Either one goes back to page one.
        search(query) {
            const url = new URL(window.location.href);
            url.searchParams.delete('page');

            if (query === '') {
                url.searchParams.delete('search');
            } else {
                url.searchParams.set('search', query);
            }

            void this.refreshList(url).catch(() => window.location.assign(url.href));
        },

        applyFilters(filters) {
            const url = new URL(window.location.href);
            url.searchParams.delete('page');

            // Remove existing filter params. The keys are copied first: deleting while iterating
            // the live list skips entries.
            for (const key of [...url.searchParams.keys()]) {
                if (key.startsWith('filters[')) {
                    url.searchParams.delete(key);
                }
            }

            // Add new filter params
            for (const [key, filter] of Object.entries(filters)) {
                if (filter && filter.operator) {
                    url.searchParams.set(`filters[${key}][operator]`, filter.operator);
                    const value = filter.value;
                    if (value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
                        if (Array.isArray(value)) {
                            value.forEach((v, i) => {
                                url.searchParams.set(`filters[${key}][value][${i}]`, v);
                            });
                        } else {
                            url.searchParams.set(`filters[${key}][value]`, value);
                        }
                    }
                }
            }

            // A full load, not a fragment refresh: the filter badges and the modal live outside the
            // results fragment and are rendered from the URL.
            window.location.assign(url.href);
        },

        async refreshList(url, updateHistory = true) {
            this.requestController?.abort();

            const controller = new AbortController();
            this.requestController = controller;

            // Marked busy only if the response is slow, so a quick search does not flicker.
            const listRoot = this.$root;
            const busyTimer = setTimeout(() => listRoot.setAttribute('aria-busy', 'true'), 150);

            try {
                const response = await fetch(url, {
                    headers: { 'X-List-Fragment': 'true' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`List refresh failed with HTTP ${response.status}.`);
                }

                const template = document.createElement('template');
                template.innerHTML = await response.text();

                const results = template.content.querySelector('[data-list-results]');

                if (!results) {
                    throw new Error('The list response did not contain the expected results fragment.');
                }

                this.currentSearch = url.searchParams.get('search')?.trim() ?? '';

                if (updateHistory) {
                    window.history.pushState({}, '', url);
                }

                // The morph re-initialises the root's x-data, so the live region is filled after it
                // rather than before: a value set beforehand belongs to the discarded instance.
                const totalResults = results.dataset.totalResults
                    ? parseInt(results.dataset.totalResults, 10)
                    : results.querySelectorAll('tbody tr').length;
                this.totalResults = totalResults;
                const announcement = (resultsTemplate ?? '').replace(
                    ':count',
                    String(totalResults),
                );

                const root = this.$root;

                window.Alpine.morph(root, results.outerHTML);
                labelListCells(root);

                const region = root.querySelector('[data-list-results-announcement]');

                if (region) {
                    region.textContent = announcement;
                }
            } catch (error) {
                if (!controller.signal.aborted) {
                    throw error;
                }
            } finally {
                clearTimeout(busyTimer);

                if (this.requestController === controller) {
                    this.requestController = null;
                    listRoot.removeAttribute('aria-busy');
                }
            }
        },
    }));
});
