<script>
    (function () {
        var vehicleRequest;
        var initialVehicle = @json(isset($service) ? (string) $service->vehicle : '');
        var $mileage = $('#service_mileage');
        $(document).on('change', '#vehicle', function () {
            var selected = this.options[this.selectedIndex];
            $mileage.val(selected ? ($(selected).attr('data-mileage') || '') : '').prop('disabled', !this.value);
        });
        $('#client_id').on('change', function () {
            if (vehicleRequest) vehicleRequest.abort();
            var clientId = $(this).val();
            var $vehicle = $('<select>', {class: 'form-control select2 vehicle', id: 'vehicle', name: 'vehicle'});
            $('.vehicle_div').empty().append($vehicle);
            $mileage.val('').prop('disabled', true);
            if (!clientId) {
                $vehicle.append(new Option(@json(__('Select Vehicle')), ''));
                select2();
                return;
            }
            vehicleRequest = $.get(@json(route('client.vehicle', ':id')).replace(':id', encodeURIComponent(clientId)), function (data) {
                $.each(data, function (_, vehicle) {
                    var option = new Option(vehicle.name, vehicle.id, false, String(vehicle.id) === initialVehicle);
                    $(option).attr('data-mileage', vehicle.mileage == null ? '' : vehicle.mileage);
                    $vehicle.append(option);
                });
                initialVehicle = '';
                if (!data.length) $vehicle.append(new Option(@json(__('Select Vehicle')), ''));
                select2();
                $vehicle.trigger('change');
            });
        });
        if ($('#client_id').val()) $('#client_id').trigger('change');
    })();
</script>
