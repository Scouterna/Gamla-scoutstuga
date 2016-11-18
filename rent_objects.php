<?php
/**
 * @package Rent_Objects
 * @author Puggan
 * @version 0.0.1
 * @filelocation: wp/wp-content/plugins/rent_object/rent_object.php
 */
/*
Plugin Name: Rent_Objects
Description: Tool for showing rentable objects
Version: 0.0.1
Author: Puggan
Author URI: https://scoutstuga.se/
*/

DEFINE("RENT_OBJECT_PLUGIN_VERSION",'0.0.1');
DEFINE("RENT_OBJECT_USER_CAP", "edit_posts");
DEFINE("RENT_OBJECT_ADMIN_CAP", "edit_others_posts");

add_action('plugins_loaded', array("rent_object_holder", 'db_check'));	
add_action("admin_menu", array("rent_object_holder", "init_admin"));
add_action("wp", array("rent_object_holder", "init_wp"));
add_action('admin_post_nopriv_add_rent_object', array("rent_object_holder", 'add_new_user') );
add_action('admin_post_add_rent_object', array("rent_object_holder", 'add_new_user') );
add_action('admin_post_nopriv_rent_object_export', array("rent_object_holder", 'rent_object_json_export') );
add_action('admin_post_rent_object_export', array("rent_object_holder", 'rent_object_json_export') );

// save the relative path, as __FILE__ only gives the absolte path
rent_object_holder::$filename = $plugin;

// make sure we can send redirect-headers
if(ob_get_level() < 1)
{
	ob_start();
}

class rent_object_holder
{
	public $admin_user = FALSE;
	static $filename;

	static function init_admin()
	{
		$GLOBALS["rent_object_holder"] = new rent_object_holder(TRUE);
	}

	static function init_wp()
	{
		$GLOBALS["rent_object_holder"] = new rent_object_holder(FALSE);
	}

	function __construct($admin)
	{
		if($admin)
		{
			$this->admin_user = current_user_can(RENT_OBJECT_ADMIN_CAP);
			if($this->admin_user)
			{
				add_object_page("Anläggningar", "Anläggningar", RENT_OBJECT_ADMIN_CAP, "rent_objects", array($this, "rent_object_admin_page"));
			}
			else
			{
				add_object_page("Anläggningar", "Anläggningar", RENT_OBJECT_USER_CAP, "rent_objects", array($this, "rent_object_admin_page"));
			}
			add_action("admin_enqueue_scripts", array($this, "rent_object_css_admin"));
		}
		else
		{
			add_action("wp_enqueue_scripts", array($this, "rent_object_css_and_script"));
			add_shortcode('rent_object', array($this, "shortcode_stuga"));
			add_shortcode('stuga', array($this, "shortcode_stuga"));
			add_shortcode('Stuga', array($this, "shortcode_stuga"));
			add_shortcode('stugor', array($this, "shortcode_stuga"));
			add_shortcode('Stugor', array($this, "shortcode_stuga"));
		}
	}

	function url($file)
	{
		return plugins_url(basename(dirname(rent_object_holder::$filename)) . '/' . $file);
	}

	function rent_object_css_and_script()
	{
		wp_register_style('rent_object', $this->url('rent_object.css'), FALSE, '1.0.0');
		wp_enqueue_style('rent_object');

		wp_register_script('rent_object', $this->url('rent_objects.js'), FALSE, '1.0.0');
		wp_enqueue_script('rent_object');
		wp_register_script('rent_object_map', $this->url('map.js'), FALSE, '1.0.0');
		wp_enqueue_script('rent_object_map');
	}

	function rent_object_css_admin()
	{
		wp_register_style( 'rent_object_css_admin', $this->url('rent_object.admin.css'), false, '1.0.0' );
		wp_enqueue_style( 'rent_object_css_admin' );
		wp_register_script('rent_object', $this->url('rent_objects.js'), FALSE, '1.0.0');
		wp_enqueue_script('rent_object');
		wp_register_script('rent_object_map', $this->url('map.js'), FALSE, '1.0.0');
		wp_enqueue_script('rent_object_map');

		// http://wordpress.stackexchange.com/questions/112592/add-media-button-in-custom-plugin
		wp_enqueue_media();
	}

	function rent_object_admin_page()
	{
		global $wpdb;

		// TODO: Remove later, fixing user that didn't get upload-permission on creation
		$user = wp_get_current_user();
		$user->add_cap('upload_files');

		echo '<div class="form-wrap">' . PHP_EOL;
		echo '<form action="#" method="post" id="rent_object_form">' . PHP_EOL;
		echo '<div id="poststuff">' . PHP_EOL;

		// wordpress uses magic quotes, even if its depricated :-( remove!!!
		$postdata = stripslashes_deep($_POST);
		if(isset($postdata['publish']) OR isset($postdata['unpublish']))
		{
			$postdata['save'] = TRUE;
			$postdata['object_status'] = (int) isset($postdata['publish']);
		}
		if(isset($postdata['save']) AND isset($postdata['before']))
		{
			$user_id = get_current_user_id();
			$old_rent_object = json_decode($postdata['before'], TRUE);
			
			// update (not add)
			if(isset($old_rent_object['rent_object_id']) AND (int) $old_rent_object['rent_object_id'])
			{
				$id = (int) $old_rent_object['rent_object_id'];
				$allowed = $this->check_permission($id);
				if(!$allowed)
				{
					$id = 0;
				}
			}
			else
			{
				$wpdb->insert("{$wpdb->prefix}rent_object", array('name' => $postdata['name']));
				$id = $wpdb->insert_id;

				// add permission
				$wpdb->insert(
					"{$wpdb->prefix}rent_object_permissions",
					array(
						'user_id' => $user->ID,
						'rent_object_id' => $id,
					),
					array('%d', '%d')
				);
				$allowed = TRUE;
			}
			$db_rent_object = $wpdb->get_row("SELECT rent_object.* FROM {$wpdb->prefix}rent_object AS rent_object WHERE rent_object_id = {$id}", ARRAY_A);
			
			$rent_object_updates = array();
			foreach($db_rent_object as $column => $db_value)
			{
				if(isset($postdata[$column]))
				{
					$postdata[$column] = trim($postdata[$column]);
					if(isset($old_rent_object[$column]) AND $postdata[$column] == $old_rent_object[$column])
					{
						continue;
					}
					if($postdata[$column] == $db_value)
					{
						continue;
					}

					$rent_object_updates[$column] = $postdata[$column];
				}
			}
			
			$db_rent_object['options'] = $this->object_settings($id);
			
			$rent_option_updates = array();
			foreach($this->object_settings_names() as $option_id => $option_name)
			{
				if(isset($postdata['rent_object_option'][$option_id]))
				{
					if(isset($old_rent_object['options'][$option_id]) AND $postdata['rent_object_option'][$option_id] == $old_rent_object['options'][$option_id])
					{
						continue;
					}
					if(isset($db_rent_object['options'][$option_id]) AND $postdata['rent_object_option'][$option_id] == $db_rent_object['options'][$option_id])
					{
						continue;
					}
					$rent_option_updates[$option_id] = ($postdata['rent_object_option'][$option_id] == '' ? NULL : $postdata['rent_object_option'][$option_id]);
				}
			}
			
			$price_scenarios = $this->price_scenarios($id);
			$price_updates = array();
			foreach($price_scenarios as $price_scenario)
			{
				$price_scenario_id = $price_scenario['price_scenario_id'];
				if(isset($postdata['price'][$price_scenario_id]))
				{
					$postdata['price'][$price_scenario_id] = (int) $postdata['price'][$price_scenario_id];
					if(isset($old_rent_object['price'][$price_scenario_id]) AND $postdata['price'][$price_scenario_id] == $old_rent_object['price'][$price_scenario_id])
					{
						continue;
					}
					if(isset($price_scenario['price']) AND $postdata['price'][$price_scenario_id] == $price_scenario['price'])
					{
						continue;
					}
					$price_updates[$price_scenario_id] = $postdata['price'][$price_scenario_id];
				}
			}

			$result = FALSE;
			if($allowed AND $rent_option_updates)
			{
				foreach($rent_option_updates as $option_id => $option_value)
				{
					if(is_null($option_value))
					{
						$result = $wpdb->delete(
							"{$wpdb->prefix}rent_object_settings", 
							array(
								'rent_object_id' => $id,
								'rent_object_settings_name_id' => $option_id,
							),
							array('%d', '%d')
						);
					}
					else
					{
						$result = $wpdb->replace(
							"{$wpdb->prefix}rent_object_settings", 
							array(
								'rent_object_id' => $id,
								'rent_object_settings_name_id' => $option_id,
								'option_value' => $option_value, 
								'user_id' => $user_id, 
							),
							array('%d', '%d', '%d', '%d')
						);
					}
				}
				// force timestamp update
				if(!$rent_object_updates)
				{
					$rent_object_updates['object_updated'] = NULL;
				}
			}

			if($allowed AND $price_updates)
			{
				foreach($price_updates as $price_scenario_id => $price)
				{
					$result = $wpdb->replace(
						"{$wpdb->prefix}rent_prices",
						array(
							'rent_object_id' => $id,
							'price_scenario_id' => $price_scenario_id,
							'price' => $price,
							'user_id' => $user_id,
						),
						array('%d', '%d', '%d', '%d')
					);
				}
				// force timestamp update
				if(!$rent_object_updates)
				{
					$rent_object_updates['object_updated'] = NULL;
				}
			}

			if($allowed AND !empty($postdata['new_images']))
			{
				foreach(explode(' ', $postdata['new_images']) as $image_id)
				{
					$image_id = (int) $image_id;
					if($image_id)
					{
						$result = $wpdb->insert(
							"{$wpdb->prefix}rent_object_images",
							array(
								'rent_object_id' => $id,
								'image_id' => $image_id,
							),
							array('%d', '%d')
						);
					}
				}
				// force timestamp update
				if(!$rent_object_updates)
				{
					$rent_object_updates['object_updated'] = NULL;
				}
			}

			if($allowed AND $rent_object_updates)
			{
				$rent_object_updates['user_id'] = $user_id;
				$result = $wpdb->update("{$wpdb->prefix}rent_object", $rent_object_updates, array('rent_object_id' => $id));

				if($result)
				{

				}
				else
				{

				}
			}


		}
		else if(isset($_GET['id']))
		{
			$rent_object = NULL;
			$id = (int) $_GET['id'];
			if($id)
			{
				$rent_object = $wpdb->get_row("SELECT rent_object.* FROM {$wpdb->prefix}rent_object AS rent_object WHERE rent_object_id = {$id}", ARRAY_A);
			}

			if(!$rent_object) $rent_object = array();

			$rent_object += array(
				'rent_object_id' => 0,
				'name' => '(missing object)',
			);
			if($rent_object['rent_object_id'])
			{
				if(!$this->check_permission($id))
				{
					$id = 0;
					$rent_object = array(
						'rent_object_id' => 0,
						'name' => '(Permission denied)',
					);
				}
			}

			$rent_object['options'] = $this->object_settings($rent_object['rent_object_id']);

			echo '<div class="postbox">' . PHP_EOL;

			if($rent_object['rent_object_id'])
			{
				printf('<h2 class="hndle ui-sortable-handle">Uppdatera %s</h2>' . PHP_EOL, htmlentities($rent_object['name']));
			}
			else
			{
				echo '<h2 class="hndle ui-sortable-handle">Lägg till anläggning.</h2>' . PHP_EOL;
				$rent_object['name'] = '';
			}
			echo '<div class="inside">' . PHP_EOL;

			// Name
			echo '<div class="form-field form-required term-name-wrap">' . PHP_EOL;
			echo '<label for="name">Namn</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="name" name="name">' . PHP_EOL, htmlentities($rent_object['name']));
			echo '<p>Namn på anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// Organistation
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="rent_organisation_id">Organistation</label>' . PHP_EOL;
// 			printf('<select aria-required="true" id="rent_organisation_id" name="rent_organisation_id"><option value="%d">%s</option></select>' . PHP_EOL, $rent_object['rent_organisation_id'], htmlentities($rent_object['organisation_name'] ?: 'Organistation ' . $rent_object['rent_organisation_id']));
			echo '<select aria-required="true" id="rent_organisation_id" name="rent_organisation_id">' . PHP_EOL;
			echo '<option value="">-- ' . htmlentities('Välj Organistation') . ' --</option>' . PHP_EOL;
			foreach($wpdb->get_results("SELECT rent_organisation_id, organisation_name FROM {$wpdb->prefix}rent_organisations AS rent_organisations", ARRAY_A) as $row)
			{
				echo '<option value="' . htmlentities($row['rent_organisation_id']) . '"' . ($row['rent_organisation_id'] == $rent_object['rent_organisation_id'] ? ' selected="selected"': '') . '>' . htmlentities($row['organisation_name']) . '</option>' . PHP_EOL;
			}
			echo '</select>' . PHP_EOL;
// 			$rent_object['rent_organisation_id'], htmlentities($rent_object['organisation_name'] ?: 'Organistation ' . $rent_object['rent_organisation_id']));
			echo '<p>Organistation som förvaltar anläggningen</p>' . PHP_EOL;
			echo '<p><b>TODO</b>: Add a new</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// Anläggningstyp
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="rent_object_type_id">Anläggningstyp</label>' . PHP_EOL;
			echo '<select aria-required="true" id="rent_object_type_id" name="rent_object_type_id">' . PHP_EOL;
// 			, $rent_object['rent_object_type_id'], htmlentities($rent_object['organisation_name'] ?: 'Typ ' . $rent_object['rent_object_type_id']));
			echo '<option value="">-- ' . htmlentities('Välj Typ') . ' --</option>' . PHP_EOL;
			foreach($wpdb->get_results("SELECT rent_object_type_id, type_name FROM {$wpdb->prefix}rent_object_types AS rent_object_types", ARRAY_A) as $row)
			{
				echo '<option value="' . htmlentities($row['rent_object_type_id']) . '"' . ($row['rent_object_type_id'] == $rent_object['rent_object_type_id'] ? ' selected="selected"': '') . '>' . htmlentities($row['type_name']) . '</option>' . PHP_EOL;
			}
			echo '</select>' . PHP_EOL;
			echo '<p>Typ av anläggning</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// city
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="city">Stad</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="city" name="city">' . PHP_EOL, htmlentities($rent_object['city']));
			echo '<p>Stad/ort där anläggningen finns</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// ingress
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="ingress">Ingress</label>' . PHP_EOL;
			echo '<textarea id="ingress" name="ingress">' . PHP_EOL;
			echo htmlentities($rent_object['ingress']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Kort beskrivning av anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// description
			echo '<div class="form-field term-description-wrap">' . PHP_EOL;
			echo '<label for="description">Beskrivning</label>' . PHP_EOL;
			echo '<textarea id="description" name="description">' . PHP_EOL;
			echo htmlentities($rent_object['description']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Lång beskrivning som visas efter ingressen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// price_description
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="price_description">Prisuppgifter</label>' . PHP_EOL;
			echo '<textarea id="price_description" name="price_description">' . PHP_EOL;
			echo htmlentities($rent_object['price_description']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Beskrivning av prissättning</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// visit_adress
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="visit_adress">Besöksadress</label>' . PHP_EOL;
			echo '<textarea id="visit_adress" name="visit_adress">' . PHP_EOL;
			echo htmlentities($rent_object['visit_adress']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Adress till anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// post_adress
			echo '<div class="form-field">' . PHP_EOL;
			echo '<label for="post_adress">Postadress</label>' . PHP_EOL;
			echo '<textarea id="post_adress" name="post_adress">' . PHP_EOL;
			echo htmlentities($rent_object['post_adress']) . PHP_EOL;
			echo '</textarea>' . PHP_EOL;
			echo '<p>Adress till postmottagare för anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// position_latitude
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="position_latitude">Koordinater</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="position_latitude" name="position_latitude">' . PHP_EOL, htmlentities($rent_object['position_latitude']));
			// position_longitude
			printf('<input type="text" aria-required="true" value="%s" id="position_longitude" name="position_longitude">' . PHP_EOL, htmlentities($rent_object['position_longitude']));
			echo '<p>Ange koordinater för markering i registrets kartvy, exempelvis 59.3049553 17.9821006</p>' . PHP_EOL;
			echo '<p><b>TODO</b>: Select by adress or map, using google-map-api</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			echo "<h3>Kontaktperson</h3>";

			// contact_name
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_name">Kontaktperson - namn</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_name" name="contact_name">' . PHP_EOL, htmlentities($rent_object['contact_name']));
			echo '<p>Namn på kontaktperson.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_phone
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_phone">Kontaktperson - telefon</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_phone" name="contact_phone">' . PHP_EOL, htmlentities($rent_object['contact_phone']));
			echo '<p>Telefonnummer till kontaktperson, för exempelvis bokning av anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_email
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_email">Kontaktperson - e-post</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_email" name="contact_email">' . PHP_EOL, htmlentities($rent_object['contact_email']));
			echo '<p>E-post till kontaktperson, för exempelvis bokning av anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// url
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="url">Hemsida</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="url" name="url">' . PHP_EOL, htmlentities($rent_object['url']));
			echo '<p>Hemsideadress för aktuell information om anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			// contact_other
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="contact_other">Kontakt - information</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="contact_other" name="contact_other">' . PHP_EOL, htmlentities($rent_object['contact_other']));
			echo '<p>Övrig information du vill ge till gäster, exempelvis telefontider.</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			echo "<h3>Sökbara parametrar</h3>";

			// beds
			echo '<div class="form-field form-required">' . PHP_EOL;
			echo '<label for="beds">Sovplatser</label>' . PHP_EOL;
			printf('<input type="text" aria-required="true" value="%s" id="beds" name="beds">' . PHP_EOL, htmlentities($rent_object['beds']));
			echo '<p>Antal sovplatser på anläggningen</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

			$setting_names = $this->object_settings_names();
			$setting_options = $this->object_settings_options();
			
			foreach($setting_names as $name_id => $setting_name)
			{
				echo '<div class="form-field">' . PHP_EOL;
				echo "<label for=\"rent_object_option_{$name_id}\">{$setting_name}</label>" . PHP_EOL;
				echo "<select id=\"rent_object_option_{$name_id}\" name=\"rent_object_option[{$name_id}]\">" . PHP_EOL;
				echo '<option value="">-- Ej vald --</option>' . PHP_EOL;
				foreach($setting_options[$name_id] as $option_value => $option_name)
				{
					echo '<option value="' . htmlentities($option_value) . '"' . (((string) $option_value) == ((string) $rent_object['options'][$name_id]) ? ' selected="selected"': '') . '>' . htmlentities($option_name) . '</option>' . PHP_EOL;
				}
				echo '</select>' . PHP_EOL;
				// echo '<p>???.</p>' . PHP_EOL;
				echo '</div>' . PHP_EOL;
			}
			
			$js = <<<JS_BLOCK
wp.media.editor.send.attachment = function(props, attachment)
{
	window.uploaded_attachment_id = attachment.id;
	var element = document.getElementById('new_images');
	if(element)
	{
		element.value += ' ' + attachment.id;
		var img = document.createElement('img');
		img.className = 'attachment-thumbnail size-thumbnail';
		img.src = attachment.sizes.thumbnail.url;
		element.parentNode.insertBefore(img, element);
	}
	console.log(attachment);
};
wp.media.editor.open();
JS_BLOCK;
			$js_html = htmlentities(str_replace("\n", " ", $js));
			echo "<div class=\"wrap\"><h3><span>Bilder</span><span class=\"page-title-action\" onclick=\"{$js_html}\">Ny bild</span></h3>";

			foreach($wpdb->get_col("SELECT image_id FROM {$wpdb->prefix}rent_object_images WHERE rent_object_id = {$id} ORDER BY pos, image_id", 0) AS $image_id)
			{
				echo wp_get_attachment_image($image_id, 'thumbnail', NULL, array('data-id' => $image_id));
			}
			echo '<input type="hidden" id="new_images" name="new_images" value="" />';

			echo "<h3>Sökbara prisscenarier</h3>";
			echo "<p>Nedan ges olika tänkbara grupper som skulle kunna hyra anläggningen. Ange totalpriset som dessa grupper skulle få betala för angiven tidsperiod</p>";

			$price_scenarios = $this->price_scenarios($id);
			foreach($price_scenarios as $price_scenario)
			{
				$price_scenario_html = array_map('htmlentities', $price_scenario);
				echo '<div class="form-field">' . PHP_EOL;
				echo "<label for=\"rent_object_price_scenario_{$price_scenario['price_scenario_id']}\">{$price_scenario_html['price_scenario_name']}</label>" . PHP_EOL;
				echo "<input id=\"rent_object_price_scenario_{$price_scenario['price_scenario_id']}\" name=\"price[{$price_scenario['price_scenario_id']}]\" value=\"{$price_scenario_html['price']}\" />" . PHP_EOL;
				echo "<p>{$price_scenario_html['price_scenario']}</p>" . PHP_EOL;
				echo '</div>' . PHP_EOL;

				$rent_object['price'][$price_scenario['price_scenario_id']] = $price_scenario['price'];
			}
			printf('<input type="hidden" name="before" value="%s" />' . PHP_EOL, htmlentities(json_encode($rent_object)));

			echo '<p>';
			if($rent_object['object_status'])
			{
				submit_button("Spara", 'primary', 'save', FALSE);
				echo ' ';
				submit_button("Avpublicera", 'secondary', 'unpublish', FALSE);
			}
			else
			{
				submit_button("Spara", 'secondary', 'save', FALSE);
				echo ' ';
				submit_button("Publicera", 'primary', 'publish', FALSE);
			}
			echo '</p>' . PHP_EOL;

			echo '</div>' . PHP_EOL;
			echo '</div>' . PHP_EOL;

// 			echo "<pre>" . htmlentities(json_encode($rent_object, JSON_PRETTY_PRINT)) . "</pre>" . PHP_EOL;
		}
		echo '</div>' . PHP_EOL;

		echo "<div class='wrap'>";
		echo "<h1>Anläggningar <a class=\"page-title-action\" href=\"?page=rent_objects&amp;id=0\">Lägg till</a></h1>";
		$table = new rent_object_table();
		$table->admin_user = $this->admin_user;
		$table->prepare_items();
		$table->display();
// echo "<pre>" . json_encode($table->items, JSON_PRETTY_PRINT) . "</pre>";
		echo "<p><b>TODO:</b>(Koppla till organistation)</p>";
		echo '</form>' . PHP_EOL;
		echo '</div>' . PHP_EOL;
		echo "</div>";
	}

	function object_settings($id, $textual = FALSE)
	{
		global $wpdb;

		if($textual)
		{
			$query = <<<SQL_BLOCK
SELECT
	rent_object_settings_names.setting_name,
	rent_object_settings_options.option_name
FROM {$wpdb->prefix}rent_object_settings
LEFT JOIN {$wpdb->prefix}rent_object_settings_names AS rent_object_settings_names USING (rent_object_settings_name_id)
LEFT JOIN {$wpdb->prefix}rent_object_settings_options AS rent_object_settings_options USING (rent_object_settings_name_id, option_value)
WHERE rent_object_id =
SQL_BLOCK;
		}
		else
		{
			$query = <<<SQL_BLOCK
SELECT rent_object_settings_name_id, option_value
FROM {$wpdb->prefix}rent_object_settings
WHERE rent_object_id =
SQL_BLOCK;
		}
		$query .= (int) $id;

		return array_combine($wpdb->get_col($query, 0), $wpdb->get_col(NULL, 1));
	}

	function object_settings_names()
	{
		global $wpdb;

		return array_combine($wpdb->get_col("SELECT rent_object_settings_name_id, setting_name FROM {$wpdb->prefix}rent_object_settings_names", 0), $wpdb->get_col(NULL, 1));
	}
	
	function object_settings_options()
	{
		global $wpdb;

		$options = array();
		foreach($wpdb->get_results("SELECT * FROM {$wpdb->prefix}rent_object_settings_options", 'ARRAY_A') as $row)
		{
			$options[$row['rent_object_settings_name_id']][$row['option_value']] = $row['option_name'];
		}
		return $options;
	}
	
	function price_scenarios($rent_object_id = NULL)
	{
		global $wpdb;

		$rent_object_id = (int) $rent_object_id;
		if($rent_object_id)
		{
			$query = <<<SQL_BLOCK
SELECT price_scenarios.*, prices.price, prices.price / people / days AS pppd, prices.price_updated
FROM {$wpdb->prefix}rent_price_scenarios AS price_scenarios
	LEFT JOIN {$wpdb->prefix}rent_prices AS prices ON (prices.price_scenario_id = price_scenarios.price_scenario_id AND prices.rent_object_id = {$rent_object_id})
ORDER BY prio DESC, days*people, days, people
SQL_BLOCK;
		}
		else
		{
			$query = "SELECT price_scenarios.* FROM {$wpdb->prefix}rent_price_scenarios AS price_scenarios ORDER BY prio DESC, days * people, days, people";
		}

		return $wpdb->get_results($query, 'ARRAY_A');
	}

	function shortcode_stuga($raw_attributes)
	{
		$default_attributes = array('id' => NULL, 'mode' => '', 'filter' => NULL, 'def' => NULL);
		$attributes = shortcode_atts($default_attributes, $raw_attributes);
		foreach(array_keys($default_attributes) as $key)
		{
			if(isset($_GET[$key]) AND !isset($raw_attributes[$key]))
			{
				$attributes[$key] = $_GET[$key];
			}
		}

		if(!$attributes['id'] AND preg_match("#/(?<id>[1-9][0-9]*)(/|$)#", $_SERVER['REQUEST_URI'], $m))
		{
			$attributes['id'] = $m['id'];
		}

		if($attributes['filter'] AND is_string($attributes['filter']))
		{
			$attributes['filter'] = html_entity_decode($attributes['filter']);
			$filter = json_decode($attributes['filter']);
			if(!$filter)
			{
				$filter = json_decode(preg_replace("#([{,]\s*)([a-z][a-z0-9]*):#i", "\$1\"\$2\":", $attributes['filter']));
			}
			if($filter)
			{
				$attributes['filter'] = $filter;
			}
		}

		switch($attributes['mode'])
		{
			case 'title':
			{
				if($attributes['id'])
				{
					return $this->display_rent_object($attributes['id'], 'title');
				}
				else if($attributes['def'])
				{
					return $attributes['def'];
				}
				else
				{
					return "";
				}
			}
			case '';
			case 'object':
			{
				if($attributes['id'])
				{
					return $this->display_rent_object($attributes['id']);
				}
				else
				{
					return "";
				}
			}

			case 'map':
			{
				if($attributes['id'])
				{
					return "";
				}
				else
				{
					return $this->add_json_items($attributes['filter']) . $this->display_map();
				}
			}

			case 'list':
			{
				if($attributes['id'])
				{
					return "";
				}
				else
				{
					return $this->add_json_items($attributes['filter']) . $this->display_list();
				}
			}

			case 'filter':
			{
				if($attributes['id'])
				{
					return "";
				}
				else
				{
					return $this->add_json_items($attributes['filter']) . $this->display_filters();
				}
			}

			case 'reg':
			{
				return $this->display_reg_form();
			}
		}

		return "";
	}

	function add_json_items($filter = array())
	{
		global $wpdb;
		static $added = FALSE;

		if($added)
		{
			return "";
		}

		$items = $this->export_itmes();

		if($filter)
		{
			$items['filters'] = $filter;
		}

		$object_rows = array();
		foreach($items as $key => $values)
		{
			$key_json = json_encode($key);
			$object_rows[] = "window.rent_objects[{$key_json}] = " . json_encode($values, JSON_NUMERIC_CHECK + JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
		}
		$object_rows = implode("\n", $object_rows);

		$plugin_url_json = json_encode($this->url(''));
		$added = TRUE;

		return <<<HTML_BLOCK
<script type="text/javascript">
if(!window.rent_objects)
{
	window.rent_objects = {};
}
{$object_rows}
window.rent_objects.plugin_url = {$plugin_url_json}
</script>
HTML_BLOCK;
	}

	function display_filters()
	{
		$add_img = $this->url('/add.png');
		$add_img_html = htmlentities($add_img);
		return "<h3>Filter</h3><ul class=\"rent_object_filters\"></ul><p class=\"rent_object_add_filters\"><img src=\"{$add_img_html}\" alt=\"[+]\" /><span>Lägg till Filter</span></p>";
	}

	function display_list()
	{
		return <<<HTML_BLOCK
<table>
	<thead>
		<tr>
			<th>Anläggning</th>
			<th>Typ</th>
			<th>Ort</th>
			<th>Organisation</th>
			<!-- th>Avstånd</th -->
			<th>Sovplatser</th>
			<th>Pris</th>
		</tr>
	</thead>
	<tbody class="rent_object_list">
		<tr>
			<td colspan="6">Loading...</td>
		</tr>
	</tbody>
</table>
HTML_BLOCK;
	}

	function display_map()
	{
		return "<div class=\"map_wrapper\"><div class=\"map_canvas mapping\"></div></div>";
	}

	function display_reg_form()
	{
		// check if there is a user logged in
		$current_user = wp_get_current_user();
		$current_user_email = $current_user ? $current_user->get('user_email') : '';
		$email_html = htmlentities($add_data['email'] ? $add_data['email'] : $current_user_email);

		$action_url = esc_url(admin_url('admin-post.php?action=add_rent_object'));

		// TODO: dynamic rent_object_type_id
		return <<<HTML_BLOCK
<form action='{$action_url}' method='post'>
	<fieldset>
		<legend>Lägg till en ny anläggning</legend>
		<label>
			<span>Anläggningsyp:</span><br />
			<select id="rent_object_type_id" name="add_rent_object[type_id]">
				<option value="">-- Välj Typ --</option>
				<option value="1">Stuga</option>
				<option value="2">Lägerplats</option>
			</select>
		</label><br />
		<label>
			<span>Anläggningsnamn:</span><br />
			<input name="add_rent_object[name]" type="name" />
		</label><br />
		<label>
			<span>Kår / Organisation:</span><br />
			<input name="add_rent_object[organisation]" type="name" />
		</label><br />
		<label>
			<span>E-post för konto:</span><br />
			<input name="add_rent_object[email]" type="name" value="{$email_html}" />
		</label><br />
		<label>
			<span>Lösenord för konto:</span><br />
			<input name="add_rent_object[password]" type="password" />
		</label><br />
		<input type="submit" value="Lägg till anläggning" />
	</fieldset>
</form>
HTML_BLOCK;
	}

	function display_rent_object($rent_object, $part = NULL)
	{
		if(!is_object($rent_object))
		{
			if(is_numeric($rent_object))
			{
				global $wpdb;

				$rent_object = (int) $rent_object;

				$query = <<<SQL_BLOCK
SELECT rent_object.*, rent_organisations.organisation_name, users.display_name AS user_name
FROM {$wpdb->prefix}rent_object AS rent_object
	LEFT JOIN {$wpdb->prefix}rent_organisations AS rent_organisations USING (rent_organisation_id)
	LEFT JOIN {$wpdb->prefix}users AS users ON (users.ID = rent_object.user_id)
WHERE rent_object.rent_object_id = {$rent_object}
SQL_BLOCK;
				$rent_object = $wpdb->get_row($query);
			}
			else
			{
				return FALSE;
			}
		}

		if(!$rent_object)
		{
			return FALSE;
		}

		if($part)
		{
			switch($part)
			{
				case 'title':
				{
					return $rent_object->name;
				}
			}
		}

		$html = array();
		$html[] = '<div class="rent_object" id="rent_object_' . ((int) $rent_object->rent_object_id) . '">';
		$object_name = htmlentities($rent_object->name);
		$html[] = '<h2 class="rent_object_name">' . $object_name . '</h2>';
		$html[] = '<p class="ingress rent_object_ingress">' . nl2br(htmlentities($rent_object->ingress)) . '</p>';
		if($rent_object->main_image)
		{
			$html[] = '<p class="rent_object_main_image rent_object_image">' . wp_get_attachment_image((int) $rent_object->main_image, 'large') . '</p>';
		}
		$html[] = '<p class="description rent_object_description">' . make_clickable(nl2br(htmlentities($rent_object->description))) . '</p>';
		$url = htmlentities($rent_object->url);
		$html[] = '<p class="rent_object_link"><a class="rent_object_link" target="_bland" href="' . $url . '">' . $url . '</a></p>';

		$html[] = '<h3 class="visit_adress rent_object_adress">Plats</h3>';
		$html[] = '<p class="visit_adress rent_object_adress">' . nl2br(htmlentities($rent_object->visit_adress)) . '</p>';
		$map_attributes = array();
		$map_attributes['name'] = 'data-name="' . $object_name . '"';
		$map_attributes['adress'] = 'data-address="' . htmlentities(str_replace("\n", ", ", $rent_object->visit_adress)) . '"';

		if($rent_object->position_latitude AND $rent_object->position_longitude)
		{
			$map_attributes['position_lat'] = 'data-lat="' . htmlentities($rent_object->position_latitude) . '"';
			$map_attributes['position_long'] = 'data-long="' . htmlentities($rent_object->position_longitude) . '"';
		}

		$html[] = '<div class="map_wrapper" ' . implode(" ", $map_attributes) . '><div class="map_canvas mapping"></div></div>';
		$html[] = '<h3 class="price rent_object_price">Pris</h3>';
		$html[] = '<p class="price rent_object_price">' . nl2br(htmlentities($rent_object->price_description)) . '</p>';

		$html[] = '<h3 class="rent_object_data">Kort Fakta</h3>';
		$html[] = '<dl class="rent_object_data">';
		$html[] = '<dt>Organistation:</dt>	<dd>' . htmlentities($rent_object->organisation_name) . '</dd>';
		if($rent_object->beds)
		{
			$html[] = '<dt>Sovplatser:</dt>	<dd>' . htmlentities($rent_object->beds) . 'st</dd>';
		}

		$settings = $this->object_settings($rent_object->rent_object_id, TRUE);

		foreach($settings as $setting_name => $setting_value)
		{
			$html[] = '<dt>' . htmlentities($setting_name) . ':</dt>	<dd>' . htmlentities($setting_value) . '</dd>';
		}

		$html[] = '</dl>';

		$html[] = '<h3 class="rent_object_contact">Kontaktuppgifter</h3>';
		$html[] = '<dl class="rent_object_contact">';
		$contact_name = htmlentities($rent_object->contact_name);
		$html[] = "<dt>Kontaktperson:</dt>	<dd>{$contact_name}</dd>";

		$contact_emails = array();
		foreach(array_map('htmlentities', array_map('trim', explode(",", $rent_object->contact_email))) as $current_email)
		{
			$contact_emails[] = "<a href=\"mailto:{$contact_name} <{$current_email}>?subject={$object_name}\">{$current_email}</a>";
		}
		$html[] = "<dt>E-post:</dt>	<dd>" . implode(', ', $contact_emails) . "</dd>";

		$contact_phone = htmlentities($rent_object->contact_phone);
		$contact_phones = array();
		foreach(array_map('htmlentities', array_map('trim', explode(",", $rent_object->contact_phone))) as $current_phone)
		{
			$contact_phones[] = "<a href=\"tel:{$current_phone}\">{$current_phone}</a>";
		}
		$html[] = "<dt>Telefon:</dt>	<dd>" . implode(", ", $contact_phones) . "</dd>";
		$html[] = '<dt>Hemsida:</dt><dd class="rent_object_link"><a class="rent_object_link" target="_bland" href="' . $url . '">' . $url . '</a></dd>';
		if($rent_object->contact_other)
		{
			$html[] = '<dt>Övrigt:</dt>	<dd>' . make_clickable(htmlentities($rent_object->contact_other)) . '</dd>';
		}
		$html[] = '</dl>';

		$html[] = '<h3 class="rent_object_images">Bilder</h3>';
		$html[] = '<p class="rent_object_image">';
		$last_portrait = 100;
		foreach($wpdb->get_col("SELECT image_id FROM {$wpdb->prefix}rent_object_images WHERE rent_object_id = " . (int) $rent_object->rent_object_id . " ORDER BY pos, image_id", 0) AS $image_id)
		{
			$attributes = array();
			$meta = wp_get_attachment_metadata($image_id);
			// portrait
			if($meta['width'] * 1.35 < $meta['height'])
			{
				$attributes['style'] = "float: left; margin-right: 3px;";
				if($last_portrait == 2)
				{
					$attributes['style'] .= " clear: both;";
				}
				$last_portrait = 0;
				$html[] = "<!-- portrait: {$meta['width']} x {$meta['height']} [{$image_id}, {$last_portrait}]-->";
			}
			else
			{
				$last_portrait++;
				$html[] = "<!-- landscape: {$meta['width']} x {$meta['height']} [{$image_id}, {$last_portrait}]-->";
				if($last_portrait == 3)
				{
					$html[] = "<br style=\"clear: both;\" />";
				}
			}
			$html[] = wp_get_attachment_image($image_id, 'large', FALSE, $attributes);
		}
		if($last_portrait < 3)
		{
			$html[] = "<br style=\"clear: both;\" />";
		}
		$html[] = '</p>';

		$html[] = '<p class="rent_object_footer">' . htmlentities("Updated {$rent_object->object_updated} by {$rent_object->user_name}, Löpnummer: {$rent_object->rent_object_id}") . '</p>';

// 		$html[] = "<pre>" . print_r($rent_object, TURE) . "</pre>";
		$html[] = '</div>';
		return implode(PHP_EOL, $html);
	}

	static function add_new_user()
	{
		global $wpdb;

		// abort if no data send
		if(empty($_POST['add_rent_object']))
		{
			return FALSE;
		}

		// populate $add_data with post-data or default values
		$add_data = $_POST['add_rent_object'] + array('name' => NULL, 'organisation' => NULL, 'type_id' => NULL, 'email' => NULL, 'password' => NULL);

		// check if there is a user logged in
		$current_user = wp_get_current_user();

		// email-adress provided?
		if($add_data['email'])
		{
			// if email-adress matches loged in user, use current user
			if($current_user AND $current_user->get('user_email') == $add_data['email'])
			{
				$user = $current_user;
			}
			else
			{
				// if there is a current user, that don't match the email, log out that user
				if($current_user)
				{
					wp_logout();
					$current_user = NULL;
				}

				// Check if the email-adress match a current user
				$user = get_user_by('email', $add_data['email']);

				// email match a current user
				if($user)
				{
					// Try to log in that user, to see if password matches
					$user = wp_signon(array('user_login' => $user->get('user_login'), 'user_password' => $add_data['password']));
					if(is_wp_error($user))
					{
						return $user;
					}
				}
				// email is unknown/new
				else
				{
					// create a user
					$user = wp_insert_user(array('role' => 'contributor', 'user_login' => $add_data['email'], 'user_pass' => $add_data['password'], 'user_email' => $add_data['email']));
					if(is_wp_error($user))
					{
						return $user;
					}

					// login as the new user
					$user = wp_signon(array('user_login' => $add_data['email'], 'user_password' => $add_data['password']));
					if(is_wp_error($user))
					{
						return $user;
					}

					// Allow uploads
					$user->add_cap('upload_files');
				}
			}
		}
		// no email provided
		else
		{
			// use current user if any
			$user = $current_user;
		}

		if(!$user)
		{
			return $user;
		}

		// Name for object provided, known (or newly created) user
		if(!$add_data['name'])
		{
			return TRUE;
		}

		// No organisation givven, use default organisation
		if(!$add_data['organisation'])
		{
			$org_id = 1;
		}
		else
		{
			// look up organisation in database
			$org_id = $wpdb->get_var($wpdb->prepare("SELECT rent_organisation_id FROM {$wpdb->prefix}rent_organisations WHERE organisation_name = %s", $add_data['organisation']));

			// organisation not found
			if(!$org_id)
			{
				// add organisation
				$db_af_rows = $wpdb->insert(
					"{$wpdb->prefix}rent_organisations",
					array(
						'parent_organisation_id' => 1,
						'organisation_name' => $add_data['organisation'],
						'user_id' => $user->ID,
					),
					array('%d', '%s', '%d')
				);
				$org_id = $db_af_rows ? $wpdb->insert_id : $db_af_rows;

				// new organisation created
				if($org_id)
				{
					// add permission for current user
					$wpdb->insert(
						"{$wpdb->prefix}rent_organisation_permissions",
						array(
							'user_id' => $user->ID,
							'rent_organisation_id' => $org_id,
						),
						array('%d', '%d')
					);
				}
			}
		}

		// organisation not found and not created
		if(!$org_id)
		{
			return $org_id;
		}

		// add rent-object
		$db_af_rows = $wpdb->insert(
			"{$wpdb->prefix}rent_object",
			array(
				'rent_organisation_id' => $org_id,
				'rent_object_type_id' => (int) $add_data['type_id'],
				'user_id' => $user->ID,
				'name' => $add_data['name']
			),
			array('%d', '%d', '%d', '%s')
		);
		$rent_object_id = $db_af_rows ? $wpdb->insert_id : $db_af_rows;

		// object not created
		if(!$rent_object_id)
		{
			return $rent_object_id;
		}

		// add permission for current user
		$wpdb->insert(
			"{$wpdb->prefix}rent_object_permissions",
			array(
				'user_id' => $user->ID,
				'rent_object_id' => $rent_object_id,
			),
			array('%d', '%d')
		);

		// redirect the user to the page where the user can update the object with more information
		wp_redirect(admin_url("admin.php?page=rent_objects&id={$rent_object_id}"));
		die();
	}

	function export_itmes()
	{
		global $wpdb;

		$settings = array();
		foreach($wpdb->get_results("SELECT rent_object_settings_name_id AS option, setting_name AS name FROM {$wpdb->prefix}rent_object_settings_names", 'ARRAY_A') as $row)
		{
			$settings[$row['option']] = $row;
		}
		foreach($wpdb->get_results("SELECT rent_object_settings_name_id AS option, option_name AS name, option_value AS value FROM {$wpdb->prefix}rent_object_settings_options", 'ARRAY_A') as $row)
		{
			$settings[$row['option']]['options'][] = $row;
		}

		$types = $wpdb->get_results("SELECT rent_object_type_id AS id, type_name AS name, url, price_scenario_id FROM {$wpdb->prefix}rent_object_types", 'ARRAY_A');
		$types_indexed = array_combine(array_column($types, 'id'), $types);

		$organisations = $wpdb->get_results("SELECT rent_organisation_id AS id, parent_organisation_id AS parent_id, organisation_name AS name FROM {$wpdb->prefix}rent_organisations WHERE object_status > 0", 'ARRAY_A');

		$price_scenarios = $this->price_scenarios();

		$items = array();
		foreach($wpdb->get_results("SELECT rent_object_id, rent_organisation_id, rent_object_type_id, name, beds, position_latitude, position_longitude, city, object_updated, type_name, CONCAT(types.url, rent_object_id) AS url FROM {$wpdb->prefix}rent_object LEFT JOIN {$wpdb->prefix}rent_object_types AS types USING (rent_object_type_id) WHERE object_status > 0", 'ARRAY_A') as $row)
		{
			$items[$row['rent_object_id']] = $row + array('options' => array(), 'price' => array());
		}
		foreach($wpdb->get_results("SELECT * FROM {$wpdb->prefix}rent_object_settings", 'ARRAY_A') as $row)
		{
			if(empty($items[$row['rent_object_id']]))
			{
				continue;
			}
			$items[$row['rent_object_id']]['options'][] = array('option' => $row['rent_object_settings_name_id'], 'value' => $row['option_value']);
		}
		foreach($wpdb->get_results("SELECT prices.*, prices.price / scenarios.people / scenarios.days AS pppd, scenarios.price_scenario_name FROM {$wpdb->prefix}rent_prices AS prices LEFT JOIN {$wpdb->prefix}rent_price_scenarios AS scenarios USING (price_scenario_id)", 'ARRAY_A') as $row)
		{
			if(empty($items[$row['rent_object_id']]))
			{
				continue;
			}

			$items[$row['rent_object_id']]['price'][] = array('price_scenario_id' => $row['price_scenario_id'], 'value' => $row['price'], 'pppd' => $row['pppd']);

			if(isset($types_indexed[$items[$row['rent_object_id']]['rent_object_type_id']]['price_scenario_id']) AND $row['price_scenario_id'] == $types_indexed[$items[$row['rent_object_id']]['rent_object_type_id']]['price_scenario_id'])
			{
				$items[$row['rent_object_id']]['senario_price'] = $row['price'];
				$items[$row['rent_object_id']]['senario_pppd'] = round($row['pppd'], 2);
				$items[$row['rent_object_id']]['senario_price_name'] = $row['price_scenario_name'];
			}
		}

		return array(
			'settings' => array_values($settings),
			'objects' => array_values($items),
			'organisations' => $organisations,
			'object_types' => $types,
			'price_scenarios' => $price_scenarios,
		);

	}

	function rent_object_json_export()
	{
		$items = $this->export_itmes();
		$rent_objects_json = json_encode($items, JSON_NUMERIC_CHECK + JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
		$mode = empty($_REQUST['mode']) ? 'json' : $_REQUST['mode'];
		switch($mode)
		{
			case 'html';
			case 'jselement';
			{
				echo '<script type="text/javascript">window.rent_objects = ' . $rent_objects_json . '</script>'. PHP_EOL;
				die();
			}

			case 'js';
			{
				echo 'window.rent_objects = ' . $rent_objects_json . PHP_EOL;
				die();
			}

			case 'json';
			default:
			{
				echo $rent_objects_json . PHP_EOL;
				die();
			}
		}
	}

	static function check_permission($rent_object_id)
	{
		global $wpdb;

		if(current_user_can(RENT_OBJECT_ADMIN_CAP))
		{
			return TRUE;
		}

		$user_id = (int) get_current_user_id();
		$rent_object_id = (int) $rent_object_id;

		if(!$user_id OR !$rent_object_id)
		{
			return FALSE;
		}

		$direct_access = $wpdb->get_var("SELECT 1 AS ok FROM {$wpdb->prefix}rent_object_permissions WHERE user_id = {$user_id} AND rent_object_id = {$rent_object_id}");

		if($direct_access)
		{
			return TRUE;
		}

		$rent_organisation_id = $wpdb->get_var("SELECT rent_organisation_id FROM {$wpdb->prefix}rent_object WHERE rent_object_id = {$rent_object_id}");

		$rent_organisation_ids = rent_object_holder::allowed_organisations($user_id);
		$rent_organisation_ids = array_combine($rent_organisation_ids, $rent_organisation_ids);

		return isset($rent_organisation_ids[$rent_organisation_id]);
	}

	static function allowed_organisations($user_id)
	{
		global $wpdb;

		$ro_table = "{$wpdb->prefix}rent_organisations";
		$rop_table = "{$wpdb->prefix}rent_organisation_permissions";
		$query = <<<SQL_BLOCK
(
	SELECT rent_organisation_id
	FROM {$rop_table}
	WHERE user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = rop.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_1.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_2 ON (link_2.parent_organisation_id = link_1.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_2.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_2 ON (link_2.parent_organisation_id = link_1.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_3 ON (link_3.parent_organisation_id = link_2.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_3.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
UNION
(
	SELECT ro.rent_organisation_id
	FROM {$rop_table} AS rop
		INNER JOIN {$ro_table} AS link_1 ON (link_1.parent_organisation_id = rop.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_2 ON (link_2.parent_organisation_id = link_1.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_3 ON (link_3.parent_organisation_id = link_2.rent_organisation_id)
		INNER JOIN {$ro_table} AS link_4 ON (link_4.parent_organisation_id = link_3.rent_organisation_id)
		INNER JOIN {$ro_table} AS ro ON (ro.parent_organisation_id = link_4.rent_organisation_id)
	WHERE rop.user_id = {$user_id}
)
SQL_BLOCK;

		return $wpdb->get_col($query);
	}
	
	function db_check()
	{
		$sql_file = __DIR__ . '/rent_objects.sql';
		// TODO on release, replace with static MD5-string
		$db_md5 = md5_file($sql_file);
		if(get_site_option('rent_object_db_md5') != $db_md5)
		{
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta(str_replace('CREATE TABLE IF NOT EXISTS `wp_', 'CREATE TABLE `' . $wpdb->prefix, file_get_contents($sql_file)));
			update_option('rent_object_db_md5', $db_md5);
		}
	}
}

require_once(__DIR__ . '/class-wp-list-table.php');
class rent_object_table extends RO_WP_List_Table
{
	public $admin_user = FALSE;

	/** Class constructor */
	public function __construct()
	{
		parent::__construct(
			array(
				'singular' => 'Anlägning',
				'plural'   => 'Anlägningar',
// 				'ajax'     => TRUE,
				'ajax'     => FALSE,
			)
		);
	}

	function prepare_items()
	{
		global $wpdb;

		$this->_column_headers = array($this->get_columns(), array(), array());
// 		$this->process_bulk_action();

		if($this->admin_user)
		{
			$query = <<<SQL_BLOCK
SELECT rent_object.*, rent_organisations.organisation_name, users.display_name AS user_name
FROM {$wpdb->prefix}rent_object AS rent_object
	LEFT JOIN {$wpdb->prefix}rent_organisations AS rent_organisations USING (rent_organisation_id)
	LEFT JOIN {$wpdb->prefix}users AS users ON (users.ID = rent_object.user_id)
WHERE rent_object.object_status >= 0
ORDER BY rent_object.rent_object_id
SQL_BLOCK;
			$this->items = $wpdb->get_results($query, 'ARRAY_A');
		}
		else
		{
			$user_id = (int) get_current_user_id();
			$rent_organisations = rent_object_holder::allowed_organisations($user_id);
			if($rent_organisations)
			{
				$rent_organisations = array_map(function ($a) {return (int) $a;}, $rent_organisations);
				$rent_organisations_where = 'OR rent_object.rent_organisation_id IN (' . implode(', ', $rent_organisations) . ')';
			}
			else
			{
				$rent_organisations_where = '';
			}

			$query = <<<SQL_BLOCK
SELECT rent_object.*, rent_organisations.organisation_name, users.display_name AS user_name
FROM {$wpdb->prefix}rent_object AS rent_object
	LEFT JOIN {$wpdb->prefix}rent_organisations AS rent_organisations USING (rent_organisation_id)
	LEFT JOIN {$wpdb->prefix}users AS users ON (users.ID = rent_object.user_id)
	LEFT JOIN {$wpdb->prefix}rent_object_permissions AS rent_object_permissions ON (rent_object_permissions.rent_object_id = rent_object.rent_object_id AND rent_object_permissions.user_id = {$user_id})
WHERE rent_object.object_status >= 0 AND (rent_object_permissions.rent_object_id IS NOT NULL {$rent_organisations_where})
ORDER BY rent_object.rent_object_id
SQL_BLOCK;
$GLOBALS['debug_query'] = $query;

			$this->items = $wpdb->get_results($query, 'ARRAY_A');
		}
		return TRUE;
	}

	function get_columns()
	{
		return array(
			"cb" => '<input type="checkbox" />',
			"rent_object_id" => "ID",
			"name"=> "Namn",
			"organisation_name"=> "Organistation",
			"object_status"=> "Status",
			"user_name"=> "Användare",
			"object_updated"=> "Uppdaterad",

// 			"rent_object_type_id"=> "2",
// 			"main_image"=> "30",
// 			"visit_adress"=> "Kragen\u00e4s\r\nMyren 3\r\n457 91 Tanumshede",
// 			"url"=> "http=>\/\/kragenas.scout.se\/",
// 			"position_latitude"=> "58.799048",
// 			"position_longitude"=> "11.226496",

// 			"ingress"=> "Kragen\u00e4s - m\u00f6jligheternas m\u00f6tesplats\r\n",
// 			"description"=> "Med det vidstr\u00e4ckta havet i v\u00e4ster, inomsk\u00e4rs milsl\u00e5nga kanotleder i norr, utbredda hajkomr\u00e5den i \u00f6ster och hela Tanums kommuns kulturarv i s\u00f6der bjuder Kragen\u00e4s in till \u00e4ventyr och minnen f\u00f6r livet. Vackert bel\u00e4get vid Tanums naturreservat 14 mil fr\u00e5n G\u00f6teborg d\u00e4r land m\u00f6ter hav tar Kragen\u00e4s tillvara p\u00e5 terr\u00e4ngens variation och erbjuder o\u00e4ndliga m\u00f6jligheter f\u00f6r l\u00e4ger med programaktiviteter ut\u00f6ver det vanliga. Genom skog och berg g\u00e5r l\u00e5nga vandringsleder och vid vattnet guppar bryggorna av kluckande v\u00e5gor. Med sina m\u00e5nga l\u00e4ger\u00e4ngar, roliga aktiviteter och inspirerande programutbud finns det plats f\u00f6r h\u00e4rliga upplevelser och \u00e4ventyr ut\u00f6ver det vanliga. V\u00e4lkommen till Kragen\u00e4s \u2013  m\u00f6jligheternas m\u00f6tesplats!",
// 			"beds"=> "0",
// 			"post_adress"=> "",
// 			"city"=> "",
// 			"price_description"=> "40 kr*\/person\/natt exklusive mat\r\n125 kr*\/person\/natt inklusive mat, servicefunktioner och programutbud (ej kanoter, kajaker och hantverksmaterial)\r\n\r\n* Scouter tillh\u00f6rande G\u00f6teborgs scoutdistrikt erh\u00e5ller 15 kr rabatt\/person\/natt.\r\n\r\nSenaste priser p\u00e5=>\r\nhttp=>\/\/kragenas.scout.se\/boka\/prislista\/",
// 			"contact_name"=> "Kragen\u00e4sgruppen",
// 			"contact_phone"=> "0525-23380",
// 			"contact_email"=> "kragenas@gbgscout.se",
// 			"contact_other"=> "",
		);
	}

	function column_cb($item)
	{
		return sprintf('<input type="checkbox" name="buld[]" value="%d" />', $item['rent_object_id']);
	}

	function column_name($item)
	{
		return sprintf('<a href="?page=rent_objects&amp;id=%d">%s</a>', $item['rent_object_id'], $item['name'] ? htmlentities($item['name']) : "(namnlöss)");
	}

	function column_object_status($item)
	{
		switch($item['object_status'])
		{
			case 0:
			{
				return "Utkast";
			}
			case 1:
			{
				return "Publicerad";
			}
			case -1:
			{
				return "Borttagen";
			}
			default:
			{
				return "Status {$item['object_status']}";
			}
		}
	}

	function column_default($item, $column_name)
	{
		return htmlentities($item[$column_name]);
	}

	public function no_items()
	{
		echo "Listan är tom";
	}
}
