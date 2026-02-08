/**
 * Real-Time Course Search - Vanilla JavaScript
 * 
 * Features:
 * - Debounced search (300ms delay)
 * - Keyword highlighting in results
 * - Keyboard navigation (arrow keys, enter, escape)
 * - Empty state handling
 * - Error state handling
 * - Loading states
 * - Dynamic API results
 * 
 * UI Reasoning:
 * - Debouncing reduces API calls and improves performance
 * - Highlighting helps users quickly identify matches
 * - Keyboard navigation improves accessibility and UX
 * - Clear visual feedback for all states (loading, empty, error)
 * - Responsive design for all screen sizes
 */

class CourseSearch {
    constructor() {
        // DOM Elements
        this.searchInput = document.getElementById('searchInput');
        this.resultsContainer = document.getElementById('resultsContainer');
        this.searchInfo = document.getElementById('searchInfo');
        this.searchIcon = document.getElementById('searchIcon');
        this.levelFilter = document.getElementById('levelFilter');
        this.priceFilter = document.getElementById('priceFilter');

        // State
        this.debounceTimer = null;
        this.debounceDelay = 300; // milliseconds
        this.selectedIndex = -1;
        this.results = [];
        this.isLoading = false;
        this.abortController = null;

        // API Configuration
        this.apiBaseUrl = 'http://localhost:8000/api/v1';
        
        // Initialize
        this.init();
    }

    /**
     * Initialize event listeners
     */
    init() {
        // Search input with debouncing
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearchInput(e.target.value);
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeydown(e);
        });

        // Filter changes
        this.levelFilter.addEventListener('change', () => {
            this.handleSearchInput(this.searchInput.value);
        });

        this.priceFilter.addEventListener('change', () => {
            this.handleSearchInput(this.searchInput.value);
        });

        // Click outside to deselect
        document.addEventListener('click', (e) => {
            if (!this.resultsContainer.contains(e.target) && e.target !== this.searchInput) {
                this.selectedIndex = -1;
                this.updateActiveState();
            }
        });

        // Show initial empty state
        this.showEmptyState('Start typing to search for courses...');
    }

    /**
     * Handle search input with debouncing
     */
    handleSearchInput(query) {
        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Cancel previous request if still pending
        if (this.abortController) {
            this.abortController.abort();
        }

        // Reset selection
        this.selectedIndex = -1;

        // Show loading state immediately for UX feedback
        this.showLoadingState(query);

        // Debounce the actual search
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceDelay);
    }

    /**
     * Perform the actual search API call
     */
    async performSearch(query) {
        const trimmedQuery = query.trim();
        const level = this.levelFilter.value;
        const maxPrice = this.priceFilter.value;

        // Show empty state if query is empty
        if (!trimmedQuery && !level && !maxPrice) {
            this.showEmptyState('Type to search for courses...');
            return;
        }

        this.isLoading = true;
        this.showLoadingIndicator();

        // Create new abort controller for this request
        this.abortController = new AbortController();

        try {
            // Build query parameters
            const params = new URLSearchParams();
            if (trimmedQuery) params.append('q', trimmedQuery);
            if (level) params.append('level', level);
            if (maxPrice) params.append('max_price', maxPrice);

            // Make API request
            const response = await fetch(`${this.apiBaseUrl}/search/courses?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.results = data.data;
                this.renderResults(trimmedQuery);
                this.updateSearchInfo(data.meta, trimmedQuery);
            } else {
                throw new Error('Search failed');
            }

        } catch (error) {
            // Ignore abort errors (happens when new search is triggered)
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Search error:', error);
            this.showErrorState(error.message);
        } finally {
            this.isLoading = false;
            this.hideLoadingIndicator();
        }
    }

    /**
     * Render search results with highlighting
     */
    renderResults(query) {
        if (this.results.length === 0) {
            this.showEmptyState('No courses found matching your search.');
            return;
        }

        const resultsHTML = this.results.map((course, index) => {
            const title = this.highlightText(course.title, query);
            const description = this.truncateAndHighlight(course.description, query, 150);
            const price = course.discount_price || course.price;
            const originalPrice = course.discount_price ? course.price : null;

            return `
                <div class="result-item" data-index="${index}" role="button" tabindex="0" aria-label="Course: ${course.title}">
                    <div class="result-title">${title}</div>
                    <div class="result-description">${description}</div>
                    <div class="result-meta">
                        <span class="badge badge-${course.level}">${this.capitalize(course.level)}</span>
                        <span class="meta-item">⏱️ ${course.duration_hours} hours</span>
                        <span class="meta-item">👥 ${course.enrolled_count} enrolled</span>
                        <span class="price">
                            ${originalPrice ? `<s style="color: #999;">$${originalPrice}</s> ` : ''}
                            $${price}
                        </span>
                    </div>
                </div>
            `;
        }).join('');

        this.resultsContainer.innerHTML = resultsHTML;

        // Add click listeners to results
        this.addResultListeners();
    }

    /**
     * Highlight matching text
     */
    highlightText(text, query) {
        if (!query || !text) return text;

        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<span class="highlight">$1</span>');
    }

    /**
     * Truncate text and highlight matches
     */
    truncateAndHighlight(text, query, maxLength) {
        if (!text) return '';

        // First, find if query exists in text
        if (query && text.toLowerCase().includes(query.toLowerCase())) {
            const queryIndex = text.toLowerCase().indexOf(query.toLowerCase());
            const start = Math.max(0, queryIndex - 50);
            const end = Math.min(text.length, queryIndex + query.length + maxLength);
            
            let truncated = text.substring(start, end);
            if (start > 0) truncated = '...' + truncated;
            if (end < text.length) truncated = truncated + '...';
            
            return this.highlightText(truncated, query);
        }

        // If no match, just truncate
        if (text.length > maxLength) {
            return text.substring(0, maxLength) + '...';
        }

        return text;
    }

    /**
     * Escape regex special characters
     */
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Capitalize first letter
     */
    capitalize(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    /**
     * Handle keyboard navigation
     */
    handleKeydown(e) {
        const items = this.resultsContainer.querySelectorAll('.result-item');

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (this.results.length > 0) {
                    this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                    this.updateActiveState();
                    this.scrollToSelected();
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (this.results.length > 0) {
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                    this.updateActiveState();
                    this.scrollToSelected();
                }
                break;

            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0 && this.selectedIndex < this.results.length) {
                    this.selectCourse(this.results[this.selectedIndex]);
                }
                break;

            case 'Escape':
                e.preventDefault();
                this.searchInput.value = '';
                this.selectedIndex = -1;
                this.results = [];
                this.showEmptyState('Type to search for courses...');
                this.searchInput.blur();
                break;
        }
    }

    /**
     * Update active state for keyboard navigation
     */
    updateActiveState() {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('active');
                item.setAttribute('aria-selected', 'true');
            } else {
                item.classList.remove('active');
                item.setAttribute('aria-selected', 'false');
            }
        });
    }

    /**
     * Scroll to selected item
     */
    scrollToSelected() {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        }
    }

    /**
     * Add click listeners to result items
     */
    addResultListeners() {
        const items = this.resultsContainer.querySelectorAll('.result-item');
        items.forEach((item, index) => {
            item.addEventListener('click', () => {
                this.selectedIndex = index;
                this.updateActiveState();
                this.selectCourse(this.results[index]);
            });

            item.addEventListener('mouseenter', () => {
                this.selectedIndex = index;
                this.updateActiveState();
            });
        });
    }

    /**
     * Handle course selection
     */
    selectCourse(course) {
        console.log('Selected course:', course);
        alert(`Selected: ${course.title}\n\nIn a real app, this would navigate to the course details page or enrollment form.`);
        
        // In production, you might:
        // window.location.href = `/courses/${course.slug}`;
        // or open a modal with course details
        // or trigger enrollment flow
    }

    /**
     * Update search info text
     */
    updateSearchInfo(meta, query) {
        if (meta.total === 0) {
            this.searchInfo.textContent = 'No results found';
        } else if (meta.total === 1) {
            this.searchInfo.textContent = `Found 1 course`;
        } else {
            this.searchInfo.textContent = `Found ${meta.total} courses`;
        }

        if (query) {
            this.searchInfo.textContent += ` matching "${query}"`;
        }
    }

    /**
     * Show loading state
     */
    showLoadingState(query) {
        this.searchInfo.textContent = 'Searching...';
    }

    /**
     * Show loading indicator
     */
    showLoadingIndicator() {
        this.searchIcon.style.display = 'none';
        const loader = document.createElement('div');
        loader.className = 'loading';
        loader.id = 'loadingIndicator';
        this.searchIcon.parentNode.appendChild(loader);
    }

    /**
     * Hide loading indicator
     */
    hideLoadingIndicator() {
        const loader = document.getElementById('loadingIndicator');
        if (loader) {
            loader.remove();
        }
        this.searchIcon.style.display = 'block';
    }

    /**
     * Show empty state
     */
    showEmptyState(message) {
        this.searchInfo.textContent = '';
        this.resultsContainer.innerHTML = `
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <h3>No Results</h3>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * Show error state
     */
    showErrorState(errorMessage) {
        this.searchInfo.textContent = '';
        this.resultsContainer.innerHTML = `
            <div class="error-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <h3>Oops! Something went wrong</h3>
                <p>Unable to load search results. Please try again.</p>
                <p style="font-size: 12px; margin-top: 8px; opacity: 0.7;">${errorMessage}</p>
            </div>
        `;
    }
}

// Initialize the search when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new CourseSearch();
    });
} else {
    new CourseSearch();
}
