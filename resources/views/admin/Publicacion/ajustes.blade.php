@extends("plantilla.app")

@section("contenido")
<h2>Ajustes de publicacion</h2>

@if(session('mensaje'))
<div class="alert alert-success">{{ session('mensaje') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{ Form::open(["url" => "admin/publicaciones/ajustes", "method" => "PUT"]) }}
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Publicaciones</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <select name="publicaciones_enabled" class="form-control">
            <option value="0" @if(!$activo) selected @endif>Apagadas</option>
            <option value="1" @if($activo) selected @endif>Encendidas</option>
        </select>
        <span class="text-muted">El cambio aplica en la corrida siguiente, sin deploy ni reinicio.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Corte global</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="datetime-local" name="publicaciones_desde" class="form-control"
               value="{{ old('publicaciones_desde', $desde ? $desde->format('Y-m-d\TH:i') : '') }}">
        <span class="text-danger">Mover el corte hacia atras puede generar un lote grande de envios.</span>
        <span class="text-muted d-block">Con el interruptor encendido el corte es obligatorio. Apagar no borra el corte.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="ln_solid"></div>
<div class="form-group">
    <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
        <a class="btn btn-primary" href="{{ url('admin/publicaciones') }}">Volver a entregas</a>
        <button type="submit" class="btn btn-success">Guardar ajustes</button>
    </div>
</div>
{{ Form::close() }}

<h4>Ultima corrida</h4>
@if($resumen['corrida_at'] === null)
<p class="text-muted">Sin ejecuciones registradas.</p>
@else
<table class="table table-bordered" style="max-width: 600px;">
    <tbody>
        <tr><th>Fecha</th><td>{{ $resumen['corrida_at']->format('Y-m-d H:i:s') }} UTC</td></tr>
        <tr><th>Sembradas</th><td>{{ $resumen['sembradas'] }}</td></tr>
        <tr><th>Enviadas</th><td>{{ $resumen['enviadas'] }}</td></tr>
        <tr><th>Fallidas</th><td>{{ $resumen['fallidas'] }}</td></tr>
        <tr><th>Inciertas</th><td>{{ $resumen['inciertas'] }}</td></tr>
        <tr><th>Duracion</th><td>{{ $resumen['duracion_ms'] }} ms</td></tr>
    </tbody>
</table>
@endif
@endsection
