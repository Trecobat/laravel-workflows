<form action="{{ route('workflows.triggers.button', ['id' => $triggerId]) }}" method="POST">
    @csrf
    <input name="model_id" id="model_id" type="hidden" value="{{ $model->id }}">
    <input name="model_class" id="model_class" type="hidden" value="{{ get_class($model) }}">
    <input name="params" id="params" type="hidden" value="{{ json_encode($params) }}">
    <button type="submit" class="{{ $css_classes }}" style="{{ $css_style }}">{{ $caption }}</button>
</form>
