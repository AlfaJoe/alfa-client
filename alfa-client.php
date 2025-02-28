<?php
/*
Plugin Name: Alfa Web Client
Description: Plugin menangani web yang dibuat Alfa
Version: 1.0.0
Author: Alfa Joe
*/

if (!defined('ABSPATH')) {
	exit;
}

/******************* GitHub *******************/
define('ALFA_CLIENT_VERSION', '1.0.0');
define('ALFA_PLUGIN_REPO', 'AlfaJoe/alfa-client');

function alfa_cek_plugin_update($transient) {
	$remote = wp_remote_get("https://api.github.com/repos/" . ALFA_PLUGIN_REPO . "/releases/latest");

	if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) {
		return $transient;
	}

	$remote = json_decode(wp_remote_retrieve_body($remote));

	if ($remote && version_compare(ALFA_CLIENT_VERSION, $remote->tag_name, '<')) {
		$transient->response[plugin_basename(__FILE__)] = (object) array(
			'slug' => 'alfa-client',
			'new_version' => $remote->tag_name,
			'package' => $remote->zipball_url,
			'url' => $remote->html_url,
		);
	}
	return $transient;
}
add_filter('site_transient_update_plugins', 'alfa_cek_plugin_update');

// Bersihkan plugin cache update
function alfa_client_update_clear_cache() {
	delete_site_transient('update_plugins');
}
add_action('upgrader_process_complete', 'alfa_client_update_clear_cache', 10, 2);

/******************* Tambah menu baru *******************/
/* function beking_menu() {
	add_menu_page(
		'Alfa Kelola Web Client',         // Page title
        'Alfa Kelola Web Client',         // Menu title
        'manage_options',          // Capability
        'alfa-kelola-web-client',   // Menu slug
        'tampilkan_data_alfa_kelola_web', // Callback function
        'dashicons-admin-site',    // Icon (WordPress dashicons)
        20                         // Position
	);
}
add_action('admin_menu', 'beking_menu'); */

global $hari_ini, $api_url, $website_target;
$website_target = home_url();
$hari_ini = date('Y-m-d');
$api_url = 'https://berbagehost.com/wp-json/alfa/v1/web-list/?nocache=' . time() . '&website=' . $website_target;

function tampilkan_data_alfa_kelola_web() {
	global $hari_ini, $api_url, $website_target;
	
	$response = wp_remote_get($api_url);
	
	if (is_wp_error($response)) {
		echo '<div class="notice notice-error"><p>Gagal mengambil data dari API.</p></div>';
        return;
	}
	
	$data = json_decode(wp_remote_retrieve_body($response));
	
	if (empty($data)) {
		//echo "<script>alert('$dataa');</script>";
		echo '<div class="notice notice-warning"><p>Tidak ada data yang tersedia.</p></div>';
        return;
	}
	?>

	<div class="wrap">
        <h1>Data Website dari API</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><b>Nama</b></th>
                    <th><b>Website</b></th>
                    <th><b>Tanggal Dibuat</b></th>
                    <th><b>Tanggal Expire</b></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row->nama); ?></td>
                        <td><a href="<?php echo esc_url($row->website); ?>" target="_blank"><?php echo esc_html($row->website); ?></a></td>
                        <td><?php echo esc_html($row->tanggal_dibuat); ?></td>
                        <td><?php echo esc_html($row->tanggal_expire); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
}
/******************* Sembunyikan plugin mulai *******************/
add_filter('all_plugins', function($plugins) {
	if (isset($plugins['alfa_client/alfa_client.php'])) {
		unset($plugins['alfa_client/alfa_client.php']);
	}
	return $plugins;
});

/****************** Notifikasi pop up mulai ******************/
function alfa_notifikasi_expire() {
	global $hari_ini, $api_url, $website_target;
	
	$response = wp_remote_get($api_url);
	$data = json_decode(wp_remote_retrieve_body($response));
	
	if (!empty($data)) {
		foreach ($data as $web) {
			$tanggal_expire = $web->tanggal_expire;
			$expire_time = strtotime($tanggal_expire);
			
			$bulan_sebelumnya = date('Y-m-d', strtotime('-1 month', $expire_time));
			$tanggal_7_hari = date('Y-m-d', strtotime('-7 days', $expire_time));
			$tanggal_3_hari = date('Y-m-d', strtotime('-3 days', $expire_time));
			$tanggal_2_hari = date('Y-m-d', strtotime('-2 days', $expire_time));
			$tanggal_1_hari = date('Y-m-d', strtotime('-1 days', $expire_time));
			
			// Tampilkan notifikasi sesuai jadwal (30, 7, 3, 2, 1 hari sebelum Expire)
			if (in_array($hari_ini, [$bulan_sebelumnya, $tanggal_7_hari, $tanggal_3_hari, $tanggal_2_hari, $tanggal_1_hari])) {
				echo "
					<script type='text/javascript'>
						document.addEventListener('DOMContentLoaded', function() {
							alert('Peringatan! Masa aktif situs Anda $web->website akan berakhir. Segera lakukan perpanjangan!');
						});
					</script>
					<div class='notice notice-warning'><p>Peringatan! Masa aktif situs Anda <b>$web->website</b> akan berakhir. Segera lakukan perpanjangan!</p></div>
				";
			}
		}
	}
	// echo "<script>alert('$selisih_hari');</script>";
}
add_action('admin_footer', 'alfa_notifikasi_expire');

/****************** 404 jika expire ******************/
function alfa_cek_expire_redirect_404() {
	global $hari_ini, $api_url;
	
	if (is_admin()) {
		return;
	}
	
	$response = wp_remote_get($api_url);
	
	if (is_wp_error($response)) {
		return;
	}
	
	$websites = json_decode(wp_remote_retrieve_body($response));
	
	if (!empty($websites)) {
		foreach ($websites as $web) {
			$tanggal_expire = $web->tanggal_expire;
			if ($hari_ini >= $tanggal_expire) {
				/*status_header(404);
				nocache_headers();
				include(get_query_template('404'));
				exit; */
				wp_die(
					'<h1>Website Expired</h1><p>Maaf, website tidak dapat diakses karena telah melewati tanggal expire.</p>', 'Website Expired',
					['response' => 404]
				);
				exit;
			}
		}
	}
}
add_action('template_redirect', 'alfa_cek_expire_redirect_404');

function alfa_cek_expire_admin_404() {
	global $hari_ini, $api_url;
	
	$response = wp_remote_get($api_url);
	
	if (is_wp_error($response)) {
		return;
	}
	
	$websites = json_decode(wp_remote_retrieve_body($response));
	
	if (!empty($websites)) {
		foreach ($websites as $web) {
			$tanggal_expire = $web->tanggal_expire;
			if ($hari_ini >= $tanggal_expire) {
				wp_die(
					'<h1>Website Expired</h1><p>Maaf, website tidak dapat diakses karena telah melewati tanggal expire.</p>', 'Website Expired',
					['response' => 404]
				);
				exit;
			}
		}
	}
}
add_action('admin_init', 'alfa_cek_expire_admin_404');
