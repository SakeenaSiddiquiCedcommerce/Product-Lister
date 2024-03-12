<?php
	$activeShop                        = isset( $_GET['shop_name'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_name'] ) ) : '';
	$renderDataOnGlobalSettings        = get_option( 'ced_etsy_global_settings', false );
	$auto_update_inventory_woo_to_etsy = isset( $renderDataOnGlobalSettings[ $activeShop ]['ced_etsy_auto_update_inventory'] ) ? $renderDataOnGlobalSettings[ $activeShop ]['ced_etsy_auto_update_inventory'] : '';
?>
<div class="ced_etsy_heading">
<?php echo esc_html_e( get_etsy_instuctions_html( 'Crons / Schedulers' ) ); ?>
<div class="ced_etsy_child_element">
	<?php wp_nonce_field( 'global_settings', 'global_settings_submit' ); ?>
	<table class="wp-list-table fixed widefat ced_etsy_schedule_wrap">
		<tbody>
			<tr>
				<th>
					<label><?php echo esc_html_e( 'Update Inventory To Etsy', 'etsy-woocommerce-integration' ); ?></label>
					<?php ced_etsy_tool_tip( 'Auto update price and stock from woocommerce to etsy.' ); ?>
				</th>
				<!-- <td>
					<label class="switch">
						<input type="checkbox" name="ced_etsy_global_settings[ced_etsy_auto_update_inventory]" <?php echo ( 'on' == $auto_update_inventory_woo_to_etsy ) ? 'checked=checked' : ''; ?>>
						<span class="slider round"></span>
					</label>
				</td> -->
				<td>
					<select name="ced_etsy_global_settings[ced_etsy_auto_update_inventory]" class="ced_etsy_select ced_etsy_select_box" >
						<option value="" ><?php esc_html_e( 'Disabled', 'woocommerce-lazada-integration' ); ?></option>
						<option <?php echo ( 'ced_etsy_6min' == $auto_update_inventory_woo_to_etsy ) ? 'selected' : ''; ?> value="ced_etsy_6min"> <?php esc_html_e( 'Every 6 minutes', 'etsy-woocommerce-integration' ); ?></option>
						<option <?php echo ( 'ced_etsy_10min' == $auto_update_inventory_woo_to_etsy ) ? 'selected' : ''; ?> value="ced_etsy_10min"> <?php esc_html_e( 'Every 10 minutes', 'etsy-woocommerce-integration' ); ?></option>
						<option <?php echo ( 'ced_etsy_15min' == $auto_update_inventory_woo_to_etsy ) ? 'selected' : ''; ?> value="ced_etsy_15min"> <?php esc_html_e( 'Every 15 minutes', 'etsy-woocommerce-integration' ); ?></option>
						<option <?php echo ( 'ced_etsy_20min' == $auto_update_inventory_woo_to_etsy ) ? 'selected' : ''; ?> value="ced_etsy_20min"> <?php esc_html_e( 'Every 20 minutes', 'etsy-woocommerce-integration' ); ?></option>
						<option <?php echo ( 'ced_etsy_30min' == $auto_update_inventory_woo_to_etsy ) ? 'selected' : ''; ?> value="ced_etsy_30min"> <?php esc_html_e( 'Every 30 minutes', 'etsy-woocommerce-integration' ); ?></option>
								
															
					</select>
				</td>
			</tr>
		</tbody>
	</table>
</div>
</div>
