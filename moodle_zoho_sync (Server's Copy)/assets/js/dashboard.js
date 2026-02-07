/**
 * Dashboard JavaScript for Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var dashboard = {
    userid: null,
    baseUrl: M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax',
    loadedTabs: {},

    /**
     * Initialize dashboard.
     */
    init: function(userid) {
        this.userid = userid;
        this.loadedTabs = {};
        
        // Load profile data initially.
        this.loadData('profile');
        
        // Setup tab change listeners.
        this.setupTabListeners();
    },

    /**
     * Setup tab change event listeners.
     */
    setupTabListeners: function() {
        var self = this;
        
        // jQuery tab change event.
        $('.dashboard-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href").substring(1); // Remove #
            
            // Load data if not already loaded.
            if (!self.loadedTabs[target]) {
                self.loadData(target);
            }
        });
    },

    /**
     * Load data for a specific tab.
     */
    loadData: function(type) {
        var self = this;
        
        // Show loader.
        $('#' + type + '-loader').show();
        $('#' + type + '-content').hide();
        
        // Make AJAX request.
        $.ajax({
            url: this.baseUrl + '/get_student_data.php',
            method: 'GET',
            data: {
                userid: this.userid,
                type: type,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json',
            success: function(response) {
                self.handleResponse(type, response);
            },
            error: function(xhr, status, error) {
                self.handleError(type, error);
            }
        });
    },

    /**
     * Handle successful response.
     */
    handleResponse: function(type, response) {
        // Hide loader.
        $('#' + type + '-loader').hide();
        $('#' + type + '-content').show();
        
        // Check for error in response.
        if (response.error) {
            $('#' + type + '-content').html(
                '<div class="alert alert-danger">' +
                '<i class="fa fa-exclamation-triangle"></i> ' +
                (response.message || 'Error loading data') +
                '</div>'
            );
            return;
        }
        
        // Mark tab as loaded.
        this.loadedTabs[type] = true;
        
        // Render data based on type.
        switch(type) {
            case 'profile':
                this.renderProfile(response);
                break;
            case 'academics':
                this.renderAcademics(response);
                break;
            case 'finance':
                this.renderFinance(response);
                break;
            case 'classes':
                this.renderClasses(response);
                break;
            case 'grades':
                this.renderGrades(response);
                break;
        }
    },

    /**
     * Handle error response.
     */
    handleError: function(type, error) {
        $('#' + type + '-loader').hide();
        $('#' + type + '-content').show();
        $('#' + type + '-content').html(
            '<div class="alert alert-danger">' +
            '<i class="fa fa-exclamation-triangle"></i> ' +
            'Error loading data. Please try again later.' +
            '</div>'
        );
    },

    /**
     * Render profile data.
     */
    renderProfile: function(data) {
        var html = '';
        
        if (data.student) {
            var student = data.student;
            html += '<div class="profile-card">';
            html += '<h4>Student Information</h4>';
            html += '<dl class="row">';
            html += '<dt class="col-sm-3">Student ID:</dt><dd class="col-sm-9">' + (student.student_id || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3">Name:</dt><dd class="col-sm-9">' + (student.full_name || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3">Email:</dt><dd class="col-sm-9">' + (student.email || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3">Phone:</dt><dd class="col-sm-9">' + (student.phone || 'N/A') + '</dd>';
            html += '<dt class="col-sm-3">Status:</dt><dd class="col-sm-9"><span class="badge badge-success">' + (student.student_status || 'Active') + '</span></dd>';
            html += '</dl>';
            html += '</div>';
        } else {
            html = '<div class="alert alert-info">No profile data available</div>';
        }
        
        $('#profile-content').html(html);
    },

    /**
     * Render academics data.
     */
    renderAcademics: function(data) {
        var html = '';
        
        if (data.programs && data.programs.length > 0) {
            html += '<div class="academics-card">';
            html += '<h4>Current Programs</h4>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped">';
            html += '<thead><tr><th>Program Name</th><th>Status</th><th>Start Date</th><th>Units</th></tr></thead>';
            html += '<tbody>';
            
            data.programs.forEach(function(program) {
                html += '<tr>';
                html += '<td>' + (program.program_name || 'N/A') + '</td>';
                html += '<td><span class="badge badge-primary">' + (program.program_status || 'N/A') + '</span></td>';
                html += '<td>' + (program.start_date || 'N/A') + '</td>';
                html += '<td>' + (program.units_count || 0) + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div></div>';
        } else {
            html = '<div class="alert alert-info">No academic data available</div>';
        }
        
        $('#academics-content').html(html);
    },

    /**
     * Render finance data.
     */
    renderFinance: function(data) {
        var html = '';
        
        if (data.payments || data.summary) {
            html += '<div class="finance-card">';
            
            // Summary.
            if (data.summary) {
                html += '<div class="payment-summary">';
                html += '<h4>Payment Summary</h4>';
                html += '<div class="row">';
                html += '<div class="col-md-4"><div class="summary-box"><h5>Total Fees</h5><p class="amount">$' + (data.summary.total_fees || 0) + '</p></div></div>';
                html += '<div class="col-md-4"><div class="summary-box"><h5>Amount Paid</h5><p class="amount text-success">$' + (data.summary.amount_paid || 0) + '</p></div></div>';
                html += '<div class="col-md-4"><div class="summary-box"><h5>Balance Due</h5><p class="amount text-danger">$' + (data.summary.balance_due || 0) + '</p></div></div>';
                html += '</div></div>';
            }
            
            // Recent payments.
            if (data.payments && data.payments.length > 0) {
                html += '<h4 class="mt-4">Recent Payments</h4>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped">';
                html += '<thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>';
                html += '<tbody>';
                
                data.payments.forEach(function(payment) {
                    html += '<tr>';
                    html += '<td>' + (payment.payment_date || 'N/A') + '</td>';
                    html += '<td>$' + (payment.amount || 0) + '</td>';
                    html += '<td>' + (payment.payment_method || 'N/A') + '</td>';
                    html += '<td><span class="badge badge-success">' + (payment.payment_status || 'Completed') + '</span></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
            }
            
            html += '</div>';
        } else {
            html = '<div class="alert alert-info">No financial data available</div>';
        }
        
        $('#finance-content').html(html);
    },

    /**
     * Render classes data.
     */
    renderClasses: function(data) {
        var html = '';
        
        if (data.classes && data.classes.length > 0) {
            html += '<div class="classes-card">';
            html += '<h4>My Classes</h4>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped">';
            html += '<thead><tr><th>Class Name</th><th>Instructor</th><th>Schedule</th><th>Room</th></tr></thead>';
            html += '<tbody>';
            
            data.classes.forEach(function(cls) {
                html += '<tr>';
                html += '<td>' + (cls.class_name || 'N/A') + '</td>';
                html += '<td>' + (cls.instructor || 'N/A') + '</td>';
                html += '<td>' + (cls.schedule || 'N/A') + '</td>';
                html += '<td>' + (cls.room || 'N/A') + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div></div>';
        } else {
            html = '<div class="alert alert-info">No classes data available</div>';
        }
        
        $('#classes-content').html(html);
    },

    /**
     * Render grades data.
     */
    renderGrades: function(data) {
        var html = '';
        
        if (data.grades && data.grades.length > 0) {
            html += '<div class="grades-card">';
            html += '<h4>My Grades</h4>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped">';
            html += '<thead><tr><th>Unit</th><th>Grade</th><th>Status</th><th>Date</th></tr></thead>';
            html += '<tbody>';
            
            data.grades.forEach(function(grade) {
                var gradeClass = 'badge-success';
                if (grade.grade_status === 'Fail') gradeClass = 'badge-danger';
                else if (grade.grade_status === 'In Progress') gradeClass = 'badge-warning';
                
                html += '<tr>';
                html += '<td>' + (grade.unit_name || 'N/A') + '</td>';
                html += '<td>' + (grade.grade || 'N/A') + '</td>';
                html += '<td><span class="badge ' + gradeClass + '">' + (grade.grade_status || 'N/A') + '</span></td>';
                html += '<td>' + (grade.submission_date || 'N/A') + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div></div>';
        } else {
            html = '<div class="alert alert-info">No grades data available</div>';
        }
        
        $('#grades-content').html(html);
    }
};

/**
 * Initialize dashboard when called from PHP.
 */
function init_dashboard(userid) {
    $(document).ready(function() {
        dashboard.init(userid);
    });
}
