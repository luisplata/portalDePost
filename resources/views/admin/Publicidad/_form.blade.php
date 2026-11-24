@php($isEdit = $creative !== null)
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
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Campaign key <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        @if($isEdit)
        <input type="text" class="form-control" value="{{ $creative->campaign_key }}" disabled>
        <span class="text-muted">La key identifica el anuncio y su historico: no se puede editar.</span>
        @else
        <input type="text" name="campaign_key" class="form-control" value="{{ old('campaign_key') }}" maxlength="64" required>
        <span class="text-muted">Minusculas, numeros, guiones y guiones bajos. Ej: promo-navidad_2026</span>
        @endif
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Nombre <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="name" class="form-control" value="{{ old('name', $creative->name ?? '') }}" maxlength="100" required>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Placement <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <select name="placement_id" class="form-control" required>
            @foreach($placements as $placement)
            <option value="{{ $placement->id }}" @if(old('placement_id', $creative->placement_id ?? '') == $placement->id) selected @endif>{{ $placement->name }} ({{ $placement->key }})</option>
            @endforeach
        </select>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Estado <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <select name="status" class="form-control" required>
            @foreach(['draft' => 'Borrador', 'active' => 'Activo', 'paused' => 'Pausado', 'ended' => 'Finalizado'] as $value => $label)
            <option value="{{ $value }}" @if(old('status', $creative->status ?? 'draft') === $value) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Inicio (hora local)</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $creative->starts_at_local ?? '') }}">
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Fin (hora local)</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $creative->ends_at_local ?? '') }}">
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Prioridad <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="number" name="priority" class="form-control" value="{{ old('priority', $creative->priority ?? 0) }}" min="-100" max="100" required>
        <span class="text-muted">Mayor prioridad se sirve primero (empate: la mas antigua).</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Imagen (URL) <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="image_url" class="form-control" value="{{ old('image_url', $creative->image_url ?? '') }}" maxlength="2048" required>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Headline <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="headline" class="form-control" value="{{ old('headline', $creative->headline ?? '') }}" maxlength="120" required>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Texto alternativo</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="alt_text" class="form-control" value="{{ old('alt_text', $creative->alt_text ?? '') }}" maxlength="150">
        <span class="text-muted">Si queda vacio se usa el headline.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Cuerpo</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <textarea name="body" class="form-control" rows="3" maxlength="500">{{ old('body', $creative->body ?? '') }}</textarea>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">CTA</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="cta_label" class="form-control" value="{{ old('cta_label', $creative->cta_label ?? 'Ver más') }}" maxlength="40">
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Destino (URL) <span class="required">*</span></label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <input type="text" name="target_url" class="form-control" value="{{ old('target_url', $creative->target_url ?? '') }}" maxlength="2048" required>
        <span class="text-muted">http(s)://... o una ruta interna que empiece con /.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Producto asociado</label>
    <div class="col-md-6 col-sm-6 col-xs-12">
        <select name="producto_id" class="form-control">
            <option value="">Sin producto</option>
            @foreach($productos as $producto)
            <option value="{{ $producto->id }}" @if(old('producto_id', $creative->producto_id ?? '') == $producto->id) selected @endif>{{ $producto->nombre }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="clearfix"></div>
