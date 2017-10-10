jQuery( document ).ready(function() {
	postJSON( 
  		"form_perfil",
       	URL_PROCESOS_PERFIL, 
       	function( data ) {
			jQuery("#btn_actualizar").val("Procesando...");
            jQuery("#btn_actualizar").attr("disabled", true);
			jQuery(".perfil_cargando").css("display", "inline-block");
       	}, 
       	function( data ) {

            console.log(data);

       		jQuery(".vlz_img_portada_valor").val("");

			jQuery("#btn_actualizar").val("Actualizar");
			jQuery("#btn_actualizar").attr("disabled", false);
            jQuery(".perfil_cargando").css("display", "none");
            location.href ="../../";
       	}
   	);
    initImg("portada");

    jQuery("#form_perfil [data-valid]").each(function( index ) {
        pre_validar( jQuery( this ) );
    });

    jQuery("#pet_type").on("change", function(e){
        var valor = jQuery("#pet_type").val();
        if( valor == "2605" ){
            var opciones = jQuery("#razas_perros").html();
            jQuery("#pet_breed").html(opciones);
        }
        if( valor == "2608" ){
            var opciones = jQuery("#razas_gatos").html();
            jQuery("#pet_breed").html(opciones);
        }
    });

    var minFecha = new Date();
    var min = minFecha.getFullYear();
    minFecha.setFullYear( parseInt(min)-30 );

    var maxFecha = new Date();
    maxFecha.setDate( parseInt(maxFecha.getDate()) + 1);

    jQuery("#pet_birthdate").datepick({
        dateFormat: 'dd/mm/yyyy',
        minDate: new Date().setYear(),
        maxDate: new Date().setYear(),
        onSelect: function(date1) {
            
        },
        yearRange: minFecha.getFullYear()+':'+maxFecha.getFullYear(),
        firstDay: 1,
        onmonthsToShow: [1, 1]
    });
});