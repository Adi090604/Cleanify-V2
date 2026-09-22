@extends('layouts.admin')

@section('title', 'Dashboard')

@push('styles')
  <style>
    .admin-dashboard-page .cleanify-main {
      background: #f6f8f6;
    }

    .admin-dashboard-page .cleanify-main main {
      padding: 1rem;
    }

    .admin-dashboard-page .cleanify-sidebar nav {
      gap: .25rem;
    }

    .admin-dashboard-page .cleanify-sidebar nav a {
      position: relative;
      min-height: 2.5rem;
      padding: .55rem .75rem;
    }

    .admin-dashboard-page .cleanify-sidebar nav a i {
      display: inline-flex;
      flex: 0 0 1.25rem;
      align-items: center;
      justify-content: center;
      margin-right: .7rem;
    }

    .admin-dashboard-page .cleanify-sidebar nav a.bg-green-700 {
      box-shadow: 0 1px 2px rgba(46, 125, 50, .06);
    }

    .admin-dashboard-shell {
      width: 100%;
      max-width: 100rem;
      margin: 0 auto;
    }

    .admin-dashboard-card {
      border: 1px solid #e2e8e3;
      border-radius: .875rem;
      background: #fff;
      box-shadow: 0 1px 2px rgba(24, 48, 32, .04), 0 8px 24px rgba(24, 48, 32, .025);
    }

    .admin-dashboard-stat {
      min-height: 7rem;
      padding: .9rem 1rem !important;
      border: 1px solid #e2e8e3 !important;
      border-left-width: 1px !important;
      border-radius: .875rem !important;
      background: #fff;
      box-shadow: 0 1px 2px rgba(24, 48, 32, .04), 0 8px 24px rgba(24, 48, 32, .025) !important;
    }

    .admin-dashboard-stat-icon {
      display: inline-flex;
      width: 2.25rem;
      height: 2.25rem;
      flex: 0 0 2.25rem;
      align-items: center;
      justify-content: center;
      border-radius: .65rem;
      font-size: .8125rem;
    }

    .admin-dashboard-stat-value {
      color: #202a23;
      font-size: 1.65rem;
      line-height: 1.1;
      letter-spacing: -.035em;
    }

    .admin-dashboard-trend {
      display: flex;
      min-height: 1rem;
      align-items: center;
      gap: .3rem;
      margin-top: .45rem;
      font-size: .6875rem;
      font-weight: 600;
    }

    .admin-dashboard-trend i {
      font-size: .6rem;
    }

    .admin-dashboard-chart {
      padding: 1.125rem;
    }

    .admin-dashboard-chart-frame {
      position: relative;
      height: 15.5rem;
    }

    .admin-dashboard-role-layout {
      display: grid;
      min-height: 15.5rem;
      grid-template-columns: minmax(0, 1fr) 10rem;
      align-items: center;
      gap: 1.25rem;
    }

    .admin-dashboard-doughnut {
      position: relative;
      width: min(100%, 12.5rem);
      height: 12.5rem;
      margin: 0 auto;
    }

    .admin-dashboard-doughnut-center {
      position: absolute;
      inset: 50% auto auto 50%;
      z-index: 1;
      display: flex;
      width: 5rem;
      height: 5rem;
      transform: translate(-50%, -50%);
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }

    .admin-dashboard-role-item + .admin-dashboard-role-item {
      margin-top: .75rem;
      padding-top: .75rem;
      border-top: 1px solid #edf1ed;
    }

    .admin-dashboard-avatar {
      display: inline-flex;
      width: 2rem;
      height: 2rem;
      flex: 0 0 2rem;
      align-items: center;
      justify-content: center;
      border-radius: 9999px;
      background: #eaf5ec;
      color: #257034;
      font-size: .6875rem;
      font-weight: 700;
      letter-spacing: .02em;
    }

    .admin-dashboard-avatar--muted {
      background: #f1f3f1;
      color: #7a877f;
    }

    .admin-dashboard-table {
      min-width: 42rem;
    }

    .admin-dashboard-table thead tr {
      border-top: 1px solid #e8eee9;
      border-bottom: 1px solid #e1e8e3;
      background: #f5f8f5;
      color: #5b6960;
    }

    .admin-dashboard-table th {
      padding-top: .625rem !important;
      padding-bottom: .625rem !important;
      font-size: .675rem;
      letter-spacing: .055em;
    }

    .admin-dashboard-table td {
      padding-top: .75rem !important;
      padding-bottom: .75rem !important;
      color: #4a574f;
      vertical-align: middle;
    }

    .admin-dashboard-table tbody tr:last-child {
      border-bottom: 0;
    }

    @media (min-width: 640px) {
      .admin-dashboard-page .cleanify-main main {
        padding: 1.25rem;
      }
    }

    @media (min-width: 1280px) {
      .admin-dashboard-page .cleanify-main main {
        padding: 1.5rem;
      }
    }

    @media (max-width: 639px) {
      .admin-dashboard-chart-frame {
        height: 14rem;
      }

      .admin-dashboard-role-layout {
        min-height: auto;
        grid-template-columns: 1fr;
        gap: .75rem;
      }

      .admin-dashboard-doughnut {
        width: 11rem;
        height: 11rem;
      }

      .admin-dashboard-role-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .5rem;
      }

      .admin-dashboard-role-item,
      .admin-dashboard-role-item + .admin-dashboard-role-item {
        margin-top: 0;
        padding: .65rem;
        border: 1px solid #edf1ed;
        border-radius: .625rem;
      }
    }
  </style>
@endpush

@section('content')
  <div class="admin-dashboard-shell">
    <header class="mb-5 flex items-end justify-between gap-4 pl-12 sm:pl-0">
      <div>
        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-green-700">Admin overview</p>
        <h2 class="text-2xl font-bold tracking-tight text-gray-800 sm:text-3xl">Welcome, Admin</h2>
        <p class="mt-1 text-sm text-gray-500">Here's what's happening with your community today.</p>
      </div>
      <div class="hidden shrink-0 items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 shadow-sm sm:flex">
        @if($admin->profile_photo_url)
          <img src="{{ $admin->profile_photo_url }}" alt="{{ $admin->name }}'s profile photo" class="h-9 w-9 rounded-full object-cover ring-2 ring-green-100">
        @else
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-sm font-bold text-green-700 ring-2 ring-green-50" aria-hidden="true">
            {{ $admin->getAvatarInitial() }}
          </span>
        @endif
        <div class="min-w-0">
          <p class="max-w-40 truncate text-sm font-semibold leading-tight text-gray-800">{{ $admin->name }}</p>
          <time datetime="{{ $currentDate->toDateString() }}" class="mt-1 block text-[11px] leading-tight text-gray-500">
            {{ $currentDate->format('D, M j, Y') }}
          </time>
        </div>
      </div>
    </header>

    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article class="admin-dashboard-stat">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold text-gray-500">Total Users</p>
          <span class="admin-dashboard-stat-icon bg-green-50 text-green-700" aria-hidden="true"><i class="fas fa-users"></i></span>
        </div>
        <p class="admin-dashboard-stat-value mt-1 font-bold">{{ $totalUsers }}</p>
        <p class="admin-dashboard-trend {{ $usersThisWeek > 0 ? 'text-green-700' : 'text-gray-400' }}">
          <i class="fas {{ $usersThisWeek > 0 ? 'fa-arrow-up' : 'fa-minus' }}" aria-hidden="true"></i>
          {{ $usersThisWeek > 0 ? '+' . $usersThisWeek . ' this week' : 'No new users this week' }}
        </p>
      </article>

      <article class="admin-dashboard-stat">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold text-gray-500">Reports</p>
          <span class="admin-dashboard-stat-icon bg-blue-50 text-blue-600" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
        </div>
        <p class="admin-dashboard-stat-value mt-1 font-bold">{{ $totalReports }}</p>
        <p class="admin-dashboard-trend {{ $reportsThisWeek > 0 ? 'text-blue-600' : 'text-gray-400' }}">
          <i class="fas {{ $reportsThisWeek > 0 ? 'fa-arrow-up' : 'fa-minus' }}" aria-hidden="true"></i>
          {{ $reportsThisWeek > 0 ? '+' . $reportsThisWeek . ' this week' : 'No new reports this week' }}
        </p>
      </article>

      <article class="admin-dashboard-stat">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold text-gray-500">Active Schedules</p>
          <span class="admin-dashboard-stat-icon bg-purple-50 text-purple-600" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
        </div>
        <p class="admin-dashboard-stat-value mt-1 font-bold">{{ $activeSchedules }}</p>
        <p class="admin-dashboard-trend {{ $activeSchedulesThisWeek > 0 ? 'text-purple-600' : 'text-gray-400' }}">
          <i class="fas {{ $activeSchedulesThisWeek > 0 ? 'fa-arrow-up' : 'fa-minus' }}" aria-hidden="true"></i>
          {{ $activeSchedulesThisWeek > 0 ? '+' . $activeSchedulesThisWeek . ' active added this week' : 'No active schedules added' }}
        </p>
      </article>

      <article class="admin-dashboard-stat">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold text-gray-500">Active Trucks</p>
          <span class="admin-dashboard-stat-icon bg-orange-50 text-orange-600" aria-hidden="true"><i class="fas fa-truck"></i></span>
        </div>
        <p class="admin-dashboard-stat-value mt-1 font-bold">{{ $activeTrucks }}</p>
        <p class="admin-dashboard-trend {{ $activeTrucksThisWeek > 0 ? 'text-orange-600' : 'text-gray-400' }}">
          <i class="fas {{ $activeTrucksThisWeek > 0 ? 'fa-arrow-up' : 'fa-minus' }}" aria-hidden="true"></i>
          {{ $activeTrucksThisWeek > 0 ? '+' . $activeTrucksThisWeek . ' active added this week' : 'No active trucks added' }}
        </p>
      </article>
    </div>

    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
      <section class="admin-dashboard-card admin-dashboard-chart chart-card" aria-labelledby="reports-chart-title">
        <div class="mb-4 flex items-center gap-3">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700" aria-hidden="true">
            <i class="fas fa-chart-bar text-sm"></i>
          </span>
          <div>
            <h3 id="reports-chart-title" class="text-base font-semibold text-gray-800">Reports Overview</h3>
            <p class="text-xs text-gray-500">Reports grouped by current status</p>
          </div>
        </div>
        <div class="admin-dashboard-chart-frame">
          <canvas id="reportsChart"></canvas>
        </div>
      </section>

      <section class="admin-dashboard-card admin-dashboard-chart chart-card" aria-labelledby="users-chart-title">
        <div class="mb-4 flex items-center gap-3">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700" aria-hidden="true">
            <i class="fas fa-chart-pie text-sm"></i>
          </span>
          <div>
            <h3 id="users-chart-title" class="text-base font-semibold text-gray-800">User Roles</h3>
            <p class="text-xs text-gray-500">Current user role distribution</p>
          </div>
        </div>
        <div class="admin-dashboard-role-layout">
          <div class="admin-dashboard-doughnut">
            <canvas id="usersChart"></canvas>
            <div class="admin-dashboard-doughnut-center" aria-hidden="true">
              <strong class="text-2xl font-bold tracking-tight text-gray-800">{{ $totalUsers }}</strong>
              <span class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Total users</span>
            </div>
          </div>
          <div class="admin-dashboard-role-details" aria-label="User role totals">
            <div class="admin-dashboard-role-item">
              <div class="flex items-center gap-2 text-xs font-medium text-gray-600">
                <span class="h-2 w-2 rounded-full bg-green-600" aria-hidden="true"></span>
                Regular Users
              </div>
              <div class="mt-1 flex items-baseline justify-between gap-3 pl-4">
                <p class="text-lg font-bold text-gray-800">{{ $usersChartData['data'][0] }}</p>
                <p class="text-xs font-semibold text-gray-500">{{ number_format($regularUserPercentage, 1) }}%</p>
              </div>
            </div>
            <div class="admin-dashboard-role-item">
              <div class="flex items-center gap-2 text-xs font-medium text-gray-600">
                <span class="h-2 w-2 rounded-full bg-blue-600" aria-hidden="true"></span>
                Admins
              </div>
              <div class="mt-1 flex items-baseline justify-between gap-3 pl-4">
                <p class="text-lg font-bold text-gray-800">{{ $usersChartData['data'][1] }}</p>
                <p class="text-xs font-semibold text-gray-500">{{ number_format($adminPercentage, 1) }}%</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="admin-dashboard-card overflow-hidden" aria-labelledby="recent-reports-title">
      <div class="flex items-center justify-between px-4 py-4 sm:px-5">
        <div>
          <h3 id="recent-reports-title" class="text-base font-semibold text-gray-800">Recent Reports</h3>
          <p class="mt-0.5 text-xs text-gray-500">The five most recently submitted reports</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="admin-dashboard-table w-full table-auto">
          <thead>
            <tr>
              <th class="px-4 text-left sm:px-5">#</th>
              <th class="px-4 text-left">User</th>
              <th class="px-4 text-left">Type</th>
              <th class="px-4 text-left">Status</th>
              <th class="px-4 text-left">Date</th>
              <th class="px-4 text-right sm:px-5">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse ($recentReports as $report)
              @php
                $statusStyles = [
                  'pending' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                  'resolved' => 'bg-green-50 text-green-700 ring-green-600/20',
                  'rejected' => 'bg-red-50 text-red-700 ring-red-600/20',
                ];
              @endphp
              <tr class="transition-colors duration-150 hover:bg-gray-50">
                <td class="px-4 font-medium text-gray-500 sm:px-5">{{ $report->id }}</td>
                <td class="px-4">
                  @if($report->user)
                    <div class="flex items-center gap-2.5">
                      <span class="admin-dashboard-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($report->user->name), 0, 1)) }}</span>
                      <span class="font-semibold text-gray-700">{{ $report->user->name }}</span>
                    </div>
                  @else
                    <div class="flex items-center gap-2.5">
                      <span class="admin-dashboard-avatar admin-dashboard-avatar--muted" aria-hidden="true">?</span>
                      <span class="font-normal text-gray-400">Unknown User</span>
                    </div>
                  @endif
                </td>
                <td class="max-w-xs px-4">{{ \Illuminate\Support\Str::limit($report->description, 30) }}</td>
                <td class="px-4">
                  <span class="{{ $statusStyles[$report->status] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20' }} inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset">
                    {{ ucfirst($report->status) }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-4">
                  <span class="block text-gray-600">{{ $report->created_at->format('M d, Y') }}</span>
                  <span class="mt-0.5 block text-[11px] text-gray-400">{{ $report->created_at->format('h:i A') }}</span>
                </td>
                <td class="px-4 text-right sm:px-5">
                  <a href="{{ route('admin.reports', ['report' => $report->id]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-semibold text-gray-600 transition-colors duration-150 hover:border-green-200 hover:bg-green-50 hover:text-green-700" title="View Report" aria-label="View report {{ $report->id }}">
                    <i class="fas fa-eye text-[10px]" aria-hidden="true"></i>
                    View
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                  <span class="mx-auto mb-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <i class="fas fa-bullhorn"></i>
                  </span>
                  <span class="block text-sm">No reports yet</span>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const reportsCtx = document.getElementById('reportsChart');
      if (reportsCtx) {
        const reportsData = @json($reportsChartData);
        const reportValueLabels = {
          id: 'reportValueLabels',
          afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const dataset = chart.data.datasets[0];

            ctx.save();
            ctx.fillStyle = '#526158';
            ctx.font = '600 11px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';

            chart.getDatasetMeta(0).data.forEach((bar, index) => {
              ctx.fillText(dataset.data[index], bar.x, bar.y - 7);
            });

            ctx.restore();
          }
        };

        new Chart(reportsCtx, {
          type: 'bar',
          data: {
            labels: reportsData.labels,
            datasets: [{
              label: 'Reports',
              data: reportsData.data,
              backgroundColor: reportsData.colors,
              borderRadius: 6,
              borderSkipped: false,
              maxBarThickness: 44
            }]
          },
          plugins: [reportValueLabels],
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              x: {
                grid: { display: false },
                border: { display: false },
                ticks: { color: '#68756d', font: { size: 11 } }
              },
              y: {
                beginAtZero: true,
                grace: '15%',
                border: { display: false },
                grid: { color: '#edf1ed' },
                ticks: { color: '#7a877f', precision: 0, font: { size: 11 } }
              }
            }
          }
        });
      }

      const usersCtx = document.getElementById('usersChart');
      if (usersCtx) {
        const usersData = @json($usersChartData);
        new Chart(usersCtx, {
          type: 'doughnut',
          data: {
            labels: usersData.labels,
            datasets: [{
              data: usersData.data,
              backgroundColor: usersData.colors,
              borderColor: '#ffffff',
              borderWidth: 3
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                displayColors: true,
                usePointStyle: true,
                padding: 10,
                cornerRadius: 8
              }
            }
          }
        });
      }

    });
  </script>
@endpush
