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
</section>
@endsection
