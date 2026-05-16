<div class="form-group {{group_class}}">
    @if(show_label ?? true)
        <label for="{{id ?? name}}">{{label}}</label>
    @endif
    <input type="{{type}}" id="{{id ?? name}}" name="{{name}}" value="{{value}}" class="{{class}}" {{attrs}} />
    @if($error)
        <div class="error">{{error}}</div>
    @endif
</div>
