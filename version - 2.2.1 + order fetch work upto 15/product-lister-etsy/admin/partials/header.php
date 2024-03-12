<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
$activeShop = isset( $_GET['shop_name'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_name'] ) ) : '';
if ( isset( $_GET['section'] ) ) {

	$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
}
update_option( 'ced_etsy_active_shop', trim( $activeShop ) );
print_r( Cedhandler::show_notice_top( $activeShop ) );
?>
<div class="ced_etsy_loader">
	<img src="<?php echo esc_url( CED_ETSY_URL . 'admin/images/loading.gif' ); ?>" width="50px" height="50px" class="ced_etsy_loading_img" >
</div>
<div class="ced_progress">
		<h3><label for="file">Processing Request</label></h3>
		<h4>Do not press any key or refresh the page until the operation is complete</h4>
		<progress id="ced_progress" value="0" max="100"></progress>
	</div>
<div class="success-admin-notices is-dismissible"></div>
<div class="navigation-wrapper">
	<?php esc_attr( ced_etsy_cedcommerce_logo() ); ?>
	<ul class="navigation">
				<li>
					<?php
					$url = admin_url( 'admin.php?page=ced_etsy_lister&section=ced-etsy-settings&shop_name=' . $activeShop );
					?>
					<a href="<?php echo esc_attr( $url ); ?>" class="
						<?php
						if ( 'ced-etsy-settings' == $section || 'add-shipping-profile-view' == $section ) {
							echo 'active'; }
						?>
							"><?php esc_html_e( 'Global Settings', 'product-lister-etsy' ); ?></a>
							</li>
								<li>
									<?php
									$url = admin_url( 'admin.php?page=ced_etsy_lister&section=category-mapping-view&shop_name=' . $activeShop );
									?>
									<a class="
									<?php
									if ( 'category-mapping-view' == $section ) {
										echo 'active'; }
									?>
										" href="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Category Mapping', 'product-lister-etsy' ); ?></a>
									</li>
									<li>
										<?php
										$url = admin_url( 'admin.php?page=ced_etsy_lister&section=profiles-view&shop_name=' . $activeShop );
										?>
										<a class="
										<?php
										if ( 'profiles-view' == $section ) {
											echo 'active'; }
										?>
											" href="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Profile', 'product-lister-etsy' ); ?></a>
										</li>
										<li>
											<?php
											$url = admin_url( 'admin.php?page=ced_etsy_lister&section=products-view&shop_name=' . $activeShop );
											?>
											<a class="
											<?php
											if ( 'products-view' == $section ) {
												echo 'active'; }
											?>
												" href="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Products', 'product-lister-etsy' ); ?></a>
											</li>
											<li>
												<?php
												$url = admin_url( 'admin.php?page=ced_etsy_lister&section=orders-view&shop_name=' . $activeShop );
												?>
												<a class="
												<?php
												if ( 'orders-view' == $section ) {
													echo 'active'; }
												?>
													" href="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Order', 'product-lister-etsy' ); ?></a>
												</li>
											    <li>
													<?php
													$url = admin_url( 'admin.php?page=ced_etsy_lister&section=etsy-timeline&shop_name=' . $activeShop );
													?>
													<a class="
													<?php
													if ( 'etsy-timeline' == $section ) {
														echo 'active'; }
													?>
														" href="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Timeline', 'woocommerce-etsy-integration' ); ?></a>
												</li>
											</ul>
											<?php
											$active = isset( $_GET['shop_name'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_name'] ) ) : '';

											?>

										</div>
