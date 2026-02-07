/**
 * Enhanced Dashboard JavaScript with Better UX, Error Handling & Emotional Feedback
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var dashboard = {
    userid: null,
    baseUrl: M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax',
    loadedTabs: {},
    retryAttempts: 0,
    maxRetries: 3,

    /**
     * Initialize dashboard with enhanced UX
     */
    init: function(userid) {
        this.userid = userid;
        this.loadedTabs = {};
        this.retryAttempts = 0;
        
        // Load initial tab (profile)
        this.loadTabData('profile');
        
        // Setup tab click handlers
        this.setupTabHandlers();
        
        // Setup mobile optimizations
        this.setupMobileOptimizations();
    },

    /**
     * Setup tab click handlers
     */
    setupTabHandlers: function() {
        const tabs = ['academics', 'finance', 'classes', 'grades'];
        tabs.forEach(tab => {
            const tabLink = document.getElementById(tab + '-tab');
            if (tabLink) {
                tabLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.loadTabData(tab);
                    // Bootstrap tab activation
                    $(tabLink).tab('show');
                });
            }
        });
    },

    /**
     * Load tab data with improved error handling
     */
    loadTabData: function(tabName) {
        // Don't reload if already loaded
        if (this.loadedTabs[tabName]) {
            return;
        }

        const loader = document.getElementById(tabName + '-loader');
        const content = document.getElementById(tabName + '-content');

        // Show skeleton loader
        this.showSkeletonLoader(loader, tabName);

        fetch(this.baseUrl + '/get_student_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                userid: this.userid,
                type: tabName,
                sesskey: M.cfg.sesskey
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok (Status: ' + response.status + ')');
            }
            return response.json();
        })
        .then(data => {
            this.hideLoader(loader);
            
            if (data.success) {
                this.renderTabContent(tabName, data.data, content);
                this.loadedTabs[tabName] = true;
                this.retryAttempts = 0; // Reset retry counter on success
            } else {
                this.showError(content, data.message || 'Unknown error occurred', tabName);
            }
        })
        .catch(error => {
            console.error('Error loading ' + tabName + ':', error);
            this.hideLoader(loader);
            this.showError(content, error.message, tabName, true);
        });
    },

    /**
     * Show skeleton loader (better UX than spinner)
     */
    showSkeletonLoader: function(loader, tabName) {
        const skeletonHTML = `
            <div class="skeleton-loader">
                <div class="skeleton-line" style="width: 60%; height: 24px; margin-bottom: 15px;"></div>
                <div class="skeleton-line" style="width: 100%; height: 16px; margin-bottom: 10px;"></div>
                <div class="skeleton-line" style="width: 90%; height: 16px; margin-bottom: 10px;"></div>
                <div class="skeleton-line" style="width: 80%; height: 16px;"></div>
            </div>
        `;
        loader.innerHTML = skeletonHTML;
        loader.style.display = 'block';
    },

    /**
     * Hide loader
     */
    hideLoader: function(loader) {
        loader.style.display = 'none';
    },

    /**
     * Render tab content with emotional feedback
     */
    renderTabContent: function(tabName, data, container) {
        let html = '';

        switch(tabName) {
            case 'profile':
                html = this.renderProfile(data);
                break;
            case 'academics':
                html = this.renderAcademics(data);
                break;
            case 'finance':
                html = this.renderFinance(data);
                break;
            case 'classes':
                html = this.renderClasses(data);
                break;
            case 'grades':
                html = this.renderGrades(data);
                break;
        }

        container.innerHTML = html;
        container.style.display = 'block';
    },

    /**
     * Render profile with progress indicators
     */
    renderProfile: function(data) {
        if (!data || Object.keys(data).length === 0) {
            return this.getEmptyState('profile', 'No profile data available yet');
        }

        let html = '<div class="card card-body mb-3">';
        
        // Status Badge with Emotional Feedback
        const statusBadge = data.status === 'Active' 
            ? '<span class="badge badge-success"><i class="fa fa-check-circle"></i> Active Student</span>'
            : '<span class="badge badge-secondary"><i class="fa fa-clock-o"></i> ' + (data.status || 'Unknown') + '</span>';
        
        html += '<div class="d-flex justify-content-between align-items-center mb-3">';
        html += '<h4>👤 Student Profile</h4>';
        html += statusBadge;
        html += '</div>';
        
        html += '<div class="row">';
        html += '<div class="col-md-6"><p><strong>Name:</strong> ' + this.escapeHtml(data.name || 'N/A') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Email:</strong> ' + this.escapeHtml(data.email || 'N/A') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Phone:</strong> ' + this.escapeHtml(data.phone || 'N/A') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Program:</strong> ' + this.escapeHtml(data.program || 'N/A') + '</p></div>';
        html += '</div>';
        
        // Emotional Micro-copy
        html += '<div class="alert alert-info mt-3">';
        html += '<i class="fa fa-info-circle"></i> <strong>You\'re all set!</strong> Your profile is complete and active.';
        html += '</div>';
        
        html += '</div>';
        return html;
    },

    /**
     * Render academics with progress feedback
     */
    renderAcademics: function(data) {
        if (!data || !data.courses || data.courses.length === 0) {
            return this.getEmptyState('academics', 'No academic records found', 
                'Start enrolling in courses to see your progress here!');
        }

        let html = '<div class="card card-body">';
        html += '<h4>📚 Academic Progress</h4>';
        
        data.courses.forEach(course => {
            const completion = course.completion || 0;
            const status = completion >= 80 ? 'On Track 🎯' : completion >= 50 ? 'In Progress 📈' : 'Getting Started 🚀';
            const badgeClass = completion >= 80 ? 'success' : completion >= 50 ? 'warning' : 'info';
            
            html += '<div class="course-item mb-3">';
            html += '<div class="d-flex justify-content-between align-items-center mb-2">';
            html += '<h5>' + this.escapeHtml(course.name) + '</h5>';
            html += '<span class="badge badge-' + badgeClass + '">' + status + '</span>';
            html += '</div>';
            html += '<div class="progress" style="height: 25px;">';
            html += '<div class="progress-bar" role="progressbar" style="width: ' + completion + '%;" ';
            html += 'aria-valuenow="' + completion + '" aria-valuemin="0" aria-valuemax="100">';
            html += completion + '%</div>';
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    },

    /**
     * Render finance data
     */
    renderFinance: function(data) {
        if (!data || Object.keys(data).length === 0) {
            return this.getEmptyState('finance', 'No financial records yet');
        }

        let html = '<div class="card card-body">';
        html += '<h4>💳 Financial Overview</h4>';
        html += '<div class="row">';
        html += '<div class="col-md-6"><p><strong>Total Fees:</strong> $' + (data.total_fees || '0.00') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Paid:</strong> $' + (data.paid || '0.00') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Balance:</strong> $' + (data.balance || '0.00') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Status:</strong> ' + (data.status || 'N/A') + '</p></div>';
        html += '</div>';
        html += '</div>';
        return html;
    },

    /**
     * Render classes
     */
    renderClasses: function(data) {
        if (!data || !data.classes || data.classes.length === 0) {
            return this.getEmptyState('classes', 'No classes scheduled', 
                'Your class schedule will appear here once courses start!');
        }

        let html = '<div class="card card-body">';
        html += '<h4>🗓️ My Classes</h4>';
        html += '<ul class="list-group">';
        
        data.classes.forEach(cls => {
            html += '<li class="list-group-item">';
            html += '<strong>' + this.escapeHtml(cls.name) + '</strong><br>';
            html += '<small class="text-muted">' + this.escapeHtml(cls.schedule || 'Schedule TBA') + '</small>';
            html += '</li>';
        });
        
        html += '</ul></div>';
        return html;
    },

    /**
     * Render grades with motivational feedback
     */
    renderGrades: function(data) {
        if (!data || !data.grades || data.grades.length === 0) {
            return this.getEmptyState('grades', 'No grades yet', 
                '📝 Keep up the great work! Your grades will appear here once assignments are graded.');
        }

        let html = '<div class="card card-body">';
        html += '<h4>📊 My Grades</h4>';
        
        // Calculate overall status
        const avgGrade = data.average || 0;
        const statusEmoji = avgGrade >= 80 ? '🌟' : avgGrade >= 70 ? '👍' : '💪';
        const statusText = avgGrade >= 80 ? 'Excellent work!' : avgGrade >= 70 ? 'Good progress!' : 'Keep pushing!';
        
        html += '<div class="alert alert-' + (avgGrade >= 80 ? 'success' : avgGrade >= 70 ? 'info' : 'warning') + ' mb-3">';
        html += statusEmoji + ' <strong>' + statusText + '</strong> Overall Average: ' + avgGrade.toFixed(1) + '%';
        html += '</div>';
        
        html += '<table class="table table-striped">';
        html += '<thead><tr><th>Course</th><th>Assignment</th><th>Grade</th><th>Status</th></tr></thead>';
        html += '<tbody>';
        
        data.grades.forEach(grade => {
            const gradeValue = parseFloat(grade.grade) || 0;
            const statusBadge = gradeValue >= 80 ? 'success' : gradeValue >= 70 ? 'warning' : 'danger';
            
            html += '<tr>';
            html += '<td>' + this.escapeHtml(grade.course) + '</td>';
            html += '<td>' + this.escapeHtml(grade.assignment) + '</td>';
            html += '<td><strong>' + gradeValue.toFixed(1) + '%</strong></td>';
            html += '<td><span class="badge badge-' + statusBadge + '">' + (grade.status || 'Graded') + '</span></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        return html;
    },

    /**
     * Show friendly error with retry option
     */
    showError: function(container, message, tabName, allowRetry = false) {
        let html = '<div class="error-state">';
        html += '<div class="alert alert-danger">';
        html += '<h5><i class="fa fa-exclamation-triangle"></i> Oops! Something went wrong</h5>';
        html += '<p>' + this.escapeHtml(message) + '</p>';
        
        if (allowRetry && this.retryAttempts < this.maxRetries) {
            html += '<button class="btn btn-primary mt-2" onclick="dashboard.retryLoad(\'' + tabName + '\');">';
            html += '<i class="fa fa-refresh"></i> Try Again</button>';
            html += '<p class="small text-muted mt-2">Attempt ' + (this.retryAttempts + 1) + ' of ' + this.maxRetries + '</p>';
        } else if (this.retryAttempts >= this.maxRetries) {
            html += '<p class="mt-2"><strong>Unable to load data after multiple attempts.</strong></p>';
            html += '<p class="small">Please check your internet connection or contact support if the problem persists.</p>';
        }
        
        html += '</div>';
        html += '</div>';
        
        container.innerHTML = html;
        container.style.display = 'block';
    },

    /**
     * Get empty state with friendly message
     */
    getEmptyState: function(type, title, message = '') {
        const emojis = {
            'profile': '👤',
            'academics': '📚',
            'finance': '💳',
            'classes': '🗓️',
            'grades': '📊'
        };
        
        let html = '<div class="empty-state text-center py-5">';
        html += '<div class="empty-icon mb-3" style="font-size: 4rem;">' + (emojis[type] || '📋') + '</div>';
        html += '<h4>' + title + '</h4>';
        if (message) {
            html += '<p class="text-muted">' + message + '</p>';
        }
        html += '</div>';
        return html;
    },

    /**
     * Retry loading tab data
     */
    retryLoad: function(tabName) {
        this.retryAttempts++;
        this.loadedTabs[tabName] = false;
        this.loadTabData(tabName);
    },

    /**
     * Setup mobile optimizations
     */
    setupMobileOptimizations: function() {
        // Touch-friendly tab switching
        if ('ontouchstart' in window) {
            document.querySelectorAll('.nav-link').forEach(link => {
                link.style.minHeight = '44px'; // iOS minimum touch target
                link.style.display = 'flex';
                link.style.alignItems = 'center';
            });
        }

        // Responsive font sizing
        if (window.innerWidth < 768) {
            document.body.style.fontSize = '16px'; // Prevent zoom on iOS
        }
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Add CSS for skeleton loader
const style = document.createElement('style');
style.textContent = `
.skeleton-loader {
    padding: 20px;
}
.skeleton-line {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: 4px;
}
@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.empty-state {
    min-height: 300px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
.error-state {
    padding: 20px;
}
.course-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .nav-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .nav-tabs .nav-link {
        white-space: nowrap;
        font-size: 14px;
        padding: 10px 15px;
    }
    .card-body {
        padding: 15px;
    }
}

/* Accessibility - Focus States */
.nav-link:focus,
.btn:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .badge {
        border: 2px solid currentColor;
    }
}
`;
document.head.appendChild(style);
