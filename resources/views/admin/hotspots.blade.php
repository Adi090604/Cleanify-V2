@extends('layouts.admin')

@section('title', 'Reported Hotspots — '.$barangay->barangay_name)

@section('content')
  <div class="mb-4 flex flex-wrap gap-4">
    <a href="{{ route('admin.reports') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 hover:text-green-900"><i class="fas fa-arrow-left text-xs"></i> Back to Reports</a>
    <a href="{{ route('admin.reports.barangays') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900"><i class="fas fa-list text-xs"></i> Back to Barangays</a>
  </div>

  <div class="admin-page-header mb-6">
    <h2 class="text-3xl font-bold text-gray-800"><i class="fas fa-map-marker-alt mr-3 text-green-600"></i>Reported Hotspots — {{ $barangay->barangay_name }}</h2>
    <p class="mt-1 text-gray-600">Nearby GPS reports are grouped within {{ (int) \App\Services\ReportGeographyAnalytics::HOTSPOT_RADIUS_METERS }} meters. Reports without valid GPS use their exact normalized landmark. A hotspot becomes frequent at {{ $frequentMinimum }} qualifying reports.</p>
  </div>

  <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-admin.stat-card icon="fas fa-flag" title="Total Reports" value="{{ $barangay->total_reports }}" borderClass="border-l-4 border-green-500" iconWrapperClass="bg-green-100" iconColorClass="text-green-600" />
    <x-admin.stat-card icon="fas fa-users" title="Unique Reporters" value="{{ $barangay->unique_reporters }}" borderClass="border-l-4 border-indigo-500" iconWrapperClass="bg-indigo-100" iconColorClass="text-indigo-600" />
    <x-admin.stat-card icon="fas fa-fire" title="Frequent Hotspots" value="{{ $frequentHotspots->count() }}" borderClass="border-l-4 border-orange-500" iconWrapperClass="bg-orange-100" iconColorClass="text-orange-600" />
    <x-admin.stat-card icon="fas fa-clock" title="Pending" value="{{ $barangay->pending_reports }}" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-spinner" title="In Progress" value="{{ $barangay->in_progress_reports }}" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
  </div>

  @php
    $hotspotSections = [
      ['title' => 'Frequently Reported Hotspots', 'items' => $frequentHotspots, 'empty' => 'No frequently reported hotspots yet.'],
      ['title' => 'Other Reported Locations', 'items' => $otherHotspots, 'empty' => null],
    ];
  @endphp

  @foreach($hotspotSections as $section)
    @if($section['items']->isNotEmpty() || $section['empty'])
      <section class="mb-6" aria-labelledby="hotspot-section-{{ $loop->index }}">
        <h3 id="hotspot-section-{{ $loop->index }}" class="mb-3 text-xl font-bold text-gray-800">{{ $section['title'] }}</h3>
        @if($section['items']->isEmpty())
          <div class="rounded-xl bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ $section['empty'] }}</div>
        @else
          <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach($section['items'] as $hotspot)
              <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <h4 class="text-lg font-bold text-gray-900">{{ $hotspot->label }}</h4>
                      @if($hotspot->is_frequent)<span class="rounded-full bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-700">Frequent Hotspot</span>@endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ $barangay->barangay_name }} · Location basis: {{ $hotspot->basis_label }}</p>
                    @if($hotspot->latitude !== null)
                      <p class="mt-1 text-xs text-gray-400">Approx. {{ number_format($hotspot->latitude, 5) }}, {{ number_format($hotspot->longitude, 5) }}</p>
                    @endif
                  </div>
                  <a href="{{ route('admin.reports.hotspots.reports', ['barangay' => $barangayKey, 'hotspot' => $hotspot->id]) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700"><i class="fas fa-eye text-xs"></i> View Reports</a>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                  <div><dt class="text-gray-500">Reports</dt><dd class="font-semibold text-gray-900">{{ $hotspot->total_reports }} total</dd><dd class="text-xs text-gray-500">{{ $hotspot->qualifying_reports }} qualifying</dd></div>
                  <div><dt class="text-gray-500">Unique Reporters</dt><dd class="font-semibold text-gray-900">{{ $hotspot->unique_reporters }}</dd></div>
                  <div><dt class="text-gray-500">Status</dt><dd class="text-yellow-700">{{ $hotspot->pending_reports }} pending</dd><dd class="text-blue-700">{{ $hotspot->in_progress_reports }} in progress</dd></div>
                  <div><dt class="text-gray-500">Outcome</dt><dd class="text-green-700">{{ $hotspot->resolved_reports }} resolved</dd><dd class="text-red-700">{{ $hotspot->rejected_reports }} rejected</dd></div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">Latest report {{ $hotspot->latest_report_at->format('M d, Y') }}</p>
              </article>
            @endforeach
          </div>
        @endif
      </section>
    @endif
  @endforeach
@endsection
