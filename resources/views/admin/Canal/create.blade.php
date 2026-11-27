@extends("plantilla.app")

@section("contenido")
{{ Form::open(["url" => "admin/canales", "method" => "POST"]) }}
@include("admin.Canal.form", ["canal" => null, "drivers" => $drivers, "mascara" => ""])
{{ Form::close() }}
@endsection
