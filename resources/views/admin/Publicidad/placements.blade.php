@extends("plantilla.app")

@section("contenido")
<section id="container">
    <div class="row">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <h2>Placements</h2>
            <p class="text-muted">La key identifica el slot en las vistas y la API: no se puede editar.</p>
        </div>
    </div>

    @foreach($placements as $placement)
    {{Form::open(["url"=>"admin/placements/$placement->key","method"=>"PUT"])}}
    <div class="row" style="border-bottom: 1px solid #e5e5e5; padding: 10px 0; align-items: center;">
        <div class="col-md-3 col-sm-3 col-xs-12">
            <label>Key</label>
            <input type="text" class="form-control" value="{{ $placement->key }}" disabled>
        </div>
        <div class="col-md-4 col-sm-4 col-xs-12">
            <label>Nombre</label>
            <input type="text" name="name" class="form-control" value="{{ $placement->name }}" maxlength="100">
        </div>
        <div class="col-md-2 col-sm-2 col-xs-12">
            <label>Max items</label>
            <input type="number" name="max_items" class="form-control" value="{{ $placement->max_items }}" min="1" max="10">
        </div>
        <div class="col-md-2 col-sm-2 col-xs-12">
            <label>Activo</label>
            <div class="checkbox">
                <input type="hidden" name="is_active" value="0">
                <label><input type="checkbox" name="is_active" value="1" @if($placement->is_active) checked @endif> Visible</label>
            </div>
        </div>
        <div class="col-md-1 col-sm-1 col-xs-12">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
        </div>
    </div>
    {{Form::close()}}
    @endforeach
</section>
@endsection
