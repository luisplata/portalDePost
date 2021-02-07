@extends("plantilla.app")

@section("contenido")
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">Nombre <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="nombre" id="first-name" required="required" class="form-control col-md-7 col-xs-12" value="{{$producto->nombre}}" readonly="readonly">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group ">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">Estado <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="email" id="last-name" name="email" required="required" class="form-control col-md-7 col-xs-12" value="{{$producto->estado==1?"Activo":"Inactivo"}}" readonly="readonly">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group hidden">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">Descripción <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="email" id="last-name" name="email" required="required" class="form-control col-md-7 col-xs-12" value="{{$producto->descripcion}}" readonly="readonly">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">URL <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="email" id="last-name" name="email" required="required" class="form-control col-md-7 col-xs-12" value="{{$producto->url}}" readonly="readonly">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label for="middle-name" class="control-label col-md-3 col-sm-3 col-xs-12">Categoria </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input id="middle-name" class="form-control col-md-7 col-xs-12" type="tel" name="telefono" value="{{$producto->categoria->nombre}}" readonly="readonly">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label for="middle-name" class="control-label col-md-3 col-sm-3 col-xs-12">imagen </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <img src="{{$producto->imagen}}" />
    </div>
</div>
<div class="clearfix"></div>
<div class="ln_solid"></div>
<div class="form-group">
    <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
        <a class="btn btn-success" href="{{url('admin/producto')}}">Regresar</a>
    </div>
</div>
@endsection