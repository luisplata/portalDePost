@php($isEdit = $canal !== null)
@php($mascara = $mascara ?? '')
@php($tipoActual = old('tipo', $canal->tipo ?? ''))
<h2>{{ $isEdit ? 'Editar canal' : 'Nuevo canal' }}</h2>

@if($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Nombre <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="nombre" class="form-control" maxlength="60" required
               value="{{ old('nombre', $canal->nombre ?? '') }}">
        <span class="text-muted">Unico entre todos los canales, incluidos los eliminados.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Tipo <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        @if($isEdit)
        <input type="text" class="form-control" value="{{ $canal->tipo }}" disabled>
        <span class="text-muted">El tipo de un canal no se puede cambiar.</span>
        @else
        <select name="tipo" class="form-control" required>
            @foreach($drivers as $driver)
            <option value="{{ $driver }}" @if($tipoActual === $driver) selected @endif>{{ $driver }}</option>
            @endforeach
        </select>
        @endif
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Habilitado</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <select name="habilitado" class="form-control">
            <option value="0" @if((string) old('habilitado', (int) ($canal->habilitado ?? 0)) === '0') selected @endif>No</option>
            <option value="1" @if((string) old('habilitado', (int) ($canal->habilitado ?? 0)) === '1') selected @endif>Si</option>
        </select>
        <span class="text-muted">Al pasar de No a Si la vigencia vuelve a ahora.</span>
    </div>
</div>
<div class="clearfix"></div>

@if($isEdit)
<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Vigente desde</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="datetime-local" name="vigente_desde" class="form-control"
               value="{{ old('vigente_desde', $canal->vigente_desde ? $canal->vigente_desde->format('Y-m-d\TH:i') : '') }}">
        <span class="text-danger">Publicar desde esta fecha puede generar un lote grande de envios.</span>
    </div>
</div>
<div class="clearfix"></div>
@endif

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Telegram token</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="token" class="form-control" autocomplete="off"
               value="" placeholder="{{ $tipoActual === 'telegram' && $mascara !== '' ? $mascara : '123456789:AA...' }}">
        <span class="text-muted">Vacio en edicion conserva el token actual.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Telegram chat_id</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="chat_id" class="form-control" autocomplete="off"
               value="" placeholder="{{ $tipoActual === 'telegram' && $mascara !== '' ? 'Conservar el actual' : '-1001234567890' }}">
        <span class="text-muted">Token y chat_id van juntos o ninguno.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Discord webhook</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="webhook_url" class="form-control" autocomplete="off"
               value="" placeholder="{{ $tipoActual === 'discord' && $mascara !== '' ? $mascara : 'https://discord.com/api/webhooks/...' }}">
        <span class="text-muted">Vacio en edicion conserva el webhook actual.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="ln_solid"></div>
<div class="form-group">
    <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
        <a class="btn btn-primary" href="{{ url('admin/canales') }}">Cancelar</a>
        <button type="submit" class="btn btn-success">Guardar</button>
    </div>
</div>
