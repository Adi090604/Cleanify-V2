@extends('layouts.admin')

@section('title', 'Dashboard')

@push('styles')
  <style>
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
    }
  </style>
@endpush

@section('content')
  <h2 class="text-3xl font-bold text-gray-800 mb-6">Welcome, Admin 👋</h2>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <x-admin.stat-card icon="fas fa-users" title="Total Users" value="{{ $totalUsers }}" />
    <x-admin.stat-card icon="fas fa-bullhorn" title="Reports" value="{{ $totalReports }}" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
    <x-admin.stat-card icon="fas fa-calendar-check" title="Active Schedules" value="{{ $activeSchedules }}" borderClass="border-l-4 border-purple-500" iconWrapperClass="bg-purple-100" iconColorClass="text-purple-600" />
    <x-admin.stat-card icon="fas fa-truck" title="Active Trucks" value="{{ $activeTrucks }}" borderClass="border-l-4 border-orange-500" iconWrapperClass="bg-orange-100" iconColorClass="text-orange-600" />
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="chart-card bg-white rounded-xl shadow-sm p-6">
      <h5 class="text-lg font-semibold text-gray-800 mb-4">
        <i class="fas fa-chart-bar mr-2 text-green-600"></i>Reports Overview
      </h5>
      <div class="h-80">
        <canvas id="reportsChart"></canvas>
      </div>
    </div>

    <div class="chart-card bg-white rounded-xl shadow-sm p-6">
      <h5 class="text-lg font-semibold text-gray-800 mb-4">
        <i class="fas fa-chart-pie mr-2 text-green-600"></i>User Roles
      </h5>
      <div class="h-80">
        <canvas id="usersChart"></canvas>
      </div>
    </div>
  </div>

  <div class="dashboard-card bg-white rounded-xl shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
      <h4 class="text-xl font-semibold text-gray-800">Recent Reports</h4>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full table-auto">
        <thead>
          <tr class="bg-green-600 text-white">
            <th class="px-4 py-3 text-left">#</th>
            <th class="px-4 py-3 text-left">User</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse ($recentReports as $report)
            @php
              $statusStyles = [
                'pending' => 'bg-yellow-100 text-yellow-800',
                'resolved' => 'bg-green-100 text-green-800',
                'rejected' => 'bg-red-100 text-red-800',
              ];
            @endphp
            <tr class="hover:bg-gray-50 transition-colors duration-200">
              <td class="px-4 py-3">{{ $report->id }}</td>
              <td class="px-4 py-3 font-medium">
                @if($report->user)
                  {{ $report->user->name }}
                @else
                  <span class="text-gray-400">Unknown User</span>
                @endif
              </td>
              <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($report->description, 30) }}</td>
              <td class="px-4 py-3">
                <span class="{{ $statusStyles[$report->status] ?? 'bg-gray-100 text-gray-800' }} text-xs font-medium px-2.5 py-0.5 rounded-full">
                  {{ ucfirst($report->status) }}
                </span>
              </td>
              <td class="px-4 py-3">{{ $report->created_at->format('M d, Y') }}</td>
              <td class="px-4 py-3">
                <a href="{{ route('admin.reports', ['report' => $report->id]) }}" class="w-8 h-8 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors duration-300 inline-flex items-center justify-center" title="View Report">
                  <i class="fas fa-eye text-xs"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-bullhorn text-4xl mb-2 block"></i>
                No reports yet
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const reportsCtx = document.getElementById('reportsChart');
      if (reportsCtx) {
        const reportsData = @json($reportsChartData);
        new Chart(reportsCtx, {
          type: 'bar',
          data: {
            labels: reportsData.labels,
            datasets: [{
              label: 'Reports',
              data: reportsData.data,
              backgroundColor: reportsData.colors
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
          }
        });
      }

      const usersCtx = document.getElementById('usersChart');
      if (usersCtx) {
        const usersData = @json($usersChartData);
        new Chart(usersCtx, {
          type: 'pie',
          data: {
            labels: usersData.labels,
            datasets: [{
              data: usersData.data,
              backgroundColor: usersData.colors
            }]
          },
          options: { responsive: true, maintainAspectRatio: false }
        });
      }

    });
  </script>
@endpush
