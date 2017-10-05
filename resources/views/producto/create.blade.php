@extends("plantilla.app")

@section("contenido")
{{Form::open(["url"=>"producto","method"=>"POST", 'files' => true])}}

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">Nombre <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="nombre" id="first-name" required="required" class="form-control col-md-7 col-xs-12">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="last-name">URL <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" id="last-name" name="url" required="required" class="form-control col-md-7 col-xs-12">
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">Descripción <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <textarea class="form-control col-md-7 col-xs-12" name="descripcion" required></textarea>
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label for="middle-name" class="control-label col-md-3 col-sm-3 col-xs-12">Categoria </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <select class="form-control col-md-7 col-xs-12" name="categoria_id">
        @foreach($categorias as $categoria)
            <option value="{{$categoria->id}}">{{$categoria->nombre}}</option>
        @endforeach
        </select>
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="first-name">Imagen <span class="required">*</span>
    </label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="file" name="imagen" class="form-control col-md-7 col-xs-12" />
    </div>
</div>

<div class="clearfix"></div>
<div class="ln_solid"></div>
<div class="form-group">
    <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
        <button type="reset" class="btn btn-primary">Cancel</button>
        <button type="submit" class="btn btn-success">Submit</button>
    </div>
</div>


{{Form::close()}}
@endsection