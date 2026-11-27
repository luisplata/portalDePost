@extends("plantilla.app")

@section("contenido")
{{ Form::open(["url" => "admin/canales/".$canal->id, "method" => "PUT"]) }}
@include("admin.Canal.form", ["canal" => $canal, "drivers" => $drivers, "mascara" => $mascara])
{{ Form::close() }}
@endsection
