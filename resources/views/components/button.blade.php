@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'loading' => false, 'icon' => null, 'iconPosition' => 'left'])

@php
$variants = [
    'primary' => 'bg-green-600 hover:bg-green-700 text-white',
    'secondary' => 'bg-white border border-gray-200 hover:bg-gray-50 text-gray-700',
    'success' => 'bg-green-500 hover:bg-green-600 text-white',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white',
    'warning' => 'bg-yellow-600 hover:bg-yellow-700 text-white',
    'info' => 'bg-blue-600 hover:bg-blue-700 text-white',
    'outline' => 'border border-green-600 text-green-700 hover:bg-green-600 hover:text-white',
    'ghost' => 'text-gray-700 hover:bg-gray-100',
];

$sizes = [
    'sm' => 'px-2.5 py-1.5 text-xs',
    'md' => 'px-3.5 py-2 text-sm',
    'lg' => 'px-4 py-2.5 text-sm',
];

$variantClass = $variants[$variant] ?? $variants['primary'];
$sizeClass = $sizes[$size] ?? $sizes['md'];
$baseClass = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
@endphp

<button 
  type="{{ $type }}"
  {{ $attributes->merge(['class' => $baseClass . ' ' . $variantClass . ' ' . $sizeClass]) }}
  @if($loading) disabled @endif
>
  @if($loading)
    <i class="fas fa-spinner fa-spin mr-2"></i>
  @elseif($icon && $iconPosition === 'left')
    <i class="{{ $icon }} mr-2"></i>
  @endif
  
  <span>{{ $slot }}</span>
  
  @if($icon && $iconPosition === 'right' && !$loading)
    <i class="{{ $icon }} ml-2"></i>
  @endif
</button>
