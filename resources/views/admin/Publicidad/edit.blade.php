@extends("plantilla.app")

@section("contenido")
<h2>Editar creatividad</h2>
{{Form::open(["url"=>["admin/publicidad",$creative->id],"method"=>"PUT"])}}
@include("admin.Publicidad._form", ["creative" => $creative])
<div class="ln_solid"></div>
<div class="form-group">
    <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
        <a class="btn btn-primary" href="{{ url('admin/placements') }}">Cancelar</a>
        <button type="submit" class="btn btn-success">Guardar</button>
    </div>
</div>
{{Form::close()}}

{{-- Archivar = status ended: conserva la fila y sus metricas (D-03). --}}
{{Form::open(["url"=>["admin/publicidad",$creative->id],"method"=>"DELETE","onsubmit"=>"return confirm('Finalizar la creatividad? Dejara de mostrarse y conservara sus metricas.')"])}}
<button type="submit" class="btn btn-danger"><i class="fa fa-stop"></i> Finalizar</button>
{{Form::close()}}
@endsection
