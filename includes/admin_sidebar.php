<?php
$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
$adminNavItems = [
    ['file' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'fa-chart-line'],
    ['file' => 'manage_commissions.php', 'label' => 'Commissions', 'icon' => 'fa-hand-holding-dollar'],
    ['file' => 'users.php', 'label' => 'Users', 'icon' => 'fa-users'],
    ['file' => 'owners.php', 'label' => 'Hotel Owners', 'icon' => 'fa-user-tie'],
    ['file' => 'hotels.php', 'label' => 'Hotels', 'icon' => 'fa-hotel'],
    ['file' => 'rooms.php', 'label' => 'Rooms', 'icon' => 'fa-bed'],
    ['file' => 'bookings.php', 'label' => 'Bookings', 'icon' => 'fa-calendar-check'],
    ['file' => 'payments.php', 'label' => 'Payments', 'icon' => 'fa-credit-card'],
    ['file' => 'reviews.php', 'label' => 'Reviews', 'icon' => 'fa-star'],
    ['file' => 'notifications.php', 'label' => 'Notifications', 'icon' => 'fa-bell'],
    ['file' => 'audit_logs.php', 'label' => 'Audit Logs', 'icon' => 'fa-clipboard-list'],
];
?>
<aside class="sidebar admin-shared-sidebar" aria-label="Admin navigation">
    <div class="admin-sidebar-brand">
        <span class="admin-sidebar-brand-icon"><i class="fa-solid fa-hotel"></i></span>
        <span>
            <strong>StayFlow Admin</strong>
            <small>Website Control</small>
        </span>
    </div>

    <nav class="admin-sidebar-nav">
        <?php foreach ($adminNavItems as $item): ?>
            <?php $isActive = $adminCurrentPage === $item['file']; ?>
            <a class="admin-sidebar-link<?= $isActive ? ' active' : '' ?>" href="<?= htmlspecialchars($item['file']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar-footer">
        <a class="admin-sidebar-link admin-sidebar-logout" href="../logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
