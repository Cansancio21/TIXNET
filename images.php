<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: customerP.php");
    exit();
}

$username = $_SESSION['user']['c_id'];
$avatarFolder = 'Uploads/avatars/';

// Create avatar directory if it doesn't exist
if (!is_dir($avatarFolder)) {
    mkdir($avatarFolder, 0777, true);
}

// Set avatar path
$avatarPath = 'default-avatar.png';
$userAvatar = $avatarFolder . $username . '.png';
if (file_exists($userAvatar)) {
    $avatarPath = $userAvatar . '?' . time();
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $uploadFile = $_FILES['avatar'];
    $targetFile = $avatarFolder . $username . '.png';
    
    // Validate image
    $imageFileType = strtolower(pathinfo($uploadFile['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($imageFileType, $allowedTypes)) {
        if ($uploadFile['size'] <= 5000000) {
            if (move_uploaded_file($uploadFile['tmp_name'], $targetFile)) {
                // Success - set session message and redirect
                $_SESSION['message'] = 'Avatar uploaded successfully!';
                header("Location: portal.php");
                exit();
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "File too large. Maximum size is 5MB.";
        }
    } else {
        $error = "Invalid file type. Please upload JPG, PNG, or GIF.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avatar Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>

:root {
    --primary: #3d28e2;
    --secondary: #372fa7;
    --dark: #2d3436;
    --light: #f5f6fa;
    --success: #00b894;
    --danger: #d63031;
    --warning: #fdcb6e;
    --info: #0984e3;
}
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Outer container - WHITE background */
        .outer-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            width: 90%;
            max-width: 800px;
            text-align: center;
            position: relative;
        }
        
        /* Simple Text Alert Styles - No background */
        .alert-text {
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
            z-index: 100;
            /* No background - transparent */
            background: transparent;
        }
        
        .alert-success {
            color: #155724;
        }
        
        .alert-error {
            color: #721c24;
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateX(-50%) translateY(-10px); }
            100% { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        
        /* Inner container */
        .inner-container {
            border-radius: 15px;
            margin-top: 20px;
            width: 90%;
            height: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3),
                        inset 0 1px 1px rgba(255, 255, 255, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.3);
            margin-left: 35px;
        }
        
        /* Image styling - FORCE image to fill entire container */
        .inner-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 2.2rem;
            font-weight: 700;
        }
        
        .user-info {
            color: #666;
            font-size: 16px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .button-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 180px;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #008CBA, #007399);
            color: white;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        input[type="file"] {
            display: none;
        }
        
        .error-message {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px 25px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
            border-left: 5px solid #dc3545;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .button-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 280px;
            }
            
            .inner-container {
                height: 400px;
                width: 100%;
                margin-left: 0;
            }
            
            .inner-container img {
                width: 100%;
            }
            
            h2 {
                font-size: 1.8rem;
            }
            
            .outer-container {
                padding: 25px;
            }
            
            .alert-text {
                top: 10px;
                font-size: 14px;
                padding: 8px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="outer-container">
        <!-- Simple Text Alerts - Positioned absolutely so they don't affect layout -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert-text alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-text alert-error">
                <i class="fas fa-exclamation-triangle"></i> 
                <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <h2><i class="fas fa-user-circle"></i> AVATAR MANAGEMENT</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="button-container">
            <button class="btn btn-primary" onclick="document.getElementById('avatarInput').click()">
                <i class="fas fa-upload"></i> CHANGE PROFILE
            </button>
            
            <button class="btn btn-secondary" onclick="uploadAvatar()" id="uploadBtn">
                <i class="fas fa-save"></i> SET AVATAR
            </button>
            
            <button class="btn btn-back" onclick="goBack()">
                <i class="fas fa-arrow-left"></i> BACK TO PORTAL
            </button>
        </div>
        
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="previewImage(this)">
        </form>
        
        <div class="inner-container">
            <img id="avatarPreview" src="<?php echo $avatarPath; ?>" alt="Avatar Preview" 
                 onerror="this.src='default-avatar.png'">
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('avatarPreview');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    uploadBtn.style.display = 'flex';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function uploadAvatar() {
            const fileInput = document.getElementById('avatarInput');
            if (fileInput.files.length === 0) {
                // Create simple text alert for "Please select image"
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert-text alert-error';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please select an image first!';
                
                // Insert into the outer container
                const outerContainer = document.querySelector('.outer-container');
                outerContainer.appendChild(alertDiv);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 3000);
                return;
            }
            document.getElementById('uploadForm').submit();
        }
        
        function goBack() {
            window.location.href = 'portal.php';
        }
        
        // Hide upload button initially
        document.getElementById('uploadBtn').style.display = 'none';
        
        // Auto-hide session alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-text');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            });
        });
    </script>
</body>
</html>