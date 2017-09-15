<?php

/**
 * Output feature box summary
 *
 * @since 2.4
 */
function ep_autosuggest_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Suggest relevant content to users as they type search text.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 *
 * @since 2.4
 */
function ep_autosuggest_feature_box_long() {
	?>
	<p><?php esc_html_e( 'Input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As users type, a dropdown will be shown containing results relevant to their current search. Clicking a suggestion will take a user directly to that piece of content.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Setup feature functionality
 *
 * @since  2.4
 */
function ep_autosuggest_setup() {
	add_action( 'wp_enqueue_scripts', 'ep_autosuggest_enqueue_scripts' );
	add_filter( 'ep_config_mapping', 'ep_autosuggest_suggest_mapping' );
	add_filter( 'ep_post_sync_args', 'ep_autosuggest_filter_term_suggest', 10, 2 );
}

/**
 * Display decaying settings on dashboard.
 *
 * @param EP_Feature $feature Feature object.
 * @since 2.4
 */
function ep_autosugguest_settings( $feature ) {
	$settings = $feature->get_settings();

	if ( ! $settings ) {
		$settings = array();
	}

	$settings = wp_parse_args( $settings, $feature->default_settings );
	?>

	<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $feature->slug ); ?>">
		<div class="field-name status"><label for="feature_autosuggest_host"><?php esc_html_e( 'Host', 'elasticpress' ); ?></label></div>
		<div class="input-wrap">
			<input value="<?php echo esc_url( $settings['host'] ); ?>" type="text" data-field-name="host" class="setting-field" id="feature_autosuggest_host">
			<p class="field-description"><?php esc_html_e( 'For many hosting setups, a separate host should be used for autosuggest. Note that this address will be exposed to the public.', 'elasticpress' ); ?></p>
		</div>
	</div>

<?php
}

/**
 * Add mapping for suggest fields
 *
 * @param  array $mapping
 * @since  2.4
 * @return array
 */
function ep_autosuggest_suggest_mapping( $mapping ) {
	$mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] = array(
		'type' => 'text',
		'analyzer' => 'edge_ngram_analyzer',
		'search_analyzer' => 'standard',
	);

	$mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] = array(
		'type' => 'custom',
		'tokenizer' => 'standard',
		'filter' => array(
			'lowercase',
			'edge_ngram',
		),
	);

	$mapping['mappings']['post']['properties']['term_suggest'] = array(
		'type' => 'text',
		'analyzer' => 'edge_ngram_analyzer',
		'search_analyzer' => 'standard',
	);

	return $mapping;
}

/**
 * Add term suggestions to be indexed
 *
 * @param $post_args
 * @param $post_id
 * @since  2.4
 * @return array
 */
function ep_autosuggest_filter_term_suggest( $post_args, $post_id ) {
	$suggest = array();

	if ( ! empty( $post_args['terms'] ) ) {
		foreach ( $post_args['terms'] as $taxonomy ) {
			foreach ( $taxonomy as $term ) {
				$suggest[] = $term['name'];
			}
		}
	}

	if ( ! empty( $suggest ) ) {
		$post_args['term_suggest'] = $suggest;
	}

	return $post_args;
}

/**
 * Enqueue our autosuggest script
 *
 * @since  2.4
 */
function ep_autosuggest_enqueue_scripts() {
	$feature = ep_get_registered_feature( 'autosuggest' );

	$settings = $feature->get_settings();

	if ( ! $settings ) {
		$settings = array();
	}

	$settings = wp_parse_args( $settings, $feature->default_settings );

	if ( empty( $settings['host'] ) ) {
		return;
	}

	$js_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/autosuggest/assets/js/src/autosuggest.js' : EP_URL . 'features/autosuggest/assets/js/autosuggest.min.js';
	$css_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/autosuggest/assets/css/autosuggest.css' : EP_URL . 'features/autosuggest/assets/css/autosuggest.min.css';

	wp_enqueue_script(
		'elasticpress-autosuggest',
		$js_url,
		array( 'jquery' ),
		EP_VERSION,
		true
	);

	wp_enqueue_style(
		'elasticpress-autosuggest',
		$css_url,
		array(),
		EP_VERSION
	);

	/**
	 * Output variables to use in Javascript
	 * index: the Elasticsearch index name
	 * host:  the Elasticsearch host
	 * postType: which post types to use for suggestions
	 * action: the action to take when selecting an item. Possible values are "search" and "navigate".
	 */
	wp_localize_script( 'elasticpress-autosuggest', 'epas', apply_filters( 'ep_autosuggest_options', array(
		'index'        => ep_get_index_name( get_current_blog_id() ),
		'host'         => esc_url( untrailingslashit( $settings['host'] ) ),
		'postType'     => apply_filters( 'ep_term_suggest_post_type', 'all' ),
		'searchFields' => apply_filters( 'ep_term_suggest_search_fields', array(
			'post_title.suggest',
			'term_suggest',
		) ),
		'action'       => 'navigate',
	) ) );
}

/**
 * Determine WC feature reqs status
 *
 * @param  EP_Feature_Requirements_Status $status
 * @since  2.4
 * @return EP_Feature_Requirements_Status
 */
function ep_autosuggest_requirements_status( $status ) {
	$host = ep_get_host();

	$status->code = 1;

	$status->message = array();

	$status->message[] = esc_html__( 'This feature modifies the default user experience of users by showing a dropdown of suggestions as users type in search boxes.', 'elasticpress' );

	if ( ! preg_match( '#elasticpress\.io#i', $host ) ) {
		$status->message[] = __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your host is properly secured. An insecure or misconfigured autosuggest host poses a <strong>severe</strong> security risk to your website.", 'elasticpress' );
	}

	return $status;
}

/**
 * Add autosuggest setting fields
 *
 * @since 2.4
 */
function ep_autosuggest_setup_settings() {
	add_action( 'ep_feature_box_settings_autosuggest', 'ep_autosugguest_settings', 10, 1 );
}
add_action( 'admin_init', 'ep_autosuggest_setup_settings' );

/**
 * Register the feature
 *
 * @since  2.4
 */
ep_register_feature( 'autosuggest', array(
	'title' => 'Autosuggest',
	'setup_cb' => 'ep_autosuggest_setup',
	'feature_box_summary_cb' => 'ep_autosuggest_feature_box_summary',
	'feature_box_long_cb' => 'ep_autosuggest_feature_box_long',
	'requires_install_reindex' => true,
	'requirements_status_cb' => 'ep_autosuggest_requirements_status',
	'default_settings' => array(
		'host' => ep_get_host(),
	),
) );
