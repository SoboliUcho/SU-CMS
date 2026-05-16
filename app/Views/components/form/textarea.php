<div class="form-group {{group_class}}">
    @if(show_label ?? true)
        <label for="{{id ?? name}}">{{label}}</label>
    @endif
    <textarea id="{{id ?? name}}" name="{{name}}" class="{{class}}" {{attrs}}>{{value}}</textarea>
    @if($error)
        <div class="error">{{error}}</div>
    @endif
</div>
