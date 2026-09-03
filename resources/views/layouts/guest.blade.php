<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cleanify | Eco-Friendly Living</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('head')
</head>
<body class="min-h-screen bg-[#f7f9f7] text-gray-800">
  @hasSection('landing-page')
    <section class="min-h-screen relative overflow-hidden">
      <div class="absolute -top-40 -right-32 h-96 w-96 rounded-full bg-green-100/70"></div>
      <div class="absolute -bottom-48 -left-24 h-96 w-96 rounded-full bg-green-50"></div>
      <div class="relative min-h-screen px-5 py-5 sm:px-8 lg:px-12">
        @yield('content')
      </div>
    </section>
  @elseif(View::hasSection('auth-page'))
    <section class="min-h-screen flex items-center justify-center px-4 py-8">
      <div class="w-full max-w-4xl">
        @yield('content')
      </div>
    </section>
  @else
    <section class="relative min-h-screen bg-cover bg-center" style="background-image: url('/assets/background.jpg')">
      <div class="absolute inset-0 bg-black opacity-60"></div>
      <div class="relative z-10 min-h-screen flex items-center justify-center text-center text-white px-4">
        <div class="w-full max-w-2xl">
          <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-24 h-auto mx-auto mb-4">
          @yield('content')
        </div>
      </div>
    </section>
  @endif
</body>
</html>
