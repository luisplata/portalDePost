@extends("plantilla.app")

@section("contenido")
@php
$statusLabels = ["draft" => "Borrador", "active" => "Activo", "paused" => "Pausado", "ended" => "Finalizado"];
$statusBadges = ["draft" => "default", "active" => "success", "paused" => "warning", "ended" => "danger"];
@endphp
<section id="container">
    <div class="row">
        <div class="col-md-8 col-sm-8 col-xs-12">
            <h2>Publicidad</h2>
            <p class="text-muted">
                Metricas de los ultimos 30 dias ({{ $from }} a {{ $to }}, hora local).
                Las creatividades finalizadas siguen listadas y conservan su historico.
            </p>
        </div>
        <div class="col-md-4 col-sm-4 col-xs-12">
            <a class="btn btn-default" href="{{ url('admin/placements') }}"><i class="fa fa-th-list"></i> Placements</a>
            <a class="btn btn-success" href="{{ url('admin/publicidad/create') }}"><i class="fa fa-plus"></i> Nueva publicidad</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Nombre</th>
                            <th>Placement</th>
                            <th>Estado</th>
                            <th>Ventana (hora local)</th>
                            <th>Prioridad</th>
                            <th>Impresiones 30d</th>
                            <th>Clicks 30d</th>
                            <th>CTR</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($creatives as $creative)
                        @php
                        $row = $metrics[$creative->id] ?? ["impressions" => 0, "clicks" => 0, "ctr" => 0.0];
                        $live = $creative->status === "active"
                            && (bool) $creative->placement_is_active
                            && ($creative->starts_at === null || $creative->starts_at <= now())
                            && ($creative->ends_at === null || $creative->ends_at >= now());
                        @endphp
                        <tr>
                            <td><code>{{ $creative->campaign_key }}</code></td>
                            <td>{{ $creative->name }}</td>
                            <td>{{ $creative->placement_name }}<br><small class="text-muted">{{ $creative->placement_key }}</small></td>
                            <td>
                                <span class="label label-{{ $statusBadges[$creative->status] ?? "default" }}">{{ $statusLabels[$creative->status] ?? $creative->status }}</span>
                                @if($live)
                                <span class="label label-success">Vigente</span>
                                @else
                                <span class="label label-default">No vigente</span>
                                @endif
                            </td>
                            <td>
                                @if($creative->starts_at_local === null && $creative->ends_at_local === null)
                                Sin limites
                                @else
                                {{ $creative->starts_at_local === null ? "sin inicio" : str_replace("T", " ", $creative->starts_at_local) }}
                                &rarr;
                                {{ $creative->ends_at_local === null ? "sin fin" : str_replace("T", " ", $creative->ends_at_local) }}
                                @endif
                            </td>
                            <td>{{ $creative->priority }}</td>
                            <td>{{ number_format($row["impressions"]) }}</td>
                            <td>{{ number_format($row["clicks"]) }}</td>
                            <td>{{ number_format($row["ctr"], 2) }}%</td>
                            <td><a href="{{ url('admin/publicidad/'.$creative->id.'/edit') }}"><i class="fa fa-pencil"></i> Editar</a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center">No hay creatividades. Crea la primera con "Nueva publicidad".</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $creatives->links("pagination::bootstrap-4") }}
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 col-sm-8 col-xs-12">
            <div class="x_panel" style="padding: 10px 15px;">
                <h2>Impresiones vs clicks <small id="ads-range">{{ $from }} a {{ $to }}</small></h2>
                <p id="ads-impressions-empty" class="text-muted" style="display: none;">Sin datos en el rango</p>
                <canvas id="ads-impressions-chart" height="120"></canvas>
            </div>
        </div>
        <div class="col-md-4 col-sm-4 col-xs-12">
            <div class="x_panel" style="padding: 10px 15px;">
                <h2>CTR por dia</h2>
                <p id="ads-ctr-empty" class="text-muted" style="display: none;">Sin datos en el rango</p>
                <canvas id="ads-ctr-chart" height="120"></canvas>
            </div>
        </div>
    </div>
</section>
@endsection

@section("scripts")
<script src="{{ asset('vendors/Chart.js/dist/Chart.min.js') }}"></script>
<script>
(function () {
    var endpoint = "{{ url('admin/publicidad/metrics') }}";
    var rangeLabel = document.getElementById("ads-range");
    var impressionsEmpty = document.getElementById("ads-impressions-empty");
    var ctrEmpty = document.getElementById("ads-ctr-empty");

    function showEmpty() {
        if (impressionsEmpty) { impressionsEmpty.style.display = "block"; }
        if (ctrEmpty) { ctrEmpty.style.display = "block"; }
    }

    if (typeof Chart === "undefined" || !window.fetch) {
        return;
    }

    fetch(endpoint, { headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(function (response) {
            return response.ok ? response.json() : null;
        })
        .then(function (data) {
            if (!data || !data.series || data.series.length === 0) {
                showEmpty();
                return;
            }

            var labels = data.series.map(function (day) { return day.date; });
            var impressions = data.series.map(function (day) { return day.impressions; });
            var clicks = data.series.map(function (day) { return day.clicks; });
            var ctr = data.series.map(function (day) { return day.ctr; });

            if (rangeLabel) { rangeLabel.textContent = data.from + " a " + data.to; }

            new Chart(document.getElementById("ads-impressions-chart"), {
                type: "line",
                data: {
                    labels: labels,
                    datasets: [
                        { label: "Impresiones", data: impressions, borderColor: "#26B99A", backgroundColor: "rgba(38,185,154,0.15)", fill: false, borderWidth: 2 },
                        { label: "Clicks", data: clicks, borderColor: "#03586A", backgroundColor: "rgba(3,88,106,0.15)", fill: false, borderWidth: 2 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { yAxes: [{ ticks: { beginAtZero: true } }] }
                }
            });

            new Chart(document.getElementById("ads-ctr-chart"), {
                type: "bar",
                data: {
                    labels: labels,
                    datasets: [{ label: "CTR %", data: ctr, backgroundColor: "#26B99A" }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: { yAxes: [{ ticks: { beginAtZero: true, callback: function (value) { return value + "%"; } } }] }
                }
            });
        })
        .catch(function () {
            // El fetch falla: la pagina (y el listado) siguen funcionando.
            showEmpty();
        });
})();
</script>
@endsection
