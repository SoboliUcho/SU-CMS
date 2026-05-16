<div class="form-group {{group_class}}">
    @if(show_label ?? true)
        <label for="{{id ?? name}}">{{label}}</label>
    @endif
    <select name="{{name}}" id="{{id ?? name}}" class="{{class}}" {{attrs}}>
        {{optionValues}}
    </select>
    @if($error)
        <div class="error">{{error}}</div>
    @endif
</div>