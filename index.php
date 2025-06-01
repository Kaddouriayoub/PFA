<?php 
// index.php
session_start();

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/admin_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] == 'professeur') {
        header("Location: professor/professor_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] == 'financier') {
        header("Location: finance/finance_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ENSIAS Payment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 400px;
            max-width: 90vw;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 10px 25px rgba(255, 107, 107, 0.3));
            transition: transform 0.3s ease;
        }

        .logo img:hover {
            transform: scale(1.05);
        }

        .brand-name {
            color: #ff6b6b;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .login-title {
            color: #333;
            font-size: 32px;
            font-weight: 300;
            text-align: center;
            margin-bottom: 40px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-input {
            width: 100%;
            padding: 18px 20px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            color: #333;
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
            background: white;
        }

        .form-input::placeholder {
            color: #999;
        }

        .input-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #666;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #ff6b6b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 14px;
        }

        .test-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .test-accounts h4 {
            color: #ff6b6b;
            margin-bottom: 15px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .test-accounts p {
            color: #666;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .test-accounts strong {
            color: #333;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(-5px); }
            50% { transform: translateY(5px); }
        }

        .logo {
            animation: float 3s ease-in-out infinite;
        }

        .logo img {
            animation: inherit;
        }

        .login-container {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .login-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <div class="logo">
                <img src="assets/Ensias.png" alt="ENSIAS Logo">
            </div>
        </div>
        
        <h1 class="login-title">Login</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error">
                <?php
                if ($_GET['error'] == 'invalid') {
                    echo "Identifiants incorrects";
                } elseif ($_GET['error'] == 'role_unknown') {
                    echo "Rôle non reconnu";
                } else {
                    echo "Erreur de connexion";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <form action="auth.php" method="post">
            <div class="form-group">
                <input type="text" name="username" class="form-input" placeholder="Username" required>
                <div class="input-icon"></div>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Password" required id="password">
                <div class="password-toggle" onclick="togglePassword()"></div>
            </div>
            
            <button type="submit" class="submit-btn">Submit</button>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = '👁';
            }
        }

        // Animation au focus des inputs
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>
