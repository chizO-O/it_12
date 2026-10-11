@props(['bag'])
@if($errors->getBag($bag)->any())
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold">Please check the following:</p>
        <ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->getBag($bag)->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
