<?php
/**
 * Advanced RSS Widget
 *
 * More advanced RSS widget, with more options and customization
 *
 * @package   Advanced_RSS_Widget
 * @author    Jonathan Harris <jonathan.harris@timeinc.com>
 * @license   GPL-2.0+
 * @link      http://www.timeincuk.com/
 * @copyright 2015 Time Inc. (UK) Ltd
 *
 * @wordpress-plugin
 * Plugin Name:       Advanced RSS Widget
 * Plugin URI:        http://www.timeincuk.com/
 * Description:       More advanced RSS widget, with more options and customization
 * Version:           2.0.0
 * Author:            Jonathan Harris
 * Author URI:        http://www.jonathandavidharris.co.uk/
 * Text Domain:       advanced-rss-widget
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /lang
 * GitHub Plugin URI: https://github.com/TimeIncUK/advanced-rss-widget
 */


// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



class Advanced_RSS_Widget extends WP_Widget {

	/**
	 *
	 * Unique identifier for your widget.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * widget file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $widget_slug = 'advanced-rss-widget';

	/**
	 *
	 * Version number.
	 *
	 * @since    2.0.0
	 *
	 * @var      string
	 */
	protected $version = '2.0.0';

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

		// load plugin text domain
		add_action( 'init', array( $this, 'widget_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		parent::__construct(
			$this->get_widget_slug(),
			__( 'Advanced RSS Widget', 'advanced-rss-widget' ),
			array(
				'classname'   => $this->get_widget_slug() . '-class',
				'description' => __( 'More advanced RSS widget, with more options and customization ', 'advanced-rss-widget' )
			)
		);

		add_filter( 'cron_schedules', array( $this, 'cron_add_twicehourly' ) );
		add_action( 'advanced_rss_widget_get_urls',  array( $this, 'event_rss_urls' ) );

		// Refreshing the widget's cached output with each new post
		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );

	} // end constructor

	public function single_activate(){
		$op  = 'advanced_rss_widget_dbv';
		$this->schedule_event_rss_urls();
		update_option( $op, $this->version );
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    2.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public function activate( $network_wide  ) {

		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
			// Get all blog ids of the current network
			$sites = wp_get_sites();

			foreach ( $sites as $id => $site ) {
				switch_to_blog( $site['blog_id'] );
				$this->single_activate();
			}
			restore_current_blog();
		} else {
			$this->single_activate();
		}

	}

	public function single_deactivate(){
		$this->unschedule_event_rss_urls();
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    2.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public function deactivate( $network_wide ) {

		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
			// Get all blog ids of the current network
			$sites = wp_get_sites();

			foreach ( $sites as $id => $site ) {
				switch_to_blog( $site['blog_id'] );
				$this->single_deactivate();
			}
			restore_current_blog();
		} else {
			$this->single_deactivate();
		}

	}

	public function maybe_upgrade() {
		$op  = 'advanced_rss_widget_dbv';
		$ver = get_option( $op, '1.0.0' );
		if ( version_compare( $ver, '2.0.0', '<' ) ) {
			$this->single_activate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    2.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		$this->single_activate();
		restore_current_blog();

	}

	/**
	 * Creates a custom wp cron schedule interval (30min)
	 *
	 * @param array $schedules Intervals pre defined in WordPress
	 *
	 * @return array $schedules Intervals plus our custom value
	 */
	public function cron_add_twicehourly( $schedules ) {
	 	// Adds twice hourly to the existing schedules.
	 	$schedules['twicehourly'] = array(
	 		'interval' => 1800,
	 		'display'  => __( 'Twice Hourly' ),
	 	);
	 	return $schedules;
	}

	/**
	 * Fetch all the rss urls
	 *
	 * @return void
	 */
	public function event_rss_urls(){

		$option_instances = $this->get_settings();

		foreach( $option_instances as $id => $instance ){
			$processed = $this->get_feed( $instance );
			if( ! empty( $processed ) ){

				wp_cache_set( $this->id_base . '-' . $id,  $processed, $this->get_widget_slug() );
			}

		}
		set_transient( 'rss_processed_time', date( 'Y-m-d H:i:s' ) );
		// Force widget to show the new feed
		$this->flush_widget_cache();
	}

	/**
	 * Schedule a wp cron event to fetch all the rss urls every 30min
	 *
	 * @return void
	 */
	public function schedule_event_rss_urls(){
		wp_schedule_event( time(), 'twicehourly', 'advanced_rss_widget_get_urls' );
	}

	/**
	 * Unschedule the wp cron event to fetch all the rss urls every 30min
	 *
	 * @return void
	 */
	public function unschedule_event_rss_urls(){
		$op  = 'advanced_rss_widget_dbv';
		delete_option( $op );
		wp_clear_scheduled_hook( 'advanced_rss_widget_get_urls' );
	}

	/**
	 * Processing the url to fetch the RSS feed
	 *
	 * @param  string original $url
	 * @return string processed $url
	 */
	public function process_url( $url ) {
		$url = $url = ! empty( $url ) ? $url : '';
		while ( stristr( $url, 'http' ) != $url ) {
			$url = substr( $url, 1 );
		}

		return $url;
	}

	/**
	 * Fetch feed url and save all the object in a unique object cache so we can display it at any time without refetching
	 *
	 * @param array $instance Widget settings array
	 *
	 * @return array $processed Array with necessary elements to display the fetched posts
	 */
	public function get_feed( $instance ){

		$url = $this->process_url( $instance['url'] );
		$cache_bust = $instance['cache_bust'];

		$cache_bust = ( isset ( $cache_bust ) ? $cache_bust : false );

		$current_date = date( "YmdGi" );

		if ( $cache_bust ) {
			$url = add_query_arg( 'cache_bust', $current_date, $url );
		}

		$url = apply_filters( 'advanced_rss_widget_url', $url, $this->id );

		$processed = array();

		if ( ! empty( $url ) ) {
			$rss = fetch_feed( $url );
			if ( ! is_wp_error( $rss ) && $rss->get_item_quantity() ) {
				$processed = $this->process_rss_input( $rss, $instance );
				$rss->__destruct();
			}
			unset( $rss );

		}

		return $processed;
	}

	/**
	 * Return the widget slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_widget_slug() {
		return $this->widget_slug;
	}

	/*--------------------------------------------------*/
	/* Widget API Functions
	/*--------------------------------------------------*/

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array args  The array of form elements
	 * @param array instance The current instance of the widget
	 */
	public function widget( $args, $instance ) {

		$rss_processed_time = get_transient( 'rss_processed_time' );
		printf("<!-- rss_processed_time %s -->", $rss_processed_time);

		// Check if there is a cached output
		$cache = wp_cache_get( $this->get_widget_slug(), 'widget' );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		if ( ! isset ( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}
		if ( isset ( $cache[ $args['widget_id'] ] ) ) {
			return print $cache[ $args['widget_id'] ];
		}

		// Cache for 5 minutes
		$expires = apply_filters( 'advanced_rss_widget_expiry_time', 300 );

		$processed = wp_cache_get( $this->id, $this->get_widget_slug() );

		$source = 'cache';

		if( false === $processed ){
			$processed = $this->get_feed( $instance );
			wp_cache_set( $this->id, $processed, $this->get_widget_slug() );
			$source = 'inline';
		}

		printf("<!-- generated from %s -->", $source);

		if( empty( $processed ) ){
			if ( is_admin() || current_user_can( 'manage_options' ) ) {
				echo '<p>' . __( 'An error has occurred, which probably means the feed is down or the url is wrong. Try again later.', 'advanced-rss-widget' ) . '</p>';
			}
			return;
		}

		$link = $processed['link_rss'];
		while ( stristr( $link, 'http' ) != $link ) {
			$link = substr( $link, 1 );
		}

		$extra_classes = array();
		$extra_classes = apply_filters( 'advanced_rss_widget_classes', $extra_classes, $instance, $this->id );

		$args['before_widget'] = str_replace( 'class="', 'class="' . implode( ' ', $extra_classes ) . ' ', $args['before_widget'] );

		$widget_string .= $args['before_widget'];

		$title = '';
		if ( ! empty( $instance['title'] ) ) {
			$title = $instance['title'];
		}

		$title = apply_filters( 'widget_title', $title, $instance, $rss, $this->id_base );

		if ( ! empty( $title ) ) {

			$format       = '<a href="%s" class="%s">%s</a>';
			$format       = apply_filters( 'advanced_rss_widget_title_format', $format, $link, $this->get_widget_slug(), $title, $instance, $this->id_base );
			$title_styled = sprintf( $format, $link, $this->get_widget_slug(), $title, $instance, $this->id_base );

			$widget_string .= $args['before_title'] . $title_styled . $args['after_title'];
		}

		ob_start();
		$directory_path = $this->get_public_directory();
		$filename       = $instance['template'];
		$file_path      = $directory_path . $filename;

		if ( file_exists( $file_path ) ) {
			include( $file_path );
		}

		$widget_string .= ob_get_clean();
		$widget_string .= $args['after_widget'];

		$cache[ $args['widget_id'] ] = $widget_string;
		wp_cache_set( $this->get_widget_slug(), $cache, 'widget', $expires );

		echo $widget_string;

		return;

	} // end widget

	/**
	 * Flush cache for all widgets
	 */
	public function flush_widget_cache() {
		wp_cache_delete( $this->get_widget_slug(), 'widget' );
	}

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array new_instance The new instance of values to be generated via the update.
	 * @param array old_instance The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $new_instance;

		$this->event_rss_urls();
		$cached = array_key_exists( 'cache_bust', $instance );

		return $instance;

	} // end widget

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array instance The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		$instance = wp_parse_args( (array) $instance, $this->get_defaults() );

		// Display the admin form
		include( plugin_dir_path( __FILE__ ) . 'views/admin/admin.php' );

	} // end form

	/**
	 * Process rss data, format it to a usable array
	 *
	 * @param $rss
	 * @param $instance
	 *
	 * @return array
	 */
	private function process_rss_input( $rss, $instance ) {
		$processed = array();
		$items        = (int) $instance['items'];
		$show_summary = (int) $instance['show_summary'];
		$show_author  = (int) $instance['show_author'];
		$show_date    = (int) $instance['show_date'];
		$show_image   = (int) $instance['show_image'];
		$teaser_size  = (int) $instance['teaser_size'];

		foreach ( $rss->get_items( 0, $items ) as $item ) {
			$link = $item->get_link();
			while ( stristr( $link, 'http' ) != $link ) {
				$link = substr( $link, 1 );
			}

			$link = esc_url( strip_tags( $link ) );
			$link = apply_filters( 'advanced_rss_widget_link', $link, $item );

			$title = esc_html( trim( strip_tags( $item->get_title() ) ) );
			if ( empty( $title ) ) {
				$title = __( 'Untitled', 'advanced-rss-widget' );
			}
			$desc = @html_entity_decode( $item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) );
			$desc = wp_strip_all_tags( $desc );

			$summary = '';
			if ( $show_summary ) {
				$summary = $desc;

				if ( $teaser_size ) {
					$summary = esc_attr( wp_trim_words( $summary, $teaser_size, ' ' ) );
				}

				// Change existing [...] to [&hellip;].
				if ( '[...]' == substr( $summary, - 5 ) ) {
					$summary = substr( $summary, 0, - 5 ) . '[&hellip;]';
				}
				$summary = esc_html( $summary );
			}
			$date = '';
			if ( $show_date ) {
				$date = $item->get_date( 'U' );
				if ( $date ) {
					$date = date_i18n( get_option( 'date_format' ), $date );
				}
			}
			$author = '';
			if ( $show_author ) {
				$author = $item->get_author();
				if ( is_object( $author ) ) {
					$author = $author->get_name();
				}
				if ( is_string( $author ) ){
					$author = esc_html( strip_tags( $author ) );
				}
			}
			$image = '';
			if ( $show_image ) {
				if ( $enclosure = $item->get_enclosure() ) {
					if ( $enclosure->get_medium() == 'image' ) {
						$image = sprintf( '<img src="%s" alt="%s" height="%s" width="%s"/>', $enclosure->get_link(), $enclosure->get_description(), $enclosure->get_height(), $enclosure->get_width() );
					}
				}
			}

			$link_rss = esc_url( strip_tags( $rss->get_permalink() ) );

			$time_processed = date( "Y-m-d H:i:s" );
			$processed[] = compact( "link", "title", "author", "date", "image", "summary", "desc", "link_rss", "time_processed" );

		}

		return $processed;
	}


	/**
	 * Returns the list of the php files available to be used as a template
	 *
	 * @return array The list of templates
	 */
	function get_available_templates( $base_path ) {
		$templates = array();
		if ( $dh = opendir( $base_path ) ) {
			while ( ( $file = readdir( $dh ) ) !== false ) {
				$full_path = $base_path . DIRECTORY_SEPARATOR . $file;
				if ( filetype( $full_path ) === "dir" ) {
					continue;
				}
				if ( ! preg_match( '|Widget Template Name:(.*)$|mi', file_get_contents( $full_path ), $header ) ) {
					continue;
				}
				$templates[ basename( $file ) ] = _cleanup_header_comment( $header[1] );

			}
		}
		closedir( $dh );

		return $templates;
	}

	/*--------------------------------------------------*/
	/* Public Functions
	/*--------------------------------------------------*/
	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() {
		load_plugin_textdomain( $this->get_widget_slug(), false, plugin_dir_path( __FILE__ ) . 'lang/' );
	} // end widget_textdomain


	/*--------------------------------------------------*/
	/* Filtable Functions
	/*--------------------------------------------------*/

	/**
	 * Get the path for the Widget templates
	 *
	 * @return string PATH to directory of with templates
	 */
	public function get_public_directory() {
		$path = plugin_dir_path( __FILE__ ) . 'views/public/';
		$path = apply_filters( 'advanced_rss_widget_public_path', $path );

		return $path;
	}

	/**
	 * Get an array of widget's default values
	 *
	 * @return array defaults
	 */
	public function get_defaults() {
		$defaults = array(
			'title'        => '',
			'url'          => '',
			'before_text'  => '',
			'after_text'   => '',
			'template'     => 'widget.php',
			'cache_time'   => 3600,
			'items'        => 10,
			'show_summary' => 0,
			'show_author'  => 0,
			'show_date'    => 0,
			'show_image'   => 0,
			'cache_bust'   => 0,
			'teaser_size'  => 0
		);
		$defaults = apply_filters( 'advanced_rss_widget_defaults', $defaults );

		return $defaults;
	}

} // end class


function advanced_rss_widget_activate( $network_wide ) {
	$object = new Advanced_RSS_Widget();
	$object->activate( $network_wide );
}
register_activation_hook( __FILE__, 'advanced_rss_widget_activate' );

function advanced_rss_widget_deactivate( $network_wide ) {
	$object = new Advanced_RSS_Widget();
	$object->deactivate( $network_wide );
}
register_deactivation_hook( __FILE__, 'advanced_rss_widget_deactivate' );

add_action( 'widgets_init', function () {
	register_widget( "Advanced_RSS_Widget" );
} );
