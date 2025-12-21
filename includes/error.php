<?php
// includes/error.php

// 确保页面以正确的 HTTP 状态码响应（如果是包含进来的，可能已经发送了 header，这里做个检查）
if (!headers_sent()) {
    http_response_code(500);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - Retro Echo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #121212; color: #e0e0e0; font-family: sans-serif; }
        .error-container { min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .btn-outline-warning { color: #d4af37; border-color: #d4af37; }
        .btn-outline-warning:hover { background-color: #d4af37; color: #000; }
    </style>
</head>
<body>
    <div class="container error-container text-center">
        <div class="mb-4">
            <i class="fa-solid fa-triangle-exclamation fa-5x text-warning"></i>
        </div>
        <h1 class="display-4 fw-bold mb-3">System Unavailable</h1>
        <p class="lead text-secondary mb-5">
            We are experiencing technical difficulties connecting to our services.<br>
            Please try again in a few moments.
        </p>
        
        <?php if (isset($errorMessage) && strpos($errorMessage, 'SQL') === false): ?>
            <div class="alert alert-dark border-secondary d-inline-block px-4 mb-4">
                <small class="text-danger"><?= htmlspecialchars($errorMessage) ?></small>
            </div>
            <br>
        <?php endif; ?>

        <a href="/" class="btn btn-outline-warning px-5 py-2 rounded-pill">
            <i class="fa-solid fa-rotate-right me-2"></i>Retry Connection
        </a>
        
        <p class="mt-5 small text-muted">Error Ref: DB_CONN_FAIL</p>
    </div>
</body>
</html>