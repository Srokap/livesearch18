<?php
elgg_register_event_handler('init', 'system', array('livesearch18', 'init'));

/**
 * @param stdClass $row
 * @return array
 * @access private
 * @see _elgg_input_livesearch_page_handler
 */
function _elgg_input_livesearch_data_callback($row) {
	$entity = entity_row_to_elggstar($row);

	$output = elgg_view_list_item($entity, array(
		'use_hover' => false,
		'class' => 'elgg-autocomplete-item',
	));

	$icon = elgg_view_entity_icon($entity, 'tiny', array(
		'use_hover' => false,
	));

	$result = array(
		'type' => $entity->getType(),
		'name' => livesearch18::getDisplayName($entity),
		'guid' => $entity->guid,
		'label' => $output,
		'value' => $entity->guid,
		'icon' => $icon,
		'url' => $entity->getURL(),
	);
	if (elgg_instanceof($entity, 'user')) {
		$result['desc'] = $entity->username;
	} else {
		$result['desc'] = strip_tags($entity->description);
	}

	return array($entity, $result);
}