<!-- SUPPORT MODAL -->
<div id="mc-support-modal" class="mc-modal">
    <div class="mc-modal-content">
        <span class="mc-modal-close" onclick="mcCloseSupportModal();">&times;</span>

        <h2 class="mc-card-title">Contact Support</h2>

        <form method="post">
            <?php wp_nonce_field('mc_support_form', 'mc_support_form_nonce'); ?>

            <p>
                <label>Your Message</label><br>
                <textarea name="mc_support_message" required></textarea>
            </p>

            <p>
                <button type="submit" name="mc_support_submit">Send Message</button>
            </p>
        </form>
    </div>
</div>

<!-- SUCCESS TOAST -->
<div id="mc-toast" class="mc-toast">Message sent successfully</div>
