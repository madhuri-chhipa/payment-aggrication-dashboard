@if (session()->has('success'))
<script>
  document.addEventListener('DOMContentLoaded', function() {
    toastr.success(@json(session('success')));
  });
</script>
@endif

@if (session()->has('error'))
<script>
  document.addEventListener('DOMContentLoaded', function() {
    toastr.error(@json(session('error')));
  });
</script>
@endif

@if ($errors->any())
<script>
  document.addEventListener('DOMContentLoaded', function () {
    @foreach ($errors->all() as $error)
      toastr.error(@json($error));
    @endforeach
  });
</script>
@endif