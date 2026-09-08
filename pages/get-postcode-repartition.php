<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap oxfam-admin-styling">
	<h1>Postcodeverdeling</h1>
	
	<p>Hieronder zie je alle geldige postcodes uit het Vlaamse en Brusselse Gewest, en welke webshop er thuislevering organiseert. Dit overzicht is vooral handig als er webshops bijkomen/verdwijnen. Voor elke postcode moet er te allen tijde minstens één webshop verantwoordelijk zijn. (Postcodes 1804, 1818 en 1934 zijn interne postcodes voor respectievelijk Cargovil, VTM en Zaventem, het is normaal dat die niet verdeeld zijn.) Postcodes waarbij twee of meer webshops in overlap werken met elkaar worden in het oranje aangeduid.</p>
	
	<?php list_shops_per_postcode(); ?>
</div>

<?php
	function list_shops_per_postcode() {
		$sites = get_sites( array( 'path__not_in' => array('/'), 'public' => 1, 'site__not_in' => get_site_option('oxfam_blocked_sites') ) );
		$postcodes = get_site_option('oxfam_flemish_zip_codes');
		$list = array();
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			
			// Forceer herberekening bij openen van deze pagina
			delete_transient('oxfam_covered_zips');
			
			foreach ( get_oxfam_covered_zips() as $zip ) {
				if ( array_key_exists( $zip, $list ) ) {
					$list[ $zip ][] = get_webshop_name(true);
				} else {
					$list[ $zip ] = array( get_webshop_name(true) );
				}
			}
			restore_current_blog();
		}
		
		ksort( $list, SORT_NUMERIC );
		echo '<ul>';
		foreach ( $list as $postcode => $webshops ) {
			echo '<li>'.$postcode.' '.$postcodes[ $postcode ].': <span class="'.( count( $webshops ) > 1 ? 'warning' : 'ok' ).'">'.implode( ', ', $webshops ).'</span></li>';
			unset( $postcodes[ $postcode ] );
		}
		echo '</ul>';
		
		if ( count( $postcodes ) > 0 ) {
			echo '<p class="error">Opgelet: postcodes '.implode( ', ', array_keys( $postcodes ) ).' zijn nog niet gelinkt aan een webshop!</p>';
		}
	}
?>