@extends('layouts.guest')

@section('auth-page', 'true')

@section('content')
  <div class="relative grid overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_16px_40px_rgba(30,58,38,0.10)] md:grid-cols-[.95fr_1.05fr]">
    <a href="{{ url('/') }}" class="absolute left-4 top-4 z-20 inline-flex items-center gap-1.5 rounded-md bg-green-800 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-green-900 transition-colors md:bg-white/10 md:text-green-100 md:hover:bg-white/20">
      <i class="fas fa-arrow-left text-[10px]"></i> Back to Home
    </a>

    <aside class="relative hidden min-h-[30rem] overflow-hidden bg-green-800 p-8 text-white md:flex md:flex-col md:justify-center">
      <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full border border-green-300/20 bg-green-700/30"></div>
      <div class="absolute -bottom-24 -left-20 h-56 w-56 rounded-full border border-green-300/20 bg-green-700/30"></div>
      <div class="absolute bottom-20 right-8 flex h-10 w-10 items-center justify-center rounded-xl border border-green-300/20 bg-green-700/30 text-green-100"><i class="fas fa-recycle"></i></div>

      <div class="relative z-10 max-w-xs">
        <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="h-16 w-16 object-contain rounded-2xl bg-white/10 p-1.5 ring-1 ring-white/20">
        <p class="mt-5 text-2xl font-semibold tracking-tight">Cleanify</p>
        <h2 class="mt-5 text-3xl font-semibold leading-tight tracking-tight">A cleaner community starts with better waste management.</h2>
        <p class="mt-4 text-sm leading-6 text-green-100">Stay informed, report issues quickly, and help your community keep collection services running smoothly.</p>
      </div>
      <p class="absolute bottom-8 left-8 z-10 text-xs font-medium text-green-100"><i class="fas fa-leaf mr-1.5"></i> Better reporting. Better collection.</p>
    </aside>
    <div class="p-6 pt-16 sm:p-8 sm:pt-16 text-left md:pt-8">
      <div class="md:hidden mb-6 flex items-center gap-2"><img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="h-9 w-9 object-contain"><span class="font-semibold text-gray-800">Cleanify</span></div>
      <h1 class="text-xl font-semibold tracking-tight text-gray-900">Welcome back</h1>
      <p class="mt-1 text-sm text-gray-500">Sign in to manage your community updates.</p>

    @if(session('status'))
      <x-alert type="success" dismissible class="mb-4">
        {{ session('status') }}
      </x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
      @csrf
      <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
        <div class="relative">
          <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            class="w-full bg-white border {{ $errors->has('email') ? 'border-red-500' : 'border-gray-300' }} text-gray-800 rounded-lg pl-10 py-2.5 focus:outline-none focus:border-green-600 transition-colors duration-300"
            required
            autofocus
            autocomplete="email"
          >
        </div>
        @error('email')
          <p class="mt-1.5 text-xs text-red-600 font-semibold flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
          </p>
        @enderror
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
        <div class="relative">
          <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="w-full bg-white border {{ $errors->has('password') ? 'border-red-500' : 'border-gray-300' }} text-gray-800 rounded-lg pl-10 py-2.5 focus:outline-none focus:border-green-600 transition-colors duration-300"
            required
            autocomplete="current-password"
          >
        </div>
        @error('password')
          <p class="mt-1.5 text-xs text-red-600 font-semibold flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
          </p>
        @enderror
      </div>

      <div class="flex items-center">
        <input
          type="checkbox"
          id="remember"
          name="remember"
          value="1"
          {{ old('remember') ? 'checked' : '' }}
          class="w-4 h-4 text-green-600 bg-transparent border-gray-400 rounded focus:ring-green-500 focus:ring-2"
        >
        <label for="remember" class="ml-2 text-sm text-gray-600 cursor-pointer">Remember me</label>
      </div>

      <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white text-sm font-medium py-2.5 rounded-lg transition-colors duration-300 inline-flex items-center justify-center gap-2">
        <i class="fas fa-sign-in-alt"></i>
        Sign in
      </button>
    </form>

    <div class="mt-5 text-sm text-center space-y-2">
      <a href="{{ route('password.request') }}" class="text-green-500 hover:underline inline-flex items-center justify-center gap-1">
        <i class="fas fa-key mr-1"></i>Forgot Password?
      </a>
      <span class="text-gray-500 block">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-green-500 hover:underline inline-flex items-center gap-1">
          <i class="fas fa-user-plus"></i>
          Sign Up
        </a>
      </span>
    </div>
    </div>
  </div>
@endsection
