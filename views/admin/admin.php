<?php

$inputs = array(
	array( 'name' => 'title',       'title' => __('Title'), 'type' => 'text' ),
	array( 'name' => 'url',         'title' => __('URL'), 'type' => 'url' ),
	array( 'name' => 'items',       'title' => __('Number of Items'), 'type' => 'number' ),
	array( 'name' => 'show_summary','title' => __('Display item content?'), 'type' => 'checkbox' ),
	array( 'name' => 'show_author', 'title' => __('Display item author if available?'), 'type' => 'checkbox' ),
	array( 'name' => 'show_date',   'title' => __('Display item date?'), 'type' => 'checkbox' ),
	array( 'name' => 'show_image',  'title' => __('Display item image if available?'), 'type' => 'checkbox' ),
	array( 'name' => 'hide_mobile', 'title' => __('Hide on mobile'), 'type' => 'checkbox' ),
	array( 'name' => 'cache_bust',  'title' => __('Cache bust feed URL'), 'type' => 'checkbox' ),
	array( 'name' => 'teaser_size', 'title' => __('Teaser Size (if 0 display all)'), 'type' => 'number' ),
	//array( 'name' => 'cache_time',  'title' => __('Cache Time (In seconds)'), 'type' => 'number' ),
	array( 'name' => 'before_text', 'title' => __('Intro Text'), 'type' => 'textarea' ),
	array( 'name' => 'after_text',  'title' => __('Footer Text'), 'type' => 'textarea' )
);

foreach ( $inputs as $input ) {

	switch ( $input['type'] ) {
		case 'checkbox':
			$format = '<p><input id="%1$s" name="%2$s" value="1" type="%5$s" ' . checked( $instance[ $input['name'] ], 1, false ) . ' /><label for="%1$s">%3$s</label></p>';
			break;
		case 'number':
			$format = '<p><label for="%1$s">%3$s:</label> <input class="widefat" id="%1$s" name="%2$s"  min="0" step="1" value="%4$s" type="%5$s" /></p>';
			break;
		case 'textarea':
			$format = '<p><label for="%1$s">%3$s:</label> <textarea class="widefat" id="%1$s" rows="5" name="%2$s">%4$s</textarea></p>';
			break;
		default:
			$format = '<p><label for="%1$s">%3$s:</label> <input class="widefat" id="%1$s" name="%2$s"  value="%4$s" type="%5$s" /></p>';
			break;
	}

	$value = (isset($instance[ $input['name'] ])) ? $instance[ $input['name'] ] : '';

	printf( $format, $this->get_field_id( $input['name'] ), $this->get_field_name( $input['name'] ), $input['title'], esc_attr( $value ), $input['type'] );

}

$templates = $this->get_available_templates($this->get_public_directory());
$counter =  count($templates);

if($counter == 1){
	$format = '<input id="%1$s" name="%2$s"  value="%3$s" type="hidden" />';
	$value = key($templates);
	printf( $format, $this->get_field_id( 'template' ), $this->get_field_name( 'template' ), $value);
}else{
	$options = '';
	foreach($templates as $template => $name){
		$options .= sprintf('<option value="%s" %s>%s</option>', $template, selected( $instance[ 'template' ], $template , false ), $name);
	}

	$format = '<p><label for="%1$s">%3$s:</label> <select class="widefat" id="%1$s" name="%2$s">%4$s</select></p>';
	printf($format, $this->get_field_id( 'template' ), $this->get_field_name( 'template' ), __('Templates'), $options);
}

?>
