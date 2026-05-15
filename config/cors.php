<?php
// config/cors.php

$allowed_origins = [
    "https://vehicle-frontend-murex.vercel.app",
    "https://vehicle-frontend-nexutaasi-ooo1650s-projects.vercel.app",
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Accept any Vercel preview deployment for this project, plus explicit list
$is_allowed = in_array($origin, $allowed_origins)
    || preg_match('/^https:\/\/vehicle-frontend[a-z0-9\-]*\.vercel\.app$/', $origin);

$allowed_origin = $is_allowed ? $origin : $allowed_origins[0];

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