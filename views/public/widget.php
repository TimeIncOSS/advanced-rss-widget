<?php
/*
Widget Template Name: Standand Template
*/

echo $instance['before_text'];
?>
<ul>
	<?php
	foreach ( $processed as $item ) {

		if ( $item['summary'] ) {
			$summary = '<div class="rssSummary">' . $item['summary'] . '</div>';
		}

		if ( $item['date'] ) {
			$date = ' <span class="rss-date">' . $item['date'] . '</span>';
		}
		if ( $item['author'] ) {

			$author = ' <cite>' . $item['author'] . '</cite>';

		}

		$link = sprintf( '<a href="%s">%s</a>', $item['link'], $item['title'] );

		$image = $item['image'];

		echo "<li>$link $author $date $image $summary</li>";

	}
	?>
</ul>
<?php echo $instance['after_text'];?>