<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Reality မှာတော့ ဒီကနေတစ်ဆင့် Admin Notification Table ထဲထည့်ခြင်း သို့မဟုတ် Email ပို့ခြင်းများ လုပ်ဆောင်နိုင်သည်
    $msg = "Thank you for contacting us! Our administration support unit will review your query shortly.";
}
?>

<main class="container my-5" style="max-width: 920px;">

    <!-- Hero Header Section -->
    <div class="text-center mb-5">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-3" style="font-size: 0.78rem;">
            <i class="fa-solid fa-headset me-1.5"></i> 24/7 Administration Support
        </span>
        <h1 class="fw-extrabold text-dark tracking-tight mb-2">Get in Touch with Us</h1>
        <p class="text-secondary mx-auto" style="max-width: 600px; font-size: 0.95rem;">
            Have questions about system integrations, owner registration, or platform issues? Drop us a message below.
        </p>
    </div>

    <!-- Alert Message Banner -->
    <?php if ($msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center p-3.5 mb-4" role="alert">
            <div class="bg-success text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="fa-solid fa-circle-check fs-6"></i>
            </div>
            <div>
                <strong class="d-block fw-bold text-success-emphasis mb-0.5">Message Sent Successfully!</strong>
                <span class="text-success-emphasis opacity-85" style="font-size: 0.88rem;"><?php echo htmlspecialchars($msg); ?></span>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Side: Modern Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white h-100">
                <h4 class="fw-bold text-dark mb-4 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-paper-plane text-primary"></i> Send a Message
                </h4>

                <form action="contact.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark fs-7">Your Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user fs-7"></i></span>
                                <input type="text" name="name" class="form-control bg-light border-start-0 fs-7" placeholder="John Doe" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark fs-7">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope fs-7"></i></span>
                                <input type="email" name="email" class="form-control bg-light border-start-0 fs-7" placeholder="name@example.com" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark fs-7">Subject</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-tag fs-7"></i></span>
                                <input type="text" name="subject" class="form-control bg-light border-start-0 fs-7" placeholder="Inquiry about hotel listing" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark fs-7">Message Details</label>
                            <textarea name="message" class="form-control bg-light fs-7" rows="4" placeholder="How can our support team help you today?" required></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2.5 fw-bold fs-7 shadow-sm w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i> Send Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Side: Corporate HQ Info Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-dark text-white h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(145deg, #0f172a, #1e293b) !important;">
                <div>
                    <div class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-10 px-3 py-1.5 rounded-pill fw-medium mb-3" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-building me-1 text-primary"></i> Main HQ
                    </div>
                    
                    <h4 class="fw-bold text-white mb-4">Corporate Headquarters</h4>

                    <div class="d-flex flex-column gap-3.5 mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-white bg-opacity-10 rounded-3 p-2 text-primary flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div style="font-size: 0.88rem;">
                                <span class="text-white-50 d-block fs-7 mb-0.5">Address</span>
                                <span class="text-white">No. 123, Sule Pagoda Road, Pabedan Township, Yangon, Myanmar.</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-white bg-opacity-10 rounded-3 p-2 text-primary flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div style="font-size: 0.88rem;">
                                <span class="text-white-50 d-block fs-7 mb-0.5">Phone Line</span>
                                <span class="text-white">+95 9 111 222 333</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-white bg-opacity-10 rounded-3 p-2 text-primary flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div style="font-size: 0.88rem;">
                                <span class="text-white-50 d-block fs-7 mb-0.5">Support Email</span>
                                <span class="text-white">management@hbs-v3.com</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-white bg-opacity-10 rounded-3 p-2 text-primary flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div style="font-size: 0.88rem;">
                                <span class="text-white-50 d-block fs-7 mb-0.5">Office Hours</span>
                                <span class="text-white">Monday - Friday (9:00 AM - 5:00 PM)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer note inside card -->
                <div class="pt-3 border-top border-white border-opacity-10 text-white-50" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-shield-halved text-success me-1"></i> Responses usually delivered within 24 business hours.
                </div>
            </div>
        </div>
    </div>

</main>

<?php require_once 'includes/footer.php'; ?>