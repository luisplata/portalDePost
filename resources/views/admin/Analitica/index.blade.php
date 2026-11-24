@extends("plantilla.app")

@section("contenido")
<section id="container">
    <div class="row">
        <div class="col-md-8 col-sm-8 col-xs-12">
            <h2>Analitica de publicaciones</h2>
            <p class="text-muted">
                Publicaciones mas populares y conversion visita &rarr; click. El dia operativo es
                America/Bogota y el rango se expresa como fecha local.
            </p>
        </div>
        <div class="col-md-4 col-sm-4 col-xs-12 text-right">
            <a class="btn btn-default" href="{{ url('admin/graficas') }}">
                <i class="fa fa-line-chart"></i> Graficas de visitas
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <form class="form-inline" method="GET" action="{{ url('admin/analitica') }}" id="analitica-filters">
                <div class="form-group">
                    <label for="from">Desde</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                </div>
                <div class="form-group">
                    <label for="to">Hasta</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                </div>
                <div class="form-group">
                    <label for="metric">Metrica</label>
                    <select class="form-control" id="metric" name="metric">
                        <option value="uv" {{ $metric === 'uv' ? 'selected' : '' }}>Visitantes unicos (UV)</option>
                        <option value="pageviews" {{ $metric === 'pageviews' ? 'selected' : '' }}>Pageviews</option>
                    </select>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="include_inactive" value="1" {{ $includeInactive ? 'checked' : '' }}>
                        Incluir inactivos
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Aplicar</button>

                @foreach ($shortcuts as $days => $shortcut)
                    <a class="btn btn-default btn-xs" href="{{ url('admin/analitica').'?'.http_build_query([
                        'from' => $shortcut['from'],
                        'to' => $shortcut['to'],
                        'metric' => $metric,
                        'include_inactive' => $includeInactive ? 1 : 0,
                    ]) }}">{{ $days }} dias</a>
                @endforeach
            </form>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col-md-12 col-sm-12 col-xs-12"
             id="analitica-panel"
             data-endpoint="{{ url('admin/analitica/metricas').'?'.http_build_query([
                 'from' => $from,
                 'to' => $to,
                 'metric' => $metric,
                 'include_inactive' => $includeInactive ? 1 : 0,
             ]) }}"
             data-content-base="{{ url('content') }}/">

            <div class="alert alert-danger" id="analitica-error" style="display: none;">
                No se pudieron cargar las metricas
                <button type="button" class="btn btn-default btn-xs" id="analitica-retry">Reintentar</button>
            </div>

            <div id="analitica-content">
                <div class="row">
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="x_panel" style="padding: 10px 15px;">
                            <h2>Pageviews</h2>
                            <p id="analitica-total-visits">&mdash;</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="x_panel" style="padding: 10px 15px;">
                            <h2>Visitantes unicos</h2>
                            <p id="analitica-total-uv">&mdash;</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="x_panel" style="padding: 10px 15px;">
                            <h2>Clicks</h2>
                            <p id="analitica-total-clicks">&mdash;</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="x_panel" style="padding: 10px 15px;">
                            <h2>Conversion principal</h2>
                            <p id="analitica-total-conversion">&mdash;</p>
                        </div>
                    </div>
                </div>

                <p id="analitica-coverage" class="text-muted"></p>
                <p id="analitica-empty" class="text-muted" style="display: none;">Sin datos en el rango</p>

                <div class="row">
                    <div class="col-md-8 col-sm-8 col-xs-12">
                        <div class="x_panel" style="padding: 10px 15px;">
                            <h2>Pageviews vs clicks</h2>
                            <canvas id="analitica-visits-chart" height="140"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-4 col-xs-12">
                        <div class="x_panel" style="padding: 10px 15px;">
                            <h2>UV por dia</h2>
                            <canvas id="analitica-uv-chart" height="140"></canvas>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="analitica-ranking">
                        <thead>
                            <tr>
                                <th>Puesto</th>
                                <th>Publicacion</th>
                                <th id="analitica-uv-header">UV</th>
                                <th>Pageviews</th>
                                <th>Clicks</th>
                                <th>Conversion principal</th>
                                <th>Conversion por pageview</th>
                                <th>Clicks por visitante</th>
                            </tr>
                        </thead>
                        <tbody id="analitica-ranking-body"></tbody>
                    </table>
                </div>

                <nav>
                    <ul class="pagination" id="analitica-pagination"></ul>
                </nav>

                <div class="x_panel" id="analitica-legacy" style="padding: 10px 15px;">
                    <h2>Acumulado historico</h2>
                    <p class="text-muted">
                        desde siempre, sin dedupe ni filtro de bots. visit_posts cuenta pageviews.
                        Este bloque no se suma a las metricas del rango.
                    </p>
                    <p>
                        Visitas: <span id="analitica-legacy-visits">&mdash;</span> &middot;
                        Clicks: <span id="analitica-legacy-clicks">&mdash;</span> &middot;
                        Conversion por pageview: <span id="analitica-legacy-conversion">&mdash;</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section("scripts")
<script src="{{ asset('vendors/Chart.js/dist/Chart.min.js') }}"></script>
<script>
@verbatim
(function () {
    var panel = document.getElementById('analitica-panel');

    if (!panel || !window.fetch) {
        return;
    }

    var base = panel.getAttribute('data-endpoint');
    var contentBase = panel.getAttribute('data-content-base');
    var chartsRendered = false;

    function num(value) {
        return value === null || typeof value === 'undefined' ? 'sin dato' : value;
    }

    function pct(value) {
        return value === null || typeof value === 'undefined'
            ? 'sin dato'
            : Number(value).toFixed(2) + ' %';
    }

    function pctLabel(value) {
        return value === null || typeof value === 'undefined' ? 'sin dato' : Number(value).toFixed(2);
    }

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value === null || typeof value === 'undefined' ? '' : String(value);
        return div.innerHTML;
    }

    function gap(value) {
        return value === null || typeof value === 'undefined' ? null : value;
    }

    function maxDate(a, b) {
        if (!a) {
            return b;
        }
        if (!b) {
            return a;
        }
        return a > b ? a : b;
    }

    function rowHtml(item, index, data) {
        var position = (data.ranking.current_page - 1) * data.ranking.per_page + index + 1;

        return '<tr>'
            + '<td>' + position + '</td>'
            + '<td><a href="' + contentBase + esc(item.slug) + '">' + esc(item.nombre) + '</a></td>'
            + '<td>' + num(item.unique_visits) + '</td>'
            + '<td>' + num(item.visits) + '</td>'
            + '<td>' + num(item.clicks) + '</td>'
            + '<td>' + pct(item.conversion_principal) + '</td>'
            + '<td>' + pct(item.conversion_by_pageview) + '</td>'
            + '<td>' + num(item.clicks_per_visitor) + '</td>'
            + '</tr>';
    }

    function renderPagination(ranking) {
        var container = document.getElementById('analitica-pagination');
        var previous = ranking.current_page > 1 ? ranking.current_page - 1 : null;
        var next = ranking.current_page < ranking.last_page ? ranking.current_page + 1 : null;

        container.innerHTML = '<li class="' + (previous ? '' : 'disabled') + '">'
            + '<a href="#" data-page="' + (previous || 1) + '">Anterior</a></li>'
            + '<li class="disabled"><a href="#">Pagina ' + ranking.current_page
            + ' de ' + ranking.last_page + ' (' + ranking.total + ')</a></li>'
            + '<li class="' + (next ? '' : 'disabled') + '">'
            + '<a href="#" data-page="' + (next || ranking.current_page) + '">Siguiente</a></li>';
    }

    function renderCoverage(data) {
        var notes = [];

        if (data.metric === 'pageviews') {
            if (data.coverage.identities_from) {
                notes.push('UV disponible desde ' + data.coverage.identities_from);
            } else {
                notes.push('La captura de analitica empieza con este cambio');
            }
        }
        if (data.uv_approximate) {
            notes.push('Suma de unicos diarios (aproximado)');
        }
        if (data.coverage.clicks_from) {
            notes.push('Clicks disponibles desde ' + data.coverage.clicks_from);
        }

        if (data.coverage.identities_from && data.coverage.clicks_from) {
            notes.push('Conversion por visitante disponible desde ' + maxDate(data.coverage.identities_from, data.coverage.clicks_from));
        }

        document.getElementById('analitica-coverage').textContent = notes.join(' \u00b7 ');
    }

    function renderCharts(data) {
        if (typeof Chart === 'undefined' || chartsRendered) {
            return;
        }

        var labels = data.series.map(function (day) { return day.date; });

        new Chart(document.getElementById('analitica-visits-chart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pageviews',
                        data: data.series.map(function (day) { return gap(day.visits); }),
                        borderColor: '#26B99A',
                        backgroundColor: 'rgba(38,185,154,0.15)',
                        fill: false,
                        borderWidth: 2,
                    },
                    {
                        label: 'Clicks',
                        data: data.series.map(function (day) { return gap(day.clicks); }),
                        borderColor: '#03586A',
                        backgroundColor: 'rgba(3,88,106,0.15)',
                        fill: false,
                        borderWidth: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { yAxes: [{ ticks: { beginAtZero: true } }] }
            }
        });

        new Chart(document.getElementById('analitica-uv-chart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'UV por dia',
                        data: data.series.map(function (day) { return gap(day.unique_visits); }),
                        borderColor: '#8549ba',
                        backgroundColor: 'rgba(133,73,186,0.15)',
                        fill: false,
                        borderWidth: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { yAxes: [{ ticks: { beginAtZero: true } }] }
            }
        });

        chartsRendered = true;
    }

    function showError() {
        document.getElementById('analitica-error').style.display = 'block';
        document.getElementById('analitica-content').style.display = 'none';
    }

    function hideError() {
        document.getElementById('analitica-error').style.display = 'none';
        document.getElementById('analitica-content').style.display = '';
    }

    function render(data) {
        hideError();

        document.getElementById('analitica-total-visits').textContent = num(data.totals.visits);
        document.getElementById('analitica-total-uv').textContent = num(data.totals.unique_visits);
        document.getElementById('analitica-total-clicks').textContent = num(data.totals.clicks);
        document.getElementById('analitica-total-conversion').textContent = pctLabel(data.totals.conversion_principal);

        document.getElementById('analitica-legacy-visits').textContent = num(data.legacy.visits);
        document.getElementById('analitica-legacy-clicks').textContent = num(data.legacy.clicks);
        document.getElementById('analitica-legacy-conversion').textContent = pctLabel(data.legacy.conversion_by_pageview);

        document.getElementById('analitica-uv-header').textContent = data.metric === 'uv'
            ? (data.uv_approximate ? 'Suma de unicos diarios' : 'UV')
            : 'Pageviews';

        document.getElementById('analitica-ranking-body').innerHTML = data.ranking.data.map(function (item, index) {
            return rowHtml(item, index, data);
        }).join('');

        var isEmpty = data.warnings.indexOf('no_data') !== -1 || data.ranking.total === 0;
        document.getElementById('analitica-empty').style.display = isEmpty ? 'block' : 'none';

        renderCoverage(data);
        renderCharts(data);
        renderPagination(data.ranking);
    }

    function load(page) {
        fetch(base + '&page=' + page, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('metrics_unavailable');
                }

                return response.json();
            })
            .then(render)
            .catch(showError);
    }

    document.getElementById('analitica-pagination').addEventListener('click', function (event) {
        var link = event.target.closest('a[data-page]');

        if (!link) {
            return;
        }

        event.preventDefault();
        load(link.getAttribute('data-page'));
    });

    document.getElementById('analitica-retry').addEventListener('click', function () {
        load(1);
    });

    load(1);
})();
@endverbatim
</script>
@endsection
