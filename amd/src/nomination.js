define([], function() {
    'use strict';

    return {
        init: function() {
            var form = document.querySelector('form.mform');
            if (!form) {
                return;
            }

            var submitBtn = form.querySelector('[name="submitnominations"]');
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
            var bypass = false;

            function getCategoryStatus() {
                /* hidden inputs may not have id= in some Moodle versions */
                var fieldMapEl = document.getElementById('id_awardfieldmap')
                    || form.querySelector('input[name="awardfieldmap"]')
                    || document.querySelector('input[name="awardfieldmap"]');
                var fieldMap = {};
                if (fieldMapEl && fieldMapEl.value) {
                    try {
                        fieldMap = JSON.parse(fieldMapEl.value);
                    } catch (e) {}
                }
                var categories = [];
                var fieldNames = Object.keys(fieldMap);
                for (var f = 0; f < fieldNames.length; f++) {
                    var fieldName = fieldNames[f];
                    /* selects are now named fieldname[] for PHP array submission */
                    var select = form.querySelector('[name="' + fieldName + '[]"]')
                        || form.querySelector('[name="' + fieldName + '"]');
                    var count = 0;
                    if (select) {
                        for (var i = 0; i < select.options.length; i++) {
                            if (select.options[i].selected) {
                                count++;
                            }
                        }
                    }
                    categories.push({
                        name: String(fieldMap[fieldName]),
                        studentCount: count,
                        hasSelection: count > 0
                    });
                }
                var selected = categories.filter(function(c) { return c.hasSelection; }).length;
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
                    var selectedCats = status.categories.filter(function(c) { return c.hasSelection; });
                    var unselectedCats = status.categories.filter(function(c) { return !c.hasSelection; });
                    html += '<div class="spotaward-summary-bar mb-2">Selected: <strong>' + status.selected + '/' + status.total + '</strong> categories &mdash; Not selected: <strong>' + status.unselected + '/' + status.total + '</strong> categories</div>';
                    if (selectedCats.length > 0) {
                        html += '<div class="spotaward-section-label">Selected categories:</div>';
                        html += '<div class="spotaward-category-items spotaward-category-items-selected">';
                        for (var c = 0; c < selectedCats.length; c++) {
                            var cat = selectedCats[c];
                            html += '<div class="spotaward-category-item is-ok">' +
                                '<span class="spotaward-category-icon">\u2705</span>' +
                                '<span class="spotaward-category-name">' + cat.name + '</span>' +
                                '<span class="spotaward-category-count">' + cat.studentCount + ' student(s)</span>' +
                            '</div>';
                        }
                        html += '</div>';
                    }
                    if (unselectedCats.length > 0) {
                        html += '<div class="spotaward-section-label mt-2">Not selected categories:</div>';
                        html += '<div class="spotaward-category-items spotaward-category-items-unselected">';
                        for (var c = 0; c < unselectedCats.length; c++) {
                            var cat = unselectedCats[c];
                            html += '<div class="spotaward-category-item is-missing">' +
                                '<span class="spotaward-category-icon">\u26A0\uFE0F</span>' +
                                '<span class="spotaward-category-name">' + cat.name + '</span>' +
                            '</div>';
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                } else {
                    html = '<p>No award categories configured for this course.</p>';
                }
                categoryList.innerHTML = html;
                backdrop.classList.add('is-open');
            }

            function submitForm() {
                bypass = true;
                if (typeof window.localSpotawardStartSuccessOverlay === 'function') {
                    window.localSpotawardStartSuccessOverlay(submitBtn.value || submitBtn.textContent || 'Submit');
                }
                form.requestSubmit ? form.requestSubmit(submitBtn) : form.submit();
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
