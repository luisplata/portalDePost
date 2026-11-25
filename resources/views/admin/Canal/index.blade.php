@extends("plantilla.app")

@section("contenido")
@php
$badges = [
    'ok' => 'success',
    'invalido_credencial' => 'danger',
    'invalido_config' => 'warning',
    'tipo_desconocido' => 'default',
];
@endphp
<div class="row">
    <div class="col-md-8 col-sm-8 col-xs-12">
        <h2>Canales de publicacion</h2>
        <p class="text-muted">
            Telegram y Discord se administran aca. Las credenciales nunca se muestran completas.
        </p>
    </div>
    <div class="col-md-4 col-sm-4 col-xs-12 text-right">
        <a class="btn btn-success" href="{{ url('admin/canales/create') }}">
            <i class="fa fa-plus"></i> Nuevo canal
        </a>
        <a class="btn btn-default" href="{{ url('admin/publicaciones') }}">
            <i class="fa fa-list"></i> Entregas
        </a>
    </div>
</div>

@if(session('mensaje'))
<div class="alert alert-success">{{ session('mensaje') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Id</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Habilitado</th>
                <th>Vigente desde</th>
                <th>Estado</th>
                <th>Verificado</th>
                <th>Credencial</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($canales as $canal)
            <tr>
                <td>{{ $canal->id }}</td>
                <td>{{ $canal->nombre }}</td>
                <td>{{ $etiquetas[$canal->tipo] ?? $canal->tipo }}</td>
                <td>
                    @if($canal->habilitado)
                    <span class="label label-success">Si</span>
                    @else
                    <span class="label label-default">No</span>
                    @endif
                </td>
                <td>{{ $canal->vigente_desde ? $canal->vigente_desde->format('Y-m-d H:i') : '-' }}</td>
                <td>
                    <span class="label label-{{ $badges[$canal->estado] ?? 'default' }}">{{ $canal->estado }}</span>
                    @if($canal->estado_detalle)
                    <br><small class="text-muted">{{ $canal->estado_detalle }}</small>
                    @endif
                </td>
                <td>{{ $canal->estado_verificado_at ? $canal->estado_verificado_at->format('Y-m-d H:i') : '-' }}</td>
                <td><code>{{ \App\Services\Publicacion\Secretos::mascaraCanal($canal) }}</code></td>
                <td>
                    <a class="btn btn-primary btn-xs" href="{{ url('admin/canales/'.$canal->id.'/edit') }}">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                    {{ Form::open(["url" => "admin/canales/".$canal->id."/estado", "method" => "POST", "style" => "display:inline"]) }}
                    <input type="hidden" name="habilitado" value="{{ $canal->habilitado ? 0 : 1 }}">
                    <button type="submit" class="btn btn-default btn-xs">
                        {{ $canal->habilitado ? 'Deshabilitar' : 'Habilitar' }}
                    </button>
                    {{ Form::close() }}
                    {{ Form::open(["url" => "admin/canales/".$canal->id."/probar", "method" => "POST", "style" => "display:inline"]) }}
                    <button type="submit" class="btn btn-info btn-xs"><i class="fa fa-paper-plane"></i> Probar</button>
                    {{ Form::close() }}
                    {{ Form::open(["url" => "admin/canales/".$canal->id, "method" => "DELETE", "style" => "display:inline"]) }}
                    <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> Eliminar</button>
                    {{ Form::close() }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No hay canales. Crea el primero con "Nuevo canal".</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $canales->links() }}
@endsection
