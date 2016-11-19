if(!window.rent_objects)
{
	window.rent_objects = {};
}

window.rent_objects.fetch_item_by_id = function(list, id_field, id_value, select_field)
{
	var l = list.length;
	for(var li = 0; li < l; li++)
	{
		if(list[li][id_field] == id_value)
		{
			if(select_field)
			{
				return list[li][select_field];
			}
			else
			{
				return list[li];
			}
		}
	}
};

window.rent_objects.filter = function()
{
	var objects = window.rent_objects.objects;
	if(!objects)
	{
		return;
	}
	var objects_length = objects.length;
	var active_objects = [];
	var filters = window.rent_objects.filters;
	var filter_count = filters.length;

	if(window.google && google.maps)
	{
		if(!window.rent_objects.cmp_pos)
		{
			if(window.rent_objects.user_pos)
			{
				window.rent_objects.cmp_pos = window.rent_objects.user_pos;
			}
			else
			{
				window.rent_objects.cmp_pos = {lat: 58.519222, lng: 15.0198165};
			}
			window.rent_objects.cmp_pos.marker = false;
			window.rent_objects.cmp_pos.auto = true;
		}
		if(window.rent_objects.cmp_pos)
		{
			if(!window.rent_objects.cmp_pos.marker)
			{
				var map_wrapper = document.getElementsByClassName('map_wrapper')[0];
				var position = new google.maps.LatLng(window.rent_objects.cmp_pos.lat, window.rent_objects.cmp_pos.lng);

				window.rent_objects.cmp_pos.marker = new google.maps.Marker({
					map: map_wrapper.map,
					title: 'Avstånd från här',
					draggable: true,
					position: position,
					icon: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
				});
				var cmp_update = function()
					{
						var pos = window.rent_objects.cmp_pos.marker.getPosition();
						window.rent_objects.cmp_pos.lat = pos.lat();
						window.rent_objects.cmp_pos.lng = pos.lng();
						window.rent_objects.cmp_pos,auto = false;
						window.rent_objects.filter();
					};
				google.maps.event.addListener(
					window.rent_objects.cmp_pos.marker,
					'dragend',
					cmp_update
				);
				google.maps.event.addListener(
					window.rent_objects.cmp_pos.marker,
					'drag',
					cmp_update
				);
			}
		}
	}

	for(var oi = 0; oi < objects_length; oi++)
	{
		var ok = true;
		var object = objects[oi];
		if(window.rent_objects.cmp_pos)
		{
			object.distance = window.rent_objects.distance(window.rent_objects.cmp_pos.lat, window.rent_objects.cmp_pos.lng, object.position_latitude, object.position_longitude);
		}
		for(var fi = 0; fi < filter_count; fi++)
		{
			var filter = filters[fi];
			switch(filter.type)
			{
				case 'option':
				{
					var option = false;
					var option_count = object.options.length;
					for(var o_index = 0; o_index < option_count; o_index++)
					{
						if(object.options[o_index].option == filter.option)
						{
							option = object.options[o_index];
							break;
						}
					}
					if(filter.method == "show")
					{
						if(!option)
						{
							ok = false;
							break;
						}
						var value_count = filter.values.length;
						var found = false;
						for(var v_index = 0; v_index < value_count; v_index++)
						{
							if(option.value == filter.values[v_index])
							{
								found = true;
								break;
							}
						}
						if(!found)
						{
							ok = false;
						}
					}
					else if(filter.method == "hide")
					{
						var value_count = filter.values.length;
						for(var v_index = 0; v_index < value_count; v_index++)
						{
							if(option.value == filter.values[v_index])
							{
								ok = false;
								break;
							}
						}
					}
					break;
				}
				case 'type':
				{
					if(filter.value > 0)
					{
						if(object.rent_object_type_id != filter.value)
						{
							ok = false;
						}
					}
					else
					{
						if(object.rent_object_type_id == -filter.value)
						{
							ok = false;
						}
					}
					break;
				}
				case 'name':
				{
					if(!window.rent_objects.filter_text(object.name, filter.value, filter.method))
					{
						ok = false;
					}
					break;
				}
				case 'organisation':
				{
					var organisation = false;
					var organisations = window.rent_objects.organisations;
					var organisation_count = organisations.length;
					for(var o_index = 0; o_index < organisation_count; o_index++)
					{
						if(organisations[o_index].id == object.rent_organisation_id)
						{
							organisation = organisations[o_index].name;
							break;
						}
					}
					if(!organisation)
					{
						ok = false;
					}
					else if(!window.rent_objects.filter_text(organisation, filter.value, filter.method))
					{
						ok = false;
					}
					break;
				}
				case 'beds':
				{
					switch(filter.method)
					{
						case 'equal':
						{
							if(object.beds != filter.value)
							{
								ok = false;
							}
							break;
						}

						case 'more':
						{
							if(object.beds < filter.value)
							{
								ok = false;
							}
							break;
						}

						case 'less':
						{
							if(object.beds > filter.value)
							{
								ok = false;
							}
							break;
						}

						case 'not':
						{
							if(object.beds == filter.value)
							{
								ok = false;
							}
							break;
						}
					}
					break;
				}
				case 'distance':
				{
					// TODO
				}
				case 'price':
				{
					var price_count = object.price.length;
					var price = false;
					for(var price_index = 0; price_index < price_count; price_index++)
					{
						if(object.price[price_index].price_scenario_id == filter.price_scenario_id)
						{
							price = object.price[price_index].value;
							break;
						}
					}
					if(!price)
					{
						ok = false;
					}
					else if(price > filter.value)
					{
						ok = false;
					}
				}
			}
		}
		if(ok)
		{
			active_objects.push(object);
			object.visible = true;
		}
		else
		{
			object.visible = false;
		}
	}

	// TODO sort
window.rent_objects.sortorder = 'distance';
	if(window.rent_objects.sortorder)
	{
		switch(window.rent_objects.sortorder)
		{
			case 'distance':
			{
				active_objects.sort(
					function (a, b)
					{
						if(a.distance)
						{
							if(b.distance)
							{
								return a.distance - b.distance;
							}
							else
							{
								return -1;
							}
						}
						else if(b.distance)
						{
							return 1;
						}
						return 0;
					}
				);
				break;
			}
		}
	}
	window.rent_objects.active_objects = active_objects;
	window.rent_objects.render_filters();
	window.rent_objects.update_list();
	window.rent_objects.update_map();
};

window.rent_objects.filter_text = function(haysatack, needle, method)
{
	var reverse = (needle.substr(0, 1) == '!');
	if(reverse)
	{
		needle = needle.substr(1);
	}

	switch(method)
	{
		case 'begins':
		{
			return (haysatack.substr(0, needle.length) == needle);
		}

		case 'ends':
		{
			return (haysatack.substr(haysatack.length - needle.length) == needle);
		}

		case 'regexp':
		{
			return (new RegExp(needle)).test(haysatack);
		}

		case 'contains':
		case '':
		default:
		{
			return (haysatack.indexOf(needle) >= 0);
		}
	}
}
// Haversine formula for the sphere called earth
window.rent_objects.distance = function(lat1, lng1, lat2, lng2)
{
	// converter
	var deg2rad = function(deg) {return deg * (Math.PI/180)};
	// Sphare radius (earth) in meters
	var R = 6371000;
	// delta values
	var dLat = deg2rad(lat2 - lat1);
	var dLong = deg2rad(lng2 - lng1);
	// calculate angel(?) between dots
	var a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * Math.sin(dLong/2) * Math.sin(dLong/2);
	var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
	// multipliy angle in radians with sphare radius
	var d = R * c; // Distance in km
	return d;
}
window.rent_objects.update_list = function()
{
	var objects = window.rent_objects.active_objects;
	var objects_count = objects.length;

	var org = window.rent_objects.organisations;
	var types = window.rent_objects.object_types;

	var lists = document.getElementsByClassName('rent_object_list');
	var lists_count = lists.length;
	for(var li = 0; li < lists_count; li++)
	{
		var list = lists[li];
		list.innerHTML = '';

		for(var oi = 0; oi < objects_count; oi++)
		{
			var object = objects[oi];

			var tr = document.createElement('tr');
			tr.setAttribute('data-rent-object-id', object.rent_object_id);

			// Name
			var td = document.createElement('td');
			var a = document.createElement('a');
			a.innerText = object.name;
			a.href = object.url;
			td.appendChild(a);
			tr.appendChild(td);

			// Organisation
			var td = document.createElement('td');
			td.innerText = window.rent_objects.fetch_item_by_id(org, 'id', object.rent_organisation_id, 'name');
			tr.appendChild(td);

			// Type
			var td = document.createElement('td');
			td.innerText = window.rent_objects.fetch_item_by_id(types, 'id', object.rent_object_type_id, 'name');
			tr.appendChild(td);

			// City
			var td = document.createElement('td');
			td.innerText = object.city;
			tr.appendChild(td);

			// Distance
			var td = document.createElement('td');
			if(object.distance)
			{
				if(object.distance < 3000)
				{
					td.innerText = object.distance + 'm';
				}
				else if(object.distance < 30000)
				{
					td.innerText = (Math.round(object.distance /100) / 10) + 'km';
				}
				else
				{
					td.innerText = (Math.round(object.distance /1000) / 10) + 'mil';
				}
			}
			else
			{
				td.innerText = '';
			}
			tr.appendChild(td);

			// Beds
			var td = document.createElement('td');
			if(object.beds > 0)
			{
				td.innerText = object.beds;
			}
			else
			{
				td.innerText = '';
			}
			tr.appendChild(td);

			// Price
			var td = document.createElement('td');
			if(object.senario_price)
			{
				td.innerText = '~' + object.senario_pppd  + ' kr/p/d';
				td.title = 'Pris för ' + object.senario_price_name + ': ' + object.senario_price + 'kr ~= ' + object.senario_pppd + ' kr per person och dygn';
			}
			else
			{
				td.innerText = '';
			}
			tr.appendChild(td);

			list.appendChild(tr);
		}
	}
	// TODO
	// ...
};

window.rent_objects.update_map = function()
{
	var map_wrapper = document.getElementsByClassName('map_wrapper')[0];
	if(!map_wrapper) return false;
	if(!map_wrapper.map) return false;
	if(!map_wrapper.map_bounds) return false;

	var added = 0;
	var objects = window.rent_objects.objects;
	var objects_count = objects.length;
	for(var oi = 0; oi < objects_count; oi++)
	{
		var object = objects[oi];
		if(object.map_marker)
		{
			object.map_marker.setVisible(object.visible);
		}
		else
		{
			if(object.position_latitude)
			{
				var icon = 'https://maps.google.com/mapfiles/ms/icons/green-dot.png';
				if(object.rent_object_type_id == 2)
				{
					icon = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
				}
				var position = new google.maps.LatLng(object.position_latitude, object.position_longitude);

				object.map_marker = new google.maps.Marker({
					map: map_wrapper.map,
					title: object.name,
					position: position,
					icon: icon
				});
				object.map_marker.reference = object;
				var rows = [
					'<a href="' + object.url + '">' + object.name + '</a>',
					object.city,
				];
				object.infowindow = new google.maps.InfoWindow({content: rows.join("<br />\n")});

				object.map_marker.addListener('click', function() {
					if(window.rent_objects.map_open_info_window)
					{
						window.rent_objects.map_open_info_window.close();

					}
					window.rent_objects.map_open_info_window = this.reference.infowindow;
					this.reference.infowindow.open(this.map, this);
				});

				map_wrapper.map_bounds.extend(position);
				added++;
			}
		}
	}

	if(added)
	{
		map_wrapper.map.fitBounds(map_wrapper.map_bounds);
	}
};

window.rent_objects.render_filters = function()
{
	var elements = document.getElementsByClassName('rent_object_filters');
	var element_count = elements.length;
	var filters = window.rent_objects.filters;
	var filter_count = filters.length;
	if(!filter_count) return false;
	for(var f_index = 0; f_index < filter_count; f_index++)
	{
		var filter = filters[f_index];

		// TODO: get better texts
		var li_text = JSON.stringify(filter);
		if(filter.text)
		{
			li_text = filter.text;
		}
		else
		{
			switch(filter.type)
			{
				case 'type':
				{
					var object_types = window.rent_objects.object_types
					var object_types_count = object_types.length;
					for(var ot_index = 0; ot_index < object_types_count; ot_index++)
					{
						var object_type = object_types[ot_index];
						if(filter.value > 0)
						{
							if(object_type.id == filter.value)
							{
								li_text = object_type.name;
								break;
							}
						}
						else
						{
							if(object_type.id == -filter.value)
							{
								li_text = 'Ingen ' + object_type.name;
								break;
							}
						}
					}

					break;
				}

				case 'name':
				{
					switch(filter.method)
					{
						case 'begins':
						{
							li_text = 'Namn börjar med "' + filter.value + '"';
							break;
						}

						case 'ends':
						{
							li_text = 'Namn slutar med "' + filter.value + '"';
							break;
						}

						case 'regexp':
						{
							li_text = 'Namn matchar /' + filter.value + '/';
							break;
						}

						case 'contains':
						case '':
						default:
						{
							li_text = 'Namn innehåller "' + filter.value + '"';
							break;
						}
					}
					break;
				}

				case 'organisation':
				{
					switch(filter.method)
					{
						case 'begins':
						{
							li_text = 'Organisation börjar med "' + filter.value + '"';
							break;
						}

						case 'ends':
						{
							li_text = 'Organisation slutar med "' + filter.value + '"';
							break;
						}

						case 'regexp':
						{
							li_text = 'Organisation matchar /' + filter.value + '/';
							break;
						}

						case 'contains':
						case '':
						default:
						{
							li_text = 'Organisation innehåller "' + filter.value + '"';
							break;
						}
					}
					break;
				}

				case 'beds':
				{
					switch(filter.method)
					{
						case 'equal':
						{
							switch(filter.value)
							{
								case 0:
								{
									li_text = 'Inga sovplatser';
									break;
								}
								case 1:
								{
									li_text = 'En sovplats';
									break;
								}
								default:
								{
									li_text = filter.value + ' sovplatser';
									break;
								}
							}
							break;
						}

						case 'more':
						{
							switch(filter.value)
							{
								case 0:
								{
									li_text = 'Har ett värde för sovplatser';
									break;
								}
								case 1:
								{
									li_text = 'Har sovplatser';
									break;
								}
								default:
								{
									li_text = 'Har minst ' + filter.value + ' sovplatser';
									break;
								}
							}
							break;
						}

						case 'less':
						{
							switch(filter.value)
							{
								case 0:
								{
									li_text = 'Inga sovplatser';
									break;
								}
								case 1:
								{
									li_text = 'Har max en sovplats';
									break;
								}
								default:
								{
									li_text = 'Har inte fler än ' + filter.value + ' sovplatser';
									break;
								}
							}
							break;
						}

						case 'not':
						{
							switch(filter.value)
							{
								case 0:
								{
									li_text = 'Har sovplatser';
									break;
								}
								case 1:
								{
									li_text = 'Har inte bara en sovplats';
									break;
								}
								default:
								{
									li_text = 'Har inte ' + filter.value + ' sovplatser';
									break;
								}
							}
							break;
						}

					}
				}
				case 'option':
				{
					var s = false;
					var s_count = rent_objects.settings.length;
					for(var s_index = 0; s_index < s_count; s_index++)
					{
						if(rent_objects.settings[s_index].option == filter.option)
						{
							s = rent_objects.settings[s_index];
							break;
						}
					}
					if(s)
					{
						var names = [];
						var v_count = filter.values.length;
						var o_count = s.options.length;
						for(var v_index = 0; v_index < v_count; v_index++)
						{
							for(var o_index = 0; o_index < o_count; o_index++)
							{
								if(filter.values[v_index] == s.options[o_index].value)
								{
									names.push(s.options[o_index].name);
									break;
								}
							}

						}

						if(filter.method == "show")
						{
							if(names.length == 1)
							{
								li_text = s.name + ' är: ' + names[0];
							}
							else
							{
								li_text = s.name + ' är någon av: ' + names.join(', ');
							}
						}
						else if(filter.method == "hode")
						{
							if(names.length == 1)
							{
								li_text = s.name + ' inte är: ' + names[0];
							}
							else
							{
								li_text = s.name + ' inte är någon av: ' + names.join(', ');
							}
						}
					}
					break;
				}
				case 'distance':
				{
					// TODO
				}
				case 'price':
				{
					var senario = false;
					var ps_count = rent_objects.price_scenarios.length;
					for(var ps_index = 0; ps_index < ps_count; ps_index++)
					{
						if(rent_objects.price_scenarios[ps_index].price_scenario_id == filter.price_scenario_id)
						{
							senario = rent_objects.price_scenarios[ps_index];
							break;
						}
					}
					if(senario)
					{
						li_text = 'Max ' + filter.value + ' kr för pris-senario ' + senario.price_scenario_name + ' (Max ' + Math.round( filter.value / senario.days / senario.people ) + ' kr / person / dag)';
					}
					break;
				}
			}
		}

		if(element_count)
		{
			if(!filter.display_text)
			{
				filter.display_text = '';
			}

			if(filter.elements && filter.display_text == li_text)
			{
				continue;
			}

			if(filter.elements)
			{
				var fe_count = filter.elements.length;
				for(var e_index = 0; e_index < fe_count; e_index++)
				{
					var element = filter.elements[e_index];
					element.firstChild.innerText = li_text;
				}
			}
			else
			{
				filter.elements = [];
				for(var e_index = 0; e_index < element_count; e_index++)
				{
					var element = elements[e_index];

					var li = document.createElement('li');
					var span = document.createElement('span');
					span.innerText = li_text;
					li.appendChild(span);
					var img = document.createElement('img');
					img.className = 'filter_delete';
					img.alt = '[X]';
					img.src = window.rent_objects.plugin_url + 'x.png';
					img.filter = filter;

					// W3C
					if(window.addEventListener) img.addEventListener('click', function () {window.rent_objects.remove_filter(img.filter);}, false);
					// IE
					else img.attachEvent('click', function () {window.rent_objects.remove_filter(img.filter);});

					li.appendChild(img);
					element.appendChild(li);
					filter.elements.push(li);
				}
			}
			filter.display_text == li_text
		}
	}
};

window.rent_objects.remove_filter = function(filter)
{
	var filters = window.rent_objects.filters;
	var filter_count = filters.length;
	if(!filter_count) return false;
	for(var f_index = 0; f_index < filter_count; f_index++)
	{
		var current_filter = filters[f_index];

		if(current_filter == filter)
		{
			if(filter.elements)
			{
				var fe_count = filter.elements.length;
				for(var e_index = fe_count - 1; e_index >= 0; e_index--)
				{
					var element = filter.elements[e_index];
					element.remove();
				}
				filter.elements = [];
			}

			filter_count--;
			if(f_index < filter_count)
			{
				filters[f_index] = filters[filter_count];
			}
			filters.length--;
			break;
		}
	}
	window.rent_objects.filter();
}

window.rent_objects.add_filter = function(filter)
{
	var filters = window.rent_objects.filters;
	switch(filter.type)
	{
		case 'type':
		{
			var filter_count = filters.length;
			for(var index = filter_count - 1; index >= 0; index--)
			{
				if(filters[index].type == filter.type)
				{
					window.rent_objects.remove_filter(filters[index]);
				}
			}
		}
	}
	filters.push(filter);
	window.rent_objects.filter();
};

window.rent_objects.hide_add_filter = function(filter)
{
	if(filter && filter.type && (filter.value || filter.method))
	{
		window.rent_objects.add_filter(filter);
	}
	var element = document.getElementById('rent_object_add_filter_dialog');
	element.style.display = 'none';
};

window.rent_objects.show_add_filter = function()
{
	var element = document.getElementById('rent_object_add_filter_dialog');
	if(!element)
	{
		element = document.createElement('div');
		element.id = 'rent_object_add_filter_dialog';

		var bg_element = document.createElement('div');
		// W3C
		if(window.addEventListener) bg_element.addEventListener('click', window.rent_objects.hide_add_filter, false);
		// IE
		else bg_element.attachEvent('click', window.rent_objects.hide_add_filter);

		element.appendChild(bg_element);

		var fg_element = document.createElement('div');
		element.appendChild(fg_element);
		document.body.appendChild(element);
		element = fg_element;
	}
	else
	{
		element.style.display = 'block';
		element = element.children[1];
	}
	var content = '<h3>Välj Filter</h3><fieldset id="rento_object_add_option"></fieldset><div>'

		+ '<fieldset><legend>Typ</legend>'
			+ '<input type="button" value="Stuga" onclick="window.rent_objects.hide_add_filter({type: &quot;type&quot;, value: 1})" />'
			+ '<input type="button" value="Lägerplats" onclick="window.rent_objects.hide_add_filter({type: &quot;type&quot;, value: 2})" />'
		+ '</fieldset>'

		+ '<fieldset><legend>Namn</legend>'
			+ '<input type="button" value="Namn innhåller" onclick="var f = {type: &quot;name&quot;, method: &quot;contains&quot;}; f.value = prompt(this.value); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Namn börjar med" onclick="var f = {type: &quot;name&quot;, method: &quot;begins&quot;}; f.value = prompt(this.value); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Namn slutar med" onclick="var f = {type: &quot;name&quot;, method: &quot;ends&quot;}; f.value = prompt(this.value); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Namn regexp" onclick="var f = {type: &quot;name&quot;, method: &quot;regexp&quot;}; f.value = prompt(this.value, &quot;^.*$&quot;); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
		+ '</fieldset>'

		+ '<fieldset><legend>Organisation</legend>'
			+ '<input type="button" value="Organisation innhåller" onclick="var f = {type: &quot;organisation&quot;, method: &quot;contains&quot;}; f.value = prompt(this.value); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Organisation börjar med" onclick="var f = {type: &quot;organisation&quot;, method: &quot;begins&quot;}; f.value = prompt(this.value); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Organisation slutar med" onclick="var f = {type: &quot;organisation&quot;, method: &quot;ends&quot;}; f.value = prompt(this.value); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Organisation regexp" onclick="var f = {type: &quot;organisation&quot;, method: &quot;regexp&quot;}; f.value = prompt(this.value, &quot;^.*$&quot;); if(f.value > &quot;&quot;) window.rent_objects.hide_add_filter(f);" />'
		+ '</fieldset>'

		+ '<fieldset><legend>Sovplatser</legend>'
			+ '<input type="button" value="Minst antal sovplatser" onclick="var f = {type: &quot;beds&quot;, method: &quot;more&quot;}; f.value = parseInt(prompt(this.value, 10)); if(f.value > 0) window.rent_objects.hide_add_filter(f);" />'
			+ '<input type="button" value="Max antal sovplatser" onclick="var f = {type: &quot;beds&quot;, method: &quot;less&quot;}; f.value = parseInt(prompt(this.value, 10)); if(f.value > 0) window.rent_objects.hide_add_filter(f);" />'
		+ '</fieldset>'

		+ '<fieldset><legend>Pris</legend>';
	var ps_count = rent_objects.price_scenarios.length;
	for(var ps_index = 0; ps_index < ps_count; ps_index++)
	{
		var senario = rent_objects.price_scenarios[ps_index];

		content += '<input type="button" value="' +
			senario.price_scenario_name +
			'" onclick="var f = {type: &quot;price&quot;, method: &quot;less&quot;, price_scenario_id: ' +
			senario.price_scenario_id +
			'}; f.value = parseInt(prompt(&quot;Max pris för &quot; + this.value + &quot;\\\\n&quot; + &quot;' +
			senario.price_scenario +
			'&quot;, ' +
			(100 * senario.days * senario.people) +
			')); if(f.value > 0) window.rent_objects.hide_add_filter(f);" />';
	}

	content += '</fieldset>'

		+ '<fieldset><legend>Övrigt</legend>';
	var s_count = rent_objects.settings.length;
	for(var s_index = 0; s_index < s_count; s_index++)
	{
		var setting = rent_objects.settings[s_index];

		content += '<input type="button" value="' +
			setting.name +
			'" onclick="window.rent_objects.show_add_filter_options(' +
			setting.option +
			')" /><br />';
	}

	content += '</fieldset>'
		+ '</div>';

	element.innerHTML = content;
};

window.rent_objects.show_add_filter_options = function(option_id)
{
	var element = document.getElementById('rento_object_add_option');

	var content = '<table><thead><tr><th>Visa</th><th>Alternativ</th><th>Dölj</th></tr></thead><tbody>';

	var options = false
	var settings_count = rent_objects.settings.length;
	for(var s_index = 0; s_index < settings_count; s_index++)
	{
		if(rent_objects.settings[s_index].option == option_id)
		{
			options = rent_objects.settings[s_index].options;
			break;
		}
	}
	if(!options)
	{
		return false;
	}
	var option_count = options.length;
	for(var o_index = 0; o_index < option_count; o_index++)
	{
		var option = options[o_index];
		content += '<tr>' +
			'<td><input type="radio" name="option[' + option_id + '][' + option.value + ']" data-option-value="' + option.value + '" value="1" /></td>' +
			'<td>' + option.name + '</td>' +
			'<td><input type="radio" name="option[' + option_id + '][' + option.value + ']" data-option-value="' + option.value + '" value="-1" /></td>' +
			'</tr>';
	}

	content += '<tfoot><tr>' +
		'<td><input type="radio" name="option[' + option_id + '][null]" value="1" /></td>' +
		'<td>(Ej valt)</td>' +
		'<td><input type="radio" name="option[' + option_id + '][null]" value="-1" /></td>' +
		'</tr></tfoot></table>' +
		'<input type="button" value="Lägg till filter" onclick="window.rent_objects.add_filter_options(' + option_id + ', &quot;rento_object_add_option&quot;)" />';

	element.innerHTML = content;
	element.style.display = 'block';
	element.nextElementSibling.style.display = 'none';
};

window.rent_objects.add_filter_options = function(option_id, parent_element)
{
	var show_values = [];
	var hide_values = [];
	var all_values = [];
	var null_value = 0;

	if((typeof parent_element) == 'string')
	{
		parent_element = document.getElementById(parent_element);
	}

	if(!parent_element)
	{
		return false;
	}

	var inputs = parent_element.getElementsByTagName('input');
	var input_count = inputs.length;
	for(var index = 0; index < input_count; index++)
	{
		var input = inputs[index];

		if(input.type != 'radio') continue;
		if(input.name.substr(0, 7) != 'option[') continue;
		var option_value = input.getAttribute('data-option-value');
		if(!option_value)
		{
			if(input.checked)
			{
				null_value = parseInt(input.value);
			}
			continue;
		}
		option_value = parseInt(option_value);
		if(!input.checked)
		{
			all_values.push(option_value);
			continue;
		}
		if(parseInt(input.value) > 0)
		{
			show_values.push(option_value);
		}
		else
		{
			hide_values.push(option_value);
		}
	}

	if(null_value > 0)
	{
		var a_count = all_values.length;
		var s_count = show_values.length;
		var h_count = hide_values.length;

		for(var a_index = 0; a_index < a_count; a_index++)
		{
			var found = false;
			var a = all_values[a_index];
			for(var s_index = 0; s_index < s_count; s_index++)
			{
				if(show_values[s_index] == a)
				{
					found = true;
					break;
				}
			}
			if(found)
			{
				continue;
			}
			for(var h_index = 0; h_index < h_count; h_index++)
			{
				if(hide_values[h_index] == a)
				{
					found = true;
					break;
				}
			}
			if(found)
			{
				continue;
			}

			hide_values.push(a);
			h_count++;
		}

		window.rent_objects.hide_add_filter({type: "option", option: option_id, method: "hide", values: hide_values});
	}
	else if(null_value < 0)
	{
		var a_count = all_values.length;
		var s_count = show_values.length;
		var h_count = hide_values.length;

		for(var a_index = 0; a_index < a_count; a_index++)
		{
			var found = false;
			var a = all_values[a_index];
			for(var s_index = 0; s_index < s_count; s_index++)
			{
				if(show_values[s_index] == a)
				{
					found = true;
					break;
				}
			}
			if(found)
			{
				continue;
			}
			for(var h_index = 0; h_index < h_count; h_index++)
			{
				if(hide_values[h_index] == a)
				{
					found = true;
					break;
				}
			}
			if(found)
			{
				continue;
			}

			show_values.push(a);
			s_count++;
		}

		window.rent_objects.hide_add_filter({type: "option", option: option_id, method: "show", values: show_values});
	}
	else if(show_values.length > 0)
	{
		window.rent_objects.hide_add_filter({type: "option", option: option_id, method: "show", values: show_values});
	}
	else if(hide_values.length > 0)
	{
		window.rent_objects.hide_add_filter({type: "option", option: option_id, method: "hide", values: hide_values});
	}
}

window.rent_objects.init = function()
{
	if(!window.rent_objects.filters)
	{
		window.rent_objects.filters = [];
	}
	if(!window.rent_objects.sort_order)
	{
		window.rent_objects.sort_order = 'name';
	}
	window.rent_objects.filter();
	window.rent_objects.add_listners();

	if(window.rent_objects.objects)
	{
		var map_wrapper = document.getElementsByClassName('map_wrapper')[0];
		if(map_wrapper)
		{
			// run filter, that sets visibility, and then runs update_map()
			map_wrapper.map_callback = window.rent_objects.filter;
		}

		var old_user_pos = localStorage.getItem('user_pos');
		if(old_user_pos)
		{
			window.rent_objects.user_pos = JSON.parse(old_user_pos);
			if(!window.rent_objects.user_pos)
			{
				old_user_pos = false;
			}
			else if(window.rent_objects.user_pos.ts < Date.now() - 60*60*1000)
			{
				old_user_pos = false;
			}
		}
		if(!old_user_pos)
		{
			navigator.geolocation.getCurrentPosition(function (position)
				{
					if(window.rent_objects.cmp_pos)
					{
						// todo, check if cmp-pos is user-pos
						if(window.rent_objects.cmp_pos.auto)
						{
							if(window.rent_objects.cmp_pos.marker)
							{
								window.rent_objects.cmp_pos.marker.setMap(null);
							}
							window.rent_objects.cmp_pos = false;
						}
					}
					window.rent_objects.user_pos = {lat: position.coords.latitude, lng: position.coords.longitude, ts: Date.now()};
					localStorage.setItem('user_pos', JSON.stringify(window.rent_objects.user_pos));
					window.rent_objects.filter();
				}
			);
		}
	}
};

window.rent_objects.add_listners = function()
{
	var elements = document.getElementsByClassName('rent_object_add_filters');
	var elements_count = elements.length;
	for(var index = 0; index < elements_count; index++)
	{
		var element = elements[index];

		// W3C
		if(window.addEventListener) element.addEventListener('click', window.rent_objects.show_add_filter, false);
		// IE
		else element.attachEvent('click', window.rent_objects.show_add_filter);
	}
};

// W3C
if(window.addEventListener) window.addEventListener('load', window.rent_objects.init, false);
// IE
else window.attachEvent('onload', window.rent_objects.init);
