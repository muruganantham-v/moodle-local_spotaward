define([], function() {
    'use strict';

    function parseJson(value, fallback) {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (e) {
            return fallback;
        }
    }

    function formatSavedTime(timestamp) {
        if (!timestamp) {
            return '';
        }
        try {
            return new Date(timestamp * 1000).toLocaleTimeString([], {
                hour: 'numeric',
                minute: '2-digit'
            });
        } catch (e) {
            return '';
        }
    }

    return {
        init: function(config) {
            config = config || {};

            var form = document.querySelector('form.mform');
            if (!form) {
                return;
            }

            var submitBtn = form.querySelector('[name="submitnominations"]');
            var previewBtn = form.querySelector('[name="previewdraft"]');
            var clearBtn = form.querySelector('[name="cleardraft"]');
            var statusEl = document.getElementById('spotaward-draft-status');
            var autosaveUrl = String(config.autosaveurl || '');
            var autosaveIntervalMs = Math.max(15000, parseInt(config.autosaveintervalms, 10) || 60000);
            var autosaveDebounceMs = Math.max(1000, parseInt(config.autosavedebouncems, 10) || 8000);
            var strings = config.strings || {};
            var lastSavedAt = parseInt(config.initialsavedat, 10) || 0;
            var hasRecoverableState = !!config.hasrecoverablestate;
            var bypass = false;
            var allowNavigation = false;
            var dirty = false;
            var saveInFlight = false;
            var changeRevision = 0;
            var saveRevision = 0;
            var lastSavedSerialized = '';
            var debounceTimer = null;
            var ignoreChangesUntil = Date.now() + (hasRecoverableState ? 4000 : 1500);

            function setStatus(message, tone) {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = message || '';
                statusEl.className = 'spotaward-draft-status';
                if (tone) {
                    statusEl.classList.add(tone);
                }
            }

            function setActionButtonsEnabled(enabled) {
                if (clearBtn) {
                    clearBtn.disabled = !enabled;
                }
                if (submitBtn) {
                    submitBtn.disabled = !enabled;
                }
            }

            function getSelectValues(select) {
                var values = [];
                if (!select) {
                    return values;
                }
                for (var i = 0; i < select.options.length; i++) {
                    if (select.options[i].selected && select.options[i].value !== '') {
                        values.push(parseInt(select.options[i].value, 10) || 0);
                    }
                }
                values = values.filter(function(value) {
                    return value > 0;
                });
                values.sort(function(a, b) {
                    return a - b;
                });
                return values;
            }

            function getField(name) {
                return form.querySelector('[name="' + name + '"]');
            }

            function collectFormState() {
                var courseField = getField('courseid');
                var coursePicker = getField('coursepicker');
                var moduleField = getField('modulename');
                var professionalField = getField('professional');
                var pmField = getField('programmanagerid');
                var maacField = getField('maacexecutiveid');
                var awardFieldMapField = getField('awardfieldmap');
                var fieldMap = parseJson(awardFieldMapField ? awardFieldMapField.value : '', {});
                var awardAllocations = {};

                Object.keys(fieldMap).sort().forEach(function(fieldName) {
                    var category = String(fieldMap[fieldName] || '');
                    var select = form.querySelector('[name="' + fieldName + '[]"]') ||
                        form.querySelector('[name="' + fieldName + '"]');
                    var values = getSelectValues(select);
                    if (category && values.length) {
                        awardAllocations[category] = values;
                    }
                });

                return {
                    courseid: parseInt(courseField ? courseField.value : (coursePicker ? coursePicker.value : '0'), 10) || 0,
                    modulename: moduleField ? String(moduleField.value || '').trim() : '',
                    professional: professionalField ? String(professionalField.value || '').trim() : '',
                    programmanagerid: parseInt(pmField ? pmField.value : '0', 10) || 0,
                    maacexecutiveid: parseInt(maacField ? maacField.value : '0', 10) || 0,
                    awardallocations: awardAllocations
                };
            }

            function serializeState(state) {
                return JSON.stringify(state);
            }

            function hasMeaningfulContent(state) {
                return !!(
                    state.courseid ||
                    state.modulename ||
                    (state.professional && state.professional !== '0') ||
                    state.programmanagerid ||
                    state.maacexecutiveid ||
                    Object.keys(state.awardallocations || {}).length
                );
            }

            function updateActionButtons() {
                var state = collectFormState();
                setActionButtonsEnabled(hasRecoverableState || dirty || hasMeaningfulContent(state));
            }

            function showSavedStatus() {
                if (!lastSavedAt) {
                    setStatus('', '');
                    return;
                }
                var formatted = formatSavedTime(lastSavedAt);
                var message = strings.savedprefix || 'Draft saved at';
                if (formatted) {
                    message += ' ' + formatted;
                }
                setStatus(message, 'is-saved');
            }

            function markDirty() {
                if (Date.now() < ignoreChangesUntil) {
                    return;
                }
                dirty = true;
                changeRevision++;
                if (hasMeaningfulContent(collectFormState())) {
                    setStatus(strings.unsaved || 'Unsaved changes', 'is-warning');
                } else {
                    setStatus('', '');
                }
                updateActionButtons();
                scheduleAutosave();
            }

            function scheduleAutosave() {
                if (!autosaveUrl) {
                    return;
                }
                if (debounceTimer) {
                    window.clearTimeout(debounceTimer);
                }
                debounceTimer = window.setTimeout(function() {
                    saveDraft(false);
                }, autosaveDebounceMs);
            }

            function saveDraft(force) {
                if (!autosaveUrl || saveInFlight) {
                    return;
                }

                var state = collectFormState();
                var serialized = serializeState(state);
                var meaningful = hasMeaningfulContent(state);
                var shouldClear = !meaningful && (hasRecoverableState || lastSavedSerialized !== '');

                if (!dirty && !force && !shouldClear) {
                    return;
                }

                if (!meaningful && !shouldClear) {
                    dirty = false;
                    updateActionButtons();
                    setStatus('', '');
                    return;
                }

                if (!shouldClear && serialized === lastSavedSerialized && !dirty) {
                    showSavedStatus();
                    return;
                }

                saveInFlight = true;
                saveRevision = changeRevision;
                setStatus(strings.saving || 'Saving draft...', 'is-saving');

                var payload = [
                    'sesskey=' + encodeURIComponent(String(config.sesskey || '')),
                    'courseid=' + encodeURIComponent(String(state.courseid || 0)),
                    'modulename=' + encodeURIComponent(state.modulename || ''),
                    'awardpayload=' + encodeURIComponent(JSON.stringify(state.awardallocations || {})),
                    'professional=' + encodeURIComponent(state.professional || ''),
                    'programmanagerid=' + encodeURIComponent(String(state.programmanagerid || 0)),
                    'maacexecutiveid=' + encodeURIComponent(String(state.maacexecutiveid || 0))
                ].join('&');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', autosaveUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState !== 4) {
                        return;
                    }
                    if (xhr.status < 200 || xhr.status >= 300) {
                        handleSaveError();
                        return;
                    }
                    var data = parseJson(xhr.responseText, null);
                    if (!data) {
                        handleSaveError();
                        return;
                    }

                    saveInFlight = false;
                    hasRecoverableState = !!data.saved;
                    lastSavedAt = parseInt(data.timesaved, 10) || 0;
                    lastSavedSerialized = data.cleared ? '' : serialized;

                    if (changeRevision !== saveRevision) {
                        dirty = true;
                        setStatus(strings.unsaved || 'Unsaved changes', 'is-warning');
                        updateActionButtons();
                        scheduleAutosave();
                        return;
                    }

                    dirty = false;
                    updateActionButtons();

                    if (data.cleared) {
                        setStatus('', '');
                        return;
                    }

                    showSavedStatus();
                };
                xhr.onerror = handleSaveError;
                xhr.send(payload);
            }

            function handleSaveError() {
                saveInFlight = false;
                dirty = true;
                setStatus(strings.failed || 'Draft save failed. Your latest changes are still on this page.', 'is-error');
                updateActionButtons();
                scheduleAutosave();
            }

            form.addEventListener('change', markDirty, true);
            form.addEventListener('input', markDirty, true);

            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    allowNavigation = true;
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    allowNavigation = true;
                });
            }

            window.addEventListener('beforeunload', function(e) {
                if (allowNavigation || (!dirty && !saveInFlight)) {
                    return;
                }
                var message = strings.leavewarning || 'You have unsaved nomination changes. If you leave now, your latest edits may not be saved.';
                e.preventDefault();
                e.returnValue = message;
                return message;
            });

            if (autosaveUrl) {
                window.setInterval(function() {
                    if (dirty && !saveInFlight) {
                        saveDraft(true);
                    }
                }, autosaveIntervalMs);
            }

            updateActionButtons();
            if (lastSavedAt) {
                showSavedStatus();
            }

            if (!submitBtn) {
                return;
            }

            var backdrop = document.createElement('div');
            backdrop.id = 'spotaward-submit-confirm-modal';
            backdrop.className = 'spotaward-report-backdrop';
            backdrop.innerHTML =
                '<div class="spotaward-report-modal" role="dialog" aria-modal="true" aria-labelledby="spotaward-submit-confirm-title">' +
                    '<div class="spotaward-report-header">' +
                        '<h3 class="spotaward-report-title" id="spotaward-submit-confirm-title">Submit nomination</h3>' +
                        '<button type="button" class="spotaward-report-close" data-action="cancel" aria-label="Cancel">&times;</button>' +
                    '</div>' +
                    '<div class="spotaward-report-body">' +
                        '<div class="spotaward-category-list"></div>' +
                        '<br>' +
                        '<p class="spotaward-summary-text">Are you sure you want to submit these nominations?</p>' +
                        '<div class="mt-3 d-flex gap-2">' +
                            '<button type="button" class="btn btn-secondary" data-action="cancel">Cancel</button>' +
                            '<button type="button" class="btn btn-primary" data-action="confirm">Submit</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(backdrop);

            var categoryList = backdrop.querySelector('.spotaward-category-list');

            function getCategoryStatus() {
                var fieldMapEl = document.getElementById('id_awardfieldmap') ||
                    form.querySelector('input[name="awardfieldmap"]') ||
                    document.querySelector('input[name="awardfieldmap"]');
                var fieldMap = {};
                if (fieldMapEl && fieldMapEl.value) {
                    fieldMap = parseJson(fieldMapEl.value, {});
                }
                var categories = [];
                Object.keys(fieldMap).forEach(function(fieldName) {
                    var select = form.querySelector('[name="' + fieldName + '[]"]') ||
                        form.querySelector('[name="' + fieldName + '"]');
                    var count = getSelectValues(select).length;
                    categories.push({
                        name: String(fieldMap[fieldName]),
                        studentCount: count,
                        hasSelection: count > 0
                    });
                });
                var selected = categories.filter(function(category) {
                    return category.hasSelection;
                }).length;
                var unselected = categories.length - selected;
                return {
                    total: categories.length,
                    selected: selected,
                    unselected: unselected,
                    categories: categories
                };
            }

            function closeModal() {
                backdrop.classList.remove('is-open');
            }

            function openModal() {
                var status = getCategoryStatus();
                categoryList.innerHTML = '';
                var html = '';
                if (status.total > 0) {
                    var selectedCats = status.categories.filter(function(category) {
                        return category.hasSelection;
                    });
                    var unselectedCats = status.categories.filter(function(category) {
                        return !category.hasSelection;
                    });
                    html += '<div class="spotaward-summary-bar mb-2">Selected: <strong>' + status.selected + '/' + status.total + '</strong> categories &mdash; Not selected: <strong>' + status.unselected + '/' + status.total + '</strong> categories</div>';
                    if (selectedCats.length > 0) {
                        html += '<div class="spotaward-section-label">Selected categories:</div>';
                        html += '<div class="spotaward-category-items spotaward-category-items-selected">';
                        selectedCats.forEach(function(category) {
                            html += '<div class="spotaward-category-item is-ok">' +
                                '<span class="spotaward-category-icon">&#10003;</span>' +
                                '<span class="spotaward-category-name">' + category.name + '</span>' +
                                '<span class="spotaward-category-count">' + category.studentCount + ' student(s)</span>' +
                            '</div>';
                        });
                        html += '</div>';
                    }
                    if (unselectedCats.length > 0) {
                        html += '<div class="spotaward-section-label mt-2">Not selected categories:</div>';
                        html += '<div class="spotaward-category-items spotaward-category-items-unselected">';
                        unselectedCats.forEach(function(category) {
                            html += '<div class="spotaward-category-item is-missing">' +
                                '<span class="spotaward-category-icon">!</span>' +
                                '<span class="spotaward-category-name">' + category.name + '</span>' +
                            '</div>';
                        });
                        html += '</div>';
                    }
                } else {
                    html = '<p>No award categories configured for this course.</p>';
                }
                categoryList.innerHTML = html;
                backdrop.classList.add('is-open');
            }

            function submitForm() {
                allowNavigation = true;
                bypass = true;
                if (typeof window.localSpotawardStartSuccessOverlay === 'function') {
                    window.localSpotawardStartSuccessOverlay(submitBtn.value || submitBtn.textContent || 'Submit');
                }
                if (form.requestSubmit) {
                    form.requestSubmit(submitBtn);
                    return;
                }
                form.submit();
            }

            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openModal();
            });

            form.addEventListener('submit', function(e) {
                if (bypass) {
                    bypass = false;
                    return;
                }
                var submitter = e.submitter;
                if (!submitter || submitter.name !== 'submitnominations') {
                    allowNavigation = true;
                    return;
                }
                e.preventDefault();
                openModal();
            }, true);

            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop || e.target.getAttribute('data-action') === 'cancel') {
                    closeModal();
                    return;
                }
                if (e.target.getAttribute('data-action') === 'confirm') {
                    closeModal();
                    submitForm();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && backdrop.classList.contains('is-open')) {
                    closeModal();
                }
            });
        }
    };
});
