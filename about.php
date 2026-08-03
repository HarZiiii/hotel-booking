<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container my-5" style="max-width: 920px;">

    <!-- Hero Header Section -->
    <div class="text-center mb-5">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-3" style="font-size: 0.78rem;">
            <i class="fa-solid fa-sparkles me-1.5"></i> Enterprise Edition v3.0
        </span>
        <h1 class="fw-extrabold text-dark tracking-tight mb-2">About Our Platform</h1>
        <p class="text-secondary mx-auto" style="max-width: 650px; font-size: 0.95rem;">
            Empowering hotel owners and travelers with an all-in-one, high-performance booking & inventory management ecosystem.
        </p>
    </div>

    <!-- Main About Content Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
        
        <!-- Welcome Intro -->
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3 p-3 flex-shrink-0" style="width: 52px; height: 52px;">
                <i class="fa-solid fa-building-circle-check fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-1">Welcome to HBS v3</h4>
                <p class="text-muted leading-relaxed mb-0" style="font-size: 0.93rem;">
                    <strong>Hotel Booking System v3 (HBS v3)</strong> is a robust multi-tenant platform designed to bridge the gap between travelers and hotel property providers. Built with performance and modern database architecture in mind, our system guarantees smooth workflows for corporate administrators, property owners, and everyday guests.
                </p>
            </div>
        </div>

        <hr class="my-4 text-border opacity-25">

        <!-- Core Modules Feature Grid -->
        <h5 class="fw-bold text-dark mb-3.5">
            <i class="fa-solid fa-cubes text-primary me-2"></i>Key Core Modules
        </h5>

        <div class="row g-3 mb-4">
            
            <!-- Module 1 -->
            <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light h-100 border border-slate-100">
                    <div class="text-primary mb-2">
                        <i class="fa-solid fa-wallet fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark fs-7 mb-1">Real-time Ledger & Wallets</h6>
                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                        Secure internal transactional control allowing automated hotel owner revenue splits and withdrawals.
                    </p>
                </div>
            </div>

            <!-- Module 2 -->
            <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light h-100 border border-slate-100">
                    <div class="text-primary mb-2">
                        <i class="fa-solid fa-boxes-stacked fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark fs-7 mb-1">Dynamic Inventory Tracking</h6>
                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                        Comprehensive daily room availability tracking engineered to eliminate overbooking completely.
                    </p>
                </div>
            </div>

            <!-- Module 3 -->
            <div class="col-md-4">
                <div class="p-3 rounded-3 bg-light h-100 border border-slate-100">
                    <div class="text-primary mb-2">
                        <i class="fa-solid fa-user-gear fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark fs-7 mb-1">Granular Profile Control</h6>
                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                        Enhanced customer profile setups right from the initial registration phase for secure validations.
                    </p>
                </div>
            </div>

        </div>

        <!-- Production Status Callout Banner -->
        <div class="p-3.5 rounded-3 border border-info-subtle bg-info-subtle bg-opacity-25 d-flex align-items-center gap-3">
            <div class="text-info flex-shrink-0">
                <i class="fa-solid fa-shield-check fs-4"></i>
            </div>
            <div style="font-size: 0.85rem;" class="text-dark">
                <strong class="d-block text-info-emphasis fw-bold mb-0.5">Production Status: Active Enterprise V3 Edition</strong>
                Configured with strict Foreign Key Referential Integrity and high-performance SQL Indexing layouts.
            </div>
        </div>

    </div>

</main>

<?php require_once 'includes/footer.php'; ?>