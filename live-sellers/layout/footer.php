        </div>
    </main>

    <!-- JavaScript -->
    <?php $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '..' ; ?>
    <script src="<?php echo $base; ?>/assets/js/main.js"></script>
    <script src="<?php echo $base; ?>/assets/js/admin.js"></script>
    <script src="<?php echo $base; ?>/assets/js/live-seller.js"></script>
    <!-- Fallbacks: ensure scripts load from the relative assets path -->
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/live-seller.js"></script>
</body>
</html>