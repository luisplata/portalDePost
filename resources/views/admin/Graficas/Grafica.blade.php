@extends("plantilla.app")

@section('contenido')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    <div class="col-md-6 col-sm-12 col-xs-12">
        <canvas id="myChart"></canvas>
    </div>
    
    <script>
        var ctx = document.getElementById('myChart').getContext('2d');
        var server = []
        var labels = []
        var dataSets = []
        window.onload = function() {
            loadGrafica()
        }

        function loadGrafica() {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var datosCargados = JSON.parse(this.responseText)
                    server = datosCargados
                    server.forEach(function(a, b) {
                        labels.push(a.date_of_day)
                        dataSets.push(a.count_of_day)
                    })
                    var chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label:"visitas por día",
                                data: dataSets
                            }]
                        }
                    });
                }
            };
            xhttp.open("GET", "{{ url('api/grafica') }}", true);
            xhttp.send();
        }

    </script>
@endsection
