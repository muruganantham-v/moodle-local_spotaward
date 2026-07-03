define([], function() {
    var observerStarted = false;

    function normalise(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function parseColumns(table) {
        try {
            return JSON.parse(table.getAttribute('data-columns') || '[]');
        } catch (e) {
            return [];
        }
    }

    function escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function exportTableCSV(table, columns) {
        var rows = Array.prototype.slice.call(table.tBodies[0].rows || []);
        var visibleRows = rows.filter(function(row) { return !row.hidden; });
        if (!visibleRows.length) return;

        var csv = [];
        var headers = [];
        columns.forEach(function(col) {
            if (col.key !== 'actions') {
                headers.push('"' + col.label.replace(/"/g, '""') + '"');
            }
        });
        csv.push(headers.join(','));

        visibleRows.forEach(function(row) {
            var rowData = [];
            columns.forEach(function(col, idx) {
                if (col.key === 'actions') return;
                var cell = row.cells[idx];
                var text = cell ? cell.getAttribute('data-sort-value') || cell.textContent.trim() : '';
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });

        var blob = new Blob(['\ufeff' + csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = (table.getAttribute('data-table-label') || 'export') + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function compareValues(left, right, type) {
        if (type === 'number' || type === 'date') {
            var leftNumber = parseFloat(left || 0);
            var rightNumber = parseFloat(right || 0);
            if (leftNumber === rightNumber) {
                return 0;
            }
            return leftNumber < rightNumber ? -1 : 1;
        }

        return left.localeCompare(right, undefined, {
            numeric: true,
            sensitivity: 'base'
        });
    }

    function updateSortIndicators(table, state) {
        var headers = table.tHead ? table.tHead.rows[0].cells : [];
        Array.prototype.forEach.call(headers, function(header) {
            header.classList.remove('sort-asc', 'sort-desc');
            if (header.getAttribute('data-column-key') === state.key) {
                header.classList.add(state.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            }
        });
    }

    function sortRows(table, columns, state) {
        var tbody = table.tBodies[0];
        if (!tbody || !state.key || !state.direction) {
            return;
        }

        var columnIndex = -1;
        var columntype = 'text';
        columns.forEach(function(column, index) {
            if (column.key === state.key) {
                columnIndex = index;
                columntype = column.type || 'text';
            }
        });

        if (columnIndex === -1) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function(firstRow, secondRow) {
            var firstCell = firstRow.cells[columnIndex];
            var secondCell = secondRow.cells[columnIndex];
            var firstValue = firstCell ? (firstCell.getAttribute('data-sort-value') || '') : '';
            var secondValue = secondCell ? (secondCell.getAttribute('data-sort-value') || '') : '';
            var comparison = compareValues(firstValue, secondValue, columntype);
            return state.direction === 'asc' ? comparison : comparison * -1;
        });

        rows.forEach(function(row) {
            tbody.appendChild(row);
        });
    }

    function buildSelectOptions(table, columns) {
        var optionsByColumn = {};
        var rows = Array.prototype.slice.call(table.tBodies[0].rows || []);

        columns.forEach(function(column, columnIndex) {
            if (column.filter !== 'select') {
                return;
            }

            var values = {};
            rows.forEach(function(row) {
                var cell = row.cells[columnIndex];
                var rawValue = cell ? (cell.getAttribute('data-filter-value') || '') : '';
                var value = rawValue.trim();
                if (value !== '') {
                    values[value] = true;
                }
            });

            optionsByColumn[column.key] = Object.keys(values).sort(function(a, b) {
                return a.localeCompare(b, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                });
            });
        });

        return optionsByColumn;
    }

    function rowMatches(row, filters, columns, query) {
        var searchHaystack = [];
        var matches = true;

        columns.forEach(function(column, columnIndex) {
            var cell = row.cells[columnIndex];
            var searchValue = normalise(cell ? cell.getAttribute('data-search-value') : '');
            var filterValue = normalise(cell ? cell.getAttribute('data-filter-value') : '');
            var dateValue = cell ? (cell.getAttribute('data-date-value') || '') : '';

            if (column.searchable !== false && searchValue) {
                searchHaystack.push(searchValue);
            }

            if (column.filter === 'select' && filters[column.key] && filterValue !== normalise(filters[column.key])) {
                matches = false;
            }

            if (column.filter === 'date') {
                var fromValue = filters[column.key + '_from'] || '';
                var toValue = filters[column.key + '_to'] || '';

                if (fromValue && (!dateValue || dateValue < fromValue)) {
                    matches = false;
                }

                if (toValue && (!dateValue || dateValue > toValue)) {
                    matches = false;
                }
            }
        });

        if (!matches) {
            return false;
        }

        if (!query) {
            return true;
        }

        return searchHaystack.join(' ').indexOf(normalise(query)) !== -1;
    }

    function updateResults(root, visibleCount) {
        var status = root.querySelector('[data-table-status]');
        var emptyState = root.querySelector('[data-table-empty]');
        var label = root.getAttribute('data-results-label') || 'matching records';

        if (status) {
            status.textContent = visibleCount + ' ' + label;
        }

        if (emptyState) {
            var hidden = visibleCount > 0;
            emptyState.hidden = hidden;
            emptyState.classList.toggle('hidden', hidden);
        }
    }

    function applyFilters(root, table, columns, controls, sortState) {
        var rows = Array.prototype.slice.call(table.tBodies[0].rows || []);
        var query = controls.search ? controls.search.value : '';
        var filters = {};

        Object.keys(controls.filters).forEach(function(key) {
            filters[key] = controls.filters[key].value;
        });

        rows.forEach(function(row) {
            var visible = rowMatches(row, filters, columns, query);
            row.hidden = !visible;
        });

        sortRows(table, columns, sortState);
        updateResults(root, rows.filter(function(row) {
            return !row.hidden;
        }).length);
    }

    function createToolbar(root, table, columns, sortState) {
        var toolbar = root.querySelector('[data-table-toolbar]');
        if (!toolbar) {
            return null;
        }

        var filterPrefix = root.getAttribute('data-filter-prefix') || 'Filter by';
        var searchLabel = root.getAttribute('data-search-label') || 'Search table';
        var searchPlaceholder = root.getAttribute('data-search-placeholder') || 'Search by keyword';
        var clearLabel = root.getAttribute('data-clear-label') || 'Clear filters';
        var exportLabel = root.getAttribute('data-export-label') || 'Export CSV';
        var allLabel = root.getAttribute('data-all-label') || 'All';
        var dateFromLabel = root.getAttribute('data-date-from-label') || 'From date';
        var dateToLabel = root.getAttribute('data-date-to-label') || 'To date';
        var selectOptions = buildSelectOptions(table, columns);
        var controls = {
            filters: {}
        };

        var html = '<div class="spotaward-table-controls">';

        var nosearch = root.getAttribute('data-table-nosearch') === '1';
        if (!nosearch) {
            html += '<label class="spotaward-filter-control spotaward-filter-control-search">';
            html += '<span class="spotaward-filter-label">' + escapeHtml(searchLabel) + '</span>';
            html += '<input type="search" class="spotaward-filter-input" data-table-search="1" placeholder="' + escapeHtml(searchPlaceholder) + '">';
            html += '</label>';
        }

        columns.forEach(function(column) {
            if (column.filter === 'select') {
                html += '<label class="spotaward-filter-control">';
                html += '<span class="spotaward-filter-label">' + escapeHtml(filterPrefix + ' ' + column.label) + '</span>';
                html += '<select class="spotaward-filter-select" data-column-filter="' + escapeHtml(column.key) + '">';
                html += '<option value="">' + escapeHtml(allLabel) + '</option>';
                (selectOptions[column.key] || []).forEach(function(option) {
                    html += '<option value="' + escapeHtml(option) + '">' + escapeHtml(option) + '</option>';
                });
                html += '</select>';
                html += '</label>';
            }

            if (column.filter === 'date') {
                html += '<div class="spotaward-filter-control spotaward-date-range">';
                html += '<span class="spotaward-filter-label">' + escapeHtml(filterPrefix + ' ' + column.label) + '</span>';
                html += '<button type="button" class="spotaward-date-trigger" data-date-trigger="' + escapeHtml(column.key) + '">';
                html += '<span class="spotaward-date-trigger-value">' + escapeHtml(allLabel) + '</span>';
                html += '<span class="spotaward-date-trigger-arrow">▾</span>';
                html += '</button>';
                html += '<div class="spotaward-date-popup" data-date-popup="' + escapeHtml(column.key) + '">';
                html += '<div class="spotaward-date-popup-body">';
                html += '<label class="spotaward-date-popup-label">' + escapeHtml(dateFromLabel) + '</label>';
                html += '<input type="date" class="spotaward-filter-input spotaward-date-popup-input" data-column-filter="' + escapeHtml(column.key + '_from') + '">';
                html += '<label class="spotaward-date-popup-label">' + escapeHtml(dateToLabel) + '</label>';
                html += '<input type="date" class="spotaward-filter-input spotaward-date-popup-input" data-column-filter="' + escapeHtml(column.key + '_to') + '">';
                html += '<div class="spotaward-date-popup-actions">';
                html += '<button type="button" class="btn btn-primary spotaward-date-apply" data-date-key="' + escapeHtml(column.key) + '">Apply</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
        });

        var noclear = root.getAttribute('data-table-noclear') === '1';
        if (!noclear) {
            html += '<button type="button" class="btn btn-secondary spotaward-filter-reset" data-table-clear="1">' +
                escapeHtml(clearLabel) + '</button>';
        }
        html += '</div>';
        toolbar.innerHTML = html;

        var controlsEl = toolbar.querySelector('.spotaward-table-controls');
        if (controlsEl && !controlsEl.children.length) {
            toolbar.style.display = 'none';
        }

        controls.search = toolbar.querySelector('[data-table-search]');
        Array.prototype.forEach.call(toolbar.querySelectorAll('[data-column-filter]'), function(control) {
            controls.filters[control.getAttribute('data-column-filter')] = control;
        });
        controls.clear = toolbar.querySelector('[data-table-clear]');

        var exportBar = root.querySelector('[data-table-export-bar]');
        if (!exportBar) {
            exportBar = document.createElement('div');
            exportBar.setAttribute('data-table-export-bar', '1');
            exportBar.className = 'spotaward-export-bar';
            
            var btnHtml = '<button type="button" class="spotaward-export-btn" data-table-export="1">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                escapeHtml(exportLabel) + '</button>';
                
            var pdfUrl = root.getAttribute('data-download-pdf-url');
            var pdfLabel = root.getAttribute('data-download-pdf-label') || 'Download Student details';
            if (pdfUrl) {
                btnHtml += ' <a href="' + escapeHtml(pdfUrl) + '" class="spotaward-export-btn spotaward-pdf-btn" style="margin-left: 8px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                    escapeHtml(pdfLabel) + '</a>';
            }
            
            exportBar.innerHTML = btnHtml;
            root.insertBefore(exportBar, root.firstChild);
        }
        controls.exportBtn = exportBar.querySelector('[data-table-export]');
        controls.dateApplyBtns = toolbar.querySelectorAll('[data-date-key]');

        if (controls.search) {
            controls.search.addEventListener('input', function() {
                applyFilters(root, table, columns, controls, sortState);
            });
        }

        Object.keys(controls.filters).forEach(function(key) {
            var isDateFilter = key.indexOf('_from') !== -1 || key.indexOf('_to') !== -1;
            if (isDateFilter) {
                return;
            }
            controls.filters[key].addEventListener('input', function() {
                applyFilters(root, table, columns, controls, sortState);
            });
            controls.filters[key].addEventListener('change', function() {
                applyFilters(root, table, columns, controls, sortState);
            });
        });

        var dateTriggerBtns = toolbar.querySelectorAll('[data-date-trigger]');
        Array.prototype.forEach.call(dateTriggerBtns, function(trigger) {
            var key = trigger.getAttribute('data-date-trigger');
            var popup = root.querySelector('[data-date-popup="' + key + '"]');
            if (!popup) return;

            var fromInput = popup.querySelector('[data-column-filter="' + key + '_from"]');
            var toInput = popup.querySelector('[data-column-filter="' + key + '_to"]');

            function updateTriggerText() {
                var valueEl = trigger.querySelector('.spotaward-date-trigger-value');
                if (!valueEl) return;
                var fromVal = fromInput ? fromInput.value : '';
                var toVal = toInput ? toInput.value : '';
                if (fromVal || toVal) {
                    valueEl.textContent = (fromVal || '...') + ' — ' + (toVal || '...');
                } else {
                    valueEl.textContent = allLabel;
                }
            }

            function closePopup() {
                popup.classList.remove('is-open');
            }

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = popup.classList.contains('is-open');
                var allPopups = root.querySelectorAll('.spotaward-date-popup');
                Array.prototype.forEach.call(allPopups, function(p) { p.classList.remove('is-open'); });
                if (!isOpen) {
                    popup.classList.add('is-open');
                }
            });

            document.addEventListener('click', function(e) {
                if (!trigger.contains(e.target) && !popup.contains(e.target)) {
                    closePopup();
                }
            });

            var applyBtn = popup.querySelector('[data-date-key="' + key + '"]');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    updateTriggerText();
                    closePopup();
                    applyFilters(root, table, columns, controls, sortState);
                });
            }

            if (fromInput) {
                fromInput.addEventListener('change', updateTriggerText);
            }
            if (toInput) {
                toInput.addEventListener('change', updateTriggerText);
            }

            updateTriggerText();
        });

        Array.prototype.forEach.call(controls.dateApplyBtns, function(btn) {
            btn.addEventListener('click', function() {
                applyFilters(root, table, columns, controls, sortState);
            });
        });

        controls.clear.addEventListener('click', function() {
            if (controls.search) {
                controls.search.value = '';
            }
            Object.keys(controls.filters).forEach(function(key) {
                controls.filters[key].value = '';
            });
            Array.prototype.forEach.call(root.querySelectorAll('.spotaward-date-trigger-value'), function(el) {
                el.textContent = allLabel;
            });
            applyFilters(root, table, columns, controls, sortState);
        });

        controls.exportBtn.addEventListener('click', function() {
            exportTableCSV(table, columns);
        });

        return controls;
    }

    function enhanceHeaders(root, table, columns, sortState, controls) {
        var headers = table.tHead ? table.tHead.rows[0].cells : [];

        Array.prototype.forEach.call(headers, function(header, index) {
            var column = columns[index];
            if (!column || header.getAttribute('data-header-enhanced') === '1') {
                return;
            }

            header.setAttribute('data-column-key', column.key);

            if (column.sortable === false) {
                header.setAttribute('data-header-enhanced', '1');
                return;
            }

            header.classList.add('sortable');
            var wrap = document.createElement('span');
            wrap.className = 'sort-icon-wrap';
            var up = document.createElement('i');
            up.className = 'fa-solid fa-sort-up';
            var down = document.createElement('i');
            down.className = 'fa-solid fa-sort-down';
            wrap.appendChild(up);
            wrap.appendChild(down);
            header.appendChild(wrap);
            header.setAttribute('data-header-enhanced', '1');
        });

        table.addEventListener('click', function(e) {
            var th = e.target.closest('th.sortable');
            if (!th) return;

            var key = th.getAttribute('data-column-key');
            if (!key) return;

            if (sortState.key === key) {
                if (sortState.direction === 'asc') {
                    sortState.direction = 'desc';
                } else if (sortState.direction === 'desc') {
                    sortState.direction = '';
                    sortState.key = '';
                } else {
                    sortState.direction = 'asc';
                }
            } else {
                sortState.key = key;
                sortState.direction = 'asc';
            }

            updateSortIndicators(table, sortState);
            applyFilters(root, table, columns, controls, sortState);
        });
    }

    function enhanceTable(table) {
        if (!table || table.getAttribute('data-table-enhanced') === '1') {
            return;
        }

        var root = table.closest('[data-table-root]');
        var columns = parseColumns(table);
        if (!root || !columns.length || !table.tBodies.length) {
            return;
        }

        var sortState = {key: '', direction: ''};
        var controls = createToolbar(root, table, columns, sortState);
        if (!controls) {
            return;
        }

        enhanceHeaders(root, table, columns, sortState, controls);
        table.setAttribute('data-table-enhanced', '1');
        applyFilters(root, table, columns, controls, sortState);
    }

    function scan(context) {
        Array.prototype.forEach.call((context || document).querySelectorAll('[data-enhanced-table="1"]'), enhanceTable);
    }

    function observe() {
        if (observerStarted || typeof MutationObserver === 'undefined') {
            return;
        }

        observerStarted = true;
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function(node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches('[data-enhanced-table="1"]')) {
                        enhanceTable(node);
                        return;
                    }

                    scan(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    return {
        init: function() {
            scan(document);
            observe();
        }
    };
});
