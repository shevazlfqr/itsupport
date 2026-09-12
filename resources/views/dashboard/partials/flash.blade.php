@if ($flash)
    <script type="application/json" id="flash-data">{!! json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
