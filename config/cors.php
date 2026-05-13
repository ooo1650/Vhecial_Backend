<?php
// config/cors.php

// In production, replace '*' with your actual Vercel URL: 
// e.g., https://your-app-name.vercel.app
$allowed_origins = [
    "https://vehicle-frontend-murex.vercel.app",
    "https://vehicle-frontend-nexutaasi-ooo1650s-projects.vercel.app",
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = in_array($origin, $allowed_origins) ? $origin : $allowed_origins[0];

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