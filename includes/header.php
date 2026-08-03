<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Booking System v3</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Plus Jakarta Sans Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4f46e5;         /* Indigo Primary */
            --primary-hover: #4338ca;
            --navbar-bg: #0f172a;            /* Slate 900 Dark Nav Background */
            --navbar-text: #f8fafc;          /* High Contrast Light Text */
            --navbar-muted: #94a3b8;         /* Subtle Muted Links */
            --body-bg: #f8fafc;              /* Slate 50 Light Page Body */
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--body-bg);
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }

        /* Navbar Specific High-Contrast Styles */
        .custom-navbar {
            background-color: var(--navbar-bg) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .custom-navbar .navbar-brand {
            color: #ffffff !important;
            font-weight: 800;
        }

        .custom-navbar .nav-link {
            color: var(--navbar-muted) !important;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .custom-navbar .nav-link:hover,
        .custom-navbar .nav-link.active {
            color: #ffffff !important;
        }

        /* Dropdown Styling */
        .custom-navbar .dropdown-menu {
            background-color: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .custom-navbar .dropdown-item {
            color: #cbd5e1 !important;
            font-weight: 500;
        }

        .custom-navbar .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff !important;
        }

        /* Footer High-Contrast Styles */
        .custom-footer {
            background-color: var(--navbar-bg);
            color: var(--navbar-muted);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .custom-footer a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .custom-footer a:hover {
            color: #ffffff;
        }
    </style>
</head>
<body>