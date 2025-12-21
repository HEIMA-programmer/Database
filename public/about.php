<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row align-items-center mb-5">
        <div class="col-lg-6">
            <h1 class="display-4 text-warning fw-bold mb-3" style="font-family: 'Playfair Display', serif;">Our Story</h1>
            <p class="lead text-white">"More than music, it's a collection of time."</p>
            <p class="text-secondary">
                Founded in 2010, Retro Echo Records began as a small passion project in the heart of Changsha. 
                What started with a single crate of vintage vinyl has grown into a premier destination for audiophiles and collectors across China.
            </p>
            <p class="text-secondary">
                Today, we manage a curated collection of over 10,000 unique releases, ranging from rare 1960s rock pressings to the latest indie releases. 
                With flagship locations in Changsha and Shanghai, and a state-of-the-art fulfillment center serving our online community, we are dedicated to keeping the analog spirit alive in a digital world.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="vinyl-placeholder mx-auto" style="width: 300px; height: 300px;">
                <div class="vinyl-center">
                    <div class="text-dark fw-bold">EST.<br>2010</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 text-center mt-5">
        <div class="col-md-4">
            <div class="card bg-dark border-secondary h-100 p-4">
                <i class="fa-solid fa-compact-disc fa-3x text-warning mb-3"></i>
                <h3 class="h5 text-white">Curated Selection</h3>
                <p class="text-muted small">Every record in our inventory is hand-inspected and graded by our experts.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-secondary h-100 p-4">
                <i class="fa-solid fa-users fa-3x text-info mb-3"></i>
                <h3 class="h5 text-white">Community First</h3>
                <p class="text-muted small">Over 5,000 members enjoy exclusive benefits, events, and priority access.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-secondary h-100 p-4">
                <i class="fa-solid fa-recycle fa-3x text-success mb-3"></i>
                <h3 class="h5 text-white">Sustainable Cycle</h3>
                <p class="text-muted small">Our buyback program gives pre-loved records a new home, reducing waste.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>