@props(['id', 'title', 'icon' => null, 'color' => 'green', 'variant' => 'default'])

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
$accentClasses = [
    'green' => 'bg-green-50 text-green-600',
    'red' => 'bg-red-50 text-red-600',
    'blue' => 'bg-blue-50 text-blue-600',
    'yellow' => 'bg-yellow-50 text-yellow-600',
    'purple' => 'bg-purple-50 text-purple-600',
    'orange' => 'bg-orange-50 text-orange-600',
];
$accentClass = $accentClasses[$color] ?? $accentClasses['red'];
$isConfirmation = $variant === 'confirmation';
@endphp

<div id="{{ $id }}" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-xl border border-gray-200 shadow-xl w-full {{ $isConfirmation ? 'admin-confirm-modal max-w-md mx-3' : 'max-w-2xl' }} max-h-[80vh] overflow-hidden">
    <div class="{{ $isConfirmation ? 'admin-confirm-header' : $headerClass . ' text-white px-4 py-3' }} flex justify-between items-center">
      <h5 class="{{ $isConfirmation ? 'admin-confirm-heading' : 'font-semibold text-base' }}">
        @if($icon)
          @if($isConfirmation)
            <span class="admin-confirm-icon {{ $accentClass }}"><i class="{{ $icon }}"></i></span>
          @else
            <i class="{{ $icon }} mr-2"></i>
          @endif
        @endif
        <span>{{ $title }}</span>
      </h5>
      <button type="button" onclick="closeModal('{{ $id }}')" class="{{ $isConfirmation ? 'text-gray-400 hover:text-gray-600' : 'text-white hover:text-gray-200' }} transition-colors duration-300" aria-label="Close {{ $title }}">
        <i class="fas fa-times {{ $isConfirmation ? 'text-base' : 'text-xl' }}"></i>
      </button>
    </div>
    <div class="{{ $isConfirmation ? 'admin-confirm-body' : 'p-4' }} overflow-y-auto max-h-96">
      {{ $slot }}
    </div>
    @if(isset($footer))
      <div class="{{ $isConfirmation ? 'admin-confirm-footer' : 'p-4 border-t border-gray-200' }}">
        {{ $footer }}
      </div>
    @endif
  </div>
</div>
