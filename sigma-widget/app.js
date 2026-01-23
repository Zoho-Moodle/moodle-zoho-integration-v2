/**
 * Moodle Integration Sigma Widget
 * 
 * All API calls go through Zoho Deluge proxy function (no direct backend calls)
 * Proxy function: "standalone.api_proxy"
 */

// Configuration
const CONFIG = {
    tenantId: 'default', // Can be changed per installation
    delugeFunction: 'standalone.api_proxy', // Name of Zoho function
    apiVersion: 'v1'
};

// State
let currentModule = '';
let canonicalSchema = null;

// ===== Initialization =====

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initEventListeners();
    loadInitialData();
});

function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.dataset.tab;
            
            // Update buttons
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // Update content
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${tabName}-tab`) {
                    content.classList.add('active');
                    onTabActivated(tabName);
                }
            });
        });
    });
}

function initEventListeners() {
    // Settings
    document.getElementById('save-settings-btn').addEventListener('click', saveSettings);
    
    // Mappings
    document.getElementById('mapping-module').addEventListener('change', (e) => {
        currentModule = e.target.value;
        if (currentModule) {
            loadMappings(currentModule);
        } else {
            document.getElementById('mappings-content').innerHTML = '';
        }
    });
    
    // Runs filter
    document.getElementById('runs-module-filter').addEventListener('change', () => {
        loadRuns();
    });
    
    // Refresh
    document.getElementById('refresh-btn').addEventListener('click', () => {
        const activeTab = document.querySelector('.tab-btn.active').dataset.tab;
        onTabActivated(activeTab);
        showToast('Refreshed!', 'success');
    });
}

function loadInitialData() {
    document.getElementById('current-tenant').textContent = CONFIG.tenantId;
    loadSettings();
}

function onTabActivated(tabName) {
    switch(tabName) {
        case 'settings':
            loadSettings();
            break;
        case 'modules':
            loadModules();
            break;
        case 'mappings':
            if (currentModule) loadMappings(currentModule);
            break;
        case 'runs':
            loadRuns();
            break;
    }
}

// ===== API Communication via Deluge Proxy =====

async function callBackendAPI(method, path, body = null) {
    try {
        // Call Zoho Deluge function which will handle HMAC signing
        const payload = {
            method: method,
            path: `/v1/extension${path}`,
            body: body ? JSON.stringify(body) : '',
            tenant_id: CONFIG.tenantId
        };
        
        // Use ZOHO.CRM.FUNCTIONS.execute to call the proxy function
        const response = await ZOHO.CRM.FUNCTIONS.execute(CONFIG.delugeFunction, payload);
        
        if (!response.code || response.code !== 'success') {
            throw new Error(response.message || 'API call failed');
        }
        
        return JSON.parse(response.details.output);
        
    } catch (error) {
        console.error('API Error:', error);
        showToast(`Error: ${error.message}`, 'error');
        throw error;
    }
}

// ===== Settings Tab =====

async function loadSettings() {
    const loading = document.getElementById('settings-loading');
    const content = document.getElementById('settings-content');
    
    loading.style.display = 'block';
    content.style.display = 'none';
    
    try {
        const settings = await callBackendAPI('GET', '/settings');
        
        document.getElementById('moodle-enabled').checked = settings.moodle_enabled || false;
        document.getElementById('moodle-url').value = settings.moodle_base_url || '';
        document.getElementById('moodle-token').value = settings.moodle_api_token ? '••••••••' : '';
        document.getElementById('zoho-enabled').checked = settings.zoho_enabled || false;
        
        loading.style.display = 'none';
        content.style.display = 'block';
        
    } catch (error) {
        loading.textContent = 'Failed to load settings';
    }
}

async function saveSettings() {
    const btn = document.getElementById('save-settings-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    try {
        const data = {
            moodle_enabled: document.getElementById('moodle-enabled').checked,
            moodle_base_url: document.getElementById('moodle-url').value,
            zoho_enabled: document.getElementById('zoho-enabled').checked
        };
        
        const token = document.getElementById('moodle-token').value;
        if (token && token !== '••••••••') {
            data.moodle_api_token = token;
        }
        
        await callBackendAPI('PUT', '/settings', data);
        
        showToast('Settings saved successfully!', 'success');
        
    } catch (error) {
        showToast('Failed to save settings', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Save Settings';
    }
}

// ===== Modules Tab =====

async function loadModules() {
    const loading = document.getElementById('modules-loading');
    const list = document.getElementById('modules-list');
    
    loading.style.display = 'block';
    list.innerHTML = '';
    
    try {
        const modules = await callBackendAPI('GET', '/modules');
        
        loading.style.display = 'none';
        
        if (modules.length === 0) {
            list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-text">No modules configured</div></div>';
            return;
        }
        
        modules.forEach(module => {
            const card = createModuleCard(module);
            list.appendChild(card);
        });
        
    } catch (error) {
        loading.textContent = 'Failed to load modules';
    }
}

function createModuleCard(module) {
    const card = document.createElement('div');
    card.className = 'module-card';
    
    const isGrades = module.module_name === 'grades';
    const statusClass = module.enabled ? 'status-enabled' : 'status-disabled';
    const statusText = module.enabled ? 'Enabled' : 'Disabled';
    
    card.innerHTML = `
        <div class="module-info">
            <h3>${module.module_name}</h3>
            <div class="module-meta">
                ${module.last_run_at ? `Last run: ${formatDate(module.last_run_at)}` : 'Never run'}
                ${module.last_run_status ? ` • Status: ${module.last_run_status}` : ''}
            </div>
        </div>
        <div class="module-actions">
            <span class="status-badge ${statusClass}">${statusText}</span>
            ${!isGrades ? `
                <button class="btn-secondary" onclick="toggleModule('${module.module_name}', ${!module.enabled})">
                    ${module.enabled ? 'Disable' : 'Enable'}
                </button>
                ${module.enabled ? `<button class="btn-success" onclick="syncModule('${module.module_name}')">▶️ Sync Now</button>` : ''}
            ` : '<span style="color: #ef4444; font-size: 12px;">⚠️ Grades: Moodle → Zoho only</span>'}
        </div>
    `;
    
    return card;
}

async function toggleModule(moduleName, enable) {
    try {
        await callBackendAPI('PUT', `/modules/${moduleName}`, { enabled: enable });
        showToast(`Module ${enable ? 'enabled' : 'disabled'} successfully!`, 'success');
        loadModules();
    } catch (error) {
        showToast(`Failed to ${enable ? 'enable' : 'disable'} module`, 'error');
    }
}

async function syncModule(moduleName) {
    if (!confirm(`Start sync for ${moduleName} module?`)) return;
    
    try {
        const result = await callBackendAPI('POST', `/sync/${moduleName}/run`, {
            triggered_by: ZOHO.CRM.CONFIG.getCurrentUser().name
        });
        
        showToast(`Sync started! Run ID: ${result.run_id}`, 'success');
        
        // Switch to Runs tab
        document.querySelector('[data-tab="runs"]').click();
        
    } catch (error) {
        if (error.message.includes('Grades')) {
            showToast('Grades sync is Moodle → Zoho direction (not implemented)', 'error');
        } else {
            showToast('Failed to start sync', 'error');
        }
    }
}

// ===== Mappings Tab =====

async function loadMappings(moduleName) {
    const loading = document.getElementById('mappings-loading');
    const content = document.getElementById('mappings-content');
    
    loading.style.display = 'block';
    content.innerHTML = '';
    
    try {
        // Load canonical schema if not loaded
        if (!canonicalSchema) {
            canonicalSchema = await callBackendAPI('GET', '/metadata/canonical-schema');
        }
        
        // Load existing mappings
        const mappings = await callBackendAPI('GET', `/mappings/${moduleName}`);
        
        loading.style.display = 'none';
        
        // Render mappings table
        renderMappingsTable(moduleName, mappings);
        
    } catch (error) {
        loading.style.display = 'none';
        content.innerHTML = '<div class="empty-state"><div class="empty-state-text">Failed to load mappings</div></div>';
    }
}

function renderMappingsTable(moduleName, existingMappings) {
    const content = document.getElementById('mappings-content');
    
    const schema = canonicalSchema[moduleName];
    if (!schema) {
        content.innerHTML = '<div class="empty-state"><div class="empty-state-text">No schema available for this module</div></div>';
        return;
    }
    
    // Create mapping rows from canonical fields
    const canonicalFields = Object.keys(schema.fields);
    const mappingMap = {};
    existingMappings.forEach(m => {
        mappingMap[m.canonical_field] = m;
    });
    
    let tableHTML = `
        <table class="mappings-table">
            <thead>
                <tr>
                    <th>Canonical Field</th>
                    <th>Zoho Field</th>
                    <th>Required</th>
                    <th>Transform</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    canonicalFields.forEach(field => {
        const fieldInfo = schema.fields[field];
        const mapping = mappingMap[field] || {};
        const required = fieldInfo.required ? 'checked' : '';
        
        tableHTML += `
            <tr>
                <td>
                    <strong>${field}</strong>
                    <div style="font-size: 12px; color: #666;">${fieldInfo.description || fieldInfo.type}</div>
                </td>
                <td>
                    <input type="text" 
                           data-canonical="${field}" 
                           data-field="zoho" 
                           value="${mapping.zoho_field_api_name || ''}" 
                           placeholder="e.g., Academic_Email">
                </td>
                <td style="text-align: center;">
                    <input type="checkbox" 
                           data-canonical="${field}" 
                           data-field="required" 
                           ${required || (mapping.required ? 'checked' : '')}>
                </td>
                <td>
                    <input type="text" 
                           data-canonical="${field}" 
                           data-field="transform" 
                           value="${mapping.transform_rules ? mapping.transform_rules.type || '' : ''}" 
                           placeholder="e.g., before_at">
                </td>
            </tr>
        `;
    });
    
    tableHTML += `
            </tbody>
        </table>
        <div style="margin-top: 20px;">
            <button class="btn-primary" onclick="saveMappings('${moduleName}')">💾 Save Mappings</button>
            <button class="btn-secondary" onclick="loadMappings('${moduleName}')">🔄 Reset</button>
        </div>
    `;
    
    content.innerHTML = tableHTML;
}

async function saveMappings(moduleName) {
    const inputs = document.querySelectorAll('[data-canonical]');
    const mappings = [];
    const groupedByField = {};
    
    // Group inputs by canonical field
    inputs.forEach(input => {
        const canonicalField = input.dataset.canonical;
        const fieldType = input.dataset.field;
        
        if (!groupedByField[canonicalField]) {
            groupedByField[canonicalField] = {};
        }
        
        if (fieldType === 'zoho') {
            groupedByField[canonicalField].zoho_field_api_name = input.value;
        } else if (fieldType === 'required') {
            groupedByField[canonicalField].required = input.checked;
        } else if (fieldType === 'transform') {
            groupedByField[canonicalField].transform_type = input.value;
        }
    });
    
    // Build mappings array
    for (const [canonical_field, data] of Object.entries(groupedByField)) {
        if (!data.zoho_field_api_name) continue; // Skip empty mappings
        
        const mapping = {
            canonical_field: canonical_field,
            zoho_field_api_name: data.zoho_field_api_name,
            required: data.required || false,
            transform_rules: {}
        };
        
        if (data.transform_type) {
            mapping.transform_rules = { type: data.transform_type };
        }
        
        mappings.push(mapping);
    }
    
    try {
        await callBackendAPI('PUT', `/mappings/${moduleName}`, { mappings });
        showToast('Mappings saved successfully!', 'success');
    } catch (error) {
        showToast('Failed to save mappings', 'error');
    }
}

// ===== Runs Tab =====

async function loadRuns() {
    const loading = document.getElementById('runs-loading');
    const list = document.getElementById('runs-list');
    const moduleFilter = document.getElementById('runs-module-filter').value;
    
    loading.style.display = 'block';
    list.innerHTML = '';
    
    try {
        const queryParams = moduleFilter ? `?module=${moduleFilter}&limit=50` : '?limit=50';
        const runs = await callBackendAPI('GET', `/runs${queryParams}`);
        
        loading.style.display = 'none';
        
        if (runs.length === 0) {
            list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📊</div><div class="empty-state-text">No sync runs found</div></div>';
            return;
        }
        
        runs.forEach(run => {
            const item = createRunItem(run);
            list.appendChild(item);
        });
        
    } catch (error) {
        loading.style.display = 'none';
        list.innerHTML = '<div class="empty-state"><div class="empty-state-text">Failed to load runs</div></div>';
    }
}

function createRunItem(run) {
    const item = document.createElement('div');
    item.className = 'run-item';
    
    const statusClass = run.status === 'completed' ? 'status-completed' : 
                       run.status === 'running' ? 'status-running' : 'status-failed';
    
    const counts = run.counts || {};
    const total = (counts.new || 0) + (counts.unchanged || 0) + (counts.updated || 0) + (counts.failed || 0);
    
    item.innerHTML = `
        <div class="run-header">
            <div class="run-title">${run.module_name} - ${run.trigger_source}</div>
            <span class="run-status ${statusClass}">${run.status}</span>
        </div>
        <div class="run-meta">
            Started: ${formatDate(run.started_at)} 
            ${run.finished_at ? ` • Finished: ${formatDate(run.finished_at)}` : ''}
        </div>
        ${total > 0 ? `
            <div class="run-counts">
                <div class="count-item">✅ New: ${counts.new || 0}</div>
                <div class="count-item">➖ Unchanged: ${counts.unchanged || 0}</div>
                <div class="count-item">🔄 Updated: ${counts.updated || 0}</div>
                <div class="count-item">❌ Failed: ${counts.failed || 0}</div>
            </div>
        ` : ''}
        ${run.status === 'failed' || (counts.failed > 0) ? `
            <button class="btn-danger" style="margin-top: 10px;" onclick="retryRun('${run.run_id}')">🔄 Retry Failed</button>
        ` : ''}
    `;
    
    item.addEventListener('click', (e) => {
        if (e.target.tagName !== 'BUTTON') {
            viewRunDetails(run.run_id);
        }
    });
    
    return item;
}

async function viewRunDetails(runId) {
    try {
        const details = await callBackendAPI('GET', `/runs/${runId}`);
        
        let message = `Run ID: ${details.run_id}\nModule: ${details.module_name}\nStatus: ${details.status}\n\n`;
        message += `Records processed: ${details.items ? details.items.length : 0}\n\n`;
        
        if (details.error_summary) {
            message += `Errors:\n${details.error_summary}`;
        }
        
        alert(message);
        
    } catch (error) {
        showToast('Failed to load run details', 'error');
    }
}

async function retryRun(runId) {
    if (!confirm('Retry all failed items from this run?')) return;
    
    try {
        const result = await callBackendAPI('POST', `/runs/${runId}/retry-failed`);
        showToast(`Retry started! New run ID: ${result.run_id}`, 'success');
        loadRuns();
    } catch (error) {
        showToast('Failed to retry run', 'error');
    }
}

// ===== Utilities =====

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ===== Zoho SDK Initialization =====

ZOHO.embeddedApp.on("PageLoad", function(data) {
    console.log("Widget loaded successfully");
    loadInitialData();
});

ZOHO.embeddedApp.init();
