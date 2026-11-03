@if ($errors->any())
    <div class="flash flash-error" role="alert">
        <strong>Periksa kembali data berikut:</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
