var is_parcel_pickup = false;

var map = null;

var points = [];

var infowindow;
var svgMarker = null;

var lang = document.documentElement.lang;

var DKLOCALE = {
  "Choose": "Vælg",
  'Cannot find pick up points' : 'Kan ikke finde afhentningspunkter',
  'Error has occurred make sure zip code is set' : 
  'Der er opstået en fejl, sørg for at postnummer er set'
};

function translate_text(text){
    if(lang == 'da_DK' || lang == 'dk' || lang == 'da')
        return DKLOCALE[text] ? DKLOCALE[text] : text;

    return text;
}

function choose_pickup_point(point_id) {
    var pickup_point = points.find(row => row.ID == point_id);

    jQuery('#parcel_pickup_id').val(point_id);

    jQuery('#parcel_pickup_place_description').val(pickup_point.Name + ', ' + pickup_point.Address + ', ' + pickup_point.Zipcode + ' ' + pickup_point.City + ', '+ pickup_point.Country + ', ID: ' + point_id);

    var contentString =
        '<strong>' + pickup_point.Name + '</strong><br/>' +
        pickup_point.Address + '<br/>' +
        pickup_point.City + ' ' + pickup_point.Zipcode + '<br/>' +
        pickup_point.Country;

    jQuery('#parcel_pickup_chosen_wrap').show();

    jQuery('.parcel_pickup_chosen_description').html(contentString);

    jQuery('form.checkout button[type="submit"]').prop('disabled', false);

    jQuery('.lss-wc-pickup-point-shipping-wrap').fadeOut();
}

(function ($) {

    check_shipping_method();
    
    $(document).on('updated_checkout', function (e, data) {
        check_carrier_type();
        
        if(!$('.lss-parcel-pickup-postalcode-input').hasClass('touched')) {
            $('.lss-parcel-pickup-postalcode-input').val($('#billing_postcode').val());
        }    
    });

    var map_holder_identifier = 'lss_parcel_pickup_gmap';

    var trigger_holder = $('.logisnap-pickup-point-trigger-holder');

    trigger_holder.hide();

    function check_carrier_type()
    {
        $.post('/wp-admin/admin-ajax.php', {
            action: 'logisnap_carrier_type',
        }, function(response)
        {
            $('form.checkout button[type="submit"]').prop('disabled', false);
    
            $('#parcel_pickup_id').val('');
            $('#parcel_pickup_place_description').val('');
            jQuery('#parcel_pickup_chosen_wrap').hide();
    
            is_parcel_pickup = false;
        
            trigger_holder.hide();
        
            if(typeof google !== 'undefined'){
                set_google_map();
            }
                    
            if(response === 'service_point') {
                trigger_holder.show();
                is_parcel_pickup = true;
                $('form.checkout button[type="submit"]').prop('disabled', true);
        
                    return;
                }  
            });
    }

    function check_shipping_method() 
    {
        $.post('/wp-admin/admin-ajax.php', {
            action: 'logisnap_google_key',
        }, function(key_response)
        {
            key_response = key_response.slice(0, -1);

            if(document.getElementById('logisnap_google_key_id') === null){

                let script = document.createElement('script');
                script.id = 'logisnap_google_key_id';
                
                if(key_response != "")
                    script.src = key_response;
                else{
                    script.src = 'https://maps.googleapis.com/maps/api/js';
                }
                document.getElementsByTagName('head')[0].appendChild(script);    
            }
            

            return key_response;
        });    
    }


    $( 'form.checkout' ).on( 'checkout_place_order', function() {
        if (is_parcel_pickup && $('#parcel_pickup_id').val().length <= 0) {
            return false;
        }

        // allow the submit AJAX call
        return true;
    });

    $(document).on('change', '.lss-parcel-pickup-postalcode-input', function() {
        $(this).addClass('touched');
        get_pickup_points();
    });

    $(document).on('input','#billing_postcode', function() {
        if(!$('.lss-parcel-pickup-postalcode-input').hasClass('touched')) {
            $('.lss-parcel-pickup-postalcode-input').val($(this).val());
        }
    });

    $(document).on('click','.lss-wc-pickup-point-shipping-close', function(e) {
        e.preventDefault();

        $('.lss-wc-pickup-point-shipping-wrap').fadeOut();
    });


    function set_google_map(){
        map = new google.maps.Map(document.getElementById(map_holder_identifier), {
            center: { lat: 56.0647094, lng: 10.9502587 },
            zoom: 12,
            mapTypeControl: false,
            scaleControl: false,
            gestureHandling: 'greedy',
            zoomControl: false,
            streetViewControl: false
        });
        var svgMarker = {
            path: "M10.453 14.016l6.563-6.609-1.406-1.406-5.156 5.203-2.063-2.109-1.406 1.406zM12 2.016q2.906 0 4.945 2.039t2.039 4.945q0 1.453-0.727 3.328t-1.758 3.516-2.039 3.070-1.711 2.273l-0.75 0.797q-0.281-0.328-0.75-0.867t-1.688-2.156-2.133-3.141-1.664-3.445-0.75-3.375q0-2.906 2.039-4.945t4.945-2.039z",
            fillColor: "#429ecc",
            fillOpacity: 1,
            strokeWeight: 0,
            rotation: 0,
            scale: 2,
            anchor: new google.maps.Point(15, 30),
        };
    }


    function get_pickup_points() {
        var zipcode = jQuery('.lss-parcel-pickup-postalcode-input').val();
        var street =  jQuery('#billing_address_1').val();

        if(street == null){
            street = "";
        }

        $('.lss-map-loader').show();

        $.post('/wp-admin/admin-ajax.php', {
            action: 'logisnap_carrier_pickup_points',
            zipcode, street
        }, function(response) {
            var rows = JSON.parse(response);
            
            $('.lss-pickup-point-list').html('');

            points = rows;
            if(points.length == 0)
            {
                var cantFindString ='<h2>'+translate_text('Cannot find pick up points')+'</h2>';

                $('.lss-pickup-point-list').append(cantFindString);
              
            }

            if(response.includes('An error has occurred'))
            {
                var cantFindString ='<h2>'+translate_text('Error has occurred make sure zip code is set')+'</h2>';

                $('.lss-pickup-point-list').append(cantFindString);
                return;
            } 


            $.each( rows, function( index, row ){
                var lat = parseFloat(row.Latitude);
                var lng = parseFloat(row.Longitude);

                if(index === 0) {

                    if(map === null){
                        set_google_map();
                        if(map !== null)
                            map.setCenter({ lat, lng }); 
                    }else{
                        map.setCenter({ lat, lng });
                    }
                }

                      var contentString =
                '<div id="content">' +
                '<div id="siteNotice">' +
                "</div>" +
                '<h4 id="firstHeading" class="firstHeading">'+ row.Name + '</h4>' +
                '<div id="bodyContent">' +
                "<p>" + row.Address + ', ' + row.City + '</p>' +
                '<p><a class="button" onclick="choose_pickup_point('+row.ID+')">'+translate_text('Choose')+'</a></p>' +
                "</div>" +
                "</div>";

                var listContent = 
                '<div class="lss-selected-pickup-point-info">' +
                '<h4 id="firstHeading" class="firstHeading">'+ row.Company_Name + '</h4>' +
                '<div id="bodyContent">' +
                "<p>" + row.Address + ', ' + row.City + '</p>' +
                '<p><a class="button" onclick="choose_pickup_point('+row.ID+')">'+translate_text('Choose')+'</a></p>' +
                '</div>';

                $('.lss-pickup-point-list').append(listContent);

                infowindow = new google.maps.InfoWindow();

                var marker =  new google.maps.Marker ({
                    position: { lat, lng },
                    map: map,
                    icon: svgMarker,
                });

                marker.addListener("click", () => {
                    infowindow.setContent(contentString);
                    infowindow.open({
                      anchor: marker,
                      map,
                      shouldFocus: true,
                    });
                });
            });

            $('.lss-map-loader').hide();
        });

        $('.lss-wc-pickup-point-shipping-wrap').fadeIn();
    }

    $(document).on('click','.lss-pickup-point-trigger-button', function(e) {
        e.preventDefault();

        get_pickup_points();
    });
}(jQuery));