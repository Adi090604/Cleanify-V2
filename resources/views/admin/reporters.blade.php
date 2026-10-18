@extends('layouts.admin')

@section('title', 'Reporter Activity')

@section('content')
  <div class="mb-4">
    <a href="{{ route('admin.reports') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 hover:text-green-900">
      <i class="fas fa-arrow-left text-xs"></i> Back to Reports
    </a>
  </div>

  <div class="admin-page-header mb-6 flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-users text-green-600 mr-3"></i>Reporter Activity
      </h2>
      <p class="mt-1 text-gray-600">All residents with report activity, ordered by total reports.</p>
    </div>
    <form method="GET" action="{{ route('admin.reports.reporters') }}" class="flex w-full max-w-lg lg:w-auto">
      <input name="search" type="search" value="{{ $search }}" class="flex-1 rounded-l-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Search reporters..." aria-label="Search reporters">
      <button type="submit" class="rounded-r-lg bg-green-600 px-4 text-white transition-colors duration-300 hover:bg-green-700" aria-label="Submit reporter search">
        <i class="fas fa-search"></i>
      </button>
      @if($search)
        <a href="{{ route('admin.reports.reporters') }}" class="ml-2 rounded-lg bg-gray-500 px-4 py-2 text-white transition-colors duration-300 hover:bg-gray-600" aria-label="Clear reporter search">
          <i class="fas fa-times"></i>
        </a>
      @endif
    </form>
  </div>

  <p class="mb-3 text-sm text-gray-600">
    @if($reporters->total())
      Showing {{ $reporters->firstItem() }}&ndash;{{ $reporters->lastItem() }} of {{ $reporters->total() }} reporters
    @else
      Showing 0 reporters
    @endif
  </p>

  <div class="admin-table-card overflow-hidden rounded-xl bg-white shadow-sm">
    @if($reporters->isNotEmpty())
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="admin-table-header">
              @foreach(['Reporter', 'Current Service Area', 'Total Reports', 'Pending', 'In Progress', 'Resolved', 'Rejected', 'Most Reported Location', 'Latest Report', 'Action'] as $heading)
                <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            @foreach($reporters as $reporter)
              @php($topLocation = $mostReportedLocations->get($reporter->id))
              <tr class="transition-colors duration-200 hover:bg-gray-50">
                <td class="px-4 py-3">
                  <p class="whitespace-nowrap font-medium text-gray-900">{{ $reporter->name }}</p>
                  <p class="whitespace-nowrap text-xs text-gray-500">{{ $reporter->email }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $reporter->service_area ?: 'Not set' }}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">{{ $reporter->reports_count }}</td>
                <td class="px-4 py-3 text-yellow-700">{{ $reporter->pending_reports_count }}</td>
                <td class="px-4 py-3 text-blue-700">{{ $reporter->in_progress_reports_count }}</td>
                <td class="px-4 py-3 text-green-700">{{ $reporter->resolved_reports_count }}</td>
                <td class="px-4 py-3 text-red-700">{{ $reporter->rejected_reports_count }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $topLocation?->location_name ?: 'No location data' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $reporter->latest_report_at ? \Carbon\Carbon::parse($reporter->latest_report_at)->format('M d, Y') : '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">
                  <a href="{{ route('admin.reports', ['reporter_id' => $reporter->id]) }}" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700">
                    <i class="fas fa-eye text-xs"></i> View Reports
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="px-4 py-12 text-center text-sm text-gray-500">{{ $search ? 'No reporters found.' : 'No reporter activity yet.' }}</p>
    @endif
  </div>

  @if($reporters->hasPages())
    <div class="mt-4">{{ $reporters->links() }}</div>
  @endif
@endsection
