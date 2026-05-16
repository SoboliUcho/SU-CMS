<div class="form-group">
    <label>{{label}}</label>
    {{ checkboxes }}
    @if($error)
        <div class="error">{{error}}</div>
    @endif

</div>