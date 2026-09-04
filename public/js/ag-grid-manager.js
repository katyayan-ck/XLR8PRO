window.initEnquiryGrid = function(config) {
    const {
        containerId = '#myGrid',
        listType,
        allColumns = [],
        defaultVisibleFields = [],
        columnDefs = [],
        dataUrl,
        exportUrl,
        csrfToken,
        exportPdfName = 'enquiries',
        rowHeight = 28
    } = config;

    let gridApi;
    let currentSearchText = '';
    let currentHighlightFilter = '';

    // 1. Setup Infinite Row Data Source
    const dataSource = {
        getRows: function(params) {
            fetch(dataUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    startRow: params.startRow,
                    endRow: params.endRow,
                    sortModel: params.sortModel,
                    filterModel: params.filterModel,
                    searchText: currentSearchText,
                    highlightFilter: currentHighlightFilter,
                    list_type: listType
                })
            })
            .then(res => res.json())
            .then(data => {
                params.successCallback(data.rows || [], data.lastRow ?? 0);
            })
            .catch(err => {
                console.error(`Failed to load ${listType} enquiries`, err);
                params.failCallback();
            });
        }
    };

    // 2. Configure Grid Options
    const gridOptions = {
        columnDefs: columnDefs,
        rowModelType: 'infinite',
        datasource: dataSource,
        pagination: true,
        paginationPageSize: 50,
        cacheBlockSize: 50,
        rowHeight: rowHeight,
        animateRows: true,
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' }
        },
        components: {
            htmlRenderer: params => params.value || ''
        },
        onGridReady: params => {
            gridApi = params.api;
            
            // Handle both Flat and Grouped columns to find all field IDs
            const allFields = [];
            columnDefs.forEach(col => {
                if (col.children) {
                    col.children.forEach(c => c.field && allFields.push(c.field));
                } else if (col.field) {
                    allFields.push(col.field);
                }
            });

            // Set initial visibility
            gridApi.setColumnsVisible(allFields, false);
            
            const fieldsToShow = defaultVisibleFields.length ? defaultVisibleFields : allFields;
            gridApi.setColumnsVisible(fieldsToShow, true);
            
            setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 300);
        }
    };

    // 3. Mount Grid
    const gridDiv = document.querySelector(containerId);
    if (gridDiv) {
        agGrid.createGrid(gridDiv, gridOptions);
    }

    // 4. Utility: Debounce
    const debounce = (fn, delay) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    // 5. Smart Global Search
    document.getElementById('quickFilter')?.addEventListener('input', debounce(e => {
        currentSearchText = e.target.value.trim();
        gridApi.setGridOption('datasource', dataSource);
    }, 400));

    // 6. Reset All Button
    document.getElementById('resetAll')?.addEventListener('click', () => {
        const quickFilter = document.getElementById('quickFilter');
        if (quickFilter) quickFilter.value = '';
        currentSearchText = '';
        currentHighlightFilter = '';
        
        document.querySelectorAll('.highlight-filter').forEach(b => b.classList.remove('active'));
        gridApi.setFilterModel(null);
        gridApi.applyColumnState({ defaultState: { sort: null } });
        gridApi.setGridOption('datasource', dataSource);
    });

    // 7. Highlight Filters (Specifically for list.blade.php)
    document.querySelectorAll('.highlight-filter').forEach(btn => {
        btn.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-filter');

            if (currentHighlightFilter === filterValue) {
                currentHighlightFilter = '';
                this.classList.remove('active');
            } else {
                currentHighlightFilter = filterValue;
                document.querySelectorAll('.highlight-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            }
            gridApi.setGridOption('datasource', dataSource);
        });
    });

    // 8. Customise Headers Bubble (With Search Functionality)
    function openColumnBubble() {
        const bubble = document.getElementById('columnBubble');
        const tbody = document.getElementById('columnBubbleBody');
        const searchInput = document.getElementById('columnSearch');
        
        if (!gridApi || !bubble || !tbody) return;

        tbody.innerHTML = '';
        if (searchInput) searchInput.value = '';

        allColumns.forEach(col => {
            if (!col.field) return;

            const tr = document.createElement('tr');
            const tdCheck = document.createElement('td');
            tdCheck.style.width = '40px';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = gridApi.getColumn(col.field)?.isVisible() ?? false;

            // Lock mandatory columns based on common patterns
            if (['serial_no', 'x8_enquiry_no', 'action', 'virtual_no'].includes(col.field)) {
                checkbox.disabled = true;
            }

            checkbox.addEventListener('change', () => {
                gridApi.setColumnsVisible([col.field], checkbox.checked);
            });

            tdCheck.appendChild(checkbox);

            const tdLabel = document.createElement('td');
            tdLabel.textContent = col.headerName || col.field;

            tr.append(tdCheck, tdLabel);
            tbody.appendChild(tr);
        });

        // Ensure all rows are visible when opened (resetting previous searches)
        document.querySelectorAll('#columnBubbleBody tr').forEach(row => row.style.display = '');
        
        bubble.style.display = 'block';
    }

    document.getElementById('btnCustomiseHeaders')?.addEventListener('click', e => {
        e.stopPropagation();
        openColumnBubble();
    });

    document.getElementById('columnSearch')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#columnBubbleBody tr');
        
        rows.forEach(row => {
            const text = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    document.getElementById('closeColumnBubble')?.addEventListener('click', () => {
        const bubble = document.getElementById('columnBubble');
        if (bubble) bubble.style.display = 'none';
    });

    document.getElementById('columnBubble')?.addEventListener('click', e => e.stopPropagation());

    document.addEventListener('click', () => {
        const bubble = document.getElementById('columnBubble');
        if (bubble?.style.display === 'block') bubble.style.display = 'none';
    });

    // 9. Standard Header Visibility Toggles
    document.getElementById('btnAllHeaders')?.addEventListener('click', () => {
        const allCols = gridApi.getAllGridColumns().map(c => c.getColId());
        gridApi.setColumnsVisible(allCols, true);
        setTimeout(() => gridApi.autoSizeAllColumns(), 200);
    });

    document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
        const allCols = gridApi.getAllGridColumns().map(c => c.getColId());
        gridApi.setColumnsVisible(allCols, false);
        
        // Use provided defaults, otherwise fallback to all
        const fieldsToShow = defaultVisibleFields.length ? defaultVisibleFields : allCols;
        gridApi.setColumnsVisible(fieldsToShow, true);
        
        setTimeout(() => gridApi.autoSizeAllColumns(), 200);
    });

    // 10. Exports
    document.getElementById('exportCsv')?.addEventListener('click', () => {
        const params = new URLSearchParams({
            searchText: currentSearchText,
            highlightFilter: currentHighlightFilter, // Sends empty if not applicable
            list_type: listType
        });
        window.location.href = exportUrl + '?' + params.toString();
    });

    document.getElementById('exportPdf')?.addEventListener('click', () => {
        if (!window.jspdf) {
            console.error('jsPDF library not loaded');
            return;
        }
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        const visibleColumns = gridApi.getAllDisplayedColumns()
            .map(col => col.getColDef())
            .filter(col => col.field && col.field !== 'action');

        const headers = visibleColumns.map(col => col.headerName);
        const rows = [];

        gridApi.forEachNodeAfterFilterAndSort(node => {
            if (!node.data) return;
            rows.push(visibleColumns.map(col => node.data[col.field] ?? ''));
        });

        doc.autoTable({
            head: [headers],
            body: rows,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [41, 128, 185] },
        });

        doc.save(`${exportPdfName}-${new Date().toISOString().slice(0, 10)}.pdf`);
    });

    return gridApi; // Return instance in case specific pages need to extend logic
};