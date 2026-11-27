@extends("plantilla.app")

@section("contenido")
@php
$badges = [
    'pendiente' => 'warning',
    'enviado' => 'success',
    'fallido' => 'danger',
    'incierto' => 'default',
];
$avisos = ['incierto' => true, 'enviado' => true];
@endphp
<div class="row">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <h2>Entregas a redes</h2>
        <p class="text-muted">Historial por producto y canal. Los errores se muestran redactados.</p>
    </div>
</div>

@if(session('mensaje'))
<div class="alert alert-success">{{ session('mensaje') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{ Form::open(["url" => "admin/publicaciones", "method" => "GET", "class" => "form-inline"]) }}
<div class="form-group">
    <label>Estado</label>
    <select name="estado" class="form-control">
        <option value="">Todos</option>
        @foreach(['pendiente', 'enviado', 'fallido', 'incierto'] as $estado)
        <option value="{{ $estado }}" @if($filtros['estado'] === $estado) selected @endif>{{ $estado }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Canal</label>
    <input type="number" name="canal" class="form-control" value="{{ $filtros['canal'] }}" min="1">
</div>
<div class="form-group">
    <label>Producto</label>
    <input type="number" name="producto" class="form-control" value="{{ $filtros['producto'] }}" min="1">
</div>
<div class="form-group">
    <label>Fecha</label>
    <input type="date" name="fecha" class="form-control" value="{{ $filtros['fecha'] }}">
</div>
<button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filtrar</button>
{{ Form::close() }}

<div class="table-responsive" style="margin-top: 15px;">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Id</th>
                <th>Producto</th>
                <th>Canal</th>
                <th>Estado</th>
                <th>Intentos</th>
                <th>Proximo intento</th>
                <th>Enviado</th>
                <th>HTTP</th>
                <th>Error</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($envios as $envio)
            <tr>
                <td>{{ $envio->id }}</td>
                <td>{{ $envio->producto ? $envio->producto->nombre : '#'.$envio->producto_id }}</td>
                <td>
                    {{ $envio->canal ? $envio->canal->nombre : 'Canal eliminado' }}
                    <br><small class="text-muted">{{ $envio->canal_tipo }}</small>
                </td>
                <td><span class="label label-{{ $badges[$envio->estado] ?? 'default' }}">{{ $envio->estado }}</span></td>
                <td>{{ $envio->intentos }}</td>
                <td>{{ $envio->proximo_intento_at ? $envio->proximo_intento_at->format('Y-m-d H:i') : '-' }}</td>
                <td>{{ $envio->enviado_at ? $envio->enviado_at->format('Y-m-d H:i') : '-' }}</td>
                <td>{{ $envio->http_status === null ? '-' : $envio->http_status }}</td>
                <td>
                    @if($envio->error)
                    <span title="{{ \Illuminate\Support\Str::limit($envio->error, 500) }}">
                        {{ \Illuminate\Support\Str::limit($envio->error, 500) }}
                    </span>
                    @else
                    -
                    @endif
                </td>
                <td>
                    @php($confirmar = isset($avisos[$envio->estado])
                        ? "return confirm('La entrega esta en estado ".$envio->estado."; reenviar puede crear un duplicado. ¿Continuar?')"
                        : null)
                    {{ Form::open(array_merge(
                        ["url" => "admin/publicaciones/".$envio->id."/reintentar", "method" => "POST", "style" => "display:inline"],
                        $confirmar ? ["onsubmit" => $confirmar] : []
                    )) }}
                    <button type="submit" class="btn btn-default btn-xs">
                        <i class="fa fa-refresh"></i> Reintentar
                    </button>
                    {{ Form::close() }}
                    @if(isset($avisos[$envio->estado]))
                    <br><small class="text-danger">Reintentar puede crear un posible duplicado.</small>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">No hay entregas con esos filtros.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $envios->links() }}
@endsection
