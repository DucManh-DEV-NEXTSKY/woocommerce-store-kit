<?php
/** Blocksy Free footer lifecycle, with exactly one Store Kit footer when its core is active. */
defined( 'ABSPATH' ) || exit;

blocksy_after_current_template();
do_action( 'blocksy:content:bottom' );
?>
	</main>
	<?php
	do_action( 'blocksy:content:after' );
	do_action( 'blocksy:footer:before' );
	if ( function_exists( '\StoreKit\SiteContent\footer' ) ) {
		echo '<footer id="footer" class="ct-footer sk-site-footer" role="contentinfo">';
		echo \StoreKit\SiteContent\footer(); // Core escapes all editable fields and resolves native attachment logos.
		echo '</footer>';
	} else {
		blocksy_output_footer();
	}
	do_action( 'blocksy:footer:after' );
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>
