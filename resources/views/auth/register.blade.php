@extends('layouts.guest')

@section('auth-page', 'true')

@section('content')
  <div class="auth-card bg-black bg-opacity-30 p-10 rounded-xl w-full max-w-md mx-auto text-left text-white">
    <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="auth-logo">
    <div class="text-3xl font-extrabold text-green-500 text-center tracking-wide mb-1">CLEANIFY</div>
    <p class="text-gray-300 italic text-center text-sm mb-6">One Click. One Report. One Clean Community.</p>


    <form method="POST" action="{{ route('register') }}" class="auth-form space-y-6">
      @csrf
      <div>
        <label for="name" class="block text-white mb-2">Full Name</label>
        <div class="relative">
          <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name') }}"
            class="w-full bg-transparent border-0 border-b {{ $errors->has('name') ? 'border-red-500' : 'border-gray-400' }} text-white pl-10 pb-2 focus:outline-none focus:border-green-500 transition-colors duration-300"
            required
            autofocus
            autocomplete="name"
          >
        </div>
        @error('name')
          <p class="mt-2 text-sm text-red-400 font-semibold flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
          </p>
        @enderror
      </div>

      <div>
        <label for="email" class="block text-white mb-2">Email Address</label>
        <div class="relative">
          <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            class="w-full bg-transparent border-0 border-b {{ $errors->has('email') ? 'border-red-500' : 'border-gray-400' }} text-white pl-10 pb-2 focus:outline-none focus:border-green-500 transition-colors duration-300"
            required
            autocomplete="email"
          >
        </div>
        @error('email')
          <p class="mt-2 text-sm text-red-400 font-semibold flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
          </p>
        @enderror
      </div>

      <div>
        <label for="password" class="block text-white mb-2">Password</label>
        <div class="relative">
          <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="w-full bg-transparent border-0 border-b {{ $errors->has('password') ? 'border-red-500' : 'border-gray-400' }} text-white pl-10 pb-2 focus:outline-none focus:border-green-500 transition-colors duration-300"
            required
            autocomplete="new-password"
          >
        </div>
        @error('password')
          <p class="mt-2 text-sm text-red-400 font-semibold flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
          </p>
        @enderror
      </div>

      <div>
        <label for="password_confirmation" class="block text-white mb-2">Confirm Password</label>
        <div class="relative">
          <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            class="w-full bg-transparent border-0 border-b {{ $errors->has('password_confirmation') ? 'border-red-500' : 'border-gray-400' }} text-white pl-10 pb-2 focus:outline-none focus:border-green-500 transition-colors duration-300"
            required
            autocomplete="new-password"
          >
        </div>
        @error('password_confirmation')
          <p class="mt-2 text-sm text-red-400 font-semibold flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
          </p>
        @enderror
      </div>

      <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-medium py-2.5 rounded-md transition-colors duration-300 inline-flex items-center justify-center gap-2">
        <i class="fas fa-user-plus"></i>
        SIGN UP
      </button>
    </form>

    <div class="mt-4 text-sm text-center text-gray-300">
      Already have an account?
      <a href="{{ route('login') }}" class="text-green-500 hover:underline inline-flex items-center gap-1">
        <i class="fas fa-sign-in-alt"></i>
        Login
      </a>
    </div>
  </div>
@endsection
