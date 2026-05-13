<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DPR Entry - Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .quick-action-btn {
            width: 100%;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .quick-action-btn.primary {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }
        
        .quick-action-btn.success {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }
        
        .quick-action-btn.warning {
            background: linear-gradient(135deg, var(--warning-color), #d39e00);
            color: #212529;
        }
        
        .quick-action-btn:active {
            transform: scale(0.98);
        }
        
        .reading-input {
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            padding: 1rem;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            transition: border-color 0.3s ease;
        }
        
        .reading-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .validation-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .validation-badge.success {
            background: var(--success-color);
        }
        
        .validation-badge.warning {
            background: var(--warning-color);
        }
        
        .validation-badge.error {
            background: var(--danger-color);
        }
        
        .billing-preview {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .photo-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .photo-upload-area:hover {
            border-color: var(--primary-color);
            background: #f8f9ff;
        }
        
        .photo-upload-area.has-photo {
            border-color: var(--success-color);
            background: #f8fff8;
        }
        
        .floating-save-btn {
            position: fixed;
            bottom: 2rem;
            right: 1rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(40,167,69,0.4);
            font-size: 1.5rem;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .floating-save-btn:active {
            transform: scale(0.95);
        }
        
        .offline-indicator {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1000;
        }
        
        .offline-indicator.online {
            background: var(--success-color);
            color: white;
        }
        
        .offline-indicator.offline {
            background: var(--warning-color);
            color: #212529;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .machinery-selector {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .machinery-option {
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .machinery-option:hover {
            background-color: var(--light-bg);
        }
        
        .machinery-option.selected {
            background-color: #e3f2fd;
            border-left: 4px solid var(--primary-color);
        }
        
        .progress-ring {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
        }
        
        .progress-ring circle {
            transition: stroke-dashoffset 0.5s ease;
        }
        
        @media (max-width: 576px) {
            .mobile-header h1 {
                font-size: 1.5rem;
                margin: 0;
            }
            
            .quick-action-btn {
                padding: 1.2rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="mb-0">
                <i class="fas fa-tachometer-alt"></i> DPR Entry
            </h1>
            <div>
                <span class="badge bg-light text-dark" id="currentDate"></span>
            </div>
        </div>
    </div>
    
    <!-- Offline Indicator -->
    <div id="offlineIndicator" class="offline-indicator online">
        <i class="fas fa-wifi"></i> Online
    </div>
    
    <!-- Main Content -->
    <div class="container-fluid p-3">
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-value" id="todayCount">0</div>
                <div class="stat-label">Today's DPRs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="pendingCount">0</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <!-- Machinery Selection -->
        <div class="machinery-selector">
            <h6 class="mb-3">Select Machinery</h6>
            <div id="machineryList">
                <!-- Will be populated dynamically -->
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div id="quickActions" style="display: none;">
            <button class="quick-action-btn primary" onclick="showReadingEntry()">
                <i class="fas fa-tachometer-alt"></i><br>
                Enter Readings
            </button>
            
            <button class="quick-action-btn success" onclick="showPhotoUpload()">
                <i class="fas fa-camera"></i><br>
                Take Photo
            </button>
            
            <button class="quick-action-btn warning" onclick="quickDPR()">
                <i class="fas fa-bolt"></i><br>
                Quick DPR
            </button>
        </div>
        
        <!-- Reading Entry Form -->
        <div id="readingEntry" style="display: none;">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-tachometer-alt"></i> Meter Reading Entry
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Previous Reading -->
                    <div class="alert alert-info" id="previousReadingInfo" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Previous:</strong> <span id="prevReading">-</span>
                                <br>
                                <small class="text-muted" id="prevDate">-</small>
                            </div>
                            <div class="validation-badge success" id="readingValidationBadge">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reading Inputs -->
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Start Reading</label>
                            <input type="number" class="reading-input" id="startReading" 
                                   placeholder="0000" onchange="validateReadings()">
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Reading</label>
                            <input type="number" class="reading-input" id="endReading" 
                                   placeholder="0000" onchange="validateReadings()">
                        </div>
                    </div>
                    
                    <!-- Progress Display -->
                    <div class="mt-3">
                        <div class="progress-ring">
                            <svg width="60" height="60">
                                <circle cx="30" cy="30" r="25" fill="none" stroke="#e9ecef" stroke-width="5"/>
                                <circle id="progressCircle" cx="30" cy="30" r="25" fill="none" 
                                        stroke="#007bff" stroke-width="5"
                                        stroke-dasharray="157" stroke-dashoffset="157"
                                        transform="rotate(-90 30 30)"/>
                            </svg>
                            <div class="text-center" style="margin-top: -40px;">
                                <strong id="progressPercent">0%</strong>
                            </div>
                        </div>
                        <div class="text-center">
                            <strong>Progress:</strong> <span id="totalProgress">0</span> units
                            <br>
                            <small class="text-muted">Estimated: <span id="estimatedHours">0</span> hours</small>
                        </div>
                    </div>
                    
                    <!-- Billing Preview -->
                    <div class="billing-preview" id="billingPreview" style="display: none;">
                        <h6><i class="fas fa-calculator"></i> Billing Preview</h6>
                        <div class="row">
                            <div class="col-6">
                                <strong>Rate Type:</strong> <span id="rateType">-</span>
                            </div>
                            <div class="col-6 text-right">
                                <strong>Est. Amount:</strong> <span id="estimatedAmount">₹0</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Work Details -->
                    <div class="mt-3">
                        <label class="form-label">Work Details</label>
                        <textarea class="form-control" id="workDetails" rows="3" 
                                  placeholder="What work was done today?"></textarea>
                    </div>
                    
                    <!-- Photo Upload -->
                    <div class="mt-3">
                        <label class="form-label">Add Photo (Optional)</label>
                        <div class="photo-upload-area" id="photoUploadArea" onclick="capturePhoto()">
                            <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                            <p class="mb-0">Tap to take photo</p>
                            <small class="text-muted">or choose from gallery</small>
                        </div>
                        <input type="file" id="photoInput" accept="image/*" style="display: none;" onchange="handlePhotoSelect(event)">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="messageContainer" style="position: fixed; top: 80px; left: 1rem; right: 1rem; z-index: 1000;">
            <!-- Messages will be shown here -->
        </div>
    </div>
    
    <!-- Floating Save Button -->
    <button class="floating-save-btn" id="saveBtn" style="display: none;" onclick="saveDPR()">
        <i class="fas fa-save"></i>
    </button>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedMachinery = null;
        let currentPhoto = null;
        let offlineMode = false;
        let pendingDPRs = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentDate();
            loadMachinery();
            loadOfflineData();
            checkOnlineStatus();
            
            // Set up periodic sync
            setInterval(checkOnlineStatus, 30000);
            setInterval(syncPendingData, 60000);
        });
        
        // Update current date
        function updateCurrentDate() {
            const today = new Date();
            document.getElementById('currentDate').textContent = today.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Load machinery list
        function loadMachinery() {
            // Simulate API call - replace with actual API
            setTimeout(() => {
                const machinery = [
                    { id: 1, name: 'Excavator JCB 220', machine_id: 'EXC-001', rate_type: 'hourly', rate: 500 },
                    { id: 2, name: 'Bulldozer CAT D6', machine_id: 'BLD-002', rate_type: 'daily', rate: 8000 },
                    { id: 3, name: 'Crane 25 Ton', machine_id: 'CRN-003', rate_type: 'hourly', rate: 600 }
                ];
                
                renderMachineryList(machinery);
                updateQuickStats();
            }, 1000);
        }
        
        // Render machinery list
        function renderMachineryList(machinery) {
            const container = document.getElementById('machineryList');
            
            let html = '';
            machinery.forEach(machine => {
                html += `
                    <div class="machinery-option" onclick="selectMachinery(${machine.id})" 
                         data-machinery='${JSON.stringify(machine)}'>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${machine.name}</strong><br>
                                <small class="text-muted">${machine.machine_id} • ${machine.rate_type} • ₹${machine.rate}</small>
                            </div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Select machinery
        function selectMachinery(machineryId) {
            // Remove previous selection
            document.querySelectorAll('.machinery-option').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selection to clicked item
            event.currentTarget.classList.add('selected');
            
            // Get machinery data
            selectedMachinery = JSON.parse(event.currentTarget.dataset.machinery);
            
            // Show quick actions
            document.getElementById('quickActions').style.display = 'block';
            
            // Load previous reading
            loadPreviousReading();
        }
        
        // Load previous reading
        function loadPreviousReading() {
            // Simulate API call
            setTimeout(() => {
                const prevReading = 1250;
                const prevDate = new Date(Date.now() - 24 * 60 * 60 * 1000);
                
                document.getElementById('prevReading').textContent = prevReading;
                document.getElementById('prevDate').textContent = prevDate.toLocaleDateString();
                document.getElementById('previousReadingInfo').style.display = 'block';
                
                // Auto-fill start reading
                document.getElementById('startReading').value = prevReading;
            }, 500);
        }
        
        // Show reading entry
        function showReadingEntry() {
            document.getElementById('readingEntry').style.display = 'block';
            document.getElementById('saveBtn').style.display = 'block';
            
            // Scroll to form
            document.getElementById('readingEntry').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Validate readings
        function validateReadings() {
            const startReading = parseFloat(document.getElementById('startReading').value) || 0;
            const endReading = parseFloat(document.getElementById('endReading').value) || 0;
            const prevReading = parseFloat(document.getElementById('prevReading').textContent) || 0;
            
            let isValid = true;
            let badgeClass = 'success';
            let message = '';
            
            // Basic validation
            if (endReading < startReading) {
                isValid = false;
                badgeClass = 'error';
                message = 'End reading cannot be less than start reading';
            } else if (endReading < prevReading) {
                isValid = false;
                badgeClass = 'error';
                message = 'End reading cannot be less than previous reading';
            } else {
                const progress = endReading - startReading;
                const jump = endReading - prevReading;
                
                if (jump > 500) {
                    badgeClass = 'warning';
                    message = 'Large reading jump detected';
                }
                
                updateProgress(progress);
                updateBillingPreview(progress);
            }
            
            // Update validation badge
            const badge = document.getElementById('readingValidationBadge');
            badge.className = `validation-badge ${badgeClass}`;
            
            if (badgeClass === 'success') {
                badge.innerHTML = '<i class="fas fa-check"></i>';
            } else if (badgeClass === 'warning') {
                badge.innerHTML = '<i class="fas fa-exclamation"></i>';
            } else {
                badge.innerHTML = '<i class="fas fa-times"></i>';
            }
            
            // Show message if any
            if (message) {
                showMessage(message, badgeClass === 'error' ? 'danger' : 'warning');
            }
        }
        
        // Update progress display
        function updateProgress(progress) {
            const maxProgress = 1000; // Assume max 1000 units per day
            const percentage = Math.min((progress / maxProgress) * 100, 100);
            const circumference = 2 * Math.PI * 25;
            const offset = circumference - (percentage / 100) * circumference;
            
            document.getElementById('progressCircle').style.strokeDashoffset = offset;
            document.getElementById('progressPercent').textContent = Math.round(percentage) + '%';
            document.getElementById('totalProgress').textContent = progress;
            document.getElementById('estimatedHours').textContent = (progress / 100).toFixed(1); // Assume 100 units = 1 hour
        }
        
        // Update billing preview
        function updateBillingPreview(progress) {
            if (!selectedMachinery) return;
            
            const hours = progress / 100; // Assume 100 units = 1 hour
            let estimatedAmount = 0;
            
            if (selectedMachinery.rate_type === 'hourly') {
                estimatedAmount = hours * selectedMachinery.rate;
            } else if (selectedMachinery.rate_type === 'daily') {
                estimatedAmount = progress > 0 ? selectedMachinery.rate : 0;
            }
            
            document.getElementById('rateType').textContent = selectedMachinery.rate_type;
            document.getElementById('estimatedAmount').textContent = '₹' + estimatedAmount.toFixed(2);
            document.getElementById('billingPreview').style.display = 'block';
        }
        
        // Capture photo
        function capturePhoto() {
            const input = document.getElementById('photoInput');
            input.click();
        }
        
        // Handle photo selection
        function handlePhotoSelect(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentPhoto = e.target.result;
                    updatePhotoUploadArea();
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Update photo upload area
        function updatePhotoUploadArea() {
            const area = document.getElementById('photoUploadArea');
            
            if (currentPhoto) {
                area.classList.add('has-photo');
                area.innerHTML = `
                    <img src="${currentPhoto}" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                    <p class="mb-0 mt-2"><small>Photo captured</small></p>
                `;
            }
        }
        
        // Quick DPR (minimal data entry)
        function quickDPR() {
            if (!selectedMachinery) {
                showMessage('Please select machinery first', 'warning');
                return;
            }
            
            // Pre-fill with minimal data
            const prevReading = parseFloat(document.getElementById('prevReading').textContent) || 0;
            const estimatedProgress = 200; // Assume 200 units for quick DPR
            
            document.getElementById('startReading').value = prevReading;
            document.getElementById('endReading').value = prevReading + estimatedProgress;
            document.getElementById('workDetails').value = 'Regular operation';
            
            validateReadings();
            showReadingEntry();
        }
        
        // Save DPR
        function saveDPR() {
            if (!selectedMachinery) {
                showMessage('Please select machinery', 'warning');
                return;
            }
            
            const dprData = {
                machinery_id: selectedMachinery.id,
                date: new Date().toISOString().split('T')[0],
                start_reading: document.getElementById('startReading').value,
                end_reading: document.getElementById('endReading').value,
                work_details: document.getElementById('workDetails').value,
                photo: currentPhoto,
                timestamp: new Date().toISOString()
            };
            
            if (offlineMode) {
                // Save to offline storage
                pendingDPRs.push(dprData);
                localStorage.setItem('pendingDPRs', JSON.stringify(pendingDPRs));
                showMessage('DPR saved locally. Will sync when online.', 'success');
            } else {
                // Send to server
                sendDPRToServer(dprData);
            }
            
            // Reset form
            resetForm();
        }
        
        // Send DPR to server
        function sendDPRToServer(dprData) {
            // Simulate API call
            setTimeout(() => {
                showMessage('DPR submitted successfully!', 'success');
                updateQuickStats();
            }, 1000);
        }
        
        // Reset form
        function resetForm() {
            document.getElementById('readingEntry').style.display = 'none';
            document.getElementById('saveBtn').style.display = 'none';
            document.getElementById('startReading').value = '';
            document.getElementById('endReading').value = '';
            document.getElementById('workDetails').value = '';
            currentPhoto = null;
            updatePhotoUploadArea();
            
            // Reset photo upload area
            const area = document.getElementById('photoUploadArea');
            area.classList.remove('has-photo');
            area.innerHTML = `
                <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                <p class="mb-0">Tap to take photo</p>
                <small class="text-muted">or choose from gallery</small>
            `;
        }
        
        // Check online status
        function checkOnlineStatus() {
            offlineMode = !navigator.onLine;
            const indicator = document.getElementById('offlineIndicator');
            
            if (offlineMode) {
                indicator.className = 'offline-indicator offline';
                indicator.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline';
            } else {
                indicator.className = 'offline-indicator online';
                indicator.innerHTML = '<i class="fas fa-wifi"></i> Online';
                
                // Sync pending data if coming back online
                if (pendingDPRs.length > 0) {
                    syncPendingData();
                }
            }
        }
        
        // Sync pending data
        function syncPendingData() {
            if (pendingDPRs.length === 0) return;
            
            pendingDPRs.forEach(dpr => {
                sendDPRToServer(dpr);
            });
            
            // Clear pending data
            pendingDPRs = [];
            localStorage.removeItem('pendingDPRs');
            showMessage('Synced pending DPRs successfully', 'success');
        }
        
        // Load offline data
        function loadOfflineData() {
            const pending = localStorage.getItem('pendingDPRs');
            if (pending) {
                pendingDPRs = JSON.parse(pending);
                if (pendingDPRs.length > 0) {
                    showMessage(`Found ${pendingDPRs.length} pending DPRs to sync`, 'info');
                }
            }
        }
        
        // Update quick stats
        function updateQuickStats() {
            // Simulate stats
            document.getElementById('todayCount').textContent = Math.floor(Math.random() * 10);
            document.getElementById('pendingCount').textContent = pendingDPRs.length;
        }
        
        // Show message
        function showMessage(message, type = 'info') {
            const container = document.getElementById('messageContainer');
            const alertClass = type === 'success' ? 'alert-success' : 
                             type === 'warning' ? 'alert-warning' : 
                             type === 'danger' ? 'alert-danger' : 'alert-info';
            
            const messageEl = document.createElement('div');
            messageEl.className = `alert ${alertClass} alert-dismissible fade show`;
            messageEl.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            container.appendChild(messageEl);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                messageEl.remove();
            }, 5000);
        }
    </script>
</body>
</html>
