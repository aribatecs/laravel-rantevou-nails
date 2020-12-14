<?php

namespace App\Http\Controllers\Admin;

use App\Appointment;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!Gate::allows('statistics_access')) {
            return abort(401);
        }

        $fromDate = new \DateTime($request->get('filter-date-from', '-30 days'));
        $toDate = new \DateTime($request->get('filter-date-to', 'now'));
        $activeTab = $request->get('tab', 'dashboard');

        // Dashboard tab
        $totalAppointments = Appointment::where(
            'appointments.start_time',
            '>=',
            $fromDate->format('Y-m-d') . ' 00:00:00'
        )
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->count();
        $completedAppointments = Appointment::where('appointments.start_time', '<=', date('Y-m-d') . ' 00:00:00')
            ->where('appointments.start_time', '>=', $fromDate->format('Y-m-d') . ' 00:00:00')
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->count();
        $upcomingAppointments = Appointment::where('appointments.start_time', '>=', date('Y-m-d') . ' 00:00:00')->count(
        );

        $occupancy = '0';
        $totalSales = Appointment::select(\DB::raw('SUM(services.price) as revenue'))
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.start_time', '>=', $fromDate->format('Y-m-d') . ' 00:00:00')
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->first()['revenue'];
        $averageSales = Appointment::select(\DB::raw('AVG(services.price) as avgSaleValue'))
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.start_time', '>=', $fromDate->format('Y-m-d') . ' 00:00:00')
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->first()['avgSaleValue'];

        $appointmentsPerDay = Appointment::select(
            \DB::raw('DATE(appointments.start_time) as appointmentDate, COUNT(*) as appointmentCount')
        )
            ->where('appointments.start_time', '>=', $fromDate->format('Y-m-d') . ' 00:00:00')
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->groupBy('appointmentDate')
            ->get();
        // End dashboard tab

        // Reports tab
        $appointmentsByStaff = Appointment::select(
            \DB::raw(
                'CONCAT(employees.first_name, " ", employees.last_name) as staff, COUNT(*) as appointmentCount, SUM(services.price) as totalValue'
            )
        )
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->leftJoin('employees', 'appointments.employee_id', '=', 'employees.id')
            ->where('appointments.start_time', '<=', date('Y-m-d') . ' 00:00:00')
            ->where('appointments.start_time', '>=', $fromDate->format('Y-m-d') . ' 00:00:00')
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->groupBy('staff')
            ->get()->toArray();

        $appointmentsByService = Appointment::select(
            \DB::raw(
                'services.name as service, COUNT(*) as appointmentCount, SUM(services.price) as totalValue'
            )
        )
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.start_time', '<=', date('Y-m-d') . ' 00:00:00')
            ->where('appointments.start_time', '>=', $fromDate->format('Y-m-d') . ' 00:00:00')
            ->where('appointments.finish_time', '<', $toDate->format('Y-m-d') . ' 00:00:00')
            ->groupBy('service')
            ->get()->toArray();
        // End reports tab

        if ($request->get('export') === 'appointmentsByStaff' || $request->get('export') === 'appointmentsByService') {
            $export = $request->get('export');
            $var = $$export;
            if (count($var) <= 0) {
                return response('Δεν υπάρχουν δεδομένα');
            }
            return $this->getCsv(array_keys($var[0]), $var, $request->get('export').'.csv');
        }

        return view(
            'admin.analytics.index',
            compact(
                'fromDate',
                'toDate',
                'activeTab',
                'totalAppointments',
                'occupancy',
                'totalSales',
                'appointmentsPerDay',
                'averageSales',
                'completedAppointments',
                'upcomingAppointments',
                'appointmentsByStaff',
                'appointmentsByService'
            )
        );
    }

    /**
     * @param array $columnNames
     * @param array $rows
     * @param string $fileName
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function getCsv($columnNames, $rows, $fileName = 'file.csv') {
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=" . $fileName,
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];
        $callback = function() use ($columnNames, $rows ) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columnNames);
            foreach ($rows as $row) {
                fputcsv($file, array_values($row));
            }
            fclose($file);
        };
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        return response()->stream($callback, 200, $headers);
    }
}
