<?php
require_once 'db.php';

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>Direct access to this script is strictly prohibited.');
}

// SECURITY: Only allow existing superintendents to access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superintendent') {
    die("Access Denied: Only a Superintendent can register new users.");
}

$current_page = 'register';

// AJAX endpoint for searching specializations
if (isset($_GET['search_specializations'])) {
    header('Content-Type: application/json');
    
    $search = $_GET['search'] ?? '';
    $search = trim($search);
    
    try {
        if (empty($search)) {
            // Return top 6 most common specializations when search is empty
            $sql = "SELECT service_name 
                    FROM available_services 
                    ORDER BY service_name ASC 
                    LIMIT 4";
            $stmt = $pdo->query($sql);
        } else {
            // Search for similar specializations (fuzzy matching)
            $sql = "SELECT service_name 
                    FROM available_services 
                    WHERE service_name LIKE ? 
                    OR service_name LIKE ?
                    OR service_name LIKE ?
                    ORDER BY 
                        CASE 
                            WHEN service_name LIKE ? THEN 1
                            WHEN service_name LIKE ? THEN 2
                            WHEN service_name LIKE ? THEN 3
                            ELSE 4
                        END,
                        service_name ASC
                    LIMIT 6";
            
            $searchTerm = "%" . $search . "%";
            $searchStart = $search . "%";
            $searchExact = $search;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $searchStart,  // Starts with search term
                $searchTerm,   // Contains search term
                $searchStart,  // For ordering priority
                $searchStart,  // For ordering priority
                $searchTerm,   // For ordering priority
                $searchExact   // For ordering priority
            ]);
        }
        
        $services = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'services' => $services]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle form submission for registration
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['dentist_name'];
    $user = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone_number'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $services = $_POST['services'] ?? [];

    try {
        $pdo->beginTransaction();

        // Optional: Enforcement - Check if we are exceeding a limit for Superintendents
        if ($role === 'superintendent') {
            $checkStmt = $pdo->query("SELECT COUNT(*) FROM dentists WHERE role = 'superintendent'");
            if ($checkStmt->fetchColumn() >= 5) {
                throw new Exception("Maximum number of Superintendents (5) reached.");
            }
        }

        // 1. Insert into dentists table (FIXED: Added missing phone parameter)
        $stmt = $pdo->prepare("INSERT INTO dentists (dentist_name, username, phone_number, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $user, $phone, $email, $pass, $role]);
        $new_id = $pdo->lastInsertId();

        // 2. Insert specializations into specializations table
        if (!empty($services)) {
            $specStmt = $pdo->prepare("INSERT INTO specializations (dentist_id, service_name) VALUES (?, ?)");
            foreach ($services as $service) {
                $specStmt->execute([$new_id, $service]);
            }
        }

        $pdo->commit();
        $success = "User registered successfully as: " . ucfirst($role);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register New User</title>
    <link rel="stylesheet" href="style.css">
    <!-- <style>
        /* Specialization-specific styles */
        .search-specialization-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        .search-input-wrapper {
            position: relative;
        }
        
        .search-input-wrapper input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            pointer-events: none;
        }
        
        .specialization-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 0 6px 6px;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        
        .specialization-result-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .specialization-result-item:hover {
            background-color: #f8f9fa;
        }
        
        .specialization-result-item.selected {
            background-color: #e7f3ff;
            color: #0066cc;
        }
        
        .specialization-result-item.selected:hover {
            background-color: #d9ebff;
        }
        
        .specialization-result-item.keyboard-selected {
            background-color: #f0f0f0;
        }
        
        .selected-specializations {
            min-height: 40px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
            background-color: #fafafa;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .selected-tag {
            display: inline-flex;
            align-items: center;
            background-color: #e7f3ff;
            color: #0066cc;
            padding: 5px 10px 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #b8d4ff;
        }
        
        .remove-tag {
            background: none;
            border: none;
            color: #0066cc;
            font-size: 18px;
            margin-left: 6px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .remove-tag:hover {
            background-color: rgba(0, 102, 204, 0.1);
        }
        
        .empty-selection {
            color: #999;
            font-style: italic;
            width: 100%;
            text-align: center;
            padding: 10px;
        }
        
        /* Form styles */
        .registration-card {
            max-width: 500px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .registration-card h2 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
        }
        
        .registration-card hr {
            border: none;
            height: 1px;
            background-color: #eee;
            margin: 20px 0 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 4px;
            color: #666;
            font-size: 12px;
        }
        
        .btn-primary {
            background-color: #0066cc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #0052a3;
        }
        
        .msg {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .msg.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .msg.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Main layout */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 250px; /* Adjust based on your sidebar width */
        } -->
    <!-- </style> -->
</head>
<body style="display: flex;">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="registration-card">
        <h2>Register New Staff Member</h2>
        <hr>
        
        <?php if(isset($success)): ?>
            <div class="msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="registrationForm">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="dentist_name" placeholder="Dr. Jane Doe" required class="form-control">
            </div>

            <div class="form-group">
                <label>Login Username</label>
                <input type="text" name="username" placeholder="jdoe_dentist" required class="form-control">
            </div>

            <div class="form-group">
                <label>Temporary Password</label>
                <input type="password" name="password" required class="form-control">
                <small>Minimum 6 characters. User will be asked to change on first login.</small>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required class="form-control" placeholder="jane.doe@clinic.com">
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone_number" required class="form-control" placeholder="(254) 712-345-678">
            </div>
            
            <div class="form-group">
                <label>System Role</label>
                <select name="role" required class="form-control">
                    <option value="">Select a role...</option>
                    <option value="dentist">Standard Dentist</option>
                    <option value="superintendent">Superintendent (Admin)</option>
                </select>
                <small>Superintendents can register other users and view all schedules.</small>
            </div>

            <div class="form-group">
                <label>Specializations</label>
                <div class="search-specialization-container">
                    <div class="search-input-wrapper">
                        <input type="text" 
                               id="specializationSearch" 
                               class="form-control" 
                               placeholder="Start typing to search specializations..."
                               autocomplete="off">
                        <div class="search-icon">🔍</div>
                    </div>
                    <div id="specializationResults" class="specialization-results">
                        <!-- Dynamic results will appear here -->
                    </div>
                </div>
        </div>
        <br/><br/><br/><br/><br/><br/>
                            <div class="form-group">
                <div id="selectedSpecializations" class="selected-specializations">
                    <div class="empty-selection">No specializations selected yet</div>
                </div>
                
                <small>Type to search for specializations. Select from the dropdown to add.</small>
            </div>

            <button type="submit" class="btn-primary">Register Staff Member</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('specializationSearch');
    const resultsContainer = document.getElementById('specializationResults');
    const selectedContainer = document.getElementById('selectedSpecializations');
    const registrationForm = document.getElementById('registrationForm');
    
    let selectedServices = new Set();
    let searchTimeout = null;
    
    // Load initial popular specializations
    searchSpecializations('');
    
    // Search as user types
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchSpecializations(this.value.trim());
        }, 300);
    });
    
    // Show results on focus
    searchInput.addEventListener('focus', function() {
        if (this.value.trim() === '') {
            searchSpecializations('');
        }
        resultsContainer.style.display = 'block';
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
    
    // Handle search
    function searchSpecializations(query) {
        const url = new URL(window.location);
        url.searchParams.append('search_specializations', '1');
        url.searchParams.append('search', query);
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResults(data.services);
                } else {
                    console.error('Search error:', data.error);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
    }
    
    // Display search results
    function displayResults(services) {
        resultsContainer.innerHTML = '';
        
        if (services.length === 0) {
            resultsContainer.innerHTML = `
                <div class="specialization-result-item" style="color: #666; font-style: italic; padding: 15px;">
                    No matching specializations found
                </div>
            `;
            return;
        }
        
        services.forEach(service => {
            const isSelected = selectedServices.has(service);
            const item = document.createElement('div');
            item.className = `specialization-result-item ${isSelected ? 'selected' : ''}`;
            item.textContent = service;
            item.dataset.service = service;
            
            if (isSelected) {
                item.innerHTML = `
                    ${service}
                    <span style="float: right; color: #0066cc;">✓</span>
                `;
            }
            
            item.addEventListener('click', function() {
                toggleSpecialization(service);
            });
            
            resultsContainer.appendChild(item);
        });
        
        resultsContainer.style.display = 'block';
    }
    
    // Toggle specialization selection
    function toggleSpecialization(service) {
        if (selectedServices.has(service)) {
            selectedServices.delete(service);
        } else {
            selectedServices.add(service);
        }
        
        updateSelectedDisplay();
        updateSearchResults();
        updateHiddenInputs();
        searchInput.focus();
    }
    
    // Update selected display
    function updateSelectedDisplay() {
        selectedContainer.innerHTML = '';
        
        if (selectedServices.size === 0) {
            selectedContainer.innerHTML = `
                <div class="empty-selection">No specializations selected yet</div>
            `;
            return;
        }
        
        selectedServices.forEach(service => {
            const tag = document.createElement('div');
            tag.className = 'selected-tag';
            
            tag.innerHTML = `
                ${service}
                <button type="button" class="remove-tag" data-service="${service}">×</button>
            `;
            
            tag.querySelector('.remove-tag').addEventListener('click', function(e) {
                e.stopPropagation();
                selectedServices.delete(service);
                updateSelectedDisplay();
                updateSearchResults();
                updateHiddenInputs();
            });
            
            selectedContainer.appendChild(tag);
        });
    }
    
    // Update search results highlighting
    function updateSearchResults() {
        const resultItems = resultsContainer.querySelectorAll('.specialization-result-item');
        resultItems.forEach(item => {
            const service = item.dataset.service;
            if (selectedServices.has(service)) {
                item.classList.add('selected');
                item.innerHTML = `
                    ${service}
                    <span style="float: right; color: #0066cc;">✓</span>
                `;
            } else {
                item.classList.remove('selected');
                item.textContent = service;
            }
        });
    }
    
    // Update hidden inputs for form submission
    function updateHiddenInputs() {
        // Remove all existing hidden service inputs
        document.querySelectorAll('input[name="services[]"]').forEach(input => {
            if (input.type === 'hidden') input.remove();
        });
        
        // Add new hidden inputs for each selected service
        selectedServices.forEach(service => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'services[]';
            hiddenInput.value = service;
            registrationForm.appendChild(hiddenInput);
        });
    }
    
    // Form validation
    registrationForm.addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long.');
            return false;
        }
        
        return true;
    });
    
    // Keyboard navigation for search results
    searchInput.addEventListener('keydown', function(e) {
        const items = resultsContainer.querySelectorAll('.specialization-result-item');
        if (items.length === 0) return;
        
        let currentIndex = -1;
        items.forEach((item, index) => {
            if (item.classList.contains('keyboard-selected')) {
                currentIndex = index;
                item.classList.remove('keyboard-selected');
            }
        });
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = (currentIndex + 1) % items.length;
            items[nextIndex].classList.add('keyboard-selected');
            items[nextIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
            items[prevIndex].classList.add('keyboard-selected');
            items[prevIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && currentIndex >= 0) {
            e.preventDefault();
            const selectedItem = items[currentIndex];
            const service = selectedItem.dataset.service;
            toggleSpecialization(service);
            searchInput.focus();
        } else if (e.key === 'Escape') {
            resultsContainer.style.display = 'none';
        }
    });
    
    // Clear search when Escape is pressed on empty input
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && this.value === '') {
            resultsContainer.style.display = 'none';
        }
    });
});
</script>
</body>
</html>