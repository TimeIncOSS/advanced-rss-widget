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
 * Version:           1.0.0
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

	/*--------------------------------------------------*/
	/* Constructor
	/*--------------------------------------------------*/

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {

		// load plugin text domain
		add_action( 'init', array( $this, 'widget_textdomain' ) );

		parent::__construct(
			$this->get_widget_slug(),
			__( 'Advanced RSS Widget', 'advanced-rss-widget' ),
			array(
				'classname'   => $this->get_widget_slug() . '-class',
				'description' => __( 'More advanced RSS widget, with more options and customization ', 'advanced-rss-widget' )
			)
		);

		// Refreshing the widget's cached output with each new post
		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );

	} // end constructor


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

		$url = ! empty( $instance['url'] ) ? $instance['url'] : '';
		while ( stristr( $url, 'http' ) != $url ) {
			$url = substr( $url, 1 );
		}

		$cache_bust = ( isset ( $instance['cache_bust'] ) ? $instance['cache_bust'] : false );

		$current_date = date( "YmdGi" );

		if ( $cache_bust ) {
			$url = add_query_arg( 'cache_bust', $current_date, $url );
		}
		$url = apply_filters( 'advanced_rss_widget_url', $url, $this->id );

		if ( empty( $url ) ) {
			return;
		}

		$widget_string = "<!-- generated $current_date -->";

		$rss = fetch_feed( $url );

		if ( is_wp_error( $rss ) ) {
			if ( is_admin() || current_user_can( 'manage_options' ) ) {
				echo '<p>' . sprintf( '<strong>%s</strong>: %s', __( 'RSS Error', 'advanced-rss-widget' ), $rss->get_error_message() ) . '</p>';
			}

			return;
		}

		if ( ! $rss->get_item_quantity() ) {
			echo '<p>' . __( 'An error has occurred, which probably means the feed is down. Try again later.', 'advanced-rss-widget' ) . '</p>';
			$rss->__destruct();
			unset( $rss );

			return;
		}

		$processed = $this->process_rss_input( $rss, $instance );

		$link = esc_url( strip_tags( $rss->get_permalink() ) );
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

		$rss->__destruct();
		unset( $rss );

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

		$this->flush_widget_cache();

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


			$processed[] = compact( "link", "title", "author", "date", "image", "summary", "desc" );

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

add_action( 'widgets_init', function () {
	register_widget( "Advanced_RSS_Widget" );
} );
