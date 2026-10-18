@extends('layouts.admin')

@section('title', $hotspot->label.' Reports')

@section('content')
  <div class="mb-4 flex flex-wrap gap-4">
    <a href="{{ route('admin.reports.hotspots', ['barangay' => $barangayKey]) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 hover:text-green-900"><i class="fas fa-arrow-left text-xs"></i> Back to Hotspots</a>
    <a href="{{ route('admin.reports.barangays') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900"><i class="fas fa-list text-xs"></i> Back to Barangays</a>
  </div>

  <div class="admin-page-header mb-6">
    <h2 class="text-3xl font-bold text-gray-800"><i class="fas fa-flag mr-3 text-green-600"></i>{{ $hotspot->label }}</h2>
    <p class="mt-1 text-gray-600">{{ $barangay->barangay_name }} · {{ $hotspot->basis_label }}-based location · {{ $hotspot->total_reports }} {{ Str::plural('report', $hotspot->total_reports) }}</p>
  </div>

  <div class="admin-table-card overflow-hidden rounded-xl bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="admin-table-header">
            @foreach(['#', 'Reporter', 'Specific Location / Landmark', 'Description', 'Status', 'Date Reported', 'Action'] as $heading)
              <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @foreach($reports as $report)
            <tr class="transition-colors hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">#{{ $report->id }}</td>
              <td class="px-4 py-3"><p class="font-medium text-gray-900">{{ $report->user?->name ?? 'Unknown User' }}</p><p class="text-xs text-gray-500">{{ $report->user?->email ?? 'N/A' }}</p></td>
              <td class="px-4 py-3 text-gray-600">{{ $report->location ?: 'Location not recorded' }}</td>
              <td class="max-w-xs px-4 py-3 text-gray-600"><p class="line-clamp-2">{{ $report->description }}</p></td>
              <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $report->getStatusBadgeBgClass() }}">{{ $report->status_label }}</span></td>
              <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $report->created_at->format('M d, Y g:i A') }}</td>
              <td class="whitespace-nowrap px-4 py-3">
                <a href="{{ route('admin.reports', ['report' => $report->id]) }}" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700"><i class="fas fa-cog text-xs"></i> View / Manage</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
