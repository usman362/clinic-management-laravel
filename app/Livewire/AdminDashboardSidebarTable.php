<?php

namespace App\Livewire;

use App\Models\Appointment;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Lazy;

#[Lazy]
class AdminDashboardSidebarTable extends Component
{
   public $upcomingAppointmentCount;
   public $totalAppointmentCount;

   public function mount()
   {
      $todayDate = Carbon::now()->format('Y-m-d');
      $this->upcomingAppointmentCount = Appointment::where('appointment_type', 'assessment')
         ->where('status', '!=', Appointment::CANCELLED)
         ->where('date', '>', $todayDate)
         ->distinct('relation_id')->count('relation_id');
      $this->totalAppointmentCount = Appointment::where('appointment_type', 'assessment')
         ->where('status', '!=', Appointment::CANCELLED)
         ->distinct('relation_id')->count('relation_id');
   }

   public function placeholder()
   {
      return view('livewire.dashboard_sidebar_skeleton');
   }
   public function render()
   {
      return view('livewire.admin-dashboard-sidebar-table');
   }
}
