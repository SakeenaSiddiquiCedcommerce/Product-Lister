<?php
// If tdis file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

require_once CED_ETSY_DIRPATH . 'admin/partials/header.php';
$shop_name = isset( $_GET['shop_name'] ) ? sanitize_text_field( $_GET['shop_name'] ) : '';

?>

<div class="ced_etsy_timeline_wrap">
	<?php
	$log_types = array(
		'Inventory' => 'ced_etsy_product_inventory_logs_' . $shop_name,
		'Product'   => 'ced_etsy_product_logs_' . $shop_name,
	);
	foreach ( $log_types as $label => $log_type ) {
		 $log_info = get_option( $log_type, '' );

		if ( empty( $log_info ) ) {
			$log_info = array();
		} else {
			$log_info = json_decode( $log_info, true );
		}
		$total_records = count( $log_info );
		$log_info      = array_slice( $log_info, 0, 50 );
		?>
		
		
	<div class="ced_etsy_timeline_body">
			<div>

				<h2><?php echo esc_attr( $label ); ?> activity  &nbsp &nbsp &nbsp &nbsp Total Records : <?php echo esc_attr($total_records); ?><input type="search" placeholder= "Search by SKU & Title" id="ced_etsy_search_<?php echo esc_attr($label); ?>" name="search_box_on_timeline" style="margin-left:auto;width:180px;font-weight: normal !important;"><button id="ced_clear_log_<?php echo esc_attr($label); ?>" style="margin-left:5px;font-weight: normal !important;" >Clear Logs</button>
			</h2>
				<table class="ced_etsy_product_log_head">
					<thead>
						<tr>
							<td>Title</td>
							<td>Operation</td>
							<td>Time</td>
							<td>Type</td>
							<td>Response</td>
						</tr>
					</thead>
				</table>
			</div>
		<div class="ced_etsy_timeline_content">
			<?php
			echo '<table class="wp-list-table ' . esc_attr( $log_type ) . ' ced_etsy_logs">';
			$offset = count( $log_info );
			foreach ( $log_info as $key => $info ) {
				echo '<tr class="ced_etsy_log_rows">';
				echo "<td><span class='log_item_label log_details'><a>" . esc_attr( $info['post_title'] ) . "</a></span><span class='log_message' style='display:none;' ><h3>Input payload for " . esc_attr( $info['post_title'] ) . '</h3><button id="ced_close_log_message">Close</button><pre>' . ( ! empty( $info['input_payload'] ) ? json_encode( $info['input_payload'], JSON_PRETTY_PRINT ) : '' ) . '</pre></span></td>';
				echo "<td><span class=''>" . esc_attr( $info['action'] ) . '</span></td>';
				echo "<td><span class=''>" . esc_attr( $info['time'] ) . '</span></td>';
				echo "<td><span class=''>" . ( $info['is_auto'] ? 'Automatic' : 'Manual' ) . '</span></td>';
				echo '<td>';
				if ( isset( $info['response']['response']['results'] ) || isset( $info['response']['results'] ) || isset( $info['response']['listing_id'] ) || isset( $info['response']['response']['products'] ) || isset( $info['response']['products'] ) || isset( $info['response']['listing_id'] ) ) {
					echo "<span class='etsy_log_success log_details'>Success</span>";
				} else {
					echo "<span class='etsy_log_fail log_details'>Failed</span>";
				}
				echo "<span class='log_message' style='display:none;'><h3>Response payload for " . esc_attr( $info['post_title'] ) . '</h3><button id="ced_close_log_message">Close</button><pre>' . ( ! empty( $info['response'] ) ? json_encode( $info['response'], JSON_PRETTY_PRINT ) : '' ) . '</pre></span>';
				echo '</td>';
				echo '</tr>';

			}
			echo '<tr>';
			if ( $offset < $total_records ) {
				echo '<td colspan="2"></td>';
				echo "<td><span class=''><i><a class='ced_etsy_load_more' data-total='" . esc_attr( $total_records ) . "' data-parent='" . esc_attr( $log_type ) . "' data-offset='" . esc_attr( $offset ) . "'>load more</a></i></span></td>";
				echo '</tr>';
			}

			echo '</table>';
			?>

		</div>
	   </div>
		<?php
	}

	?>
	
	<div class="ced_etsy_timeline_body">
			<h2>WP Crons execution</h2>
		<div class="ced_etsy_timeline_content">
		<?php

		$etsy_events = array(
			'Auto update inventory cron job'  => 'ced_etsy_inventory_scheduler_job_' . $shop_name,
			'Sync existing products cron job' => 'ced_etsy_sync_existing_products_job_' . $shop_name,
		);
		echo '<ul>';
		foreach ( $etsy_events as $label => $event ) {
			$event_info = wp_get_scheduled_event( $event );
			echo '<h4>' . esc_attr( $label ) . '</h4> ';
			if ( $event_info ) {
				echo '<li><a>Last executed at : </a>' . esc_attr( gmdate( 'F j, Y g:i a', $event_info->timestamp ) ) . '</li>';
				echo '<li><a>Next execution at: </a>' . esc_attr( gmdate( 'F j, Y g:i a', $event_info->timestamp + $event_info->interval ) ) . '</li>';
			} else {
				echo '<li>Disabled<li>';
			}
		}
		echo '</ul>';
		// echo 'NOTE :<br>';
		// $shop_info = home_url().'/wp-admin/admin-ajax.php?action=ced_etsy_inventory_schedule_manager';
		// echo 'In case youre Scheduler dosent work please ask youre hosting provider to add this URL as server cron job <br>';
		// echo '<br> URL :-'.$shop_info;
		?>
		</div>
	</div>
	
</div>
