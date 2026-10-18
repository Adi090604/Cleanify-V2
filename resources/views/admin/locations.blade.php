@extends('layouts.admin')

@section('title', 'Reported Locations')

@section('content')
  <div class="mb-4">
    <a href="{{ route('admin.reports') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 hover:text-green-900">
      <i class="fas fa-arrow-left text-xs"></i> Back to Reports
    </a>
  </div>

  <div class="admin-page-header mb-6 flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-map-marker-alt text-green-600 mr-3"></i>Reported Locations
      </h2>
      <p class="mt-1 text-gray-600">Normalized historical report locations, ordered by total reports.</p>
    </div>
    <form method="GET" action="{{ route('admin.reports.locations') }}" class="flex w-full max-w-lg lg:w-auto">
      <input name="search" type="search" value="{{ $search }}" class="flex-1 rounded-l-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Search locations..." aria-label="Search locations">
      <button type="submit" class="rounded-r-lg bg-green-600 px-4 text-white transition-colors duration-300 hover:bg-green-700" aria-label="Submit location search">
        <i class="fas fa-search"></i>
      </button>
      @if($search)
        <a href="{{ route('admin.reports.locations') }}" class="ml-2 rounded-lg bg-gray-500 px-4 py-2 text-white transition-colors duration-300 hover:bg-gray-600" aria-label="Clear location search">
          <i class="fas fa-times"></i>
        </a>
      @endif
    </form>
  </div>

  <p class="mb-3 text-sm text-gray-600">
    @if($locations->total())
      Showing {{ $locations->firstItem() }}&ndash;{{ $locations->lastItem() }} of {{ $locations->total() }} locations
    @else
      Showing 0 locations
    @endif
  </p>

  <div class="admin-table-card overflow-hidden rounded-xl bg-white shadow-sm">
    @if($locations->isNotEmpty())
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="admin-table-header">
              @foreach(['Location', 'Total Reports', 'Unique Reporters', 'Pending', 'In Progress', 'Resolved', 'Rejected', 'Latest Report', 'Action'] as $heading)
                <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            @foreach($locations as $location)
              <tr class="transition-colors duration-200 hover:bg-gray-50">
                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ $location->location_name }}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">{{ $location->total_reports }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $location->unique_reporters }}</td>
                <td class="px-4 py-3 text-yellow-700">{{ $location->pending_reports }}</td>
                <td class="px-4 py-3 text-blue-700">{{ $location->in_progress_reports }}</td>
                <td class="px-4 py-3 text-green-700">{{ $location->resolved_reports }}</td>
                <td class="px-4 py-3 text-red-700">{{ $location->rejected_reports }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ \Carbon\Carbon::parse($location->latest_report_at)->format('M d, Y') }}</td>
                <td class="whitespace-nowrap px-4 py-3">
                  <a href="{{ route('admin.reports', ['location' => $location->location_key]) }}" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700">
                    <i class="fas fa-eye text-xs"></i> View Reports
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="px-4 py-12 text-center text-sm text-gray-500">{{ $search ? 'No locations found.' : 'No reported locations yet.' }}</p>
    @endif
  </div>

  @if($locations->hasPages())
    <div class="mt-4">{{ $locations->links() }}</div>
  @endif
@endsection
