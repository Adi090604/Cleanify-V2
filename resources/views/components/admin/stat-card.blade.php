@props([
  'icon',
  'title',
  'value',
  'borderClass' => 'border-l-4 border-green-500',
  'iconWrapperClass' => 'bg-green-100',
  'iconColorClass' => 'text-green-600',
])

<div {{ $attributes->merge(['class' => "bg-white rounded-lg border border-gray-100 p-3.5 shadow-sm $borderClass"]) }}>
  <div class="flex items-center">
    <div class="p-2.5 rounded-lg mr-3 {{ $iconWrapperClass }}">
      <i class="{{ $icon }} text-lg {{ $iconColorClass }}"></i>
    </div>
    <div>
      <p class="text-xs text-gray-600">{{ $title }}</p>
      <h3 class="text-xl font-bold text-gray-800">{{ $value }}</h3>
    </div>
  </div>
</div>
