@if ($errors->any())
    <div class="pesan-alert">
        <strong>Mohon periksa kembali:</strong>
        <ul>
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    </div>
@endif
