@extends("plantilla.app")

@section("contenido")
<h2>Nueva creatividad</h2>
{{Form::open(["url"=>"admin/publicidad","method"=>"POST"])}}
@include("admin.Publicidad._form", ["creative" => null])
<div class="ln_solid"></div>
<div class="form-group">
    <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
        <a class="btn btn-primary" href="{{ url('admin/publicidad') }}">Cancelar</a>
        <button type="submit" class="btn btn-success">Guardar</button>
    </div>
</div>
{{Form::close()}}
@endsection
