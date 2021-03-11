@extends("plantilla.app")

@section('contenido')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    <div class="col-md-6 col-sm-12 col-xs-12">
        <canvas id="myChart"></canvas>
    </div>
    <div class="col-md-6 col-sm-12 col-xs-12">
        <canvas id="barGrafic"></canvas>
    </div>
    
    <div class="col-md-6 col-sm-12 col-xs-12">
        <canvas id="visitsvsclicks"></canvas>
    </div>
    
    <script>
        
        window.onload = function() {
            loadGrafica()
            LoadBarGrafic()
            VisitsVsClicks()
        }

        function loadGrafica() {
            var ctx = document.getElementById('myChart').getContext('2d');
            var server = []
            var labels = []
            var dataSets = []
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

        function LoadBarGrafic(){
            var ctx = document.getElementById('barGrafic').getContext('2d');
            var server = []
            var labels = []
            var clicks = []
            var visitas = []
            var tazaEfectividad = []
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var datosCargados = JSON.parse(this.responseText)
                    server = datosCargados
                    console.log(datosCargados)
                    server.forEach(function(a, b) {
                        labels.push(a.nombre)
                        tazaEfectividad.push(a.porcentaje_efectividad)
                    })
                    
                    var chart = new Chart(ctx, {
                        type: 'horizontalBar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label:"Taza de efectividad",
                                backgroundColor: '#00a950',
                                data: tazaEfectividad
                            }]
                        }
                    });
                }
            };
            xhttp.open("GET", "{{ url('api/TazaDeConvercion') }}", true);
            xhttp.send();
        }

        function VisitsVsClicks(){
            var ctx = document.getElementById('visitsvsclicks').getContext('2d');
            var server = []
            var labels = []
            var clicks = []
            var visitas = []
            var tazaEfectividad = []
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var datosCargados = JSON.parse(this.responseText)
                    server = datosCargados
                    console.log(datosCargados)
                    server.forEach(function(a, b) {
                        labels.push(a.nombre)
                        clicks.push(a.clicks)
                        visitas.push(a.visitas)
                    })
                    
                    var chart = new Chart(ctx, {
                        type: 'horizontalBar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label:"Visitas totales",
                                backgroundColor: '#8549ba',
                                data: visitas
                            },
                            {
                                label:"Clicks en el link",
                                backgroundColor: '#537bc4',
                                data: clicks
                            }]
                        }
                    });
                }
            };
            xhttp.open("GET", "{{ url('api/VisitsVsClicks') }}", true);
            xhttp.send();
        }

    </script>
@endsection
