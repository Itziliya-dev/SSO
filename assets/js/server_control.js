
document.addEventListener('DOMContentLoaded', function() {
    const serverStatusElement = document.getElementById('server-current-status');
    const serverIpElement = document.getElementById('server-ip');
    const serverPortElement = document.getElementById('server-port');
    const serverRamElement = document.getElementById('server-ram');
    const serverCpuElement = document.getElementById('server-cpu');
    const lastUpdatedElement = document.getElementById('last-updated');
    const messageArea = document.getElementById('message-area');
    const loadingOverlay = document.getElementById('loading-overlay'); 

    const actionButtons = document.querySelectorAll('.action-btn');

    const statusLoadingIndicator = '<i class="fas fa-sync-alt fa-spin fa-fw" style="font-size: 0.8em; opacity: 0.7;"></i>';
    let isFetchingStatus = false;
    let normalRefreshInterval = 15000; 
    let fastRefreshInterval = 4000;   
    let currentRefreshInterval = normalRefreshInterval;
    let refreshTimer;
    let fastRefreshCounter = 0;
    const maxFastRefreshCount = 15; 

    function showMainLoading(show) { 
        loadingOverlay.style.display = show ? 'flex' : 'none';
    }

    function showMessage(message, type = 'success') {
        messageArea.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i> ${message}`;
        messageArea.className = `message-area ${type}`;
        messageArea.style.display = 'flex';
    }

    function updateStatusUI(data, isError = false) {
        if (isError || !data || !data.success) {
            let errorMessage = data ? (data.message || 'خطا در دریافت وضعیت.') : 'خطا در دریافت وضعیت.';
            if (serverStatusElement) serverStatusElement.innerHTML = `<span style="color: var(--error-color);">${errorMessage} <i class="fas fa-exclamation-triangle"></i></span>`;
            
            if (lastUpdatedElement) lastUpdatedElement.innerHTML = `<span style="color:var(--error-color);">خطا در بروزرسانی</span>`;
            return;
        }

        let statusColor = 'var(--text-color)';
        let statusIcon = 'fas fa-question-circle';
        let isStableState = false; 

        switch (data.current_state_raw) {
            case 'running':
                statusColor = 'var(--success-color)'; statusIcon = 'fas fa-circle-check'; isStableState = true;
                break;
            case 'starting':
                statusColor = 'var(--warning-color)'; statusIcon = 'fas fa-spinner fa-spin';
                break;
            case 'stopping':
                statusColor = 'var(--error-color)'; statusIcon = 'fas fa-spinner fa-spin';
                break;
            case 'offline':
                statusColor = 'var(--text-muted-color)'; statusIcon = 'fas fa-power-off'; isStableState = true;
                break;
        }
        if (serverStatusElement) serverStatusElement.innerHTML = `<span style="color: ${statusColor};">${data.current_state_html} <i class="${statusIcon}"></i></span>`;

        if (serverIpElement) serverIpElement.textContent = data.network_ip || 'نامشخص';
        if (serverPortElement) serverPortElement.textContent = data.network_port || 'نامشخص';
        if (serverRamElement) serverRamElement.textContent = (data.ram_usage_mb !== null ? data.ram_usage_mb + ' MB' : '...');
        if (serverCpuElement) serverCpuElement.textContent = (data.cpu_usage_percent !== null ? data.cpu_usage_percent + ' %' : '...');
        if (lastUpdatedElement) lastUpdatedElement.textContent = new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Tehran' });

        
        if (currentRefreshInterval === fastRefreshInterval && isStableState) {
            console.log("Server reached stable state, switching to normal refresh.");
            switchToNormalRefresh();
            fastRefreshCounter = 0; 
        }
        
        if (currentRefreshInterval === fastRefreshInterval) {
            fastRefreshCounter++;
            if (fastRefreshCounter >= maxFastRefreshCount) {
                console.log("Max fast refresh attempts reached, switching to normal refresh.");
                switchToNormalRefresh();
                fastRefreshCounter = 0;
            }
        }
    }

    function fetchServerStatus() {
        if (isFetchingStatus) return;
        isFetchingStatus = true;

        if (lastUpdatedElement) { 
            lastUpdatedElement.innerHTML = statusLoadingIndicator;
        }

        fetch('/server_management/server_control_actions.php?action=get_status', {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`خطای شبکه (${response.status}): ${text || response.statusText}`);
                });
            }
            return response.json();
        })
        .then(data => {
            updateStatusUI(data);
        })
        .catch(error => {
            console.error('Fetch Status Error:', error);
            updateStatusUI(null, true); 
        })
        .finally(() => {
            isFetchingStatus = false;
        });
    }

    function sendServerAction(action) {
        if (!confirm(`آیا از ارسال دستور "${action.toUpperCase()}" به سرور مطمئن هستید؟`)) {
            return;
        }
        showMainLoading(true); 
        messageArea.style.display = 'none';

        const formData = new FormData();
        formData.append('action', action);

        fetch('/server_management/server_control_actions.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`خطای شبکه (${response.status}): ${text || response.statusText}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                showMessage(data.message || `دستور "${action}" با موفقیت ارسال شد.`, 'success');
                
                
                fetchServerStatus();
                switchToFastRefresh();
            } else {
                showMessage(data.message || `خطا در ارسال دستور "${action}".`, 'error');
            }
        })
        .catch(error => {
            console.error('Send Action Error:', error);
            showMessage('خطای جدی در ارتباط با سرور برای ارسال دستور: ' + error.message, 'error');
        })
        .finally(() => {
            showMainLoading(false);
        });
    }

    function switchToFastRefresh() {
        console.log("Switching to fast refresh mode (4 seconds).");
        clearInterval(refreshTimer);
        currentRefreshInterval = fastRefreshInterval;
        fastRefreshCounter = 0; 
        refreshTimer = setInterval(fetchServerStatus, currentRefreshInterval);
    }

    function switchToNormalRefresh() {
        console.log("Switching to normal refresh mode (15 seconds).");
        clearInterval(refreshTimer);
        currentRefreshInterval = normalRefreshInterval;
        refreshTimer = setInterval(fetchServerStatus, currentRefreshInterval);
    }


    actionButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const action = this.dataset.action;
            if (action) {
                sendServerAction(action);
            }
        });
    });

    
    fetchServerStatus();

    
    refreshTimer = setInterval(fetchServerStatus, currentRefreshInterval);

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            clearInterval(refreshTimer);
        } else {
            fetchServerStatus(); 
            
            refreshTimer = setInterval(fetchServerStatus, currentRefreshInterval);
        }
    });
});