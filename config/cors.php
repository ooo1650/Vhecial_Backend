<?php
// config/cors.php

// In production, replace '*' with your actual Vercel URL: 
// e.g., https://your-app-name.vercel.app
$allowed_origin = "https://vehicle-frontend-murex.vercel.app";

header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Id");
header("Access-Control-Allow-Credentials: true");

// Handle the Preflight (OPTIONS) request sent by the browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}