<?php
class livesearch18 {
	
	public static function init() {
		elgg_extend_view('theme_preview/miscellaneous', 'livesearch18/theme_preview');
		elgg_register_js('elgg.autocomplete', elgg_get_config('wwwroot') . 'mod/' 
			. __CLASS__ . '/js/ui.autocomplete.js');
		elgg_register_page_handler('livesearch', array(__CLASS__, '_elgg_input_livesearch_page_handler'));
	}
	
	public static function getDisplayName($entity) {
		if ($entity instanceof ElggObject) {
			return $entity->title;
		} else {
			return $entity->name;
		}
	}
	
	/**
	 * Page handler for autocomplete endpoint.
	 *
	 * /livesearch?q=<query>
	 *
	 * Other options include:
	 *     match_on	   string all or array(groups|users|friends)
	 *     match_owner int    0/1
	 *     limit       int    default is 10
	 *     name        string default "members"
	 *
	 * @param array $page
	 * @return string JSON string is returned and then exit
	 * @access private
	 */
	public static function _elgg_input_livesearch_page_handler($page) {
		global $CONFIG;
	
		// only return results to logged in users.
		if (!$user = elgg_get_logged_in_user_entity()) {
			exit;
		}
	
		if (!$q = get_input('term', get_input('q'))) {
			exit;
		}
	
		$input_name = get_input('name', 'members');
	
		$q = sanitise_string($q);
	
		// replace mysql vars with escaped strings
		$q = str_replace(array('_', '%'), array('\_', '\%'), $q);
	
		$match_on = get_input('match_on', 'all');
	
		if (!is_array($match_on)) {
			$match_on = array($match_on);
		}
	
		// all = users and groups
		if (in_array('all', $match_on)) {
			$match_on = array('users', 'groups');
		}
	
		$limit = sanitise_int(get_input('limit', 10));
	
		// grab a list of entities and send them in json.
		$results = array();
		foreach ($match_on as $match_type) {
			$options = false;
			switch ($match_type) {
				case 'users':
					$options = array(
					'type' => 'user',
					'joins' => array(
					"JOIN {$CONFIG->dbprefix}users_entity as ue ON e.guid = ue.guid"
					),
					'wheres' => array(
						"(ue.name LIKE '$q%' OR ue.name LIKE '% $q%' OR ue.username LIKE '$q%')",
					)
					);
					break;
	
				case 'groups':
					// don't return results if groups aren't enabled.
					if (!elgg_is_active_plugin('groups')) {
						continue;
					}
					$options = array(
						'type' => 'group',
						'joins' => array(
							"JOIN {$CONFIG->dbprefix}groups_entity as ge ON e.guid = ge.guid"
							),
							'wheres' => array(
								"(ge.name LIKE '$q%' OR ge.name LIKE '% $q%' OR ge.description LIKE '% $q%')",
							)
							);
					break;
	
				case 'friends':
					$options = array(
					'type' => 'user',
					'joins' => array(
					"JOIN {$CONFIG->dbprefix}users_entity as ue ON e.guid = ue.guid"
					),
					'wheres' => array(
						"(ue.name LIKE '$q%' OR ue.name LIKE '% $q%' OR ue.username LIKE '$q%')",
					),
					'relationship' => 'friend',
					'relationship_guid' => $user->getGUID()
					);
					break;
	
				default:
					$params = array(
					'q' => $q,
					'match_type' => $match_type,
					'input_name' => $input_name,
					'user' => $user,
					'limit' => $limit
					);
					$options = elgg_trigger_plugin_hook('livesearch', 'options', $params, false);
					if ($options === false) {
						header("HTTP/1.0 400 Bad Request", true);
						echo "livesearch: unknown match_on of $match_type";
						exit;
					}
					break;
			}
	
			if ($options !== false) {
				$options['callback'] = '_elgg_input_livesearch_data_callback';
				$options['limit'] = $limit;
				if (get_input('match_owner', false)) {
					$options['owner_guid'] = $user->getGUID();
				}
				$entities = elgg_get_entities_from_relationship($options);
				if ($entities) {
					foreach ($entities as $row) {
						list($entity, $result) = $row;
						if (elgg_instanceof($entity, 'user')) {
							$result['html'] = elgg_view('input/userpicker/item', array(
								'entity' => $entity,
								'input_name' => $input_name,
							));
							//@todo remove this case (used in messages plugin)
							if (!in_array('groups', $match_on)) {
								$result['value'] = $entity->username;
							}
						}
						$results[self::getDisplayName($entity) . $entity->guid] = $result;
					}
				}
			}
		}
	
		ksort($results);
		header("Content-Type: application/json");
		echo json_encode(array_values($results));
		exit;
	}
	

}