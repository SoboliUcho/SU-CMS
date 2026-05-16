<div class="form-group">
    <label>{{label}}</label>
    {{ radio_selectors }}
    @if($error)
        <div class="error">{{error}}</div>
    @endif

</div>