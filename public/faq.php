<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="text-white display-6 fw-bold">Frequently Asked Questions</h2>
        <p class="text-secondary">Everything you need to know about grading, shipping, and membership.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="accordion accordion-flush" id="faqAccordion">
                
                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-warning shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do you grade your used records?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-light-50">
                            We follow the Goldmine Standard for grading:
                            <ul class="mt-2">
                                <li><strong>Mint (M):</strong> Perfect, absolutely no signs of wear.</li>
                                <li><strong>Near Mint (NM):</strong> Nearly perfect. Looks like it was just opened.</li>
                                <li><strong>Very Good Plus (VG+):</strong> Shows some signs that it was played, but handled with care.</li>
                                <li><strong>Very Good (VG):</strong> Surface noise is evident upon playing, especially in soft passages, but does not overpower the music.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-warning shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            What are your shipping rates?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-light-50">
                            <p>We offer <strong>Free Shipping</strong> on all orders over ¥200.</p>
                            <p>For orders under ¥200, a flat rate of ¥15 applies nationwide.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-warning shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Can I pick up my order in-store?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-light-50">
                            Yes! During checkout, select "Pick up in Store" (BOPIS). You can pick up your records at our Changsha Flagship store or Shanghai Branch, depending on stock availability shown on the product page.
                        </div>
                    </div>
                </div>

                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-warning shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            How does the loyalty program work?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-light-50">
                            It's simple: Earn 1 point for every ¥1 spent. 
                            <br>
                            Accumulate points to upgrade your membership tier (VIP, Gold) and unlock permanent discounts up to 10% off. 
                            Plus, enjoy a 15% discount during your birthday month!
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>