@can('manage_admin_dashboard')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/dashboard*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/dashboard*') ? 'active' : '' }}"
            href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a>
    </li>
@endcan
@role('doctor')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/dashboard*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/dashboard*') ? 'active' : '' }}"
            href="{{ route('doctors.dashboard') }}">{{ __('messages.dashboard') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/appointments*','doctors/prescription-medicine-show*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/appointments*','doctors/prescription-medicine-show*') ? 'active' : '' }}"
            href="{{ route('doctors.appointments') }}">{{ __('messages.appointments') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/doctor-schedule-edit*','doctors/doctor-sessions/create*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/doctor-schedule-edit*','doctors/doctor-sessions/create*') ? 'active' : '' }}"
            href="{{ getLoginDoctorSessionUrl() }}">{{ __('messages.doctor_session.my_schedule') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/visits*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/visits*') ? 'active' : '' }}"
            href="{{ route('doctors.visits.index') }}">{{ __('messages.visits') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/connect-google-calendar*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/connect-google-calendar*') ? 'active' : '' }}"
            href="{{ route('doctors.googleCalendar.index') }}">{{ __('messages.setting.connect_google_calendar') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/live-consultations*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/live-consultation*') ? 'active' : '' }}"
            href="{{ route('doctors.live-consultations.index') }}">{{ __('messages.live_consultations') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/transactions*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/transactions*') ? 'active' : '' }}"
            href="{{ route('doctors.transactions') }}">{{ __('messages.transactions') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('doctors/holidays*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('doctors/holidays*') ? 'active' : '' }}"
            href="{{ route('doctors.holiday') }}">{{ __('messages.holiday.holiday') }}</a>
    </li>
@endrole
@role('patient')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0">
        <a class="nav-link p-0 {{ Request::is('patients/dashboard*') ? 'active' : '' }}"
            href="{{ route('patients.dashboard') }}">{{ __('messages.dashboard') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0">
        <a class="nav-link p-0 {{ Request::is('patients/pending-bookings*','patients/confirmed-bookings*') ? 'active' : '' }}"
            href="{{ route('patients.patient-bookings-confirmed') }}">{{ __('Packages') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0">
        <a class="nav-link p-0 {{ Request::is('patients/feedback-bookings*') ? 'active' : '' }}"
            href="{{ route('patients.patient-bookings-feedback') }}">{{ __('Feedback Bookings') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0">
        <a class="nav-link p-0 {{ Request::is('profile/edit*') ? 'active' : '' }}"
            href="{{ route('profile.setting') }}">{{ __('messages.user.account_setting') }}</a>
    </li>
@endrole
@can('manage_staff')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/staffs*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/staffs*') ? 'active' : '' }}"
            href="{{ route('staffs.index') }}">{{ __('messages.staffs') }}</a>
    </li>
@endcan
@can('manage_doctors')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/doctors*', 'admin/doctor-sessions*','admin/holidays*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/doctors*') ? 'active' : '' }}"
            href="{{ route('doctors.index') }}">{{ __('messages.doctors') }}</a>
    </li>
@endcan
@can('manage_doctor_sessions')
    <li
        class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/doctors*', 'admin/doctor-sessions*','admin/holidays*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/doctor-sessions*') ? 'active' : '' }}"
            href="{{ route('doctor-sessions.index') }}">{{ getLogInUser()->hasRole('doctor') ? __('messages.doctor_session.my_schedule') : __('messages.doctor_sessions') }}</a>
    </li>
@endcan
@can('manage_patients')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/patients*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/patients*') ? 'active' : '' }}"
            href="{{ route('patients.index') }}">{{ __('messages.patients') }}</a>
    </li>
@endcan

@if (isRole('doctor'))
<li
    class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('doctors/smart-patient-cards*', 'doctors/generate-patient-smart-cards*') ? 'd-none' : '' }}">
    <a class="nav-link p-0 {{ Request::is('doctors/smart-patient-cards*') ? 'active' : '' }}"
        href="{{ route('doctors.smart-patient-cards.index') }}">{{ __('messages.smart_patient_card.smart_patient_card_templates') }}</a>
</li>

<li
    class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('doctors/smart-patient-cards*', 'doctors/generate-patient-smart-cards*') ? 'd-none' : '' }}">
    <a class="nav-link p-0 {{ Request::is('doctors/generate-patient-smart-cards*') ? 'active' : '' }}"
        href="{{ route('doctors.generate-patient-smart-cards.index') }}">{{ __('messages.smart_patient_card.generate_patient_smart_cards') }}</a>
</li>
@endif

@if (isRole('patient'))
<li
    class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('patients/smart-patient-cards*', 'patients/generate-patient-smart-cards*') ? 'd-none' : '' }}">
    <a class="nav-link p-0 {{ Request::is('patients/generate-patient-smart-cards*') ? 'active' : '' }}"
        href="{{ route('patients.generate-patient-smart-cards.index') }}">{{ __('messages.smart_patient_card.generate_patient_smart_cards') }}</a>
</li>
@endif

@if (isRole('clinic_admin'))
<li
    class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/smart-patient-cards*', 'admin/generate-patient-smart-cards*') ? 'd-none' : '' }}">
    <a class="nav-link p-0 {{ Request::is('admin/smart-patient-cards*') ? 'active' : '' }}"
        href="{{ route('smart-patient-cards.index') }}">{{ __('messages.smart_patient_card.smart_patient_card_templates') }}</a>
</li>

<li
    class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/smart-patient-cards*', 'admin/generate-patient-smart-cards*') ? 'd-none' : '' }}">
    <a class="nav-link p-0 {{ Request::is('admin/generate-patient-smart-cards*') ? 'active' : '' }}"
        href="{{ route('generate-patient-smart-cards.index') }}">{{ __('messages.smart_patient_card.generate_patient_smart_cards') }}</a>
</li>
@endif


@can('manage_settings')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0
    {{ !Request::is('admin/settings*','admin/roles*','admin/currencies*','admin/clinic-schedules*','admin/countries*','admin/states*','admin/cities*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/settings*') ? 'active' : '' }}"
            href="{{ route('setting.index') }}">{{ __('messages.settings') }}</a>
    </li>
    {{-- Clinic Schedules, Roles, Currencies, Countries, States, Cities tabs hidden --}}

@endcan

@can('manage_specialities')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/specializations*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/specializations*') ? 'active' : '' }}"
            href="{{ route('specializations.index') }}">{{ __('messages.specializations') }}</a>
    </li>
@endcan
@can('manage_services')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/services*','admin/service-categories*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/services*') ? 'active' : '' }}"
            href="{{ route('services.index') }}">{{ __('messages.services') }}</a>
    </li>
    {{-- <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/services*','admin/service-categories*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/service-categories*') ? 'active' : '' }}"
            href="{{ route('service-categories.index') }}">{{ __('messages.service_categories') }}</a>
    </li> --}}
@endcan
@can('manage_appointments')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/appointments*','admin/admin-appointments-calendar*','admin/prescriptions*', 'admin/prescription-medicine-show*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/appointments*','admin/admin-appointments-calendar*','admin/prescriptions*', 'admin/prescription-medicine-show*') ? 'active' : '' }}"
            href="{{ route('appointments.index') }}">{{ __('messages.appointments') }}</a>
    </li>
@endcan
@can('manage_patient_visits')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/visits*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/visits*') ? 'active' : '' }}"
            href="{{ route('visits.index') }}">{{ __('messages.visits') }}</a>
    </li>
@endcan
<li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('profile/edit*') ? 'd-none' : '' }}">
    <a class="nav-link p-0 {{ Request::is('profile/edit*') ? 'active' : '' }}"
        href="{{ route('profile.setting') }}">{{ __('messages.user.profile_details') }}</a>
</li>
@can('manage_front_cms')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/front-services*','admin/faqs*','admin/front-patient-testimonials*','admin/cms*','admin/banner*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/cms*') ? 'active' : '' }}"
            href="{{ route('cms.index') }}">{{ __('messages.cms.cms') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/front-services*','admin/faqs*','admin/front-patient-testimonials*','admin/cms*','admin/banner*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/banner*') ? 'active' : '' }}"
            href="{{ route('banner.index') }}">{{ __('messages.sliders') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/front-services*','admin/faqs*','admin/front-patient-testimonials*','admin/cms*','admin/banner*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/faqs*') ? 'active' : '' }}"
            href="{{ route('faqs.index') }}">{{ __('messages.faqs') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/front-services*','admin/faqs*','admin/front-patient-testimonials*','admin/cms*','admin/banner*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/front-patient-testimonials*') ? 'active' : '' }}"
            href="{{ route('front-patient-testimonials.index') }}">{{ __('messages.front_patient_testimonials') }}</a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/enquiries*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/enquiries*') ? 'active' : '' }}"
            href="{{ route('enquiries.index') }}">{{ __('messages.enquiries') }}</a>
    </li>

    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/subscribers*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/subscribers*') ? 'active' : '' }}"
            href="{{ route('subscribers.index') }}">{{ __('messages.subscribers') }}</a>
    </li>
@endcan
@can('manage_transactions')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ !Request::is('admin/transactions*') ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/transactions*') ? 'active' : '' }}"
            href="{{ route('transactions') }}">{{ __('messages.transactions') }}</a>
    </li>
@endcan
@can('manage_medicines')
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ (!Request::is('admin/categories*','admin/brands*','admin/medicines*','admin/medicine-purchase*','admin/used-medicine*','admin/medicine-bills*')) ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/categories*') ? 'active' : '' }}"
            href="{{ route('categories.index') }}">
            {{ __('messages.medicine_categories') }}
        </a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ (!Request::is('admin/categories*','admin/brands*','admin/medicines*','admin/medicine-purchase*','admin/used-medicine*','admin/medicine-bills*')) ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/brands*') ? 'active' : '' }}"
        href="{{ route('brands.index') }}">
            {{ __('messages.medicine_brands') }}
        </a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ (!Request::is('admin/categories*','admin/brands*','admin/medicines*','admin/medicine-purchase*','admin/used-medicine*','admin/medicine-bills*')) ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/medicines*') ? 'active' : '' }}"
            href="{{ route('medicines.index') }}">
            {{ __('messages.medicines') }}
        </a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ (!Request::is('admin/categories*','admin/brands*','admin/medicines*','admin/medicine-purchase*','admin/used-medicine*','admin/medicine-bills*')) ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/medicine-purchase*') ? 'active' : '' }}"
            href="{{ route('medicine-purchase.index') }}">
            {{ __('messages.purchase_medicine.purchase_medicines') }}
        </a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ (!Request::is('admin/categories*','admin/brands*','admin/medicines*','admin/medicine-purchase*','admin/used-medicine*','admin/medicine-bills*')) ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/used-medicine*') ? 'active' : '' }}"
            href="{{ route('used-medicine.index') }}">
            {{ __('messages.used_medicine.used_medicines') }}
        </a>
    </li>
    <li class="nav-item position-relative mx-xl-3 mb-3 mb-xl-0 {{ (!Request::is('admin/categories*','admin/brands*','admin/medicines*','admin/medicine-purchase*','admin/used-medicine*','admin/medicine-bills*')) ? 'd-none' : '' }}">
        <a class="nav-link p-0 {{ Request::is('admin/medicine-bills*') ? 'active' : '' }}"
            href="{{ route('medicine-bills.index') }}">
            {{ __('messages.medicine_bills.medicine_bills') }}
        </a>
    </li>
@endcan
