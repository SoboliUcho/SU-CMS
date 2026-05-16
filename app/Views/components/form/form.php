<form action="{{action}}" method="{{method}}" class="form {{name}}-form {{class}}" id="{{name}}-form" data-form-name="{{name}}" data-ajax="{{ajax}}" data-submit-on-change="{{submit_on_change}}" data-debounce-ms="{{debounce_ms}}">
    <input type="hidden" name="__form_name" value="{{name}}" />
    <input type="hidden" name="__submit_trigger" value="submit" />
    {{fields}}
</form>
{{ajax_script}}