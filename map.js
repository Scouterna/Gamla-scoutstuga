(function() {
	var init = function() {
		var map = document.getElementsByClassName('map_wrapper');
		if(map)
		{
			// Asynchronously Load the map API
			var script = document.createElement('script');
			script.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyAY50DkMkPv8SFSD4gz3y6UUC6qyIqQtmo&callback=init_map";
			document.body.appendChild(script);
		}
	};

	if(window.addEventListener) // W3C standard
	{
		window.addEventListener('load', init, false);
	}
	else if (window.attachEvent) // Microsoft
	{
		window.attachEvent('onload', init);
	}
})();

function init_map() {
console.log("inside init_map");
	var map_wrapper = document.getElementsByClassName('map_wrapper')[0];

	map_wrapper.map_bounds = new google.maps.LatLngBounds();
	var mapOptions = {
		mapTypeId: 'roadmap'
	};

	// Display a map on the page
	map_wrapper.map = new google.maps.Map(map_wrapper.firstChild, mapOptions);
	map_wrapper.map.setTilt(45);

	var markers_count = 0;

	if(map_wrapper.map_callback)
	{
		map_wrapper.map_callback();
	}
	else if(map_wrapper.getAttribute('data-markers'))
	{
		// TODO
		// https://wrightshq.com/playground/placing-multiple-markers-on-a-google-map-using-api-3/
	}
	else if(map_wrapper.getAttribute('data-lat'))
	{
		var name = map_wrapper.getAttribute('data-name');
		var adress = map_wrapper.getAttribute('data-address');
		var pos_lat = map_wrapper.getAttribute('data-lat');
		var pos_long = map_wrapper.getAttribute('data-long');
		var position = new google.maps.LatLng(pos_lat, pos_long);
		var icon = 'https://maps.google.com/mapfiles/ms/icons/green-dot.png';
		if(map_wrapper.getAttribute('data-type'))
		{
			var type = map_wrapper.getAttribute('data-type');
			if(type == '2')
			{
				icon = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
			}
		}

		map_wrapper.map_bounds.extend(position);
		marker = new google.maps.Marker({
			map: map_wrapper.map,
			title: name,
			position: position,
			icon: icon
		});
		markers_count++;
	}
	else if(map_wrapper.getAttribute('data-address'))
	{
		var name = map_wrapper.getAttribute('data-name');
		var address = map_wrapper.getAttribute('data-address');
		var icon = 'https://maps.google.com/mapfiles/ms/icons/green-dot.png';
		if(map_wrapper.getAttribute('data-type'))
		{
			var type = map_wrapper.getAttribute('data-type');
			if(type == '2')
			{
				icon = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
			}
		}

		var geocoder = new google.maps.Geocoder();
		geocoder.geocode({'address': address}, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
				map_wrapper.map.setCenter(results[0].geometry.location);
				var marker = new google.maps.Marker({
					map: map_wrapper.map,
					title: name,
					position: results[0].geometry.location,
					icon: icon
				});
			}
		});
		markers_count++;
	}

	// Automatically center the map fitting all markers on the screen
	if(markers_count)
	{
		map_wrapper.map.fitBounds(map_wrapper.map_bounds);

		// Override our map zoom level once our fitBounds function runs (Make sure it only runs once)
		var boundsListener = google.maps.event.addListener(map_wrapper.map, 'bounds_changed', function(event) {
			this.setZoom(12);
			google.maps.event.removeListener(boundsListener);
		});
	}
}
