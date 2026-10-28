

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('notesContainer');

    const urlParams = new URLSearchParams(window.location.search);
    let currentFaculty = urlParams.get('faculty') || (container ? container.dataset.faculty : 'All') || 'All';
    let currentCategory = urlParams.get('category') || (container ? container.dataset.category : 'All') || 'All';
    let currentYear = urlParams.get('year') || (container ? container.dataset.year : 'All') || 'All';
    let currentBatch = urlParams.get('batch') || (container ? container.dataset.batch : 'All') || 'All';
    let currentSearch = urlParams.get('q') || (container ? container.dataset.search : '') || '';

    const categorySelect = document.getElementById('categorySelect');
    const yearSelect = document.getElementById('yearSelect');
    const batchSelect = document.getElementById('batchSelect');
    const topSearch = document.getElementById('topNavSearch');
    const mobileSearch = document.getElementById('mobileSearchInput');
    const clearMobileBtn = document.getElementById('clearMobileSearchBtn');
    const resetBtn = document.getElementById('resetFiltersBtn');

    if (categorySelect && currentCategory !== 'All') categorySelect.value = currentCategory;
    if (yearSelect && currentYear !== 'All') yearSelect.value = currentYear;
    if (batchSelect && currentBatch !== 'All') batchSelect.value = currentBatch;
    if (topSearch && currentSearch) topSearch.value = currentSearch;
    if (mobileSearch && currentSearch) {
        mobileSearch.value = currentSearch;
        if (clearMobileBtn) clearMobileBtn.style.display = 'block';
    }

    window.applyFilters = function() {
        const cards = document.querySelectorAll('.note-card');
        let visibleCount = 0;
        const q = currentSearch.toLowerCase().trim();

        cards.forEach(card => {
            const cardFaculty = card.getAttribute('data-faculty') || '';
            const cardCategory = card.getAttribute('data-category') || '';
            const cardYear = card.getAttribute('data-year') || '';
            const cardBatch = card.getAttribute('data-batch') || '';
            const cardTitle = card.getAttribute('data-title') || '';
            const cardSubject = card.getAttribute('data-subject') || '';
            const cardAuthor = card.getAttribute('data-author') || '';
            const cardDesc = card.getAttribute('data-desc') || '';

            const matchFaculty = (currentFaculty === 'All' || cardFaculty.toLowerCase() === currentFaculty.toLowerCase());
            const matchCategory = (currentCategory === 'All' || cardCategory.toLowerCase() === currentCategory.toLowerCase());
            const matchYear = (currentYear === 'All' || cardYear.toLowerCase() === currentYear.toLowerCase());
            const matchBatch = (currentBatch === 'All' || cardBatch.toLowerCase().includes(currentBatch.toLowerCase()));

            const matchSearch = (!q || cardTitle.includes(q) || cardSubject.includes(q) || cardAuthor.includes(q) || cardDesc.includes(q) || cardFaculty.toLowerCase().includes(q));

            if (matchFaculty && matchCategory && matchYear && matchBatch && matchSearch) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const emptyBox = document.getElementById('noResultsBox');
        const notesGrid = document.getElementById('notesGrid');
        if (emptyBox) {
            emptyBox.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
        if (notesGrid) {
            notesGrid.style.display = (visibleCount === 0) ? 'none' : 'grid';
        }

        const countText = document.getElementById('resultsCountText');
        if (countText) {
            if (currentFaculty === 'All') {
                countText.textContent = `Showing all ${visibleCount} study notes`;
            } else {
                countText.textContent = `Showing ${visibleCount} note${visibleCount === 1 ? '' : 's'} for Faculty of ${currentFaculty}`;
            }
        }

        const isFiltered = (currentFaculty !== 'All' || currentCategory !== 'All' || currentYear !== 'All' || currentBatch !== 'All' || currentSearch !== '');
        if (resetBtn) {
            resetBtn.style.display = isFiltered ? 'inline-block' : 'none';
        }

        document.querySelectorAll('.filter-tab').forEach(tab => {
            const f = tab.getAttribute('data-faculty') || '';
            if (f.toLowerCase() === currentFaculty.toLowerCase()) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
    };

    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            currentFaculty = this.getAttribute('data-faculty');

            const url = new URL(window.location);
            if (currentFaculty === 'All') {
                url.searchParams.delete('faculty');
            } else {
                url.searchParams.set('faculty', currentFaculty);
            }
            window.history.pushState({}, '', url);

            applyFilters();
        });
    });

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            currentCategory = this.value;
            applyFilters();
        });
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            currentYear = this.value;
            applyFilters();
        });
    }

    if (batchSelect) {
        batchSelect.addEventListener('change', function() {
            currentBatch = this.value;
            applyFilters();
        });
    }

    function updateSearch(val) {
        currentSearch = val;
        if (topSearch && topSearch.value !== val) topSearch.value = val;
        if (mobileSearch && mobileSearch.value !== val) mobileSearch.value = val;
        if (clearMobileBtn) clearMobileBtn.style.display = val.trim() ? 'block' : 'none';
        applyFilters();
    }

    if (topSearch) {
        topSearch.addEventListener('input', function() {
            updateSearch(this.value);
        });
    }

    if (mobileSearch) {
        mobileSearch.addEventListener('input', function() {
            updateSearch(this.value);
        });
    }

    if (clearMobileBtn) {
        clearMobileBtn.addEventListener('click', function() {
            updateSearch('');
            if (mobileSearch) mobileSearch.focus();
        });
    }

    window.clearAllFilters = function() {
        currentFaculty = 'All';
        currentCategory = 'All';
        currentYear = 'All';
        currentBatch = 'All';
        currentSearch = '';

        if (categorySelect) categorySelect.value = 'All';
        if (yearSelect) yearSelect.value = 'All';
        if (batchSelect) batchSelect.value = 'All';
        if (topSearch) topSearch.value = '';
        if (mobileSearch) mobileSearch.value = '';
        if (clearMobileBtn) clearMobileBtn.style.display = 'none';

        const url = new URL(window.location);
        url.search = '';
        window.history.pushState({}, '', url);

        applyFilters();
    };

    if (resetBtn) {
        resetBtn.addEventListener('click', clearAllFilters);
    }

    applyFilters();
});

