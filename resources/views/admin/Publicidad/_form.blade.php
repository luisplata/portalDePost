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

<div class="ln_solid"></div>

<div class="form-group">
    <label class="control-label col-md-3 col-sm-3 col-xs-12">Vista previa</label>
    <div class="col-md-9 col-sm-9 col-xs-12">
        <p class="text-muted">
            Se actualiza mientras editás. Slot: <strong id="ad-preview-slot">&mdash;</strong>.
            Si la imagen no carga acá, tampoco va a cargar en el sitio.
        </p>
        <div class="row">
            <div class="col-md-4 col-sm-4 col-xs-12">
                <small class="text-muted">Sidebar</small>
                <div class="card bg-dark text-white center" style="max-width: 280px;">
                    <img id="ad-preview-image" class="card-img" alt=""
                         style="width: 100%; height: auto; aspect-ratio: 16 / 9; object-fit: cover;">
                    <div class="card-img-overlay text-center">
                        <h5 class="card-title" id="ad-preview-headline"></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-6 col-xs-12">
                <small class="text-muted">Grilla</small>
                <div class="card bg-dark text-center" style="max-width: 280px;">
                    <img id="ad-preview-image-grid" class="card-img" alt=""
                         style="width: 100%; height: auto; aspect-ratio: 16 / 9; object-fit: cover;">
                    <div class="card-body">
                        <h2 class="card-title" id="ad-preview-headline-grid"></h2>
                        <span class="btn btn-default btn-xs" id="ad-preview-cta-grid"></span>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-danger" id="ad-preview-error" style="display: none;">
            La imagen no carga desde el navegador. Revisá la URL o los permisos del host externo.
        </p>
    </div>
</div>
<div class="clearfix"></div>

<script>
(function () {
    'use strict';

    var script = document.currentScript;
    var form = script ? script.closest('form') : null;

    if (!form) {
        return;
    }

    var imageInput = form.querySelector('[name="image_url"]');
    var headlineInput = form.querySelector('[name="headline"]');
    var altInput = form.querySelector('[name="alt_text"]');
    var ctaInput = form.querySelector('[name="cta_label"]');
    var placementSelect = form.querySelector('[name="placement_id"]');
    var slotLabel = document.getElementById('ad-preview-slot');
    var errorBox = document.getElementById('ad-preview-error');
    var ctaPreview = document.getElementById('ad-preview-cta-grid');

    var previews = [
        {
            img: document.getElementById('ad-preview-image'),
            title: document.getElementById('ad-preview-headline')
        },
        {
            img: document.getElementById('ad-preview-image-grid'),
            title: document.getElementById('ad-preview-headline-grid')
        }
    ];

    function textOr(value, fallback) {
        return value && value.trim() !== '' ? value : fallback;
    }

    function refreshError() {
        var failed = previews.some(function (preview) {
            return preview.img.getAttribute('src')
                && preview.img.complete
                && preview.img.naturalWidth === 0;
        });

        errorBox.style.display = failed ? 'block' : 'none';
    }

    function refresh() {
        var url = textOr(imageInput.value, '');
        var title = textOr(headlineInput.value, 'Sin headline');
        var altText = textOr(altInput.value, title);

        ctaPreview.textContent = textOr(ctaInput.value, 'Ver más');

        if (placementSelect && placementSelect.selectedIndex >= 0) {
            slotLabel.textContent = placementSelect.options[placementSelect.selectedIndex].text;
        }

        previews.forEach(function (preview) {
            preview.img.alt = altText;
            preview.title.textContent = title;

            if (url === '') {
                preview.img.removeAttribute('src');
                preview.img.style.visibility = 'hidden';
                return;
            }

            preview.img.style.visibility = 'visible';

            if (preview.img.getAttribute('src') !== url) {
                preview.img.setAttribute('src', url);
            }
        });

        refreshError();
    }

    previews.forEach(function (preview) {
        preview.img.addEventListener('load', refreshError);
        preview.img.addEventListener('error', refreshError);
    });

    [imageInput, headlineInput, altInput, ctaInput, placementSelect].forEach(function (field) {
        if (!field) {
            return;
        }
        field.addEventListener('input', refresh);
        field.addEventListener('change', refresh);
    });

    refresh();
})();
</script>
