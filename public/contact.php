<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="text-warning display-5 fw-bold" style="font-family: 'Playfair Display', serif;">Visit Our Locations</h2>
        <p class="text-secondary">Come browse the stacks and have a listen.</p>
    </div>

    <div class="row g-5">
        <div class="col-md-6">
            <div class="card bg-secondary text-light h-100 border-0 shadow-lg">
                <div class="card-body p-5">
                    <h3 class="card-title text-warning mb-4"><i class="fa-solid fa-location-dot me-2"></i>Changsha Flagship</h3>
                    <p class="card-text mb-4">Our original location and the heart of our vinyl collection. Featuring listening stations and our largest selection of used gems.</p>
                    
                    <ul class="list-unstyled text-light-50">
                        <li class="mb-3"><i class="fa-solid fa-map-pin me-3 text-warning"></i>123 Vinyl St, Changsha, Hunan</li>
                        <li class="mb-3"><i class="fa-regular fa-clock me-3 text-warning"></i>Mon-Sat: 10:00 AM - 9:00 PM<br><span class="ms-5">Sun: 11:00 AM - 7:00 PM</span></li>
                        <li class="mb-3"><i class="fa-solid fa-phone me-3 text-warning"></i>(+86) 731-1234-5678</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card bg-secondary text-light h-100 border-0 shadow-lg">
                <div class="card-body p-5">
                    <h3 class="card-title text-info mb-4"><i class="fa-solid fa-location-dot me-2"></i>Shanghai Branch</h3>
                    <p class="card-text mb-4">Our metropolitan hub for modern classics and rare finds. Located in the vibrant cultural district.</p>
                    
                    <ul class="list-unstyled text-light-50">
                        <li class="mb-3"><i class="fa-solid fa-map-pin me-3 text-info"></i>456 Groove Ave, Shanghai</li>
                        <li class="mb-3"><i class="fa-regular fa-clock me-3 text-info"></i>Mon-Sun: 11:00 AM - 10:00 PM</li>
                        <li class="mb-3"><i class="fa-solid fa-phone me-3 text-info"></i>(+86) 21-8765-4321</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mt-5 pt-5 border-top border-secondary">
        <div class="col-md-8">
            <h3 class="text-white mb-4 text-center">Get in Touch</h3>
            <form action="" method="POST" class="card bg-dark border-secondary p-4" onsubmit="alert('Thank you for your message! We will get back to you shortly.'); return false;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Name</label>
                        <input type="text" name="name" class="form-control bg-secondary text-white border-0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Email</label>
                        <input type="email" name="email" class="form-control bg-secondary text-white border-0" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-secondary">Message</label>
                        <textarea name="message" class="form-control bg-secondary text-white border-0" rows="4" required></textarea>
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-warning px-5">Send Message</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>