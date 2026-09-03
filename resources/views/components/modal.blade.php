@props(['id', 'title', 'icon' => null, 'color' => 'green'])

@php
$colorClasses = [
    'green' => 'bg-green-600',
    'red' => 'bg-red-600',
    'blue' => 'bg-blue-600',
    'yellow' => 'bg-yellow-600',
    'purple' => 'bg-purple-600',
    'orange' => 'bg-orange-600',
];
$headerClass = $colorClasses[$color] ?? 'bg-green-600';
@endphp

<div id="{{ $id }}" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-xl border border-gray-200 shadow-xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
    <div class="{{ $headerClass }} text-white px-4 py-3 flex justify-between items-center">
      <h5 class="font-semibold text-base">
        @if($icon)
          <i class="{{ $icon }} mr-2"></i>
        @endif
        {{ $title }}
      </h5>
      <button onclick="closeModal('{{ $id }}')" class="text-white hover:text-gray-200 transition-colors duration-300">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div class="p-4 overflow-y-auto max-h-96">
      {{ $slot }}
    </div>
    @if(isset($footer))
      <div class="p-4 border-t border-gray-200">
        {{ $footer }}
      </div>
    @endif
  </div>
</div>
