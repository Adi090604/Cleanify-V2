@extends('layouts.guest')

@section('landing-page', 'true')

@section('content')
  <header class="max-w-6xl mx-auto flex items-center justify-between">
    <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
      <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-9 h-9 object-contain">
      <span class="font-semibold tracking-tight text-gray-800">Cleanify</span>
    </a>
    <div class="flex items-center gap-3 text-sm">
      <a href="{{ route('login') }}" class="text-gray-600 hover:text-green-700 transition-colors">Sign in</a>
      <a href="{{ route('register') }}" class="rounded-lg bg-green-700 px-3.5 py-2 font-medium text-white hover:bg-green-800 transition-colors">Create account</a>
    </div>
  </header>

  <main class="max-w-6xl mx-auto min-h-[calc(100vh-84px)] grid items-center py-12 lg:grid-cols-[1.1fr_.9fr] gap-10">
    <div class="max-w-xl">
      <div class="inline-flex items-center gap-2 rounded-full border border-green-100 bg-white px-3 py-1.5 text-xs font-medium text-green-800">
        <i class="fas fa-leaf"></i> Smarter community waste management
      </div>
      <h1 class="mt-5 text-4xl sm:text-5xl font-bold tracking-tight text-gray-900 leading-[1.08]">A cleaner community starts with clear action.</h1>
      <p class="mt-5 max-w-lg text-base leading-7 text-gray-600">Cleanify helps residents report concerns, follow collection schedules, and stay connected with their community.</p>
      <div class="mt-7">
        <a href="{{ route('login') }}" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-800 transition-colors">Get started</a>
      </div>
    </div>

    <div class="justify-self-center w-full max-w-sm rounded-2xl border border-green-100 bg-white p-5 shadow-[0_12px_30px_rgba(32,75,42,0.08)]">
      <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700"><i class="fas fa-recycle"></i></span><div><p class="text-sm font-semibold">Community overview</p><p class="text-xs text-gray-500">Cleanify at a glance</p></div></div>
        <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
      </div>
      <div class="grid grid-cols-3 gap-3 py-5 text-center">
        <div class="rounded-lg bg-[#f7f9f7] p-3"><i class="fas fa-calendar-check text-green-700"></i><p class="mt-2 text-xs font-medium text-gray-700">Schedules</p></div>
        <div class="rounded-lg bg-[#f7f9f7] p-3"><i class="fas fa-bullhorn text-green-700"></i><p class="mt-2 text-xs font-medium text-gray-700">Reports</p></div>
        <div class="rounded-lg bg-[#f7f9f7] p-3"><i class="fas fa-truck text-green-700"></i><p class="mt-2 text-xs font-medium text-gray-700">Tracking</p></div>
      </div>
      <div class="rounded-lg border border-green-100 bg-green-50 px-3 py-2.5 text-xs text-green-900"><i class="fas fa-check-circle mr-1.5 text-green-700"></i> One place for cleaner, more coordinated communities.</div>
    </div>
  </main>

  <div class="hidden">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
    <!-- Left Side - Text Content -->
    <div class="text-center md:text-left">
      <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-4 text-white">Welcome to Cleanify</h1>
      <p class="text-lg md:text-xl mb-8 text-gray-200">Empowering communities for a cleaner and greener tomorrow.</p>
      <a href="{{ route('login') }}" class="inline-block bg-green-500 hover:bg-green-600 text-white font-medium rounded-full px-8 py-3 transition-colors duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
        Get Started
      </a>
    </div>

    <!-- Right Side - Visual Elements -->
    <div class="relative flex items-center justify-center md:justify-end">
      <!-- Logo -->
      <div class="absolute top-0 right-0 md:top-8 md:right-8 z-20">
        <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-24 h-24 md:w-32 md:h-32 opacity-90">
      </div>
      
      <!-- Seedling Illustration -->
      <div class="relative z-10 mt-16 md:mt-0">
        <div class="relative">
          <!-- Seedling SVG -->
          <svg class="w-48 h-64 md:w-64 md:h-80" viewBox="0 0 200 300" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Pot -->
            <rect x="70" y="220" width="60" height="50" rx="5" fill="#8B4513" opacity="0.8"/>
            <ellipse cx="100" cy="220" rx="35" ry="8" fill="#A0522D" opacity="0.8"/>
            
            <!-- Stem -->
            <line x1="100" y1="220" x2="100" y2="120" stroke="#4CAF50" stroke-width="8" stroke-linecap="round"/>
            
            <!-- Left Leaf -->
            <g transform="translate(100,100) rotate(-20) translate(-100,-100)">
              <ellipse cx="100" cy="100" rx="50" ry="60" fill="#66BB6A" opacity="0.9"/>
            </g>
            
            <!-- Right Leaf -->
            <g transform="translate(100,100) rotate(20) translate(-100,-100)">
              <ellipse cx="100" cy="100" rx="50" ry="60" fill="#4CAF50" opacity="0.9"/>
            </g>
            
            <!-- Water Droplets -->
            <circle cx="80" cy="90" r="4" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="120" cy="85" r="3" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="75" cy="110" r="3.5" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="125" cy="105" r="4" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="90" cy="115" r="3" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="110" cy="120" r="3.5" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="85" cy="100" r="2.5" fill="#E3F2FD" opacity="0.9"/>
            <circle cx="115" cy="95" r="3" fill="#E3F2FD" opacity="0.9"/>
          </svg>
        </div>
      </div>
    </div>
  </div>
  </div>
@endsection
