@extends("plantilla.app")

@section("contenido")
<div class="col-md-12 col-sm-12 col-xs-12 text-right">
    <ul class="pagination pagination-split">
        <li><a href="{{url('admin/producto/create')}}"><i class="fa fa-plus"></i> Crear</a></li>
        <li><a href="{{url('admin/producto/upload')}}"><i class="fa fa-plus"></i> Upload File</a></li>
    </ul>
</div>

<table class="table table-striped table-hover" id="tableData">
    <thead>
      <tr>
        <th scope="col">Nombre</th>
        <th scope="col">Esta Publicado?</th>
        <th scope="col">Editar</th>
        <th scope="col">Eliminar</th>
      </tr>
    </thead>
    <tbody>
        @foreach($productos as $producto)
      <tr>
        <th>{{$producto->nombre}}</th>
        <td>{{strtotime($producto->publication_date) <= strtotime(date("Y-m-d H:i:s"))?"Si":"No"}}</td>
        <td><a class="btn btn-primary" href="{{url("admin/producto/$producto->id/edit")}}"><i class="fa fa-edit"></i> Editar</a></td>
        <td>{{Form::open(["url"=>"admin/producto/$producto->id","method"=>"DELETE"])}}
            <button type="submit" class="btn btn-primary"><i class="fa fa-ellipsis-h"></i> Eliminar</button>
            {{Form::close()}}</td>
      </tr>
      @endforeach
    </tbody>
</table>

<script src="//code.jquery.com/jquery-3.5.1.js"></script>
<link rel="stylesheet" href="//cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
<br><script src = "//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js" defer ></script>
<script>
    $(document).ready( function () {
        $('#tableData').DataTable();
    } );
</script>
@endsection